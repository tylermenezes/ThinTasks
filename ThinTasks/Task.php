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
        $preferred_route = array_shift($args);
        // TODO
    }

    private function thintasks_check_method($name, $args)
    {
        $reflection = new \ReflectionMethod($this, $name);
        if (
            $reflection->isPublic() &&
            $reflection->getNumberOfParameters() === count($args)
        ) {

        }
    }
}
