<?php

namespace BOS\Player\Response;

class EnvironmentNotFound {
    function __construct($requestData) {
        $this->requestData = $requestData;
    }

    function render() {
        header('HTTP/1.1 404 Not found');
        
        return <<<HTML
<!DOCTYPE html>
<html>
    <head>
        <style>
        html, body {
            background: #f0f0f0;
            font-family: Sans;
        }
        </style>
    </head>
    <body>
        <div style="display: flex; justify-content:center; align-items: center; width: 100vw; height: 100vh;">
            <div style="background: white; padding: 100px; border: 1px solid #ccc; border-radius: 10px; box-shadow: 0 0 50px rgba(0,0,0,0.15);">
                Environment not found.
            </div>
        </div>
    </body>
</html>

HTML;
    }

    function __toString() {
        return $this->render();
    }
}