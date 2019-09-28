<?php

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';
require_once 'xcdb.php';

$usePk = auth('system');
$link = db_connect();
$comPk = reqival('comPk');

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$id = reqival('tasPk');
$sql = "SELECT T.*, traPk as Tadded FROM tblTask T left outer join tblComTaskTrack CTT on CTT.tasPk=$id where T.comPk=$comPk group by T.tasPk order by T.tasDate";                                                                              $result = mysql_query($sql,$link);         
$row = mysql_fetch_array($result, MYSQL_ASSOC);

if ($row and $row['Tadded'] > 0)
{
    json_die('Tracks associated with task, unable to delete task');
    exit(1);
}
if ($id > 0)
{
    $query = "delete from tblTask where tasPk=$id";
    $result = mysql_query($query, $link) or die('Task delete failed: ' . mysql_error());

    $query = "delete from tblComTaskTrack where tasPk=$id";
    $result = mysql_query($query, $link) or die('Task CTT delete failed: ' . mysql_error());

    $query = "delete from tblTaskWaypoint where tasPk=$id";
    $result = mysql_query($query, $link) or die('Task TW delete failed: ' . mysql_error());

    $query = "delete from tblTaskResult where tasPk=$id";
    $result = mysql_query($query, $link) or die('Task TR delete failed: ' . mysql_error());

    $res['result'] = 'ok';
    $res['comPk'] = $comPk;
    print json_encode($res);
}
else
{
    json_die("Unable to remove task: $id\n");
}
?>
