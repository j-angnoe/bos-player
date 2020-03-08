<?php

require_once __DIR__ . '/../includes.php';

try {
    /**
     * Modules that provide authentication are used intelligently.
     */    
    createFiles([
        'env.json' => json_encode([
            'partitions' => [
                'partition-1' => 1
            ],
            'modules' => [
                ['id' => 'auth-module'],
                ['id' => 'normal-module']
            ]
        ]),
        'auth-module/bosModule.json' => json_encode([
            'provides' => ['authentication'],
            'access' => 'public',
            'data' => [
                'login_url' => '/do-login.php'
            ]
        ]),

        'normal-module/bosModule.json' => json_encode([
            'access' => 'user',
            'type' => 'php'
        ]),
        'normal-module/access.php' => '<?php echo "Hello and welcome ;-)";'
    ]);

    $pid = start_webserver(TMP_DIR, TMP_DIR);

    // Sanity checks:
    assertCurl('/', 'Environment not found');
    assertCurl('/env', 'Select a partition');
    assertCurl('/env/partition-1', 'Select a module');

    // Try to access a normal-module without being logged in.
    // expect a redirect to the login url
    assertCurl('/env/partition-1/normal-module/access.php -v', [
        'Location: /env/partition-1/auth-module/do-login.php'
    ]);

    // When we enable SINGLE_USER_MODE it authorization is skipped
    // and access is granted.
    createFiles([
        'env.json' => json_encode([
            'partitions' => [
                'partition-1' => 1
            ],
            'envs' => [
                'SINGLE_USER_MODE' => 1
            ],
            'modules' => [
                ['id' => 'auth-module'],
                ['id' => 'normal-module']
            ]
        ])
    ]);

    // Assert that access is granted.
    assertCurl('/env/partition-1/normal-module/access.php -v', [
        'Hello and welcome ;-)'
    ]);

} catch (Exception $e) {
    fatalException($e);
}