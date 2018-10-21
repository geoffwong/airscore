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

$comptype = reqsval('Type');
$overallscore = reqsval('OverallScore');
$overallparam = reqfval('OverallParam');
$teamscoring = reqsval('TeamScoring');
$teamsize = reqival('TeamSize');
$teamover = reqsval('TeamOver');
$sanction = reqsval('Sanction');
$locked = reqival('Locked');

$query = "update tblCompetition set comType='$comptype', comOverallScore='$overallscore', comOverallParam=$overallparam, comTeamScoring='$teamscoring', comTeamSize=$teamsize, comTeamOver='$teamover', comSanction='$sanction', comLocked=$locked where comPk=$comPk";

$result = mysql_query($query, $link) 
    or die('Competition update failed: ' . mysql_error());

$res['result'] = "ok";
print json_encode($res);
?>

