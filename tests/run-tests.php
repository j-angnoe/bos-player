<?php
$DIR = __DIR__;

$extra = '';
$hasFilter = false;

if (isset($argv[1])) {
    $hasFilter = true;
    $extra = " | grep " . escapeshellarg($argv[1]);
    echo "Test filter `" . $argv[1] . "`\n";
} 

$tests = array_filter(explode("\n", trim(`find $DIR -name '*test.php' | sort $extra`)));

$GREEN = "\033[92m";
$RED = "\033[91m";
$INFO = "\033[93m";
$ENDC = "\033[0m";

if (empty($tests)) {
    echo "\n{$RED}No tests matched.{$ENDC}\n";
    exit(1);
}

foreach ($tests as $test) {
    $test_name = str_replace(__DIR__, '', $test);

    if (!$hasFilter && strpos($test, '.slow-test.php')) {
        echo "{$INFO}$test_name skipped{$ENDC}\n";
        continue;
    }
    $return_var = null;


    echo "Running $test_name...";

    system("php $test", $return_var);

    if ($return_var !== 0) {

        echo "\n{$RED}$test_name failed{$ENDC}.\n";

        exit(1);
    } else {
        echo "\r{$GREEN}$test_name passed{$ENDC}.\n";
    }
}

echo "\n{$GREEN}All tests passed.{$ENDC}\n";