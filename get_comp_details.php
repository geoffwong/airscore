<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';
require_once 'get_olc.php';

$link = db_connect();
$comPk = reqival('comPk');
$class = reqival('class');
$carr = [];


function get_compinfo($link, $comPk)
{
    // comp & formula info
    $compinfo = [];
    $row = get_comformula($link, $comPk);
    if ($row)
    {
        $row['comDateFrom'] = substr($row['comDateFrom'],0,10);
        $row['comDateTo'] = substr($row['comDateTo'],0,10);
        $row['TotalValidity'] = round($row['TotalValidity']*1000,0);
        $compinfo = $row;
    }

    return $compinfo;
}

function get_taskinfo($link, $comPk)
{
    $query = "select T.tasPk, T.tasDate, T.tasName, T.tasDistance, T.tasStartTime, T.tasFinishTime from tblTask T where T.comPk=$comPk order by T.tasDate";
    $result = mysql_query($query, $link) or die('Task query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $row['tasDistance'] = round($row['tasDistance'] / 1000,1);
        $alltasks[] = $row;
    }
    return $alltasks;
}

$compinfo = get_compinfo($link, $comPk);

$taskinfo = [];
$comType = $compinfo['comType'];
if ($comType == 'RACE' || $comType == 'Team-RACE' || $comType == 'Route' || $comType == 'RACE-handicap')
{
    $taskinfo = get_taskinfo($link, $comPk);
}

$keys = [];
$keys['comPk'] = $compinfo['comPk'];
$keys['forPk'] = $compinfo['forPk'];
$keys['regPk'] = $compinfo['regPk'];
foreach ($keys as $key => $value)
{
    unset($compinfo[$key]);
}


$scoring = [];
$scoring['comType'] = $compinfo['comType'];
$scoring['comSanction'] = $compinfo['comSanction'];
$scoring['comOverallScore'] = $compinfo['comOverallScore'];
$scoring['comOverallParam'] = $compinfo['comOverallParam'];
$scoring['comTeamSize'] = $compinfo['comTeamSize'];
$scoring['comTeamScoring'] = $compinfo['comTeamScoring'];
$scoring['comTeamOver'] = $compinfo['comTeamOver'];
$scoring['comLocked'] = $compinfo['comLocked'];

foreach ($scoring as $key => $value)
{
    unset($compinfo[$key]);
}

$formula = [];
foreach ($compinfo as $key => $value)
{
    if (substr($key,0,3) == "for")
    {
        $formula[$key] = $value;
        unset($compinfo[$key]);
    }
}
unset($formula['forOLCBase']);
unset($formula['forOLCPoints']);
unset($formula['forHBESS']);
unset($compinfo['comStyleSheet']);
unset($compinfo['TotalValidity']);


$data = [ 'keys' => $keys, 'compinfo' => $compinfo, 'taskinfo' => $taskinfo, 'formula' => $formula, 'scoring' => $scoring ];
print json_encode($data);
?>
