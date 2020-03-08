<?php 

namespace BOS\Player;

class LaravelModuleExecutor {
    function __construct(Module $module, $request) {
        $this->module = $module;
        $this->request = $request;
    }

    function __invoke($callback) {
        //$_SERVER['REQUEST_URI'] = $this->request->requestUri;

        // This trick ensures that Request::prepareBaseUrl will 
        // use the proper base url (which must be equal to BOS_BASE_URL)
        // Now all routes and links inside the laravel application will
        // use the appropriate base url.

        $baseUrl = "/" . ltrim($_SERVER['BOS_BASE_URL'],"/");

        $_ENV['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] = "{$baseUrl}index.php";
        $_ENV['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] = "/VIRTUAL_ROOT/{$baseUrl}index.php";
        $_ENV['APP_URL'] = $_SERVER["SERVER_URL"] . $_ENV['BOS_BASE_URL'];
        
        // Now there was a bug when calling /partition-1/laravel-auth (without trailing slash) could not
        // be resolved. THis should be fixed by adding a trailing slash...
        // $_SERVER['REQUEST_URI'] = join('/', [$_ENV['BOS_BASE_URL'], $this->request->requestUri]);
        
        ob_start();

        require_once("{$this->module->path}/public/index.php");
        $content = ob_get_clean();
        $callback($content);
    }

    /**
     * This function will be called by EnvironmentManager::initStorages()
     * to prepare all laravel directories.
     * 
     * It is important to note that this may change with laravel versions..
     * For reference, laravel 6 applications were assumed.
     */
    public function ensureDirectories() {
        $moduleId = $this->module->id;
        return [
            "/$moduleId/framework/cache",
            "/$moduleId/framework/sessions",
            "/$moduleId/framework/views",
            "/$moduleId/logs",
            "/$moduleId/bootstrap",
            "/$moduleId/bootstrap/cache"    
        ];
    }
}
