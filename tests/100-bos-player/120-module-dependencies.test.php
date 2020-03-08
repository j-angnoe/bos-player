<?php

/**
 * Demonstrate how module dependencies can be handled.
 * 
 */
require_once __DIR__ . '/../includes.php';

autoloadBosPlayer();
autoloadBosExec();

try {
    createFiles([
        'env.json' => json_encode([
            'modules' => [
                ['id' => 'user-module' ],
                ['id' => 'dependent-module' ]
            ]
        ]),
        
        'another-unit/bosModule.json' => json_encode([
            'requires' => ['todos'],
        ]),
        'user-module/bosModule.json' => json_encode([
            'provides' => ['user','authentication','permissions']
        ]),
        'dependent-module/bosModule.json' => json_encode([
            'provides' => [
                'todos'
            ],
            'requires' => [
                'user' 
            ]
        ]),
        'session-module/bosModule.json' => json_encode([
            'provides' => [
                'session'
            ],
            'requires' => [
                'user' 
            ]
        ]),
        'elasticsearch-module/bosModule.json' => json_encode([
            'provides' => [
                'elasticsearch'
            ]
        ]),
        'losse-module/bosModule.json' => json_encode([
            'name' => 'Deze heeft geen dependencies en heeft niets nodig.'
        ]),
        'interessante-unit/bosModule.json' => json_encode([
            'requires' => ['elasticsearch','session']
        ])
    ]);
    
    $environment = getTestEnvironment('env.json');

    $catalogue = \BOS\Exec\Catalogue::getInstance();


    /**
     * Topological sorting is important,
     * modules with requires first need to have there requirements met,
     * before moving on...
     * This is called topological sorting.
     * The first 3 modules (elasticsearch, losse-module and user-module) dont 
     * have any dependencies, and the way they are ordered may vary.
     * 
     * This will be used by bos-exec and other stuff.
     */
    $expectedOrder = array (
        // the first 3 may vary... 
        'elasticsearch-module', 
        'losse-module',
        'user-module',

        // These should follow
        'dependent-module',
        'session-module',
        'another-unit',
        'interessante-unit',
    );      

    assertTrue($catalogue->topologicalSort($catalogue->listModules()) === $expectedOrder);

    assertTrue($catalogue->whoProvides('user') === 'user-module');

    assertTrue($environment->provides('user'));

    assertTrue(!$environment->provides('some-random-thing'));

    $environment->whoProvides('user');
    
} catch (Exception $e) {
    echo $e;

    fatalException($e);
}