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
            ['id' => 'symphony-request-based-module']
        ]
    ]),

    'symphony-request-based-module/bosModule.json' => json_encode([
        'type' => [
            'id' => 'php',
            'main' => 'index.php'
        ]
    ]),

    'symphony-request-based-module/index.php' => '<?php

        require_once __DIR__ . "/vendor/autoload.php";

        use Symfony\Component\HttpFoundation\Request;

        $request = Request::createFromGlobals();

        echo "Base path: " . $request->getBasePath() . "\n";
        echo "Base url: " . $request->getBaseUrl() . "\n";
        echo "Path info: " . $request->getPathInfo() . "\n";
        echo "Request uri: " . $request->getRequestUri() . "\n";
        echo "uri: " . $request->getUri() . "\n";
    ',

    'symphony-request-based-module/composer.json' => json_encode([
        'name' => 'tmp/symphony-request-based-modules',
        'require' => [
            'symfony/http-foundation' => '^4.3'
        ]
    ])
]);

echo "Preparing symphony-request-based-module\n";
$TMP_DIR = TMP_DIR;
system("cd $TMP_DIR/symphony-request-based-module; composer install");


$pid = start_webserver($catalogue_dir, $environment_file);
try { 
    assertCurl("/main-partition", 'Select a module');

    function testSymphonyRequest() {
        assertCurl('/main-partition/symphony-request-based-module/blabla', [
            'Base url: /main-partition/symphony-request-based-module',
            'Path info: /blabla',
	        'Request uri: /main-partition/symphony-request-based-module/blabla'
        ]);
    }
    testSymphonyRequest();    

} catch (Exception $e) {
    fatalException($e);
}

exit(0);








