<?php

require_once $_SERVER['BOS_PLAYER_AUTOLOADER'];

use BOS\Player\Utils\DB;

$pdo = DB::getPdoMasterConnection();

$pdo->query("CREATE DATABASE IF NOT EXISTS $argv[1]");

