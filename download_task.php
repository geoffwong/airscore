<?php
require_once 'authorisation.php';
require_once 'dbextra.php';
require_once 'xcdb.php';

function get_xctrack_task($link, $comPk, $tasPk)
{
    $res = [ 'version' => 1, 'taskType' => 'CLASSIC' ];
    $goal = [ ];
    $sss = [ ];

    $region = get_comp_region($link, $comPk);

    $waytype = [ 'start' => 'TAKEOFF', 'speed' => 'SSS', 'endspeed' => 'ESS', 'waypoint' => '' ];
    $how = [ 'entry' => 'ENTER', 'exit' => 'EXIT' ];
    $shape = [ 'circle' => 'CYLINDER', 'semicircle' => 'LINE' ];

//{"version":1,"taskType":"CLASSIC","turnpoints":[{"radius":2000,"waypoint":{"lon":146.965393,"lat":-36.757881,"altSmoothed":798,"name":"mys080","description":"MysticTO"},"type":"TAKEOFF"},{"radius":2000,"waypoint":{"lon":147.031784,"lat":-36.80389,"altSmoothed":997,"name":"7S-100","description":"SmokoRidge"},"type":"SSS"},{"radius":2000,"waypoint":{"lon":146.978851,"lat":-36.746181,"altSmoothed":339,"name":"6S-034","description":"MysticLZ"},"type":"ESS"},{"radius":1000,"waypoint":{"lon":146.891525,"lat":-36.717281,"altSmoothed":279,"name":"6L-028","description":"PorepunkAir"}}],"sss":{"type":"RACE","direction":"ENTER","timeGates":["02:00:00Z"]},"goal":{"type":"CYLINDER","deadline":"07:00:00Z"},"earthModel":"WGS84"}

    $taskinfo = get_comtask($link, $tasPk);
    $waypoints = get_taskwaypoints($link, $tasPk);

    $wstr = var_export($waypoints, true);
    error_log($wstr);
    //$start = substr($taskjson['sss']['timeGates'][0], 0, 8);
    //$finish = substr($taskjson['goal']['deadline'], 0, 8);

    //$dtef = $dte;
    //if ($finish < $start)
    //{
    //    // add one day 
    //}

    // insert the turnpoints
    $turnpoints = [ ];
    $offset = $taskinfo['comTimeOffset'];
    $nextday = 0;
    foreach ($waypoints as $wpt)
    {
        //$rwpPk = $regid[$wpt['waypoint']['name']];
        $turnpt = [ ];
        $turnpt['radius'] = $wpt['tawRadius'];
        if ($wpt['tawType'] != 'waypoint' && $wpt['tawType'] != 'goal')
        {
            $turnpt['type'] = $waytype[$wpt['tawType']];
        }

        $key = $wpt['rwpPk'];
        $location = [ ];
        $location['lat'] = $region[$key]['rwpLatDecimal'];
        $location['lon'] = $region[$key]['rwpLongDecimal'];
        $location['altSmoothed'] = $region[$key]['rwpAltitude'];
        $location['name'] = $region[$key]['rwpName'];
        $location['description'] = $region[$key]['rwpDescription'];
        $turnpt['waypoint'] = $location;

        if ($wpt['tawType'] == 'speed')
        {
            # "sss":{"type":"RACE","direction":"ENTER","timeGates":["02:00:00Z"]},
            $sss = [ ];
            $sss['type'] = 'RACE';
            $sss['direction'] = $how[$wpt['tawHow']];
            $gates = [];
            $timez = substr($taskinfo['tasStartTime'], 11, 8);
            $hh = 0 + substr($timez, 0, 2) - $offset;
            if ($hh < 0)
            {
                $hh = $hh + 24;
                $nextday = 1;
            }
            $gates[] = sprintf("%02d", $hh) . substr($timez, 2, 6) . "Z";
            $sss['timeGates'] = $gates;
        }
        elseif ($wpt['tawType'] == 'endspeed')
        {
            #{"goal":{"type":"CYLINDER","deadline":"07:00:00Z"},"earthModel":"WGS84"}
            $goal = [];
        }
        elseif ($wpt['tawType'] == 'goal')
        {
            $wshape = $shape[$wpt['tawShape']];   
            $goal = [];
            $goal['type'] = $wshape;
            $timez = substr($taskinfo['tasFinishTime'], 11, 8);
            $hh = 0 + substr($timez, 0, 2) - $offset;
            $goal['deadline'] = sprintf("%02d", $hh) . substr($timez, 2, 6) . "Z";
        }

        $turnpoints[] = $turnpt;
    }

    $res['turnpoints'] = $turnpoints;
    $res['sss'] = $sss;
    $res['goal'] = $goal;
    $res['earthModel'] = 'WGS84';

    return $res;
}


//$usePk = auth('system');
$link = db_connect();
$cgi = var_export($_REQUEST,true);
error_log($cgi);
$res = [];
$comPk = reqival('comPk');
$tasPk = reqival('tasPk');
$format = reqival('format');

$result = get_xctrack_task($link, $comPk, $tasPk);

$jres = json_encode($result);

header("Content-type: text/xctsk");
header("Content-Disposition: attachment; filename=\"task_$tasPk.xctsk\"");
header("Cache-Control: no-store, no-cache");

print $jres;
?>

