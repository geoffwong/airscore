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
$regarr['forPk'] = $forPk;
$regarr['forClass'] = reqsval('Formula');
$regarr['forVersion'] = reqsval('Version');
$regarr['forNomDistance'] = reqfval('NomDistance');
$regarr['forMinDistance'] = reqfval('MinDistance');
$regarr['forNomTime'] = reqfval('NomTime');
$regarr['forNomGoal'] = reqfval('NomGoal');
$regarr['forNomLaunch'] = reqfval('NomLaunch');
$regarr['forGoalSSpenalty'] = reqfval('GoalSSpenalty');
$regarr['forLinearDist'] = reqfval('LinearDist');
$regarr['forDiffDist'] = reqfval('DiffDist');
$regarr['forDiffRamp'] = reqsval('DiffRamp');
$regarr['forDiffCalc'] = reqsval('DiffCalc');
$regarr['forDistMeasure'] = reqsval('DistMeasure');
$regarr['forArrival'] = reqsval('Arrival');
if (array_key_exists('WeightStart', $_REQUEST))
{
    $regarr['forWeightStart'] = reqfval('WeightStart');
    $regarr['forWeightArrival'] = reqfval('WeightArrival');
    $regarr['forWeightSpeed'] = reqfval('WeightSpeed');
}
$regarr['forStoppedGlideBonus'] = reqfval('StoppedGlideBonus');
$clause = "forPk=$forPk";

$forPk = insertup($link, 'tblFormula', 'forPk', $clause,  $regarr);
$sql = "update tblCompetition set forPk=$forPk where comPk=$comPk";
$result = mysql_query($sql,$link);

$res['result'] = "ok";
//$res['regarr'] = $regarr;
//$res['clause'] = $clause;
print json_encode($res);
?>

