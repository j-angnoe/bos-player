<?php

namespace BOS\Player;
use BOS\Player\BosLog;

trait AuthorizesRequest {
    function authorize($module, $throw = true) {
        if (isset($_ENV['SINGLE_USER_MODE'])) {
            BosLog::log('authorize: _ENV[SINGLE_USER_MODE] is active, allowing all.');
            return true;
        }

        // @fixme - laravel auth hardcoded...
        // If there is no laravel-auth then we skip the authorize.
        if (!$this->environment->provides('authentication')) {
            BosLog::log('authorize: Environment provides no authentication, allowing all.');
            return true;
        }

        // check accessess..
        // levels: public, user, admin, or something else.

        // default to the most heavy access..

        if (!is_string($module)) {
            $moduleId = $module->id;
            //lazy
            $getDefinition = function () use ($module) {
                return $module->definition;
            };
        } else {
            $moduleId = $module;
            //lazy
            $getDefinition = function () use ($moduleId) {
                return Module::readDefinition($moduleId);
            };
        }



        if ($this->environment->accessMap[$moduleId] ?? false) {
            $requiredAccessLevel = $this->environment->accessMap[$moduleId];
        } else {
            $definition = $getDefinition();
            $requiredAccessLevel = strtolower($definition['access'] ?? 'user');
        }

        BosLog::log('authorize: %s requires user level %s', [$moduleId, $requiredAccessLevel]);
        // echo "Check $requiredAccessLevel";

        $result = $this->userHasLevel($requiredAccessLevel, $throw);

        BosLog::log('authorize result: %b', [$result]);

        return $result;
    }    

    function userHasLevel($level, $throw = false) {

        if ($this->environment->accessLevelDefinitions ?? false) {
            $level = $this->environment->accessLevelDefinitions[$level] ?? $level;
            // echo "Level became $level\n";
        }

        switch($level) {
            case 'deny':
                // @todo - throw Exceptions\NoAccess;
                if ($throw) { throw new Exceptions\InsufficientPermissions; }
                return false;
            case 'everyone':
            case 'public': // for some old modules sake. 
                return true;
            case 'guest':
                if (!User::isLogged()) {    
                    return true;
                } else {
                    // @todo - throw Exceptions\NoAccess;
                    if ($throw) { throw new Exceptions\InsufficientPermissions; }
                    return false;
                }
            case 'user': 
                if (!User::isLogged()) {
                    if ($throw) { throw new Exceptions\LoginRequired; }
                    return false;
                } 
                return true;
            case 'admin':
                if (!User::isLogged()) {
                    if ($throw) { throw new Exceptions\LoginRequired; }
                    return false;
                } 

                $isAdmin = User::isAdmin();

                if (!$isAdmin) {
                    if ($throw) { throw new Exceptions\InsufficientPermissions; }
                    return false;
                }

                return true;
        }

        // Admin will trump specific cases:
        if (User::isAdmin()) {
            return true;
        }
        // no such case

        if (strpos($level, 'user:') === 0) {

            if (!User::isLogged()) {
                if ($throw) { throw new Exceptions\LoginRequired; }
                return false;
            } 
            
            // user tag specification

            $result = false;

            @list(, $spec, $value) = explode(':', $level, 3);

            if (is_numeric($spec)) {
                $value = $spec;
                $spec = 'id';
            }

            $userData = User::getUser();

            if (isset($userData[$spec])) {
                if ($value > '') {
                    $result = "{$userData[$spec]}" === "$value";
                    BosLog::info('userHasLevel %s === %s : %b', [$userData[$spec], $value, $result]);
                } else {
                    $result = in_array(strtolower("{$userData[$spec]}"), ["true", "1","yes","on"]);
                    BosLog::info('userHasLevel has attribute %s : %b', [$userData[$spec], $result]);
                }
            } else {
                $result = false;
            }
            
            if (!$result && $throw) { throw new Exceptions\InsufficientPermissions; }
            return $result;
        }
    }
    function mayAccessModule($module) {
        return $this->authorize($module, false);
    }
}

