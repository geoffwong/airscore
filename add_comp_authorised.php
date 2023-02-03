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
	$query = "insert into tblCompAuth (usePk,comPk,useLevel) values ($adminPk,$comPk,'admin')";
    $result = mysql_query($query, $link) or json_die('Administrator addition failed: ' . mysql_error());

	$query = "select * from tblUser where usePk=$adminPk";
    $result = mysql_query($query, $link) or json_die('Administrator query failed: ' . mysql_error());
    $admin = [];
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $admin = [ $row['usePk'], $row['useLogin'], $row['useEmail'], '' ];
    }

	$res['result'] = "ok";
	$res['data'] = $admin;
	print json_encode($res);
}
else
{
	json_die("Unknown administrator: $adminPk");
}

?>

