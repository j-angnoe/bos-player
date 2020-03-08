<?php
namespace BOS\Player\Exceptions;

use Exception;

class SelectModule extends Exception {
    var $partition;

    function __construct($partition, $request) {
        $this->partition = $partition;
        $this->request = $request;
    }
}