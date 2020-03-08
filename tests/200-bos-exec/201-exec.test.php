<?php

require __DIR__ . '/../includes.php';

try {
    /**
     * We are going to test bos-exec here...
     */

    // Make sure the binary bos-exec is available to us.
    if (strpos(`which bos-exec`, 'not found') !== false) {
        fail("Please make sure bos-exec binary is available in \$PATH before running tests.");
    }

     // Set up a dummy environment

     createFiles([

        'mod1/bosModule.json' => json_encode([
            'name' => 'Module 1',
        ]),

        'mod1/scripts/print-env.php' => '<?php
        // Just prints the environment variables.
        print_r($_SERVER);
        ',
        'mod1/scripts/print-server.php' => '<?php
        // Just prints the environment variables.
        print_r($_SERVER);
        ',
        'mod1/scripts/print-cwd.php' => '<?php echo getcwd();',

        'env.json' => json_encode([
            'envs' => [
                'MY_ENV_VAR' => 'my_env_value'
            ],
            'modules' => [
                [
                    'id' => 'mod1',
                    'settings' => [
                        'some_setting' => 'some_value',
                        'another_setting' => 'another_value'
                    ]
                ]
            ]
        ])
     ]);

     $TMP_DIR = TMP_DIR;
     
     mkdir('/tmp/bos-unit-test-userdata');

     $commandPrefix = "USERDATA_ROOT_DIR=/tmp/bos-unit-test-userdata bos-exec --catalogue $TMP_DIR --environment $TMP_DIR/env.json ";

     // The working directory should be set to the module directory:
     assertString(`$commandPrefix mod1 php scripts/print-cwd.php`, [
         '/tmp/bos-player-unit-tests/mod1'
     ]);

     $environmentAssertions = [
        // The BOS_PLAYER_AUTOLOADER is a handy utility for including autoloading bos-player stuff.
        // via require $_ENV['BOS_PLAYER_AUTOLOADER'] at the top of your script.
       '[BOS_PLAYER_AUTOLOADER] => ',

       // BOS_PLAYER_DIR points to the directory where bos-player sources are.
       '[BOS_PLAYER_DIR] => ',

       // BOS_MODULE_DEF points to mod1/bosModule.json, handy for scripts that require module introspection.
       '[BOS_MODULE_DEF] => ',

       // Environment variables defined in environment.json > envs are passed.
       '[MY_ENV_VAR] => my_env_value',
       
       // Even the module settings for this module (defined in environment.json > modules) are passed
       // to the script.
       '[BOS_MODULE_SETTINGS] => {"some_setting":"some_value","another_setting":"another_value"}'
     ];

    assertString(
        `$commandPrefix mod1 php -d variables_order=EGPCS scripts/print-env.php`, 
        $environmentAssertions
    );

    // And the same should be true for $_SERVER:
    assertString(
        `$commandPrefix mod1 php -d variables_order=EGPCS scripts/print-server.php`, 
        $environmentAssertions
    ); 
     
} catch (Exception $e) {
    fatalException($e);
}