<?php

// utilities for testing bos-player

global $pids;
global $lastPort;

if (!defined('TMP_DIR')) {
    define('TMP_DIR', '/tmp/bos-player-unit-tests/');
}


$pids = [];
$lastPort = 0;

function get_free_port() {
    // @fixme - naive implementation.
    return rand(26000, 26500);
}

function createFiles($files) {
    foreach ($files as $file => $content) {
        $file = TMP_DIR . "/$file";

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        file_put_contents($file, $content);
    }

    $files_to_destroy = array_keys($files);

    register_shutdown_function(function() use ($files_to_destroy) {
        $TMP = TMP_DIR;

        `rm -rf $TMP/**`;
    });
}

function start_webserver($catalogue_dir, $environment_file, $webserverBinary = 'bos-player') {
    $port = get_free_port();

    global $pids;

    if (is_dir($environment_file)) {
        $env_string = 'ENVIRONMENTS_DIR='.$environment_file;
        $env_options = '';
    } else {
        $env_string = '';
        $env_options = "--environment $environment_file";
    }

    $pid = exec($command = "{$env_string} $webserverBinary --catalogue $catalogue_dir $env_options --port $port 2>/dev/null 1>webserver.log & echo $!");

    usleep(300 * 1000); // 250 ms to be sure.
    
    $pids[] = $pid;

    global $lastPort;
    
    $lastPort = $port;

    register_shutdown_function('stop_webservers');

    return $port;
}

// We need to kill the process and all it's children..
// This is a naive implementation, requiring one pgrep call for each
// child recursively... 
function kill_pid_tree($pid) {
    if (!$pid || !is_numeric($pid)) {
        return;
    }

    $children = array_filter(explode("\n", trim(`pgrep -P $pid`)));
    
    array_map('kill_pid_tree', $children);

    `kill -9 $pid`;
}
function stop_webservers() {
    global $pids;

    array_map('kill_pid_tree', $pids);

    $pids = [];

    global $lastPort;
    $lastPort = null;

    @unlink('webserver.log');

}

function curl($request) {
    global $lastPort;

    if (($pos = strpos($request, '://')) && $pos > 0 && $pos < 10) {
        $url = $request;
    } else {
        $request = "/" . ltrim($request, "/");
        $url = "http://127.0.0.1:$lastPort{$request}";
    }
    
    return `curl $url -s 2>&1`;
}

function assertCurl($request, $shouldSee) {
    $response = curl($request);

    if (!$response) {
        throw new Exception('Fail: Received empty response on '.$request);
    }

    assertString($response, $shouldSee);
}

function assertString($source, $shouldSee, $subject = null) {
    foreach ((array) $shouldSee as $see) {      
        if ($see == '') {
            $INFO = "\033[93m";
            $OTHER = "\033[94m";
            $ENDC = "\033[0m";

            echo "\nInspect:\n{$INFO}$source{$ENDC}\n";
            echo "{$OTHER}Aborted test for inspection.{$ENDC}";
            exit(1);
        }          
        if (stripos($source, $see) === false) {
            $displayResponse = "string(".strlen($source)."):\n\n\t" . str_replace("\n","\n\t", $source) . "\n\n";
            throw new Exception('Fail: Expected to see ' . $see . ' '. ($subject ? ' in ' . $subject : '') . ' got: ' . $source . "\n\n");
        }
    }
}

function assertTrue($condition, $message = null) {
    if (!$condition) {
        $frame = debug_backtrace(0,1)[0];
        $start_offset =  max(0,$frame['line']-2);
        $fragment = array_slice(file($frame['file']), $start_offset, 4);
    
        $fragmentString = '';
        foreach ($fragment as $index => $fragmentLine) {
            $fragmentString .= sprintf("    %3d: %s", $start_offset+$index+1, $fragmentLine);
        }
        
        throw new Exception(sprintf("Fail: %s at %s line %d\n%s", $message, basename($frame['file']), $frame['line'], $fragmentString));
    }
}
function assertEquals($value, $expected) {
    $value = json_encode($value, JSON_PRETTY_PRINT);
    $expected = json_encode($expected, JSON_PRETTY_PRINT);

    if ($value !== $expected) {

        $frame = debug_backtrace(0,1)[0];
        $start_offset =  max(0,$frame['line']-2);
        $fragment = array_slice(file($frame['file']), $start_offset, 4);
    
        $fragmentString = '';
        foreach ($fragment as $index => $fragmentLine) {
            $fragmentString .= sprintf("    %3d: %s", $start_offset+$index+1, $fragmentLine);
        }

        @mkdir('/tmp/bos-unit-test-assertions');

        file_put_contents('/tmp/bos-unit-test-assertions/value.txt', "$value\n");
        file_put_contents('/tmp/bos-unit-test-assertions/expected.txt', "$expected\n");

        $diff = shell_exec("cd /tmp/bos-unit-test-assertions; diff --color=always -uw expected.txt value.txt");

        $message = "assertEquals fails:\n\nDiff: $diff\n";

        throw new Exception(sprintf("Fail: %s at %s line %d\n%s", $message, basename($frame['file']), $frame['line'], $fragmentString));
    }
}

function assertFalse($condition, $message = null) {
    return assertTrue(!$condition, $message);
}

function fatalException($e) {
    echo $e->getMessage() . "Stack trace:\n" . str_replace(__DIR__, '', $e->getTraceAsString());

    if (file_exists('webserver.log')) {
        echo "\n\n--- Webserver log:\n";
        echo file_get_contents('webserver.log');
    }

    exit(1);
}
function fail($message = null) {
    if ($message) {
        echo "$message\n";
    }
    exit(1);
}


// When playing with the bos-player source code directly.

function autoloadBosPlayer() {
    require_once __DIR__ . '/../../bos-player/vendor/autoload.php';
}

function autoloadBosExec() {
    require_once __DIR__ . '/../../bos-exec/vendor/autoload.php';
}

function getTestEnvironment($environment) {
    $_ENV['CATALOGUE_DIR'] = TMP_DIR;
    $_ENV['ENVIRONMENT_FILE'] = TMP_DIR . '/' . $environment;

    return BOS\Player\Environment::fromFile($_ENV['ENVIRONMENT_FILE']);
}