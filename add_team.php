<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';


$link = db_connect();
$comPk = reqival('comPk');
$usePk = auth('system');

$name = reqsval('name');

$res = [];
if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}


$query = "insert into tblTeam (comPk, teaName) values ($comPk, '$name')";
$result = mysql_query($query, $link) or json_die("Failed to add team ($name) to comp ($comPk): " . mysql_error());

$res['result'] = 'ok';
$res['teaPk'] = mysql_insert_id();
$res['teaName'] = $name;
print json_encode($res);
?>
