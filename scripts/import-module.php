<?php
/**
 * Read jsonl lines from STDIN and import them to given database.
 * 
 * This is module independent.
 * 
 * There is still room for options, like truncate before, re-index, and stuff.
 * 
 * This script is run via bos-exec, like so:
 * 
 * `cat data.jsonl | bos-exec --catalogue --environment moduleName import`
 */

require_once $_SERVER['BOS_PLAYER_AUTOLOADER'];

use BOS\Player\Utils\DB;

$pdo = DB::getPdoConnection();

$pdo->beginTransaction();

$lastTable = null;
$tableObject = null;

$imported = 0;

// @fixme - remove this when import data is topologically sorted
$pdo->query("SET FOREIGN_KEY_CHECKS=0");

$afterTableImport = function ($table) {};
$beforeTableImport = function($table) {};

while($line = fgets(STDIN)) {
    @list($table, $record) = each(json_decode($line, 1));

    if ($table{0} === '$') {
        if ($table === '$options') {
            if ($record['truncate'] ?? false) {
                $beforeTableImport = function ($table) use ($pdo) {
                    $pdo->exec("TRUNCATE $table");
                };
            }
        }
        continue;
    }

    if ($lastTable !== $table) {
        if ($lastTable) {
            $afterTableImport($table);
        }
        $tableObject = DB::table($table);
        $lastTable = $table;
        if ($table) {
            $beforeTableImport($table);
        }
    }


    try { 
        $tableObject->insert($record);

        echo "Imported $table {$record['id']}\n";
        $imported++;
    } catch (\Exception $e) {
        echo "Error at $line\n";
        echo $e;
        break;
    }
}

// @fixme - remove this when import data is topologically sorted
$pdo->query("SET FOREIGN_KEY_CHECKS=1");

echo "Imported $imported records.";

$pdo->commit();