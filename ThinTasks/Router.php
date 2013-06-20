<?php

namespace ThinTasks;

require_once(implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), 'Internal', 'require.php']));


/**
 * Routes command-line invocation to the proper file.
 * 
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) Tyler Menezes. Released under the BSD license.
 *
 * @package ThinTasks
 */
class Router {
    const default_file_name = 'main';

    public static function start($tasks_directory)
    {
        global $argv;
        $task_router = new self($tasks_directory);
        $task_router->route(array_slice($argv, 1));
    }

    public $tasks_directory;
    public function __construct($tasks_directory)
    {
        $this->tasks_directory = $tasks_directory;
    }

    public function route($args)
    {
        $args = self::get_arg_info($args);
        $dir = $this->tasks_directory;
        $file = self::get_best_match($args, function($potential_match) use ($dir) {
            return file_exists($dir.DIRECTORY_SEPARATOR.$potential_match.'.php');
        });

        $args = array_slice($args, count(explode(DIRECTORY_SEPARATOR, $file)));

        if ($file === null) {
            throw new CommandNotFoundException();
        } else {
            $task = self::get_class_from_path($file);
            $task->thintasks_route($args);
        }
    }

    /**
     * Splits the arguments into positional and keyword-wise arguments
     *
     * @param $args     array   Array of arguments passed into the function
     * @return array            Object containing 'keyword', the keyword arguments, and 'positional', the positional arguments.
     */
    public static function get_arg_info($args)
    {
        // Filter into keyword args and positional args
        $kw_args = [];
        $pos_args = [];

        foreach ($args as $arg) {

            if (in_array(substr($arg, 0, 1), ['/', '-', '\\'])) {
                $kw_info = self::get_kw_info($arg);

                $kw_args[$kw_info->key] = $kw_info->value;
            } else {
                $pos_args[] = $arg;
            }
        }

        return (object)[
            'keyword' => $kw_args,
            'positional' => $pos_args
        ];
    }

    /**
     * Gets the best-matching controller match, given a match function
     *
     * @param $arguments        array       List of position-wise command-line arguments
     * @param $match_function   callable    Match function, taking a match and returning true if it's valid
     * @return mixed                        Best matching string, or null if no match was found
     */
    private static function get_best_match($arguments, $match_function)
    {
        foreach (self::get_potential_matches($arguments) as $match) {
            if ($match_function($match)) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Gets a list of potential locations for the controllers.
     *
     * @param $arguments    array   The list of position-wise command-line arguments
     * @return array                List of potential locations from most to least specific, relative to nothing, without any extensions
     */
    private static function get_potential_matches($arguments)
    {
        $potential_matches = [implode(DIRECTORY_SEPARATOR, $arguments) . DIRECTORY_SEPARATOR . self::default_file_name];
        do {
            $potential_matches[] = implode(DIRECTORY_SEPARATOR, $arguments);
            array_pop($arguments);
            $potential_matches[] = implode(DIRECTORY_SEPARATOR, array_merge($arguments, [self::default_file_name]));
        } while (count($arguments) > 0);

        return $potential_matches;
    }

    /**
     * Gets an instance of a class from its path
     * @param  string $path Path to the class
     * @return object       Instance of the object
     */
    protected static function get_class_from_path($path)
    {
        if (file_exists($path)) {
            try {
                include_once($path);
            } catch (\Exception $ex) {
                throw new \InvalidArgumentException($path . ' did not exist.');
            }

            $class_info = static::get_class_info_from_path($path);
            $class_name = implode('\\', [$class_info->namespace, $controller_info->class]);

            return new $class_name();
        } else {
            throw new \InvalidArgumentException($path . ' did not exist.');
        }
    }

    /**
     * Gets the namespace and class name of a class from its file
     * @param  string $path Path to the file
     * @return object       Object containing details of the object in "namespace" and "class" properties.
     */
    protected static function get_class_info_from_path($path)
    {
        // This function reads in a file, uses PHP to lex it, and then processes those tokens in a very naive way. As
        // soon as it finds a class start definition, it closes the file, so there's not much unnecessary reading going
        // on, which is probably a silly optimization.
        //
        // Because it only parses up until the first class token, it's not suited for parsing anything after the first
        // class token. (A side effect of this is that it only returns the FIRST class in the file, not the last as one
        // might expect.) This method returns everything you need to know to create a reflector to get additional
        // information, though.
        //
        // Because namespaces cascade down into the class, we can just concat them until we hit a class token. This
        // doesn't deal with files of the form:
        //
        // <?php
        //     namespace red\herring { }
        //     namespace xyzzy {
        //         class plugh {}
        //     }
        //
        // -- the function would return namespace => red\herring\xyz
        //
        // But this really shouldn't be an issue for this sort of thing. The only non-hacky way to fix this is to build
        // an AST for the file, which is fairly overkill. (You might be thinking: "why not just pop off a stack on }?".
        // I did it this way at first, but realized it didn't actually solve anything, since anyone who put a function
        // in the namespace would cause the namespace stack to get double-popped. You could count the number of { not
        // associated with a T_NAMESPACE, and subtract to 0 before popping, but that gets pretty messy.)
        //
        // tl;dr: This function is magic.

        $fp = fopen($path, 'r');
        $class = $namespace = $buffer = null;
        $i = 0;

        while ($class === null) { // The namespace of the class can only be changed before the class is declared.

            // If our file pointer is at EOF, this file just isn't classy. Throw an exception, because what kind of
            // person asks for class information about a file without a class?!
            if (feof($fp)) {
                throw new \BadFunctionCallException('File ' . $path . ' did not contain a class');
            }


            $buffer .= fread($fp, 512); // Read some bytes into the buffer
            $tokens = token_get_all($buffer); // Turn the buffer into tokens


            // If we don't see a begin-bracket, we definitely haven't gotten to the class, and we probably haven't
            // gotten to the end of the namespace declaration. Go forward and read some more bytes into the buffer.
            if (strpos($buffer, '{') === false) {
                continue;
            }

            for (/* $i is global */; $i < count($tokens); $i++) {
                if ($tokens[$i][0] === T_NAMESPACE) { // If this is a namespace token, keep going
                    for ($j = $i+1; $j < count($tokens); $j++) { // If we're out of tokens, nothing to see here.
                        if ($tokens[$j][0] === T_STRING) { // If the token is a string, we're looking at a namespace!
                            $namespace .= '\\' . $tokens[$j][1]; // Add on to the namespace
                        } else if ($tokens[$j] === '{' || $tokens[$j] === ';') { // Namespace change over!
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) { // We've finally reached the beginning of a class def
                    for ($j=$i+1;$j<count($tokens);$j++) {
                        if ($tokens[$j] === '{') {
                            $class = $tokens[$i+2][1];
                        }
                    }
                }
            }
        }

        return (object)[
            'namespace' => $namespace,
            'class' => $class
        ];
    }

    /**
     * Gets the key-value pair from a keyword argument
     *
     * @param $kw       string      The keyword argument
     * @return object                An object with information about the argument, containing:
     *                                - key: the keyword argument name
     *                                - value: the keyword argument value
     * @throws \RuntimeException
     */
    private static function get_kw_info($kw)
    {
        $kw = ltrim($kw, '-/\\');

        if (strpos($kw, '=') !== false) {
            list($key, $val) = explode('=', $kw, 2);

            return (object)[
                'key' => $key,
                'value' => $val
            ];
        } else { // No value provided
            return (object)[
                'key' => $kw,
                'value' => true
            ];
        }
    }
}
