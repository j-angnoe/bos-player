<?php

namespace BOS\Player\Utils;

use \BOS\Player\Utils\DB;

/**
 * new SimpleSchema('table', [
 *  'COLUMN_NAME' => 'COLUMN_DEFINITION',
 * ], [
 *  'idx_my_index' => '(column1, column2)'
 * ]);
 */
class SimpleSchema {

    function __construct($table, $fields, $indices = []) {
        $this->table = $table;
        $this->fields = $fields;
        $this->indices = $indices;
    }

    static function format($definition) {
        $definition = explode("\n", trim($definition));

        $blocks = [];

        while(false !== (list(,$line) = @each($definition))) { 
            if (substr($line, -1) === ':') {
                $block_name = substr($line, 0, -1);

                $blocks[$block_name] = [];

                while(false !== (list(,$line) = each($definition))) { 

                    if (substr($line, -1) === ':') {
                        prev($definition);
                        continue 2;
                    }

                    if (trim($line)) {
                        @list($key, $def) = explode(' ', trim($line), 2);

                        if (!$def) {
                            $blocks[$block_name][] = $key;
                        } elseif ($key) {
                            $blocks[$block_name][$key] = $def;
                        }
                    }
                    
                }
            }
        }

        return SimpleSchema::define($blocks);

    }

    static function define($table, $fields = [], $indices = []) {
        if (is_array($table)) {
            foreach ($table as $key=>$value) {
                SimpleSchema::define($key, $value);
            }
        } else {
            $object = new self($table, $fields, $indices);
            $object->run();
            return $object;
        }
    }

    function db() {
        return DB::getPdoConnection();
    }

    function query($query, $data = []) {
        $statement = $this->db()->prepare($query);
        call_user_func_array([$statement, 'execute'], $data);
        while ($row = $statement->fetch()) {
            yield $row;
        }
        $statement->closeCursor();
    }

    function grabAll($query, $data = []) {
        return iterator_to_array($this->query($query, $data));
    }

    function describeTable($table) {
        return iterator_to_array($this->query("DESCRIBE $table"));
    }

    function processFields() {
        
        $processed = [];

        $translateFields = [
            'string' => 'VARCHAR(255)',
            'text' => 'TEXT',
            'json' => 'JSON',
            's' => 'VARCHAR(255)',
            'n' => 'INT',
            'f' => 'FLOAT',
            'b' => 'TINYINT'
        ];

        $shortcuts = [
            'timestamps' => [
                'created_at' => 'TIMESTAMP NULL DEFAULT NULL',
                'updated_at' => 'TIMESTAMP NULL DEFAULT NULL'
            ],
            'softDeletes' => [
                'deleted_at' => 'TIMESTAMP NULL DEFAULT NULL'
            ]
        ];

        foreach ($this->fields as $key => $value) {
            // automagic references.

            if (preg_match('/(foreign key )*references (?<table>\w+)\s*(\.\s*(?<field1>\w+)|\((?<field2>\w+)\))(?<fk_extra>\s(on)\s(delete|update)(\s(cascade))?)?(?<extra>.+)?/i', $value, $match)) {
                
                $table = $match['table'];
                $field = $match['field1'] ? $match['field1'] : $match['field2'];

                try { 
                    $type = $this->grabAll("DESCRIBE $table $field")[0]['Type'] . " " . ltrim($match['extra'] ?? '');

                    $processed[$key] = [$type, function () use ($key, $table, $field, $match) {
                        return [
                            "ADD FOREIGN KEY ($key) REFERENCES $table($field) {$match['fk_extra']}"
                        ];
                    }];
                } catch (\Exception $e) {
                    echo "Error at foreign key $key $value... writing an unsigned int: " . $e->getMessage();

                    $processed[$key] = "INTEGER UNSIGNED";
                }

                // get the type from the table
                continue;
            }

            if (is_numeric($key) && isset($shortcuts[$value])) {
                $processed += $shortcuts[$value];

                continue;
            } 

            $value = $translateFields[$value] ?? $value;

            $processed[$key] = $value;
        }

        return $processed;

    }
    function run() {
        $table = $this->table;

        try { 
            $definition = $this->grabAll("SHOW CREATE TABLE `$table`")[0]['Create Table'];
        } catch (\PDOException $e) {
            $definition = '';
            if (preg_match('/doesn\'t exist/', $e->getMessage())) {
                echo "Creating table\n";
                iterator_to_array($this->query("CREATE TABLE $table (id INTEGER PRIMARY KEY AUTO_INCREMENT) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"));
            }
        }
        
        $columnExists = function ($column, $def = null) use ($definition) {
            $search = "`$column`" . ($def?" $def" : '');
            return stripos($definition,$search) !== false;
        };

        $prefix = "ALTER TABLE `$table` ";

        $modifications = [];

        $fields = $this->processFields($this->fields);

        $lastField = 'id';

        foreach ($fields as $fieldName => $fd) {
            $cb = false;

            if (is_array($fd)) {
                list($fieldDefinition, $cb) = $fd;
            } else {
                $fieldDefinition = $fd;
            }
            
            $modified = false;
            if (!$columnExists($fieldName)) {
                $modified = true;
                $modifications[] = "$prefix ADD COLUMN `$fieldName` $fieldDefinition AFTER `$lastField`";
            } elseif (!$columnExists($fieldName, $fieldDefinition)) {
                $modified = true;
                $modifications[] = "$prefix MODIFY COLUMN `$fieldName` $fieldDefinition AFTER `$lastField`";
            }

            if ($modified && $cb) {
                foreach ($cb() as $mod) {
                    $modifications[] = "$prefix $mod";
                }
            }

            $lastField = $fieldName;
        }

        $this->db()->beginTransaction();

        if (empty($modifications)) {
            echo "$table is sync.\n";
        } else {
            foreach ($modifications as $m) {
                echo "Running $m\n";
                $this->db()->exec($m);
            }
        }

        $this->db()->commit();
    }
}
