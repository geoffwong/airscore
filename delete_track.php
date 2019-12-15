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

$traPk = reqival('traPk');

if ($traPk > 0)
{
    $query = "delete from tblTaskResult where traPk=$traPk";
    $result = mysql_query($query, $link) or json_die('TaskResult delete failed: ' . mysql_error());
    
    $query = "delete from tblTrack where traPk=$traPk";
    $result = mysql_query($query, $link) or json_die('Track delete failed: ' . mysql_error());
    
    $query = "delete from tblTrackLog where traPk=$traPk";
    $result = mysql_query($query, $link) or json_die('TrackLog delete failed: ' . mysql_error());
    
    $query = "delete from tblWaypoint where traPk=$traPk";
    $result = mysql_query($query, $link) or json_die('Waypoint delete failed: ' . mysql_error());
    
    $query = "delete from tblBucket where traPk=$traPk";
    $result = mysql_query($query, $link) or json_die('Bucket delete failed: ' . mysql_error());
    
    $query = "delete from tblTrackMarker where traPk=$traPk";
    $result = mysql_query($query, $link) or json_die('TrackMarker delete failed: ' . mysql_error());
    
    $query = "delete from tblComTaskTrack where traPk=$traPk";
    $result = mysql_query($query, $link) or json_die('ComTaskTrack delete failed: ' . mysql_error());

    $res['result'] = 'ok';
    $res['comPk'] = $comPk;
    print json_encode($res);
}
else
{
    json_die("Unable to remove track: $traPk\n");
}

?>
