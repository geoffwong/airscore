<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';
require_once 'xcdb.php';


function add_xctrack_task($link, $tmpfile, $comPk, $name, $regPk, $dte)
{
    $taskin = file_get_contents($tmpfile, false, NULL, 0, 10000);
    $taskjson = json_decode($taskin, true);

    if ($taskjson == NULL)
    {
        return 0;
    }

    if ($taskjson['version'] != 1)
    {
        return 0;

    }

    $region = get_region($link, $regPk, 0);
    $regid = [];
    foreach ($region as $key => $row)
    {
        $regid[$row['rwpName']] = $key;
    }

    $waytype = [ 'TAKEOFF' => "'start'", 'SSS' => "'speed'", 'ESS' => "'endspeed'", '' => "'waypoint'" ];
    $how = [ 'ENTER' => "'entry'", 'EXIT' => "'exit'"];
    $shape = [ 'CYLINDER' => "'circle'", 'LINE' => "'semicircle'" ];

//{"version":1,"taskType":"CLASSIC","turnpoints":[{"radius":2000,"waypoint":{"lon":146.965393,"lat":-36.757881,"altSmoothed":798,"name":"mys080","description":"MysticTO"},"type":"TAKEOFF"},{"radius":2000,"waypoint":{"lon":147.031784,"lat":-36.80389,"altSmoothed":997,"name":"7S-100","description":"SmokoRidge"},"type":"SSS"},{"radius":2000,"waypoint":{"lon":146.978851,"lat":-36.746181,"altSmoothed":339,"name":"6S-034","description":"MysticLZ"},"type":"ESS"},{"radius":1000,"waypoint":{"lon":146.891525,"lat":-36.717281,"altSmoothed":279,"name":"6L-028","description":"PorepunkAir"}}],"sss":{"type":"RACE","direction":"ENTER","timeGates":["02:00:00Z"]},"goal":{"type":"CYLINDER","deadline":"07:00:00Z"},"earthModel":"WGS84"}

    $start = substr($taskjson['sss']['timeGates'][0], 0, 8);
    $finish = substr($taskjson['goal']['deadline'], 0, 8);

    $dtef = $dte;
    if ($finish < $start)
    {
        // add one day 
    }

    // insert a task sub
    $query = "insert into tblTask (comPk, tasName, tasDate, tasTaskStart, tasFinishTime, tasStartTime, tasStartCloseTime, tasSSInterval, tasTaskType, regPk, tasDeparture, tasArrival) values ($comPk, '$name', '$dte', '$dte $start', '$dtef $finish', '$dte $start', '$dtef $finish', 0, 'race', $regPk, 'kmbonus', 'off')";
    $result = mysql_query($query, $link) or json_die('Add task failed: ' . mysql_error());
    // Get the task we just inserted
    $tasPk = mysql_insert_id();

    // insert the turnpoints
    $waypoints = $taskjson['turnpoints'];
    $num = 10;
    foreach ($waypoints as $wpt)
    {
        $rwpPk = $regid[$wpt['waypoint']['name']];
        $radius = $wpt['radius'];
        if ($wtype == "'endspeed'")
        {
            $wtype = "'goal'";
        }
        else
        {
            $wtype = $waytype[$wpt['type']];
        }

        if ($wtype == "'start'")
        {
            $whow = "'exit'";
        }
        else
        {
            $whow = "'entry'";
        }

        $wshape = "'circle'";
        if ($wtype == "'speed'")
        {
            $whow = $how[$wpt['sss']['direction']];   
        }
        elseif ($wtype == "'goal'")
        {
            $wshape = $shape[$wpt['goal']['type']];   
        }
        $query = "insert into tblTaskWaypoint (tasPk, rwpPk, tawNumber, tawType, tawHow, tawShape, tawRadius) values ($tasPk, $rwpPk, $num, $wtype, $whow, $wshape, $radius)";
        $result = mysql_query($query) or json_die('Failed to copy task waypoints ' . mysql_error());
        $num = $num + 10;
    }

    exec(BINDIR . "task_up.pl $tasPk", $out, $retv);
    return $tasPk;
}

function add_task($link, $comPk, $name)
{
    $date = reqsval('date');
    $region = reqival('region');
    $tasPk = 0;

    if ($name == '')
    {
        json_die('Can\'t create a task with no name');
   		exit(0);
    }

    $tmpfile = $_FILES['userfile']['tmp_name'];
    if (file_exists($tmpfile))
    {
        $copyname = '/tmp/task_' . $comPk . '_' . $name . $date;
        copy($_FILES['userfile']['tmp_name'], $copyname);
        chmod($copyname, 0644);
        // Process the file
        $tasPk = add_xctrack_task($link, $tmpfile, $comPk, $name, $region, $date);
    }

    if ($tasPk == 0)
    {
        error_log('Add empty task');

        $query = "insert into tblTask (comPk, tasName, tasDate, tasTaskStart, tasFinishTime, tasStartTime, tasStartCloseTime, tasSSInterval, tasTaskType, regPk, tasDeparture, tasArrival) values ($comPk, '$name', '$date', '$date 10:00:00', '$date 18:00:00', '$date 12:00:00', '$date 14:00:00', 0, 'race', $region, 'kmbonus', 'off')";
        $result = mysql_query($query, $link) or json_die('Add task failed: ' . mysql_error());

        // Get the task we just inserted
        $tasPk = mysql_insert_id();
    }

	// Associate pre-submitted tracks
    $query = "select CTT.traPk from tblComTaskTrack CTT, tblTask T, tblTrack TR, tblCompetition C where CTT.comPk=$comPk and C.comPk=CTT.comPk and T.tasPk=$tasPk and CTT.traPk=TR.traPk and CTT.tasPk is null and TR.traStart > date_sub(T.tasStartTime, interval C.comTimeOffset+1 hour) and TR.traStart < date_sub(T.tasFinishTime, interval C.comTimeOffset hour)";
    $result = mysql_query($query,$link);
    $tracks = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $tracks[] = $row['traPk'];
    }

    if (sizeof($tracks) > 0)
    {
        // Give them a task number 
        $sql = "update tblComTaskTrack set tasPk=$tasPk where comPk=$comPk and traPk in (" . implode(",",$tracks) . ")";
        $result = mysql_query($sql,$link);

        // Now verify the pre-submitted tracks against the task??
        //foreach ($tracks as $tpk)
        //{
            //echo "Verifying pre-submitted track: $tpk<br>";
            //$out = '';
            //$retv = 0;
            //exec(BINDIR . "track_verify.pl $tpk", $out, $retv);
        //}
    }

    return $tasPk;
}

$usePk = auth('system');
$link = db_connect();
$cgi = var_export($_REQUEST,true);
error_log($cgi);
$res = [];
$comPk = reqival('comPk');
$name = reqsval('taskname');


if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$tasPk = add_task($link, $comPk, $name);

$res['result'] = 'ok';
$res['tasPk'] = $tasPk;

print json_encode($res);
?>
