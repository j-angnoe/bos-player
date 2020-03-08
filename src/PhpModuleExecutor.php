<?php

namespace BOS\Player;

class PhpModuleExecutor {
    function __construct(Module $module, $request) {
        $this->module = $module;
        $this->request = $request;
    }

    function __invoke($callback) {

        // todo or not to do...
        // phpbb breaks on this
        // frontcontroller types break if i dont do it...
        // @fixme - temporary fix, only overwrite this for php frontcontroller types (isset(type[main]))
        // $_SERVER['REQUEST_URI'] = "/" . ltrim($this->request->requestUri,"/");

        $type = $this->module->definition['type'];

        $path = $this->module->path;
        $uri = parse_url($this->request->requestUri, PHP_URL_PATH);
        $baseUrl = "/" . ltrim($_SERVER['BOS_BASE_URL'],"/");

        $cwd = getcwd();
        chdir($path);

        $shared = [];
        if (($this->module->definition['access'] ?? false)== 'admin') {
            $shared = ['request' => &$this->request];
        }
        

        $runScript = function() use ($shared, $type, $callback) {
            extract($shared);
            // Dont serve dotfiles.
    
            if (pathinfo(func_get_arg(0), PATHINFO_EXTENSION) !== 'php') {
                throw new Exceptions\FileExecutionNotAllowed;
            }

            if ($type['require_in_global_scope'] ?? false) {
                throw new Exceptions\RequireInGlobalScope(func_get_arg(0), function($content) use ($callback) {
                    $callback($content);
                });
            } else {
                ob_start();
                require_once(func_get_arg(0));
                return ob_get_clean();
            }
        };
        
        $content = null;

        if (is_string($type) || ($type['mainAllowPhp']??false) || !isset($type['main'])) {
            // default php routing:
        
            if (is_file("{$path}/{$uri}")) {
                chdir(dirname($uri));
            
                $_ENV['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] = "{$baseUrl}{$uri}";
                $_ENV['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] = "/VIRTUAL_ROOT{$baseUrl}{$uri}";
                $content = $runScript("{$path}/{$uri}");
            } elseif (file_exists("{$path}/{$uri}.php")) {
                chdir(dirname($uri.'.php'));

                $_ENV['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] = "{$baseUrl}{$uri}.php";
                $_ENV['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] = "/VIRTUAL_ROOT{$baseUrl}{$uri}.php";
                $content = $runScript("{$path}/{$uri}.php");
            } elseif (file_exists("{$path}/{$uri}/index.php")) {
                if ($uri) { 
                    chdir($uri); 
                } 
                $_ENV['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] = "{$baseUrl}{$uri}/index.php";
                $_ENV['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] = "/VIRTUAL_ROOT{$baseUrl}{$uri}/index.php";
                $content = $runScript("{$path}/{$uri}/index.php");
            } elseif (!isset($type['main'])) {
                // last resort, split file.php/PATH_INFO
                // this happens in phpbb/install/app.php/install
                
                list($file, $pathinfo) = explode('.php', $uri, 2);
                $file .= '.php';
                if (file_exists("{$path}/{$file}")) {
                    chdir(dirname($file));

                    $_ENV['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] = "{$baseUrl}{$file}";
                    $_ENV['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] = "/VIRTUAL_ROOT{$baseUrl}{$file}";
                    $content = $runScript("{$path}/$file");
                } else {
                    header('HTTP/1.1 404 Not found');
                    exit("File not found: $path/$uri");
                }
            }      
        } 

        if ($type['main'] ?? false) {
            //  $_SERVER['REQUEST_URI'] = "/" . ltrim($this->request->requestUri,"/");
            chdir(dirname($type['main']));
            
            $_ENV['PATH_INFO'] = $_SERVER['PATH_INFO'] = "/" . ltrim($this->request->requestUri,"/");
            $_ENV['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] = "{$baseUrl}{$type['main']}";
            $_ENV['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] = "/VIRTUAL_ROOT{$baseUrl}{$type['main']}";
            $content = $runScript("$path/{$type['main']}");
        } elseif (is_array($type)) {
            $type = $type['id'];
        }

        // OpenBase dir may cause errors
        chdir($cwd);
        
        $callback($content);
    }
}


