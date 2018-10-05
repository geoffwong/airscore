<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require 'authorisation.php';
require 'hc.php';
require 'format.php';
require 'xcdb.php';

$usePk = check_auth('system');
$link = db_connect();
$tasPk = reqival('tasPk');

$fdhv= '';
$classstr = '';

$depcol = 'Dpt';
$row = get_comtask($link,$tasPk);
if ($row)
{
    $comName = $row['comName'];
    $comClass = $row['comClass'];
    $comPk = $row['comPk'];
    $_REQUEST['comPk'] = $comPk;
    $comTOffset = $row['comTimeOffset'] * 3600;
    $tasName = $row['tasName'];
    $tasDate = $row['tasDate'];
    $tasTaskType = $row['tasTaskType'];
    $tasStartTime = substr($row['tasStartTime'],11);
    $tasFinishTime = substr($row['tasFinishTime'],11);
    $tasDistance = round($row['tasDistance']/1000,2);
    $tasShortest = round($row['tasShortRouteDistance']/1000,2);
    $tasQuality = round($row['tasQuality'],2);
    $tasComment = $row['tasComment'];
    $tasDistQuality = round($row['tasDistQuality'],2);
    $tasTimeQuality = round($row['tasTimeQuality'],2);
    $tasLaunchQuality = round($row['tasLaunchQuality'],2);
    $tasArrival = $row['tasArrival'];
    $tasHeightBonus = $row['tasHeightBonus'];
    $tasStoppedTime = substr($row['tasStoppedTime'],11);

    if ($row['tasDeparture'] == 'leadout')
    {
        $depcol = 'Ldo';
    }
    elseif ($row['tasDeparture'] == 'kmbonus')
    {
        $depcol = 'Lkm';
    }
    elseif ($row['tasDeparture'] == 'on')
    {
        $depcol = 'Dpt';
    }
    else
    {
        $depcol = 'off';
    }
}
$waypoints = get_taskwaypoints($link,$tasPk);

// incorporate $tasTaskType / $tasDate in heading?
$hdname =  "$comName - $tasName";

$goalalt = 0;

$tinfo = [];
$tinfo["task type"] = $tasTaskType;
$tsinfo["class"] = $classfilter;
$tsinfo["date"] = $tasDate;
$tsinfo["start"] = $tasStartTime;
$tsinfo["end"] = $tasFinishTime;
$tsinfo["stopped"] = $tasStoppedTime;
$tsinfo["quality"] = number_format($tasQuality,3);
$tsinfo["WP dist"] = $tasDistance;
$tsinfo["task dist"] = $tasShortest;
$tsinfo["distQ"] = number_format($tasDistQuality,3);
$tsinfo["timeQ"] = number_format($tasTimeQuality,3);
$tsinfo["launchQ"] = number_format($tasLaunchQuality,3);
$tsinfo["comment"] = $tasComment;
$tsinfo["waypoints"] = $waypoints;

$data = array( 'data' => $tsinfo );
print json_encode($data);
?>

