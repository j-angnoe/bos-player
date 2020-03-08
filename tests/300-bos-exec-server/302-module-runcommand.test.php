<?php

define('TMP_DIR', __DIR__ . '/../../../tmp/bos-unit-tests/');

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
            'name' => 'First environment',
            'storages' => [
                'database' => [
                    'database' => '302_env1',
                ]
            ],
            'modules' => [
                ['id' => 'mod1']
            ]
        ]),
        'mod1/bosModule.json' => json_encode([
            'type' => 'php',
            'requires' => ['users'],
            'tasks' => [
                'install' => [
                    'bos/seeds/dynamic.sql.php', 
                ],
                'migrate' => [
                    'bos/migrate/dynamic.jsonl.php'
                ]
            ]
        ]),
        'mod1/bos/seeds/dynamic.sql.php' => '
        <?php for($i=1;$i<5;$i++) { ?>
        CREATE TABLE dynamic_table<?php echo $i ?>(id INTEGER PRIMARY KEY AUTO_INCREMENT);
        <?php } ?>
        ',
        'mod1/bos/migrate/dynamic.jsonl.php' => '
        <?php for($i=1;$i<=1000;$i++) { 
            echo json_encode(["dynamic_table1" => ["id" => $i]]) . PHP_EOL;
        } ?>
        '
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
        assertCurl("/env/destroy/env1 --data-binary @$TMP_DIR/env1.json -v", [
            'HTTP/1.1 200 OK',
            'Environment was destroyed.'
        ]);

        assertCurl("/env/create/env1 --data-binary @$TMP_DIR/env1.json", [
            'Operation createEnvironment: OK'
        ]);

        assertCurl("/env/install/env1 -X POST -v", "HTTP/1.1 200 OK");


        assertCurl('/data/tables/env1', [
            'dynamic_table1',
            'dynamic_table2',
            'dynamic_table3',
        ]);
    
        // Migrate populates table with 1000 dynamically generated records.
        assertCurl('/env/migrate/env1 -X POST', [
            'Imported 1000 records.'
        ]);
    }
    run_test();
    

} catch (Exception $e) {
    fatalException($e);
}