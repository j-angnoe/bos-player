<?php

namespace BOS\Player;
use \Exception;

class Environment {
    public $name = '';
    public $storages = [];
    public $modules = [];
    public $partitions = [];
    public $envs = [];
    public $source = null;
    protected $baseUrl = '';

    use LogTrait;

    public function __construct($definition = []) {
        $importable = ['name', 'storages','modules','partitions','envs','source'];

        foreach ($importable as $key) {
            if (isset($definition[$key])) {
                $this->$key = $definition[$key];
            }
        }

        // Pre-load inline definitions
        foreach (((isset($this->modules) && $this->modules !== null) ? $this->modules : []) as $module) {
            if (isset($module['definition'])) {
                $module['definition']['__path'] = realpath(dirname($fileName));
                $definition = json_decode(json_encode($module['definition']));
                Module::setModuleDefinition($module['id'], $definition);
            }
        }

        $this->collectedModuleData = [];
        BosLog::info('Start collecting module data of ' . count($this->modules) .' modules');
        $this->collectModuleData();
        BosLog::info('Done collecting module data');

    }

    public static function fromFile($fileName) {
        if (!file_exists($fileName)) {
            if (!isset($_ENV['ENVIRONMENTS_DIR']) || !file_exists("{$_ENV['ENVIRONMENTS_DIR']}/$fileName")) {
                throw new Exceptions\EnvironmentNotFound;
            }
            $fileName = "{$_ENV['ENVIRONMENTS_DIR']}/$fileName";
        }

        BosLog::log(__METHOD__ . ' reading file ' . $fileName);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        if ($extension === 'php') {
            $environmentDefinition = require $fileName;
        } else {
            $environmentDefinition = json_decode(file_get_contents($fileName), true);
        }
                
        if (!$environmentDefinition) {
            BosLog::error('Error reading environmentDefinition json', [
                'json_error' => json_last_error_msg()
            ]);
            throw new Exception('Failed loading environmentDefinition.');
        }

        $environment = new static([
            'name' => (isset($environmentDefinition['name']) && $environmentDefinition['name'] !== null) ? $environmentDefinition['name'] :  'Unnamed environment',
            'storages' => (isset($environmentDefinition['storages']) && $environmentDefinition['storages'] !== null) ? $environmentDefinition['storages'] :  [],
            'modules' => (isset($environmentDefinition['modules']) && $environmentDefinition['modules'] !== null) ? $environmentDefinition['modules'] :  [],
            'partitions' => (isset($environmentDefinition['partitions']) && $environmentDefinition['partitions'] !== null) ? $environmentDefinition['partitions'] :  [],
            'envs' => (isset($environmentDefinition['envs']) && $environmentDefinition['envs'] !== null) ? $environmentDefinition['envs'] :  [],
            'source' => $fileName
        ]);

        // @fixme: Collecting module data on every request is a performance 
        // penalty, for debugging it's ok, but in production this should be cached.
        return $environment;
    }

    function setBaseUrl($baseUrl) {
        $this->baseUrl = $baseUrl;
    }
    
    function getBaseUrl() {
        return $this->baseUrl;
    }
    function url($url = '') {
        return str_replace("//", "/", "/" . $this->baseUrl . "/" . $url);
    }

    public function parseRequestUrl($requestData) {

        $url = $requestData['REQUEST_URI'];
        
        // trim the environment baseUrl from the url.
        $url = substr($url, strlen($this->baseUrl));

        // add a `null` partition to the url to trigger null-partition
        if (empty($this->partitions)) {
            $url = "/null/" . ltrim($url,'/');
        }

        $pieces = explode('/', trim($url,'/'), 3);
        
        return array_merge([$this->baseUrl], $pieces);
    }


    // A map of providers and units.
    var $providers = [];

    function collectModuleData() {
        $collectedModuleData = [];

        // prevent cluttering of registered values.
        $this->providers = [];

        $specialAttributes = [
            'menu' => function($value, $module) {
                $value->module = $module['id'];
                return $value;
            },
            'provides' => function($value, $module) {
                $this->providers[$value] = (isset($this->providers[$value]) && $this->providers[$value] !== null) ? $this->providers[$value] :  [];
                $this->providers[$value][] = $module['id'];

                return $value;
            },
            'bosLayout' => function($value, $module) {
                return $value;
            },
            'directories' => function ($value) {
                return $value;
            }
        ];

        foreach ($this->modules as $module) {
            if (isset($module['disabled']) && $module['disabled']) {
                continue;
            }
            $definition = Module::readDefinition($module['id'], false);

            // Everything inside the `data` key will be collected.
            foreach (((isset($definition->data) && $definition->data !== null) ? $definition->data :  []) as $key=>$value) {
                if (!is_array($value)) {
                    $value = [$value];
                }
                $collectedModuleData[$key] = array_merge((isset($collectedModuleData[$key]) && $collectedModuleData[$key] !== null) ? $collectedModuleData[$key] :  [], $value);        
            }

            // Special collects: access-map
            if ($module['id'] === 'access-map') {
                $this->accessMap = (isset($module['accessMap']) && $module['accessMap'] !== null) ? $module['accessMap'] :  [];
                $this->accessLevelDefinitions = (isset($module['levelDefinitions']) && $module['levelDefinitions'] !== null) ? $module['levelDefinitions'] :  [];
            }

            // Special collects: certain attributes.
            foreach ($specialAttributes as $key => $getter) {
                if (!isset($definition->$key)) {
                    continue;
                }

                if (!isset($collectedModuleData[$key])) {
                    $collectedModuleData[$key] = [];
                }

                $value = $definition->$key;

                if (!is_array($value)) {
                    $value = [$value];
                }

                $collectedModuleData[$key] = array_merge($collectedModuleData[$key], array_map(function($value) use ($getter, $module) {
                    return $getter($value, $module);
                }, $value));
            }
        }

        $this->collectedModuleData = $collectedModuleData;
    }

