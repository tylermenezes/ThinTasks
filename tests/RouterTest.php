<?php

require_once(implode(DIRECTORY_SEPARATOR, [dirname(dirname(__FILE__)), 'ThinTasks', 'Internal', 'require.php']));

class RouterTest extends PHPUnit_Framework_TestCase {
    public function testArgs()
    {
        $args = \ThinTasks\Router::get_arg_info(['one', 'two', 'three', '--true', '--xyz=123', 'four']);
        $this->assertEquals(['one', 'two', 'three', 'four'], $args->positional);
        $this->assertEquals(true, $args->keyword['true']);
        $this->assertEquals('123', $args->keyword['xyz']);
    }

    public function testMarker()
    {
        $x = ['foo', 'bar', 'xyzzy'];
        $potential_matches = [implode('/', $x) . '/main'];
        do {
            $potential_matches[] = implode('/', $x);
            array_pop($x);
            $potential_matches[] = implode('/', array_merge($x, ['main']));
        } while (count($x) > 0);

    }
}
