<?php

namespace BOS\Player;

use BOS\Player\User;

class RequestAgainstEnvironment {
    var $environment;
    var $partition;
    var $requestData;

    use AuthorizesRequest;

    function __construct($requestData, $environment) {
        $this->environment = $environment;
        $this->partition = null;
        $this->requestData = $requestData;
    }


    function hasModule($id) {
        return $this->partition->hasModule($id);
    }

    function dispatch() {


        @list($prefix, $partitionId, $moduleId, $restUrl) = $this->environment->parseRequestUrl($this->requestData);

        if (!$partitionId) {
            throw new Exceptions\SelectPartition();
        }

        // will throw an UnknownEnvironmentException if it fails.
        $partition = $this->environment->getPartition($partitionId);

        $this->partition = $partition;

        $this->environment->setGlobals();

        // Must be a multi environment server
        // and USERDATA_ROOT_DIR should be defined (for setting session_save_path)
        if ($this->environment->getBaseUrl()) {
            if (isset($_ENV['USERDATA_ROOT_DIR'])) {
                $session_save_path = $this->environment->getHomeDir() . '/sessions';
                BosLog::info('Set session save path to %s', $session_save_path);
                ini_set('session.save_path', $session_save_path);
            }
            BosLog::info('Set session cookie path to %s', $_ENV['BOS_ENVIRONMENT_URL']);
            ini_set('session.cookie_path', $_ENV['BOS_ENVIRONMENT_URL']);
        }

        if ($this->isHttps()) {
            // set secure cookie always
            ini_set('session.cookie_secure', 1);
        }

        if (!$moduleId) {
            throw new Exceptions\SelectModule($partition, $this);
        }

        $baseUrl = $partition->url("$moduleId/");
        // This is important for laraval and spa's and stuff.
        // when accessing module INDEX (/) there must be a trailing slash.
        if (strpos($_SERVER['REQUEST_URI'], $baseUrl) === false) {
            header("Location: $baseUrl"); 
            exit;
        }

        $_SERVER['BOS_BASE_URL'] = $_ENV['BOS_BASE_URL'] = $baseUrl;
        $_SERVER['BOS_MODULE_URL'] = $_ENV['BOS_MODULE_URL'] = $baseUrl;
        $_SERVER['SERVER_URL'] = $_ENV['SERVER_URL'] = ((isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] !== null) ? $_SERVER["HTTP_X_FORWARDED_PROTO"] :  (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] !== null) ? $_SERVER['REQUEST_SCHEME'] :  'http'). '://'.$_SERVER['HTTP_HOST'];
        $_SERVER['BOS_BASE_URL_FULL'] = $_ENV['BOS_BASE_URL_FULL'] = "{$_ENV['SERVER_URL']}{$_ENV['BOS_BASE_URL']}"; 
        // $this->setGlobals();

        $partition->setGlobals();

        // will throw on failure.
        $module = $partition->getModule($moduleId);

        $this->requestUri = $restUrl;

        $_SERVER['BOS_REQUEST_URI'] = $_ENV['BOS_REQUEST_URI'] = $this->requestUri;

        $this->requestBase = $partition->url("$moduleId/");

        if (method_exists($this, 'authorize')) {
            $this->authorize($module);
        }


        // ipv $content = $module->executeRequest 
        // gaan we over naar een callback mechanisme, 
        // vanwege de requireInGlobalScope mogelijkheid
        // zal het verdere verloop van de request niet meer linear zijn        

        BosLog::info('Starting module::executeRequest');
        $module->executeRequest($this, function($content) use ($module) {

            $isAjaxRequest = $this->isAjaxRequest();

            $shouldWrapLayout = !$isAjaxRequest && !$module->isStandaloneType() && !isset($_ENV['SKIP_LAYOUT']);
    
            BosLog::info('Post module execution isAjax: %b shouldWrapLayout: %b', [$isAjaxRequest, $shouldWrapLayout]);

            if ($shouldWrapLayout) {
                $layout = $this->getLayout($module);
                echo $layout->render($content);
            } else {
                echo $content;
            }    
        });
    }

    function getLayout($module) {
        //echo '<pre>';print_r($this->environment);

        $cmd = $this->environment->collectedModuleData;

        if (isset($cmd['bosLayout'])) {
            BosLog::info('layout: environment has custom layout %s', reset($cmd['bosLayout']));

            $object = new Layouts\Stack($module, $this, [
                Layouts\ExternallyProvidedLayout::class => [
                    'layout' => reset($cmd['bosLayout'])
                ]
            ]);    
        } else {
            BosLog::info('layout: using BOS\Player\Layouts\Base');

            $object = new Layouts\Base($module, $this);
        }

        return $object;
    }

    function isHttps() {
        return ((isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] !== null) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] :  '') === 'https' ||
                isset($_SERVER['HTTPS'])
        ;
    }

    function isAjaxRequest() {

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            return true;
        }

        if (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }
    }

    function getMenuItemsForUser() {
        $menuItems = (isset($this->environment->collectedModuleData['menu']) && $this->environment->collectedModuleData['menu'] !== null) ? $this->environment->collectedModuleData['menu'] :  [];

        // First: Filter menu items for user:
        $menuItems = array_values(array_filter($menuItems, function ($menuItem) {
            if ((isset($menuItem->access) && $menuItem->access !== null) ? $menuItem->access :  false) {
                return $this->userHasLevel($menuItem->access);
            } else {
                return $this->mayAccessModule($menuItem->module);
            }
        }));

        // Now sort them based on weight.
        usort($menuItems, function($a, $b) {
            return ((isset($a->weight) && $a->weight !== null) ? $a->weight :  0) - ((isset($b->weight) && $b->weight !== null) ? $b->weight :  0);
        });

        return array_map(function($menuItem) {
            $part = (isset($this->partition) && $this->partition !== null) ? $this->partition :  $this->environment;

            if ((isset($menuItem->absoluteUrl) && $menuItem->absoluteUrl !== null) ? $menuItem->absoluteUrl :  false) {
                $menuItem->url = $menuItem->absoluteUrl;
            } else {
                $menuItem->url = $part->url($menuItem->module ."/" . ((isset($menuItem->url) && $menuItem->url !== null) ? $menuItem->url :  ''));
            }
            return $menuItem;
        }, $menuItems);

    }

}
