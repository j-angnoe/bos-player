<?php

namespace BOS\Player\Utils;

class Laravel {

    /**
     * Bootstrap a laravel application inside a sibling
     * module. To be used inside a non-laravel module.
     * 
     * We assume that the module being bootstrapped containing laravel
     * contains laravel 6.
     */
    static public function bootstrapLaravel($path = null) {
        if ($path === null) {
            $path = getcwd();
        }
        $root = substr($path,0,1) === '/' ? '' : $_ENV["CATALOGUE_DIR"];


        // Dont set globals, might mess up modules that try to bootstrap this.
        // $_SERVER['SCRIPT_NAME'] = "{$baseUrl}index.php";
        // $_SERVER['PATH_INFO'] = $baseUrl;
        // $_SERVER['SCRIPT_FILENAME'] = "/VIRTUAL_ROOT/{$baseUrl}index.php";
        // $_SERVER['APP_URL'] = $_ENV['APP_URL'] = $_SERVER["SERVER_URL"] . $baseUrl;

        require_once $root . "/$path/vendor/autoload.php";
        $app = require_once $root . "/$path/bootstrap/app.php";
        
        $app->bootstrapWith([
            \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
            \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
            \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
            \Illuminate\Foundation\Bootstrap\SetRequestForConsole::class,
            \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
            \Illuminate\Foundation\Bootstrap\BootProviders::class
        ]);
    
        if (isset($_SERVER['SERVER_URL'])) {
            $baseUrl = "/" . ltrim($_ENV['BOS_ENVIRONMENT_URL'] ?? '',"/") . "/" . basename($path);
 
            // But do change UrlGenerator root
            $app->get(\Illuminate\Routing\UrlGenerator::class)->forceRootUrl($_SERVER["SERVER_URL"] . $baseUrl);
        }
        restore_error_handler();
        restore_exception_handler();
        
        return $app;
    }
    static public function bootstrap($path = null) {
        return static::bootstrapLaravel($path);
    }
}
