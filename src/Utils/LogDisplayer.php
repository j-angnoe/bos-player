<?php

namespace BOS\Player\Utils;

class LogDisplayer {
    function __construct($logs) {
        $this->logs = $logs;
    }

    function render() {
        if (empty($this->logs)) {
            return;
        }
        $htmlOutput = false;
        if (((isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] !== null) ? $_SERVER['HTTP_ACCEPT'] :  false) && preg_match("~(text/html|\*.\*)~", $_SERVER['HTTP_ACCEPT'])) {
            $htmlOutput = true;
        }
        if ($htmlOutput) {
            echo '<style>
            td { font-family: mono,monospace; font-size:8pt; border-bottom:1px solid #999; padding: 2px 5px;} 
            table { }</style>';
            echo '<table cellpadding=0 cellspacing=0 width=100%>';
            $firstTimeLogged = $this->logs[0]['time'];
            foreach ($this->logs as $log) {

                $message = $log['message'];
                if (strpos($message, '%') !== false) {
                    $message = vsprintf($message, $log['context']);
                    $log['context'] = null;
                }
                $message = substr($message, 0, 250);
                if ((isset($log['context']) && $log['context'] !== null) ? $log['context'] :  false) {
                    $message = json_encode($log['context'], JSON_PRETTY_PRINT);
                }

                echo '<tr>';
                echo '<td>' . round(1000*($log['time'] - $firstTimeLogged),2) . '</td>';
                echo '<td>' . ((isset($log['type']) && $log['type'] !== null) ? $log['type'] :  ''). '</td>';
                echo '<td>' . $message;
                echo '</td>';

                echo '</tr>' . PHP_EOL;
            }
            echo '</table>';
        }
    }
}