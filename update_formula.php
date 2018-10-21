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
$forPk = reqival('forPk');

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

// Add/update the formula
$regarr = [];
$regarr['comPk'] = $comPk;
$regarr['forClass'] = reqsval('formula');
$regarr['forVersion'] = reqsval('version');
$regarr['forNomDistance'] = reqfval('nomdist');
$regarr['forMinDistance'] = reqfval('mindist');
$regarr['forNomTime'] = reqfval('nomtime');
$regarr['forNomGoal'] = reqfval('nomgoal');
$regarr['forNomLaunch'] = reqfval('nomlaunch');
$regarr['forGoalSSPenalty'] = reqfval('sspenalty');
$regarr['forLinearDist'] = reqfval('lineardist');
$regarr['forDiffDist'] = reqfval('diffdist');
$regarr['forDiffRamp'] = reqsval('difframp');
$regarr['forDiffCalc'] = reqsval('diffcalc');
$regarr['forDistMeasure'] = reqsval('distmeasure');
$regarr['forArrival'] = reqsval('arrivalmethod');
if (array_key_exists('weightstart', $_REQUEST))
{
        $regarr['forWeightStart'] = reqfval('weightstart');
        $regarr['forWeightArrival'] = reqfval('weightarrival');
        $regarr['forWeightSpeed'] = reqfval('weightspeed');
}
$regarr['forStoppedGlideBonus'] = reqfval('glidebonus');
$clause = "comPk=$comPk";

$forPk = insertup($link, 'tblFormula', 'forPk', $clause,  $regarr);
$sql = "update tblCompetition set forPk=$forPk where comPk=$comPk";
$result = mysql_query($sql,$link);

$res['result'] = "ok";
print json_encode($res);
?>

