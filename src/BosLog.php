<?php

namespace BOS\Player;

class BosLog {
    static function log($message, $context = null, $type = 'log') {
        $logEntry = ['time' => microtime(true), 'context' => $context, 'type' => $type, 'message' => $message];
        $GLOBALS['BosPlayerLogs'][] = $logEntry;
    }
    static function info($message, $context = null) {
        static::log($message, $context, 'info');
    }
    static function error($message, $context = null) {
        static::log($message, $context, 'info');
    }

    function spy($name, $data) {
        return new Spy($name, $data);
    }
}

class Spy implements \ArrayAccess {
    function __construct($name, $data) {
        $this->name = $name;
        $this->data = $data;
    }
    function offsetExists($key) {
        return isset($this->data[$key]);
    }
    function offsetGet($key) {
        BosLog::info('%s: accesses %s', [$this->name, $key]);
        return $this->data[$key];
    }
    function offsetSet($key, $value) {
        BosLog::info('%s: sets %s', [$this->name, $key]);
        $this->data[$key] = $value;
    }
    function offsetUnset($key) {
        unset($this->data[$key]);
    }
}