<?php

require_once __DIR__ . '/../includes.php';

try {
    // Test the environment selector bos-player

    $dir = '/tmp/bos-player-unit-tests';

    createFiles([
        'env1.json' => json_encode([
            'name' => 'env1',
            'partitions' => [
                'partition-1' => 1
            ]
        ]),
        'env2.json' => json_encode([
            'name' => 'env1',
        ])
    ]);

    $pid = start_webserver($dir, $dir);

    assertCurl('/ -v',[
        'HTTP/1.1 404 Not found',
        'Environment not found.'
    ]);

    // We open env1 and it will ask us for to select a partition
    assertCurl('/env1/', 'Select a partition');
    assertCurl('/env1/partition-1', 'Select a module');

    assertCurl('/env2/', 'Select a module');

} catch (Exception $e) {
    fatalException($e);
}

