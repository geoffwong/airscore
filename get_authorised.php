<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';


function get_comp_admin($link, $comPk)
{
	// Administrators 
	$sql = "select U.usePk, U.useLogin, U.useEmail FROM tblCompAuth A, tblUser U where U.usePk=A.usePk and A.comPk=$comPk";
	$result = mysql_query($sql,$link) or json_die("Failed to get competition administrations");
	$admin = [];
	while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
	{
	    $admin[] = [ $row['usePk'], $row['useLogin'], $row['useEmail'], '' ];
	}
	return $admin;
}

function get_all_admin($link,$comPk)
{
	$sql = "select U.usePk as user, U.*, A.* FROM tblUser U left outer join tblCompAuth A on A.usePk=U.usePk where A.comPk is null or A.comPk<>$comPk group by U.useLogin order by U.useLogin";
	$admin = [];
	$result = mysql_query($sql,$link) or json_die("Failed to query administrators");
	while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
	{
	    $admin[$row['useLogin']] = intval($row['user']);
	}
	return $admin;
}

$comPk = reqival('comPk');
$usePk = auth('system');
$link = db_connect();

$res = [];
if (!is_admin('admin', $usePk, $comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$res['administrators'] = get_all_admin($link,$comPk);
$res['data'] = get_comp_admin($link,$comPk);
$res['compinfo'] = get_comformula($link, $comPk);

print json_encode($res);
?>
