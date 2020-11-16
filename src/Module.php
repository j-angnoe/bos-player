<?php

namespace BOS\Player;

class Module {
    static $moduleCache = [];

    var $id;
    var $path;
    var $settings;
    var $definition;

    function __construct($id, $configurationData) {
        $this->id = $id;

        $this->path = realpath($_ENV['CATALOGUE_DIR'] . '/' . trim($id,'/') . '/');

        $moduleDefinition = self::readDefinition($id);

        if ((isset($moduleDefinition['__path']) && $moduleDefinition['__path'] !== null) ? $moduleDefinition['__path'] :  false) {
            $this->path = $moduleDefinition['__path'];
        }
        $this->disabled = (isset($configurationData['disabled']) && $configurationData['disabled'] !== null) ? $configurationData['disabled'] :  null;
        $this->settings = (isset($configurationData['settings']) && $configurationData['settings'] !== null) ? $configurationData['settings'] :  [];
        $this->definition = $moduleDefinition;
    }

    static function setModuleDefinition($id, $definitionString) {
        if (!is_string($definitionString)) {
            $definitionString = json_encode($definitionString);
        }
        static::$moduleCache[$id] = $definitionString;
    }

    static function readDefinition($id, $asArray = true) {
        if (isset(static::$moduleCache[$id])) {
            return json_decode(static::$moduleCache[$id], $asArray);
        }

        $path = $_ENV['CATALOGUE_DIR'] . '/' . trim($id,'/') . '/';

        if (!is_dir($path)) {
            $path = __DIR__ . '/../catalogue/' . trim($id,'/') . '/';
        }

        if (!is_dir($path)) {
            throw new \Exception("Could not find module $id");
        }

        $moduleDefinitionFile = $path . 'bosModule.json';

        if (!file_exists($moduleDefinitionFile)) {
            throw new \Exception("No bosModule.json file found for module {$id}");
        }

        $moduleDefinition = json_decode(file_get_contents($moduleDefinitionFile),$asArray);

        if (!$moduleDefinition) {
            throw new \Exception("Error parsing bosModule.json file for module {$id}");
        }

        return $moduleDefinition;
    }

    public function setGlobals($environment) {
        $envs = [];
        $envs['BOS_MODULE_PATH'] = realpath($this->path);
        $envs['BOS_MODULE_ID'] = $this->id;

        $GLOBALS['BOS_MODULE_SETTINGS'] = $this->settings;

        $environment->setGlobals($envs);
    }

    function setOpenbasedirRestrictions() {
        if (isset($_ENV['SKIP_OPEN_BASEDIR'])) {
            return;

        }
        $openbase_dir = [
            "/tmp",             // voor laravel nativesessionhandler...
            $_ENV['BOS_PLAYER_DIR'],
            // All of catalogue, or not?
            $_ENV['CATALOGUE_DIR'],
            $this->path,
        ];

        if (isset($this->definition['open_basedir'])) {
            foreach ((array) $this->definition['open_basedir'] as $directory) {
                if ($directory > '/') {
                    $openbase_dir[] = $directory;
                }
            }
        }

        // If the module defines directories
        // we assume you're only going to read stuff from 
        // these directories.
        if (isset($this->definition['directories'])) {
            foreach ($this->definition['directories'] as $dir) {
                $openbase_dir[] = $_ENV['HOME'] . $dir;
            }
        } else {
            $openbase_dir[] = $_ENV['HOME'];
        }

        BosLog::info('Set open_basedir to %s', join(', ', $openbase_dir));

        //echo "Set openbasedir to " . join('<br>', $openbase_dir);

        ini_set('open_basedir', join(":", $openbase_dir));
    }
    function executeRequest($request, $callback = null) {
        // check if file exists inside a public/dist/build directory

        if ($this->disabled) {
            throw new Exceptions\ModuleNotFound('Module is disabled.');
        }

        $this->setGlobals($request->environment);

        $this->setOpenbasedirRestrictions();

        $requestedPath = parse_url($request->requestUri, PHP_URL_PATH);

        if (isset($this->definition['serveFrom'])) {
            
            $possibleFile = str_replace('//','/', $this->path . ($this->definition['serveFrom'] ? "/{$this->definition['serveFrom']}" : ''). "/". $requestedPath);

            BosLog::info('executeRequest: see serveFrom, check if %s can be served', $possibleFile);

            if (!Utils\BuiltinWebserver::serveFileAndExit($possibleFile)) {
                // handle it as a php file.   
            }
        }
        

        if (isset($this->definition['allowServeFrom'])) {
            
            foreach ((array) $this->definition['allowServeFrom'] as $root) {
                if (strpos("/" . ltrim($requestedPath,'/'), "/" . ltrim($root,'/')) === 0) {
                    $possibleFile = str_replace('//','/', $this->path . "/". $requestedPath);  

                    BosLog::info('executeRequest: allowServeFrom, check if %s can be served', $possibleFile);

                    if (!Utils\BuiltinWebserver::serveFileAndExit($possibleFile)) {
                        // handle as php file.
                    }
                }
            }
        }

        $executor = $this->getExecutor($request);

        BosLog::info('executeRequest, executor is of type %s', get_class($executor));

        $restoreGlobals = [
            '_SERVER' => $_SERVER,
            '_ENV' => $_ENV
        ];

        $executor(function ($content) use ($callback, $restoreGlobals) {
            BosLog::info('Module has been executed');

            foreach ($restoreGlobals as $key=>$value) {
                $GLOBALS[$key] = $value;
            }
            
            restore_error_handler();
            restore_exception_handler();

            if (!$callback) {
                echo $content;
            } else {
                $callback($content);
            }
        });
    }

    function isStandaloneType() {
        $type = $this->definition['type'];

        if (is_array($type)) {
            return (isset($type['standalone']) && $type['standalone'] !== null) ? $type['standalone'] :  false;
        }
        return false;
    }

    function getExecutor($request) {
        if (!isset($this->definition['type'])) {
            BosLog::error('Module %s does not have a type', $this->id);
            throw new \Exception("Module {$this->id} does not have a type.");
        }
        $type = $this->definition['type'];
        
        if (is_array($type) && isset($type['extends'])) {
            BosLog::info('Module %s extends %s', [$this->id, $type['extends']]);
            $baseModule = new Module($type['extends'], []);
            return $baseModule->getExecutor($request);
        }

        $type = ucfirst(is_string($type) ? $type : ((isset($type['id']) && $type['id'] !== null) ? $type['id'] :  'unknown'));
        $executorClass = "\BOS\Player\\{$type}ModuleExecutor";

        if (class_exists($executorClass)) {
            return new $executorClass($this, $request);
        }
        return false;
    }
}
