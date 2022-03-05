<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require 'authorisation.php';
require 'xcdb.php';

function del_airspace($airPk)
{
    $ret = [];
    $link = db_connect();

    $query = "delete from tblAirspace where airPk=$airPk";
    $result = mysql_query($query, $link) or json_die('Airspace delete failed: ' . mysql_error());

    $query = "delete from tblAirspaceWaypoint where airPk=$airPk";
    $result = mysql_query($query, $link) or json_die('Airspace delete failed: ' . mysql_error());

    $query = "delete from tblTaskAirspace where airPk=$airPk";
    $result = mysql_query($query, $link) or json_die('Airspace delete failed: ' . mysql_error());
}

$authorised = check_auth('system');
$res = [];

if (!$authorised)
{
    $res['result'] = 'unauthorised';
}
else
{
    $airPk = reqival('airPk');
    del_airspace($airPk);

    $res['result'] = 'ok';
}

print json_encode($res);
?>

