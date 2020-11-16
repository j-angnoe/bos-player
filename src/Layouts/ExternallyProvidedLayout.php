<?php

namespace BOS\Player\Layouts;

/**
 * Provides us the capability to use layouts which are defined
 * inside catalogue modules.
 */
class ExternallyProvidedLayout extends Base {
    function __construct($module, $request, $options) {
        $this->module = $module;
        $this->request = $request;
        $this->options = $options;
    }

    function url($path = '') {
        $r = $this->request;

        $part = (isset($r->partition) && $r->partition !== null) ? $r->partition :  $r->environment;

        return '/' . ltrim($part->url($path),'/');
    }

    function render($content) {
        $layoutFile = $_ENV['CATALOGUE_DIR'] . '/'.$this->options['layout'];

        ob_start();
        $request = $this->request;
        $module = $this->module;

        include($layoutFile);

        $content = ob_get_clean();


        return $content;
    }
}
