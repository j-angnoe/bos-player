<?php

namespace BOS\Player;

trait LogTrait {
    public $logs;

    function log($message, $context = null, $type = 'log') {
        $logEntry = ['time' => microtime(true), 'context' => $context, 'type' => $type, 'message' => $message];
        $this->logs[] = $logEntry;
        
        BosLog::log($message, $context, $type);
    }
    function info($message, $context = null) {
        $this->log($message, $context, 'info');
    }
    function error($message, $context = null) {
        $this->log($message, $context, 'info');
    }
}