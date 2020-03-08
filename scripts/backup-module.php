<?php
/**
 * Exports records in JSON Lines format. 
 * See http://jsonlines.org/ for more info.
 * 
 * The record format is like so:
 * 
 * { "table": { "column1" : "value", "column2" : "..." } } 
 * 
 * 
 * This script is run via bos-exec, like so:
 * 
 * `bos-exec --catalogue --environment moduleName backup`
 */

require_once $_SERVER['BOS_PLAYER_AUTOLOADER'];

use BOS\Player\Utils\DB;

$def = json_decode(file_get_contents($_SERVER['BOS_MODULE_DEF']), true);

if (!isset($def['data']) || !isset($def['data']['tables'])) {
    exit("Please fill in bosModule.json data.tables so we know which tables to export.");
}

// @fixme - tables should be sorted topologically
$tables = $def['data']['tables'];

$pdo = DB::getPdoConnection();

if (is_string($tables)) {
    // regex mode ;-)
    $statement = $pdo->query($q= "SHOW TABLES LIKE " . $pdo->quote($tables));    
    $tables = array_map('current', $statement->fetchAll());
}
foreach ($tables as $table) {
    $statement = $pdo->query("SELECT * FROM $table");
    while($row = $statement->fetch()) {
        echo json_encode([$table => $row]) . PHP_EOL;
    }
}



