<?php

require __DIR__ . '/../includes.php';

try {
    /**
     * We are going to test bos-exec migrate features...
     */

     // Set up a dummy environment
     createFiles([
        'mod1/bosModule.json' => json_encode([
            'name' => 'Module 1',
            'tasks' => [
                'migrate' => 'php scripts/my-migration-script.php'
            ],
        ]),

        'mod1/scripts/my-migration-script.php' => '<?php
        echo "mod1 migrate script running.\n";
        print_r($_SERVER);
        ',

        'mod2/bosModule.json' => json_encode([
            'name' => 'Module 2',
            'tasks' => [
                'migrate' => 'php scripts/my-migration-script.php'
            ]
        ]),

        'mod2/scripts/my-migration-script.php' => '<?php
        echo "mod2 migrate script running.\n";
        print_r($_SERVER);
        ',        

        'env.json' => json_encode([
            'storages' => [
                // 'database' => [
                //     'host' => 'my-host',
                //     'username' => 'my-username',
                //     'password' => 'my-password'
                // ]
            ],
            'envs' => [
                'SOME_ENV' => 'some-val'
            ],
            'modules' => [
                [
                    'id' => 'mod1',
                    'settings' => [
                        'some_setting' => 'some_value',
                        'another_setting' => 'another_value'
                    ]
                ],
                [
                    'id' => 'mod2'
                ]

            ]
        ])
     ]);

     $TMP_DIR = TMP_DIR;
     @mkdir('/tmp/bos-unit-test-userdata');

     $commandPrefix = "USERDATA_ROOT_DIR=/tmp/bos-unit-test-userdata/ bos-exec --catalogue $TMP_DIR --environment $TMP_DIR/env.json ";

     assertString(`$commandPrefix environment:create`, [
         'Operation createEnvironment: OK'
     ]);

     // When i call bos-exec ... mod1 migrate, my-migration-script should run.
     // We assert that environment variables are passed.
     assertString(
        `$commandPrefix mod1:migrate`, [
            'peration migrateModule: OK',
            '[SOME_ENV] => some-val',
        ]
     );

     // We also have a migrate all modules option:
     assertString(
        `$commandPrefix environment:migrate`, [
           'mod1 migrate script running.',
           'mod2 migrate script running.'
        ]
    );

} catch (Exception $e) {
    fatalException($e);
}