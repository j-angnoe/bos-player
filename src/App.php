<?php

namespace BOS\Player;

use BOS\Player\Environment;

use BOS\Player\Utils\BuiltinWebserver;
use BOS\Player\Utils\ExceptionHandler;
use BOS\Player\RequestAgainstEnvironment;

define('BOS_PLAYER_REQUEST_START', microtime(true));

class App {

    var $featureOauth = false;

    function setCatalogueDir($CATALOGUE_DIR) {
        $_ENV['CATALOGUE_DIR'] = $CATALOGUE_DIR;
    }

    function setEnvironmentFile($ENVIRONMENT_FILE) {
        $_ENV['ENVIRONMENT_FILE'] = $ENVIRONMENT_FILE;
    }

    function enableOauth() {
        $this->featureOauth = true;
    }

    function init() {
        if (!isset($_ENV['CATALOGUE_DIR'])) {
            exit("Please make sure the CATALOGUE_DIR has been set.");
        }

        if (!is_dir($_ENV['CATALOGUE_DIR'])) {
            exit("CATALOGUE_DIR `" . $_ENV['CATALOGUE_DIR'] . "` does not exist.");
        }

        $GLOBALS['BosPlayerLogs'] = [
            [
                'time' => microtime(true),
                'message' => 'Started'
            ]
        ];
        
        $_ENV['BOS_PLAYER_DIR'] = realpath(__DIR__ . '/../../');
        $_ENV['BOS_PLAYER_AUTOLOADER'] = "{$_ENV['BOS_PLAYER_DIR']}/vendor/autoload.php";
        $_ENV['BOS_PLAYER_CATALOGUE'] = "{$_ENV['BOS_PLAYER_DIR']}/catalogue/";
    }
    function dispatch() {
        $this->init();

        try {     
        
            if ($this->featureOauth) { 
                Utils\OAuth::middleware();
            }
    
            ExceptionHandler::register();
            
            //file_put_contents('php://stderr', $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . PHP_EOL);

            BuiltinWebserver::tryServeStatics();
            $environment = Environment::fromFile($_ENV['ENVIRONMENT_FILE']);
    
            $GLOBALS['collectedModuleData'] = $environment->collectedModuleData;
            
            $request = new RequestAgainstEnvironment($_SERVER, $environment);
            
            // $displayLog = false;
            
            // if (isset($_GET['bos-player-logs'])) {
            //     $displayLog = true;
            // }
    
            // if ($displayLog) { ob_start(); }
                
            $response = $request->dispatch();        
            
            // if ($displayLog) { 
            //     BosLog::log('Request finished.');
            //     ob_get_clean(); 
            //     (new Utils\LogDisplayer($GLOBALS['BosPlayerLogs']))->render();
            // }
        } catch (Exceptions\UnknownPartition $e) {
            echo (new Response\SelectPartition($environment, $mayRedirect = false));    
        } catch (Exceptions\SelectPartition $e) {
            echo (new Response\SelectPartition($environment));
        } catch (Exceptions\SelectModule $e) {
            echo (new Response\SelectModule($e));
        } catch (Exceptions\LoginRequired $e) {
            // @fixme - this should not be hardcoded.
    
            header('HTTP/1.1 403 Forbidden');
    
            if ($environment->provides('authentication')) {
                $module = $environment->whoProvides('authentication', true);
                $login_url = $module->definition['data']['login_url'] ?? false;
                $login_url = $request->partition->url('/'.$module->id.'/'.ltrim($login_url,'/'));
                if ($request->isAjaxRequest()) {
                    exit(json_encode([
                        'error' => 'You are not logged in',
                        'solution' => [
                            'location' => $login_url
                        ]
                    ]));
                } else {
                    header('Location: '.$login_url . '?redirect='.urlencode($_SERVER['REQUEST_URI']));
                }
            } else {
                exit("Login required.");
            }
    
        } catch (Exceptions\FileExecutionNotAllowed $e) {
    
            if ($_SERVER['APP_DEBUG'] ?? false) {
                header('HTTP/1.1 403 Not allowed');
                echo "Tried to execute a non-php file.";
            } else {
                header('HTTP/1.1 404 Not found');
            }
    
        } catch (Exceptions\InsufficientPermissions $e) {
            header('HTTP/1.1 401 Not authorized');
    
            if (!$request->isAjaxRequest()) {
                echo "Not enough permissions";
            }
        } catch (Exceptions\RequireInGlobalScope $e) {
            throw $e;
        } catch (\Exception $e) {
            if (isset($displayLog) && $displayLog) { 
                //LogTrait::error($e->getMessage(), $e->getTraceAsString(), 'exception');
                (new Utils\LogDisplayer($GLOBALS['BosPlayerLogs']))->render();
                echo $e;
            } else {
                throw $e;
            }
        }        
    }

    // When a module requires it should be run 
    // inside the `global` scope (i.e. the scope of the initial php 
    // file). Variables that are defined in this initial php script
    // are globally accessible. Legacy applications often depend on 
    // this rule.    
    function dispatchInGlobalScope($variableName = 'app') {
        return '
            try { 
                $'. $variableName .'->dispatch();    
            } catch (BOS\Player\Exceptions\RequireInGlobalScope $e) {
                ob_start();
                require_once($e->file);
            
                if ($e->callback) {
                    $content = ob_get_clean();
                    call_user_func($e->callback, $content);
                } else {
                    ob_end_flush();
                }
            }
        ';
    }
}


