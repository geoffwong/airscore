<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';


function save_task_waypoints($link, $tasPk, $waypoints)
{
    if (!sizeof($waypoints) || !$tasPk)
    {
        json_die("Can't create a task with no waypoints");
    }

    $query = "delete from tblTaskWaypoint where tasPk=$tasPk";
    $result = mysql_query($query, $link) or json_die('Delete existing waypoints failed: ' . mysql_error());

    $query = "insert into tblTaskWaypoint (tasPk, rwpPk, tawNumber, tawType, tawHow, tawShape, tawRadius) values ";
    $rwpPk = 0;
    $cnt = count($waypoints);
    if ($cnt > 1)
    {
        $waypoints[$cnt-1][3] = 'goal';
    }
    foreach ($waypoints as $row)
    {
        if ($rwpPk != 0)
        {
            $query = $query . ",";
        }
        $rwpPk = $row[1];
        $count = $row[0];
        $type = $row[3];
        $how = $row[4];
        $shape = $row[5];
        $radius = $row[6];
        $query = $query . "($tasPk, $rwpPk, $count, '$type', '$how', '$shape', $radius)";
    }
    $result = mysql_query($query, $link) or json_die('Failed to insert new task waypoints: ' . $query);
}

$usePk = auth('system');
$link = db_connect();
$res = [];
$comPk = reqival('comPk');
$tasPk = reqival('tasPk');

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$waypoints = json_decode($_REQUEST['waypoints']);

$tawPk = save_task_waypoints($link, $tasPk, $waypoints);

$out = '';
$retv = 0;
exec(BINDIR . "task_up.pl $tasPk 3", $out, $retv);

$res['result'] = 'ok';
print json_encode($res);
?>

