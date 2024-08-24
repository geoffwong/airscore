<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'dbextra.php';
require_once 'xcdb.php';


$link = db_connect();
$comPk = reqival('comPk');
$tasPk = reqival('tasPk');
$usePk = auth('system');

$res = [];

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$query = "delete* from tblTaskAirspace where tasPk=$tasPk";
$result = mysql_query($query, $link) or json_die("Failed to delete task airspace $tasPk: " . mysql_error());

$res['result'] = 'ok';
print json_encode($res);
?>

