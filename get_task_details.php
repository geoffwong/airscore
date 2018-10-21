<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';

function nice_date($today, $date)
{
    if ($today == substr($date, 0, 10))
    {
        $ret = substr($date, 11);
    }
    else
    {
        $ret = $date;
    }
    return $ret;
}

function get_task_setup($link, $tasPk)
{
	$query = "select T.tasPk, T.tasName, T.tasDate, T.regPk, T.tasTaskType, T.tasStoppedTime, T.tasTaskStart, T.tasFinishTime, T.tasStartTime, T.tasStartCloseTime, T.tasSSInterval, T.tasDeparture, T.tasArrival, T.tasHeightBonus, T.tasComment from tblTask T where T.tasPk=$tasPk";
	$result = mysql_query($query,$link) or die("Unable to select task information: " . mysql_error());
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
	{
        $tasDate = $row['tasDate'];
        $row['tasStartTime'] =  nice_date($tasDate, $row['tasStartTime']);
        $row['tasFinishTime'] =  nice_date($tasDate, $row['tasFinishTime']);
        $row['tasTaskStart'] =  nice_date($tasDate, $row['tasTaskStart']);
        $row['tasStartCloseTime'] =  nice_date($tasDate, $row['tasStartCloseTime']);
		return $row;
	}
}

$link = db_connect();
$comPk = reqival('comPk');
$tasPk = reqival('tasPk');

$taskinfo = get_task_setup($link, $tasPk);
$waypoints = get_taskwaypoints($link, $tasPk);
$region = get_region($link, $taskinfo['regPk']);

$keys = [];
$keys['tasPk'] = $taskinfo['tasPk'];
$keys['regPk'] = $taskinfo['regPk'];

foreach ($keys as $key => $value)
{
    unset($taskinfo[$key]);
}

$data = [ 'keys' => $keys, 'taskinfo' => $taskinfo, 'waypoints' => $waypoints, 'region' => $region ];
print json_encode($data);
?>

