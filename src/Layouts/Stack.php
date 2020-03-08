<?php

namespace BOS\Player\Layouts;

/**
 * A layout pipeline, allows us to re-use layouts.
 */
class Stack {
    function __construct($module, $request, $stack) {
        $this->module = $module;
        $this->request = $request;
        $this->stack = $stack;
    }

    function render($content) {
        foreach ($this->stack as $class => $options) {

            if (is_numeric($class)) {
                $class = $options;
                $options = [];
            }

            if (!is_object($class) && class_exists($class)) {
                $obj = new $class($this->module, $this->request, $options);
            } else {
                $obj = $class;
            }

            $content = $obj->render($content);
        }
        return $content;
    }
}