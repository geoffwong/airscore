<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require 'authorisation.php';
require 'hc.php';
require 'format.php';
require 'xcdb.php';

$usePk = check_auth('system');
$link = db_connect();
$comPk = reqival('comPk');

$tasks = get_all_tasks($link,$comPk);

print json_encode($tasks);
?>

