<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';
require_once 'xcdb.php';

function insert_waypoint($link, $regPk, $wpt)
{
    $name = $wpt['name'];
    $lat = $wpt["lat"];
    $lon = $wpt["lon"];
    $alt = $wpt["altSmoothed"];
    $desc = rtrim($wpt["description"]);

    $map = [ 'regPk' => $regPk, 'rwpPk' => $rwpPk, 'rwpName' => $name, 'rwpLatDecimal' => $lat, 'rwpLongDecimal' => $lon, 'rwpAltitude' => $alt, 'rwpDescription' => $desc ];

    if ($name != '' and $lat != 0)
    {
        unset($map['rwpPk']);
        $wptid = insertup($link,'tblRegionWaypoint','rwpPk',"rwpName='$name' and regPk=$regPk", $map);

        return $wptid;
    }
}

function subtract_hhmmss($second, $first)
{
    $second_secs = intval(substr($second, 0, 2)) * 3600 + intval(substr($second, 3, 2)) * 60 + intval(substr($second, 6, 2));
    $first_secs = intval(substr($first, 0, 2)) * 3600 + intval(substr($first, 3, 2)) * 60 + intval(substr($first, 6, 2));
    return abs($second_secs - $first_secs);
}

function add_xctrack_task($link, $tmpfile, $comPk, $name, $regPk, $createwpts, $dte, $offset)
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
        $regid[strtolower(trim($row['rwpName']))] = $key;
    }
    //$regids = var_export($regid,true);
    error_log("region $regPk");
    //error_log($regids);

    $waytype = [ 'TAKEOFF' => "'start'", 'SSS' => "'speed'", 'ESS' => "'endspeed'", '' => "'waypoint'" ];
    $how = [ 'ENTER' => "'entry'", 'EXIT' => "'exit'"];
    $shape = [ 'CYLINDER' => "'circle'", 'LINE' => "'semicircle'" ];
    $tasktype = "'race'";
    $interval = 0;
    $numgates = 1;

//{"version":1,"taskType":"CLASSIC","turnpoints":[{"radius":2000,"waypoint":{"lon":146.965393,"lat":-36.757881,"altSmoothed":798,"name":"mys080","description":"MysticTO"},"type":"TAKEOFF"},{"radius":2000,"waypoint":{"lon":147.031784,"lat":-36.80389,"altSmoothed":997,"name":"7S-100","description":"SmokoRidge"},"type":"SSS"},{"radius":2000,"waypoint":{"lon":146.978851,"lat":-36.746181,"altSmoothed":339,"name":"6S-034","description":"MysticLZ"},"type":"ESS"},{"radius":1000,"waypoint":{"lon":146.891525,"lat":-36.717281,"altSmoothed":279,"name":"6L-028","description":"PorepunkAir"}}],"sss":{"type":"RACE","direction":"ENTER","timeGates":["02:00:00Z"]},"goal":{"type":"CYLINDER","deadline":"07:00:00Z"},"earthModel":"WGS84"}

    $finish = substr($taskjson['goal']['deadline'], 0, 8);
    $start = $finish;
    $gateclose = $finish;

    if (array_key_exists('sss', $taskjson))
    {
        $start = substr($taskjson['sss']['timeGates'][0], 0, 8);
        $numgates = sizeof($taskjson['sss']['timeGates']);
    }

    if ($numgates > 1)
    {
        $tasktype = "'speedrun-interval'";
        $gateclose = substr($taskjson['sss']['timeGates'][$numgates-1], 0, 8);
        $second = substr($taskjson['sss']['timeGates'][1], 0, 8);
        $interval = subtract_hhmmss($second, $start);
    }

    $dtef = $dte;
    if ($finish < $start)
    {
        // add one day 
    }

    $start = gmdate('H:i:s', strtotime($start) - strtotime('TODAY') + $offset*3600);
    $finish = gmdate('H:i:s', strtotime($finish) - strtotime('TODAY') + $offset*3600);
    $gateclose = gmdate('H:i:s', strtotime($gateclose) - strtotime('TODAY') + $offset*3600);
    error_log("  $dte start=$start finish=$finish gateclose=$gateclose");

    // insert a task sub
    $query = "insert into tblTask (comPk, tasName, tasDate, tasTaskStart, tasFinishTime, tasStartTime, tasStartCloseTime, tasSSInterval, tasTaskType, regPk, tasDeparture, tasArrival) values ($comPk, '$name', '$dte', '$dte $start', '$dtef $finish', '$dte $start', '$dtef $gateclose', $interval, $tasktype, $regPk, 'leadout', 'off')";
    $result = mysql_query($query, $link) or json_die("Add task failed ($query): " . mysql_error());
    // Get the task we just inserted
    $tasPk = mysql_insert_id();

    // insert the turnpoints
    $waypoints = $taskjson['turnpoints'];
    $num = 10;
    $tawPk = 0;
    $wtype = "'waypoint'";
    for ($i = 0; $i < count($waypoints); $i++)
    {
        $wpt = $waypoints[$i];

        $rwpPk = $regid[strtolower(trim($wpt['waypoint']['name']))];
        if (!$rwpPk)
        {
            $rwpPk = insert_waypoint($link, $regPk, $wpt['waypoint']);
        }
        $radius = $wpt['radius'];
        if ($wtype == "'endspeed'")
        {
            // always goal after endspped
            $wtype = "'goal'";
        }
        else
        {
            $wtype = $waytype[$wpt['type']];
        }

        $whow = "'entry'";
        if ($wtype == "'start'")
        {
            $whow = "'exit'";
        }
        if ($i < count($waypoints) - 1)
        {
            $next = $waypoints[$i+1];
            // should physically check if inside next waypoint .. not just same point
            if (($wpt['waypoint']['name'] == $next['waypoint']['name']) and ($radius < $next['radius']))
            {
                $whow = "'exit'";
            }
        }

        $wshape = "'circle'";
        if ($wtype == "'speed'")
        {
            $whow = $how[trim($taskjson['sss']['direction'])]; 
        }
        elseif ($wtype == "'goal'")
        {
            $wshape = $shape[trim($taskjson['goal']['type'])];
        }
        $query = "insert into tblTaskWaypoint (tasPk, rwpPk, tawNumber, tawType, tawHow, tawShape, tawRadius) values ($tasPk, $rwpPk, $num, $wtype, $whow, $wshape, $radius)";
        error_log($query);
        $result = mysql_query($query) or json_die('Failed to add task waypoints: ' . mysql_error());
        $tawPk = mysql_insert_id();
        $num = $num + 10;
    }

    // Tidy the task up a bit - Airscore likes to have a 'goal'
    if ($taskjson['goal']['type'] == 'LINE' && $tawPk > 0)
    {
        $query = "update tblTaskWaypoint set tawShape='line', tawType='goal' where tawPk=$tawPk";
        $result = mysql_query($query) or json_die('Failed to update goal line: ' . mysql_error());
    }
    elseif  ($tawPk > 0 && $wtype != 'goal')
    {
        $query = "update tblTaskWaypoint set tawType='goal' where tawPk=$tawPk";
        $result = mysql_query($query) or json_die('Failed to update last point to goal: ' . mysql_error());
    }

    exec(BINDIR . "task_up.pl $tasPk", $out, $retv);
    return $tasPk;
}

