<?php

namespace BOS\Player\Utils;

class ExceptionHandler { 
    static function handleException($exception) {
        header('Content-type: text/plain');
        http_response_code(500);

        echo "Fatal error\n";
        echo "Request: " . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . "\n";
        
        $tmp = tempnam('/tmp', 'bos-error-reports-');

        file_put_contents($tmp, json_encode([
            'request' => $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'],
            'error' => $exception->getMessage(),
            '_ENV' => $_ENV,
            '_SERVER' => $_SERVER,
            '_SESSION' => isset($_SESSION) ? $_SESSION : '(no session)',
            'trace' => $exception->getTraceAsString(),
            'input' => file_get_contents('php://input')
        ]));

        echo "Logged to " . $tmp . "\n\n";

        echo $exception;
    }

    static function register() {
        set_exception_handler([static::class, 'handleException']);
    }
}