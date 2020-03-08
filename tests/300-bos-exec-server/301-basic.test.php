<?php

define('TMP_DIR', __DIR__ . '/../../../tmp/bos-unit-tests/');

require_once __DIR__ . '/../includes.php';

try {

    // @fixme - this test should be moved
    // to bos-exec tests.

    // This is an incomplete test, it assumes
    // stuff is already running on port 9011
    $lastPort = '19111';

    $DC_UNIT_TEST = __DIR__ . '/../../../docker-compose.unit-test.yml';

    // Make sure our tmp directory exists before starting docker-compose
    createFiles([
        'env1.json' => json_encode([
            'name' => 'First environment'
        ]),
        'mod1/bosModule.json' => json_encode([
            'type' => 'php',
            'requires' => ['users'],
            'tasks' => [
                'install' => [
                    'php scripts/install.php', 
                    'scripts/seed.sql'
                ],
                'uninstall' => 'scripts/drop-tables.sql'
            ]
        ]),
        'mod1/scripts/install.php' => '<?php

            require $_SERVER["BOS_PLAYER_AUTOLOADER"];

            use BOS\Player\Utils\DB;

            // This will generate a nice exception when table users doesn\'t exist.

            DB::getPdoConnection()->query("CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255),
                user_id INTEGER,
                CONSTRAINT `some_idx` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
            )");
        ',

        'mod1/scripts/seed.sql' => '
        INSERT INTO users(id, name) VALUES (2, "joshua");        
        INSERT INTO posts(id, title, user_id) VALUES (1, "post 1", 2);
        INSERT INTO posts(id, title, user_id) VALUES (2, "post 2", 2);
        ',
        'mod1/scripts/drop-tables.sql' => '
        DROP TABLE IF EXISTS posts;
        ',

        'user-module/bosModule.json' => json_encode([
            'provides' => ['users'],
            'tasks' => [
                'migrate' => [
                    'php scripts/run-migrations.php',
                    'scripts/update-users.sql',
                    'scripts/groups.simpleschema.txt',
                    'scripts/group-data.jsonl',
                ]
            ],
            'data' => [
                'tables' => [
                    'users',
                    'groups',
                    'groups_users'
                ]
            ]

        ]),
        'user-module/scripts/run-migrations.php' => '<?php
            
            require $_SERVER["BOS_PLAYER_AUTOLOADER"];

            use BOS\Player\Utils\SimpleSchema;

            SimpleSchema::format(join("\n", [
                "users:",
                "   name s",
                "   timestamps"
            ]));
        ',

        'user-module/scripts/update-users.sql' => '
            INSERT INTO users (id, name) VALUES (1, "first user");
            
            -- Please note, on a clean install, this migration is 
            -- called BEFORE  mod1/scripts/seed.sql, so this update
            -- will only affect user 1 and not user 2.
            UPDATE users SET created_at = "2020-01-01 00:00:00"
        ',

        'user-module/scripts/groups.simpleschema.txt' => trim('        
groups:
    name s
groups_users:
    group_id references groups(id)
    user_id references users(id)                
'),
        'user-module/scripts/group-data.jsonl' => trim('        
        {"groups": {"id": 1, "name" : "Group 1"}}
        '),
        'data/first-env.json' => json_encode([
            'name' => 'first-env',
            'storages' => [
                'database' => [
                    'host' => 'xxxx',
                    'user' => 'xxxx',
                    'database' => 'db_first_env'
                ]
            ],
            
            'modules' => [
                ['id' => 'mod1'],
                ['id' => 'user-module']
            ],
        ])
    ]);

    // It's important that the tmp-directory is not destroyed,
    // otherwise the docker-container will lose its track on the directory.


    //system("tree " . TMP_DIR);

    echo "Starting docker...\n";
    system("docker-compose -f $DC_UNIT_TEST up -d");

    echo "\n\n";

    // Is the bos-exec-server available on port 9011?
    assertCurl('/ -v', 'HTTP/1.1 200 OK');

    assertCurl('/info -v', [
        'HTTP/1.1 200 OK',
        'Content-type: application/json',
        'env1',
        'mod1'
    ]);

    function test_environment_create(){

        $TMP_DIR = TMP_DIR;

        assertCurl("/env/destroy/first-env --data-binary @$TMP_DIR/data/first-env.json", 'Environment was destroyed');

        assertCurl("/env/create/first-env --data-binary @$TMP_DIR/data/first-env.json", [
            'Operation createEnvironment: OK',
        ]);

        assertCurl("/env/install/first-env --data 1", 'Running module task');

        // Assert that the database has been created / exists
        assertCurl('/data/databases', '"db_first_env"');

        // Assert that the user table was created. tables in the database.
        assertCurl('/data/tables/first-env', [
            // a sql file install is working
            '"posts"',
            // normal migrate php script is working
            '"users"',
            // simpleschema is working
            '"groups"'
        ]);

        // Assert some values from my seed.sql:
        assertCurl('/data/export/first-env', [
            '{"posts":{"id":1,"title":"post 1","user_id":2}}',
            '{"posts":{"id":2,"title":"post 2","user_id":2}',
            // user 2 created in mod1
            '{"users":{"id":2,"name":"joshua"',
            // this special date created by a migration.
            '2020-01-01',

            // data created with import json-lines
            '{"groups":{"id":1,"name":"Group 1"}}'
        ]);

        // Assert module uninstall is working properly.
        // default uninstall checks bosModule > data > tables
        assertCurl('/env/uninstall/first-env/user-module --data xxx', [
            'Running default uninstall', 
        ]);
        assertCurl('/env/uninstall/first-env/mod1 --data xxx', [
            'Running module task uninstall on mod1',
            'with mysql',
        ]);

        // There are no tables left, the entire database was cleaned up nicely.
        assertCurl('/data/tables/first-env', '[]');
    }
    test_environment_create();

    function test_sql_import() {
        createFiles([
            'tmp.sql' => '
            DROP TABLE IF EXISTS `tmp_table`;
            CREATE TABLE `tmp_table` (id INTEGER PRIMARY KEY AUTO_INCREMENT);
            '
        ]);
    
        $TMP_DIR = TMP_DIR;
        
        assertCurl("/data/import/first-env --data @$TMP_DIR/tmp.sql", 'Done.');
        
        // Expect our data to end up in the export.
        assertCurl('/data/export/first-env?format=sql\&table=tmp_table', [
            'CREATE TABLE `tmp_table`'
        ]);    
    }
    test_sql_import();

    
    function test_sql_import_errors() {
        $TMP_DIR = TMP_DIR;

        createFiles([
            'with-errors.sql' => '
            DROP TABLE IF EXISTS `tmp_table2`;
            CREATE TABLE `tmp_table2` (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                some_id INTEGER,
                CONSTRAINT `some_idx` FOREIGN KEY (`some_id`) REFERENCES `non_existing_table` (`id`)
            );
            '
        ]);

        // What happens when an error occurs?
        assertCurl("/data/import/first-env --data-binary @$TMP_DIR/with-errors.sql -v", [
            'HTTP/1.1 400'
        ]);
    }
    test_sql_import_errors();


    function test_jsonl_import() {
        $TMP_DIR = TMP_DIR;

        // The table must already be present.
        createFiles([
            'data.jsonl' => '{"$options": {"truncate" : 1}}
            {"tmp_table" : {"id" : 1 } }
            {"tmp_table" : {"id" : 2 } }
            {"tmp_table" : {"id" : 3 } }
            '
        ]);

        // What happens when an error occurs?
        assertCurl("/data/import/first-env --data-binary @$TMP_DIR/data.jsonl -v", [
            'Imported 3 records'
        ]);
    }

    test_jsonl_import();

} catch (Exception $e) {
    fatalException($e);
}