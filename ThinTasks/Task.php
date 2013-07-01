<?php

namespace ThinTasks;

require_once(implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), 'Internal', 'require.php']));

/**
 *
 * 
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) Tyler Menezes. Released under the BSD license.
 *
 * @package ThinTasks
 */
trait Task {
    public function thintasks_route($args)
    {
        $this->args = $args;

        $preferred_route = array_shift($args->positional);
        if ($preferred_route === null) {
            $preferred_route = 'main';
        }

        if ($this->thintasks_check_method($preferred_route, $args->positional)) {
            call_user_func_array([$this, $preferred_route], $args->positional);
        } else {
            throw new CommandNotFoundException();
        }
    }

    private function thintasks_check_method($name, $args)
    {
        $reflection = new \ReflectionMethod($this, $name);
        return $reflection->isPublic() && $reflection->getNumberOfParameters() <= count($args);
    }

    protected function needs()
    {
        $missing_args = [];
        foreach (func_get_args() as $arg) {
            if (!isset($this->args->keyword[$arg])) {
                $missing_args[] = $arg;
            }
        }

        if (count($missing_args) > 0) {
            echo "The following paramaters were required but missing:\n";
            foreach ($missing_args as $arg) {
                echo "  * " . $arg . "\n";
            }

            exit;
        }
    }

    protected function kw($name)
    {
        return isset($this->args->keyword[$name]) ? $this->args->keyword[$name] : false;
    }
}
