<?php

require_once __DIR__ . '/../includes.php';

try {
    /**
     * Module assets / resources can be pulled in a few ways.
     * - GET /module-name/asset-name.js
     */    
    createFiles([
        'env.json' => json_encode([
            'partitions' => [
                'partition-1' => 1
            ],
            'modules' => [
                ['id' => 'mod1'],
                ['id' => 'mod2']
            ]
        ]),
        'mod1/bosModule.json' => json_encode([
            'name' => 'Module 1',
            'type' => 'php'
        ]),
        'mod1/protected-asset.txt' => 'protected asset content',
        'mod1/secret.php' => '<?php 
            // THIS_IS_SECRET
            echo join(" ", ["this", "should", "run", "and","not","be","served"]);
        ',
        'mod1/.htaccess' => 'should not be served',
        'mod1/.htpasswd' => 'should not be served',

        'mod1/public/public-asset.txt' => 'public asset content',
        'mod1/dist/dist-asset.js' => 'dist asset content',
        'mod1/build/build-asset.css' => 'build asset content',

        'mod2/bosModule.json' => json_encode([
            'name' => 'Module 2',
            'type' => 'php',
            'serveFrom' => 'some_exotic_folder',
            // @fixme: 'serveFrom' => ['folder1', 'folder2'] should work...
        ]),
        'mod2/some_exotic_folder/exotic-asset.txt' => 'exotic content'
    ]);

    $pid = start_webserver(TMP_DIR, TMP_DIR);

    // Sanity checks:
    assertCurl('/', 'Environment not found');
    assertCurl('/env', 'Select a partition');
    assertCurl('/env/partition-1', 'Select a module');

    assertCurl('/mod1/protected-asset.txt', 'Environment not found');

    // Stuff inside public/ directory will be served.
    // directly.. + some variations
    assertCurl('/mod1/public-asset.txt -v', [
        'Content-type: text/plain',
        'public asset content'
    ]);

    // fetch it via the environment prefix
    assertCurl('/env/mod1/public-asset.txt -v', [
        'Content-type: text/plain',
        'public asset content'
    ]);

    // fetch it via environment / partition prefix.
    assertCurl('/env/partition-1/mod1/public-asset.txt -v', [
        'Content-type: text/plain',
        'public asset content'
    ]);

    // Fetches stuff from dist/
    // and check that it is served as application/javascript
    assertCurl('/mod1/dist-asset.js -v', [
        'Content-Type: application/javascript',
        'dist asset content'
    ]);

    // Fetches stuff from build/
    assertCurl('/mod1/build-asset.css -v', [
        'Content-Type: text/css',
        'build asset content'
    ]);


    // Test the serveFrom directive:

    /* @fixme: This should also work:

    assertCurl('/mod2/exotic-asset.txt', [
        'exotic content'
    ]);
    */

    assertCurl('/env/partition-1/mod2/exotic-asset.txt', [
        'exotic content'
    ]);

    // Security checks:

    assertCurl('/mod1/secret.php -v', [
        'HTTP/1.1 404 Not found',
    ]);

    assertCurl('/env/mod1/secret.php -v', [
        'HTTP/1.1 404 Not found',
    ]);

    // It is found, but it is executed ;-)
    assertCurl('/env/partition-1/mod1/secret.php -v', [
        'this should run and not be served',
    ]);



    assertCurl('/mod1/.htaccess -v', [
        'HTTP/1.1 404 Not found',
    ]);
    assertCurl('/env/mod1/.htaccess -v', [
        'HTTP/1.1 404 Not found',
    ]);
    assertCurl('/env/partition-1/mod1/.htaccess -v', [
        'HTTP/1.1 404 Not found',
    ]);
    assertCurl('/mod1/.htpasswd -v', [
        'HTTP/1.1 404 Not found',
    ]);

} catch (Exception $e) {
    fatalException($e);
}