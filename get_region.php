<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json');

require 'authorisation.php';
require 'xcdb.php';

$regPk = reqival('regPk');
$trackid = reqival('trackid');
$link = db_connect();

$sorted = get_region($link, $regPk, $trackid);
$data = [ 'region' => $sorted ];
print json_encode($data);
?>

