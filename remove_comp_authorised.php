<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';
require_once 'xcdb.php';

$authed = auth('system');
$link = db_connect();
$comPk = reqival('comPk');
$adminPk = reqival('usePk');
$res = [];

if (!is_admin('admin', $authed, $comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

if ($adminPk > 0)
{
	$query = "delete from tblCompAuth where usePk=$adminPk and $comPk=$comPk";
    $result = mysql_query($query, $link) or json_die('Administrator removal failed: ' . mysql_error());
	$res['result'] = "ok";
	json_encode($res);
}
else
{
	json_die("Unknown administrator: $adminPk");
}

?>