function add_task($link, $comPk, $name, $offset)
{
    error_log("add_task region " . $_REQUEST['region']);
    $date = reqsval('date');
    $region = reqival('region');
    $createwpts = reqsval('createwpts');
    $tasPk = 0;

    if ($name == '')
    {
        json_die('Can\'t create a task with no name');
        return;
    }

    if (!$region and !$createwpts)
    {
        // @todo: remove this hack
        json_die('Can\'t create a task with no region (or createwpts)');
        return;
    }

    $tmpfile = $_FILES['userfile']['tmp_name'];
    if (file_exists($tmpfile))
    {
        $copyname = '/tmp/task_' . $comPk . '_' . $name . $date;
        copy($_FILES['userfile']['tmp_name'], $copyname);
        chmod($copyname, 0644);
        // Process the file
        if ($createwpts)
        {
            // create a new "region" for the task itself
            $now = time() % 1000;
            $taskdesc = "taskreg_${comPk}_${date}_${now}";
            $query = "insert into tblRegion (regDescription) values ('$taskdesc')";
            $result = mysql_query($query) or die('Create task region failed: ' . mysql_error());
            $region = mysql_insert_id();
            $res['region'] = "Added reg=$region for task=$tasPk";
        }
        $tasPk = add_xctrack_task($link, $tmpfile, $comPk, $name, $region, $createwpts, $date, $offset);
    }

    if ($tasPk == 0)
    {
        error_log('Add empty task');

        $query = "insert into tblTask (comPk, tasName, tasDate, tasTaskStart, tasFinishTime, tasStartTime, tasStartCloseTime, tasSSInterval, tasTaskType, regPk, tasDeparture, tasArrival) values ($comPk, '$name', '$date', '$date 10:00:00', '$date 18:00:00', '$date 12:00:00', '$date 14:00:00', 0, 'race', $region, 'leadout', 'off')";
        $result = mysql_query($query, $link) or json_die("Add task failed ($query): " . mysql_error());

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
#$cgi = var_export($_REQUEST,true);
#error_log($cgi);
$comPk = reqival('comPk');
$name = reqsval('taskname');
$res = [];

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$comformula = get_comformula($link, $comPk);
$offset = $comformula['comTimeOffset'];
$tasPk = add_task($link, $comPk, $name, $offset);

$createwpts = reqsval('createwpts');
$res['createwpts'] = $createwpts;
$res['result'] = 'ok';
$res['tasPk'] = $tasPk;

print json_encode($res);
?>