    function handleSpecialEnvValue($value) {   

        if (substr($value, 0, 2) === './' || substr($value, 0, 3) === '../') {
            return realpath(dirname($this->source).'/'.$value);
        }
        return $value;
    }

    public function getHomeDir() {
        if (!isset($_ENV['USERDATA_ROOT_DIR'])) {
            throw new \Exception('Running getHomeDir without USERDATA_ROOT_DIR being set.');
        }
        if ($this->name > '') {
            return rtrim($_ENV['USERDATA_ROOT_DIR'],'/') . '/' . $this->name . '/';
        } else {
            throw new \Exception('Attempt to getHomeDir on nameless environment');
        }
    }

    function getGlobals() {
        $env = [];

        // Ensure catalogue dir is an absolute path, 
        // because we'll fix PhpModuleExecutor will chdir to everywhere.
        $_ENV['CATALOGUE_DIR'] = realpath($_ENV['CATALOGUE_DIR']);
        if ((isset($_ENV['ENVIRONMENT_FILE']) && $_ENV['ENVIRONMENT_FILE'] !== null) ? $_ENV['ENVIRONMENT_FILE'] :  false) {
            $_ENV['ENVIRONMENT_FILE'] = realpath($_ENV['ENVIRONMENT_FILE']);
        }
        if ((isset($_ENV["ENVIRONMENTS_DIR"]) && $_ENV["ENVIRONMENTS_DIR"] !== null) ? $_ENV["ENVIRONMENTS_DIR"] :  false) {
            $_ENV['ENVIRONMENTS_DIR'] = realpath($_ENV['ENVIRONMENTS_DIR']);
        }

        $env['DB_HOST']     = (isset($_ENV['DB_HOST']) && $_ENV['DB_HOST'] !== null) ? $_ENV['DB_HOST'] :  (isset($this->storages['database']['host']) && $this->storages['database']['host'] !== null) ? $this->storages['database']['host'] :  null;
        $env['DB_PORT']     = (isset($_ENV['DB_PORT']) && $_ENV['DB_PORT'] !== null) ? $_ENV['DB_PORT'] :  (isset($this->storages['database']['port']) && $this->storages['database']['port'] !== null) ? $this->storages['database']['port'] :  null;
        $env['DB_DATABASE'] = (isset($_ENV['DB_DATABASE']) && $_ENV['DB_DATABASE'] !== null) ? $_ENV['DB_DATABASE'] :  (isset($this->storages['database']['database']) && $this->storages['database']['database'] !== null) ? $this->storages['database']['database'] :  null;
        $env['DB_USERNAME'] = (isset($_ENV['DB_USERNAME']) && $_ENV['DB_USERNAME'] !== null) ? $_ENV['DB_USERNAME'] :  (isset($this->storages['database']['username']) && $this->storages['database']['username'] !== null) ? $this->storages['database']['username'] :  null;
        $env['DB_PASSWORD'] = (isset($_ENV['DB_PASSWORD']) && $_ENV['DB_PASSWORD'] !== null) ? $_ENV['DB_PASSWORD'] :  (isset($this->storages['database']['password']) && $this->storages['database']['password'] !== null) ? $this->storages['database']['password'] :  null;    

        if (isset($_ENV['USERDATA_ROOT_DIR'])) {
            $env["HOME"] = $this->getHomeDir();
        }

        $env['BOS_ENVIRONMENT_URL'] = $this->url();

        foreach ($this->envs as $category => $envs) {
            if (is_array($envs)) {
                foreach ($envs as $env_name => $env_value) {
                    $env[$env_name] = $this->handleSpecialEnvValue($env_value);
                }
            } else {
                $env[$category] = $this->handleSpecialEnvValue($envs);
            }
        }

        return $env;
    }

    function setGlobals($vars = null, $putEnv = false) {
        if (!$vars) {
            $vars = $this->getGlobals();
        }

        $_ENV = array_merge($_ENV, $vars);
        $_SERVER = array_merge($_SERVER, $vars);

        // warning: putenv/getenv may cause segmentation faults
        // when they occur simultanously, as they are not thread-safe.
        if ($putEnv) {
            foreach($vars as $key=>$value) {
                putenv("$key=$value");
            }
        }
    }

    function listPartitions() {
        return $this->partitions;
    }

    function getPartition($id) {
        if (empty($this->partitions) && $id === 'null') {
            return new EnvironmentNullPartition(null, $this);
        }
        return new EnvironmentPartition($id, $this);
    }

    function getModuleList() {
        return array_map(function($m) { return $m['id']; }, $this->modules);
    }

    function getModule($id) {
        $modules = $this->modules;
        $foundModule = false;
        foreach ($modules as $mod) {
            if ($mod['id'] === $id) {
                $foundModule = $mod;
                break;
            }
        }

        if (!$foundModule) {
            // @fixme - more specific module.
            throw new Exceptions\ModuleNotFound("Module `$id` is not installed for environment.");
        }

        return new Module($id, $foundModule);
    }

    // Does the environment provide some service?
    function provides($subject) {
        return isset($this->providers[$subject]);
    }

    function whoProvides($subject, $returnModule = false) {
        if ($this->provides($subject)) {
            $module = $this->providers[$subject];
            if (!$returnModule) {
                return $module;
            }

            if (is_array($module)) {
                $module = reset($module);
            }
            return $this->getModule($module);
        }
        if ($returnModule) {
            throw new \Exception(__METHOD__ . ': no providers for ' . $subject);
        }
    }
}
