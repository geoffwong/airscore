<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');
require_once 'authorisation.php';

// Connecting, selecting database
$login = reqsval('username');
$passwd = reqsval('password');
$ip = $_SERVER['REMOTE_ADDR'];

$link = db_connect();
$query = "select usePk from tblUser where useLogin='$login' and usePassword='$passwd'";
$result = mysql_query($query,$link) or json_die('Query failed: ' . mysql_error());

$usePk = 0;
if (mysql_num_rows($result) > 0)
{
    $usePk = mysql_result($result,0,0);
}

$res = [];
if ($usePk > 0)
{
    $magic = rand() % 100000000000;
    $query= "insert into tblUserSession (usePk, useSession, useIP) values ($usePk, '$magic', '$ip')";
    $result = mysql_query($query,$link) or json_die('Query failed: ' . mysql_error());
    # setCookie 
    if (setcookie("XCauth", $magic))
    {
        $res['result'] = 'ok';
    }
    else
    {
        $res['result'] = 'failed';
    }
}
else
{
    $res['result'] = 'failed';
}

// Closing connection
mysql_close($link);
print json_encode($res);

