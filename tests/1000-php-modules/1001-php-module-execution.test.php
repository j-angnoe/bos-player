<?php
require_once __DIR__ . '/../includes.php';

$catalogue_dir = TMP_DIR;
$environment_file = TMP_DIR . '/test-environment.json';

createFiles([
    'test-environment.json' => json_encode([

        'partitions' => [
            'main-partition' => 1
        ],

        'modules' => [
            ['id' => 'traditional-php'],
            ['id' => 'frontcontroller-php'],
        ]
    ]),
    'frontcontroller-php/bosModule.json' => json_encode([
        'id' => 'frontcontroller-php',
        'type' => [
            'id' => 'php',
            'main' => 'frontcontroller.php'
        ]
    ]),
    'frontcontroller-php/frontcontroller.php' => <<<'PHP'
<?php
switch($_SERVER['BOS_REQUEST_URI']) {
    case 'perform-redirect':
    case '/perform-redirect':
        header('Location: ' . $_SERVER['BOS_BASE_URL'] . 'redirected');
    break;
    case 'redirected':
    case '/redirected':
        echo "You have been redirected.\n";
    break;
}

echo "frontcontroller-php/frontcontroller.php is working\n";
PHP
    ,

    'frontcontroller-php/.htaccess' => 'this may not be served.',

    'traditional-php/.htaccess' => 'this may not be served.',

    'traditional-php/bosModule.json' => json_encode([
        'type' => 'php'
    ]),
    'traditional-php/includes-stuff.php' => '<?php
        // current working dir must be properly set
        // in order for this to work.

        require_once("includes.inc.php");
        
        echo "includes-stuff.php is working" . PHP_EOL;
    ',
    'traditional-php/includes.inc.php' => '<?php 
        define("STUFF_IS_INCLUDED",1);
    ',

    'traditional-php/index.php' => '<?php
    echo "traditional-php index.php is working";
    ',
    'traditional-php/perform-redirect.php' => '<?php
    header("Location: ".dirname($_SERVER["SCRIPT_NAME"]). "/redirected.php");
    exit;   
    ',
    'traditional-php/redirected.php' => '<?php
    echo "You have been redirected.";
    '
]);

$pid = start_webserver($catalogue_dir, $environment_file);
try { 
    assertCurl("/main-partition", 'Select a module');

    function testTraditionPhp() {
        ### Traditional-php tests

        assertCurl('/main-partition/traditional-php/non-existing-unit.php', 'File not found');

        // automatically routes to index.php
        assertCurl('/main-partition/traditional-php/', 'traditional-php index.php is working');

        // is the same as calling /index.php
        assertCurl('/main-partition/traditional-php/index.php', 'traditional-php index.php is working');

        // The module should be able to determine it's webroot (being /traditional-php)
        // The module properly redirects
        assertCurl('/main-partition/traditional-php/perform-redirect.php -v','Location: /main-partition/traditional-php/redirected.php');

        // Modules have no problem including their relative sources
        // require_once 'some-file.php' instead of require_once __DIR__ . '/some-file.php';
        assertCurl('/main-partition/traditional-php/includes-stuff.php', 'includes-stuff.php is working');


        // Security: dont serve dot files.

        assertCurl('/main-partition/traditional-php/.htaccess -v', 'HTTP/1.1 404 Not found');

    }

    testTraditionPhp();


    function testFrontcontrollerPhp() {

        assertCurl('/main-partition/frontcontroller-php/non-existing-unit', 'frontcontroller-php/frontcontroller.php is working');

        // automatically routes to frontcontroller.php
        assertCurl('/main-partition/frontcontroller-php/', 'frontcontroller-php/frontcontroller.php is working');

        // The module should be able to determine it's webroot (being /traditional-php)
        // The module properly redirects
        assertCurl('/main-partition/frontcontroller-php/perform-redirect -v','Location: /main-partition/frontcontroller-php/redirected');

        // Security: dont serve dot files
        assertCurl('/main-partition/traditional-php/.htaccess -v', 'HTTP/1.1 404 Not found');

    }
    testFrontcontrollerPhp();

} catch (Exception $e) {
    fatalException($e);
}

exit(0);








