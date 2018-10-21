<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';

$usePk = auth('system');
$link = db_connect();
$res = [];
$comPk = reqival('comPk');

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$comname = reqsval('Name');
$location = reqsval('Location');
$datefrom = reqsval('DateFrom');
$dateto = reqsval('DateTo');
$director = reqsval('MeetDirName');
$sanction = reqsval('Sanction');
$comcode = reqsval('Code');
$timeoffset = reqfval('TimeOffset');
$compclass = reqsval('Class');
$rentry = reqsval('EntryRestrict');

$query = "update tblCompetition set comName='$comname', comLocation='$location', comDateFrom='$datefrom', comDateTo='$dateto', comMeetDirName='$director', comTimeOffset=$timeoffset, comCode='$comcode', comClass='$compclass', comEntryRestrict='$rentry' where comPk=$comPk";

$result = mysql_query($query, $link) 
    or die('Competition update failed: ' . mysql_error());

$res['result'] = "ok";
print json_encode($res);
?>

