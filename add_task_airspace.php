<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
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


$airPk = reqival('airPk');

$query = "select * from tblAirspace where airPk=$airPk";
$result = mysql_query($query, $link) or json_die("Failed to find airspace $airPk: " . mysql_error());
if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $airspace = $row;
}

$query = "insert into tblTaskAirspace (tasPk, airPk) values ($tasPk, $airPk)";
$result = mysql_query($query, $link) or json_die("Failed to connect airspace ($airPk) to task ($tasPk): " . mysql_error());

print json_encode($airspace);
?>
