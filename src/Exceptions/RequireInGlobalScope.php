<?php

namespace BOS\Player\Exceptions;

use Exception;

class RequireInGlobalScope extends Exception {
    public $file;
    public $callback;
    
    function __construct($file, $callback) {
        $this->file = $file;
        $this->callback = $callback;
    }
}