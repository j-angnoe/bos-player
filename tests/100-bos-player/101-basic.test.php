<?php

require_once __DIR__ . '/../includes.php';

try {

    // First, we assume that `bos-player` bin is available.
    
    if (strpos(`which bos-player`, 'not found') !== false) {
        fail("Please make sure bos-player binary is available in \$PATH before running tests.");
    }

    /**
     * Demonstrates that if you have an environment file
     * and point it to a catalogue (directories containing 
     * directories with bosModule.json files)
     */    
    createFiles([
        'env.json' => json_encode([
            'envs' => [
                'some-category' => [
                    'CAT_VAR_1' => 'value for CAT_VAR_1'
                ],
                'SOME_ENV_VAR' => 'value for SOME_ENV_VAR'
            ],
            'modules' => [
                ['id' => 'mod1'],
                [
                    'id' => 'mod2',
                    'settings' => [
                        'mod2_setting_1' => 'value for mod2_setting_1'
                    ]
                ]
            ]
        ]),
        'mod1/bosModule.json' => json_encode([
            'name' => 'Module 1',
            'type' => 'php'
        ]),
        'mod1/index.php' => '<?php
             echo "Hello from mod1/index.php"; ',
            
        'mod1/show-environment-vars.php' => '<?php echo "_ENV: "; print_r($_ENV);',
        'mod1/show-server-vars.php' => '<?php echo "_SERVER: "; print_r($_ENV);',

        'mod2/bosModule.json' => json_encode([
            'name' => 'Module 2',
            'type' => 'php'
        ]),
        'mod2/print-module-settings.php' => '<?php 
            echo "BOS_MODULE_SETTINGS: ";
            print_r($GLOBALS["BOS_MODULE_SETTINGS"]);
        '
    ]);

    $pid = start_webserver('/tmp/bos-player-unit-tests', '/tmp/bos-player-unit-tests/env.json');

    // Assert that the webserver is up:

    // When you start the server you are prompted to
    // select a module. This may change.
    assertCurl('/', 'Select a module');

    // We open mod1 and see stuff.
    assertCurl('/mod1/', 'Hello from mod1/index.php');

    // Some environment variables are available to via $_ENV
    assertCurl('/mod1/show-environment-vars.php', [
        '[BOS_MODULE_PATH] => /tmp/bos-player-unit-tests/mod1',
        '[BOS_MODULE_ID] => mod1',
        '[BOS_BASE_URL] => /mod1/',
        '[BOS_REQUEST_URI] => show-environment-vars.php', 
    ]);

    // As well is via $_SERVER
    assertCurl('/mod1/show-server-vars.php', [
        '[BOS_MODULE_PATH] => /tmp/bos-player-unit-tests/mod1',
        '[BOS_MODULE_ID] => mod1',
        '[BOS_BASE_URL] => /mod1/',
        '[BOS_REQUEST_URI] => show-server-vars.php',
    ]);

    // Assert that our environment.json settings
    // are passed thru to our script;

    assertCurl('/mod1/show-environment-vars.php', [
        // this is passed via env.json category variable.
        '[CAT_VAR_1] => value for CAT_VAR_1',

        // This is a normal env file.
	    '[SOME_ENV_VAR] => value for SOME_ENV_VAR',
    ]);


    // Assert that BOS_MODULE_SETTINGS are passed to the module.
    // via $GLOBALS['BOS_MODULE_SETTINGS']
    assertCurl('/mod2/print-module-settings.php', [
        '[mod2_setting_1] => value for mod2_setting_1'
    ]);

} catch (Exception $e) {
    fatalException($e);
}