<?php

define('TMP_DIR', __DIR__ . '/../../../tmp/bos-unit-tests/');
define('USERDATA_DIR', dirname(TMP_DIR).'/bos-unit-test-userdata/');

require_once __DIR__ . '/../includes.php';

try {

    // In this test we dont cover .sql, .jsonl 
    // as there are covered in 301-basics
    // In this test we focus on .sql.php and .jsonl.php
    // which allows us to modify the sql / jsonl based on 
    // environment variables and possibly module settings.

    $lastPort = '19111';

    $DC_UNIT_TEST = __DIR__ . '/../../../docker-compose.unit-test.yml';

    // Make sure our tmp directory exists before starting docker-compose
    createFiles([
        'env1.json' => json_encode([
            'name' => 'env1',
            'storages' => [
                'database' => [
                    'database' => '304_env1',
                ]
            ],
            'modules' => [
                ['id' => 'mod1']
            ]
        ]),
        'mod1/bosModule.json' => json_encode([
            'type' => 'php',
            'requires' => ['users'],
        ])
    ]);

    // It's important that the tmp-directory is not destroyed,
    // otherwise the docker-container will lose its track on the directory.

    //system("tree " . TMP_DIR);

    echo "Starting docker...";
    system("docker-compose -f $DC_UNIT_TEST up -d");

    // Is the bos-exec-server available on port 9011?
    assertCurl('/ -v', 'HTTP/1.1 200 OK');

    assertCurl('/info -v', [
        'HTTP/1.1 200 OK',
        'Content-type: application/json',
        'env1',
        'mod1'
    ]);

    function run_test() {
        $TMP_DIR = TMP_DIR;

        // Environment gets created with a dynamic sql 
        // generated by php.

        assertCurl("/env/destroy/env1 -X POST -v", [
            'HTTP/1.1 200 OK',
        ]);

        assertCurl("/env/create/env1 -X POST -v", ["Operation createEnvironment: OK"]);
        
    
        assertCurl("/env/install/env1 -X POST -v", ["Operation installEnvironment: OK"]);

        $environmentUserDataDir = USERDATA_DIR . "env1";
        // Assert that this is the environment directory.
        assertCurl("/env/info/env1", [
            '"home_directory":"\/userdata\/env1\/"',
            // mod1 is installed.
            '"installed":{"mod1"'
        ]);

        // Prevent reinstallation of already installed module
        assertCurl("/env/install/env1 -X POST -v", ["Already installed module: mod1"]);

        // Uninstall module will reset the installation status of mod1.
        assertCurl("/env/uninstall_module/env1/mod1 -X POST -v", ["Operation uninstallModule: OK"]);

        // So we can reinstall it.
        assertCurl("/env/install_module/env1/mod1 -X POST -v", ["Installing module: mod1"]);

        assertTrue(is_dir($environmentUserDataDir), 'userdata dir for this environment should be created.');

        assertCurl("/env/destroy/env1 -X POST -v", [
            'HTTP/1.1 200 OK',
            'Deleting environment userdata /userdata/env1/',
            'Operation destroyEnvironment: OK'
        ]);

        assertTrue("" === exec(" ls " . USERDATA_DIR . " | grep env1"), 'userdata dir for this environment should have been removed.');

        // @warnin this php process still assumes this directory exists... 
        // use ls instead.
        // assertTrue(!is_dir($environmentUserDataDir), 'userdata dir for this environment should have been removed.');
    }

    run_test();
} catch (Exception $e) {
    fatalException($e);
}