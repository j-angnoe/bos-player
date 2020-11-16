<?php

namespace BOS\Player;


class EnvironmentPartition {
    var $data;
    var $name;
    var $environment;

    function __construct($id, $environment) {
        if (!isset($environment->partitions[$id])) {
            throw new Exceptions\UnknownPartition($id);
        }

        $data = $environment->partitions[$id];

        $this->data = $data;
    $this->environment = $environment;
        $this->name = $data['name'];
        $this->id = $id;

        // @fixme - using all environment modules is just to get 
        // started. This should use the partition/module mapping in the future.

        if ((isset($data['modules']) && $data['modules'] !== null) ? $data['modules'] :  false) {
            $moduleMap = [];
            foreach ($environment->modules as $mod){
                if (!isset($moduleMap[$mod['id']])) {
                    $moduleMap[$mod['id']]=$mod;
                }
            }

            $this->modules = array_map(function($modId) use ($moduleMap) {
                return $moduleMap[$modId];
            }, $data['modules']);

        } else {
            $this->modules = $this->environment->modules;
        }
    }

    function getModules() {
        return $this->modules;
    }

    function url($path = '') {
        return $this->environment->url("{$this->id}/" . ltrim($path,'/'));
    }

    function setGlobals() {
        $envs = [];
        $envs['BOS_PARTITION_URL'] = $this->url();
        $envs['BOS_PARTITION_ID'] = (isset($this->data['id']) && $this->data['id'] !== null) ? $this->data['id'] :  null;

        $this->environment->setGlobals($envs);
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

    function hasModule($id) {
        foreach ($this->modules as $mod) {
            if ($mod['id'] === $id) {
                return true;
            }
        }
        return false;
    }
}