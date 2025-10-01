<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';


function add_comp($link, $usePk)
{
    $comname = reqsval('compname');
    $datefrom = reqsval('datefrom');
    $dateto = reqsval('dateto');

    if ($comname == '')
    {
        return -1;
    }
    $query = "insert into tblCompetition (comName, comDateFrom, comDateTo, comType, regPk) values ('$comname','$datefrom', '$dateto', 'RACE', 1)";

    $result = mysql_query($query, $link) or json_die('Competition addition failed: ' . mysql_error());
    $comPk = mysql_insert_id();

    $regarr = [];
    $regarr['comPk'] = $comPk;
    $regarr['forClass'] = 'gap';
    $regarr['forVersion'] = '2018';
    $regarr['forNomDistance'] = 35.0;
    $regarr['forMinDistance'] = 5.0;
    $regarr['forNomTime'] = 90;
    $regarr['forNomGoal'] = 30.0;
    $regarr['forGoalSSPenalty'] = 1.0;
    $regarr['forLinearDist'] = 0.5;
    $regarr['forDiffDist'] = 3.0;
    $regarr['forDiffRamp'] = 'flexible';
    $regarr['forDiffCalc'] = 'lo';
    $regarr['forDistMeasure'] = 'average';
    $regarr['forWeightStart'] = 0.125;
    $regarr['forWeightArrival'] = 0.175;
    $regarr['forWeightSpeed'] = 0.7;
    $clause = "comPk=$comPk";

    $forPk = insertup($link, 'tblFormula', 'forPk', $clause,  $regarr);

    $query = "update tblCompetition set forPk=$forPk where $clause";
    $result = mysql_query($query, $link) or json_die('Competition formula update failed: ' . mysql_error());

    $query = "insert into tblCompAuth values ($usePk, $comPk, 'admin')";
    $result = mysql_query($query, $link) or json_die('CompAuth addition failed: ' . mysql_error());

    return $comPk;
}

$usePk = auth('system');
$link = db_connect();
$res = [];
#$comPk = -1;
#if (!is_admin('admin',$usePk,$comPk))

$usePk = check_auth('system');
if ($usePk == 0)
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$comPk = add_comp($link, $usePk);

if ($comPk == -1)
{
	$res['result'] = 'error';
	$res['error'] = 'Can\'t create a competition with no name';    
	print json_encode($res);
	return;
}

$res['result'] = 'ok';
$res['comPk'] = $comPk;

print json_encode($res);
?>

