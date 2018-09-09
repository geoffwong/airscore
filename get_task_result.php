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
$comPk = reqival('comPk');

$rnd = 0;
if (reqexists('rnd'))
{
    $rnd = reqival('rnd');
}

$fdhv= '';
$classstr = '';

$depcol = 'Dpt';
$row = get_comtask($link,$tasPk);
if ($row)
{
    $comName = $row['comName'];
    $comClass = $row['comClass'];
    $comPk = $row['comPk'];
    //$_REQUEST['comPk'] = $comPk;
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
$goalalt = 0;
$tsinfo = [];
$tsinfo["comp_name"] = $comName;
$tsinfo["task_name"] = $tasName;
$tsinfo["date"] = $tasDate;
$tsinfo["task_type"] = $tasTaskType;
$tsinfo["class"] = $classfilter;
$tsinfo["start"] = $tasStartTime;
$tsinfo["end"] = $tasFinishTime;
$tsinfo["stopped"] = $tasStoppedTime;
$tsinfo["quality"] = number_format($tasQuality,3);
$tsinfo["wp_dist"] = $tasDistance;
$tsinfo["task_dist"] = $tasShortest;
$tsinfo["dist_quality"] = number_format($tasDistQuality,3);
$tsinfo["time_quality"] = number_format($tasTimeQuality,3);
$tsinfo["launch_quality"] = number_format($tasLaunchQuality,3);
$tsinfo["comment"] = $tasComment;
$tsinfo["waypoints"] = $waypoints;

# Pilot Info
$pinfo = [];
# total, launched, absent, goal, es?

# Formula / Quality Info
$finfo = [];
# gap, min dist, nom dist, nom time, nom goal ?

// FIX: Print out task quality information.
// add in country from tblCompPilot if we have entries ...


function task_result($link, $comPk, $offset, $tasPk, $fdhv)
{
    $count = 1;
    $sql = "select TR.*, T.*, P.* from tblTaskResult TR, tblTrack T, tblPilot P where TR.tasPk=$tasPk $fdhv and T.traPk=TR.traPk and P.pilPk=T.pilPk order by TR.tarScore desc, P.pilFirstName";
    $result = mysql_query($sql,$link) or die('Task Result selection failed: ' . mysql_error());
    $lastscore = 0;
    $hh = 0;
    $mm = 0;
    $ss = 0;
    while ($row = mysql_fetch_array($result))
    {
        $name = $row['pilFirstName'] . ' ' . $row['pilLastName'];
        $nation = $row['pilNationCode'];
        $tarPk = $row['tarPk'];
        $traPk = $row['traPk'];
        $dist = round($row['tarDistanceScore'], $rnd);
        $dep = round($row['tarDeparture'], $rnd);
        $arr = round($row['tarArrival'], $rnd);
        $speed = round($row['tarSpeedScore'], $rnd);
        $score = round($row['tarScore'], $rnd);
        $lastalt = round($row['tarLastAltitude']);
        $resulttype = $row['tarResultType'];
        $start = $row['tarSS'];
        $end = $row['tarES'];
        $endf = "";
        $startf = "";
        $timeinair = "";
        if ($end)
        {
            $hh = floor(($end - $start) / 3600);
            $mm = floor((($end - $start) % 3600) / 60);
            $ss = ($end - $start) % 60;
            $timeinair = sprintf("%01d:%02d:%02d", $hh,$mm,$ss);
            $hh = floor(($offset + $start) / 3600) % 24;
            $mm = floor((($offset + $start) % 3600) / 60);
            $ss = ($offset + $start) % 60;
            $startf = sprintf("%02d:%02d:%02d", $hh,$mm,$ss);
            $hh = floor(($offset + $end) / 3600) % 24;
            $mm = floor((($offset + $end) % 3600) / 60);
            $ss = ($offset + $end) % 60;
            $endf = sprintf("%02d:%02d:%02d", $hh,$mm,$ss);
        }
        else
        {
            $timeinair = "";
            if ($tasTaskType == 'speedrun-interval')
            {
                $hh = floor(($offset + $start) / 3600) % 24;
                $mm = floor((($offset + $start) % 3600) / 60);
                $ss = ($offset + $start) % 60;
                if ($hh >= 0 && $mm >= 0 && $ss >= 0)
                {
                    $startf = sprintf("%02d:%02d:%02d", $hh,$mm,$ss);
                }
                else
                {
                    $startf = "";
                }
            }
        }
        $time = ($end - $start) / 60;
        $tardist = round($row['tarDistance']/1000,2);
        $penalty = round($row['tarPenalty']);
        $glider = htmlspecialchars($row['traGlider']);
        $dhv = $row['traDHV'];
        if (0 + $tardist == 0)
        {
            $tardist = $resulttype;
        }
    
        if ($lastscore != $score)
        {
            $place = "$count";
            $lastplace = $place;
        }
        else
        {
            $place = $lastplace;
        }
        $lastscore = $score;
    
        //<th>Rank</th> <th>Pilot</th> <th>Nat</th> <th>Glider</th> <th>SS</th> <th>ES</th> <th>Time</th> <th id="altbonus">HBs</th> 
        // <th>Kms</th> <th id="leading">Lkm</th> <th>Spd</th> <th>Dst</th> <th>Pen</th> <th>Total</th>
        $trrow = [fb($place), "<a href=\"tracklog_map.html?trackid=$traPk&comPk=$comPk&tasPk=$tasPk\">$name</a>", $nation ];
        $trrow[] = $glider;
        if ($dhv == 'competition')
        {
            $trrow[] = 'CCC';
        }
        elseif ($dhv == '2/3')
        {
            $trrow[] = 'D';
        }
        elseif ($dhv == '2')
        {
            $trrow[] = 'C';
        }
        elseif ($dhv == '1/2')
        {
            $trrow[] = 'B';
        }
        else
        {
            $trrow[] = 'A';
        }
        $trrow[] = $startf;
        $trrow[] = $endf;
        $trrow[] = $timeinair;

        if ($tasStoppedTime != '')
        {
            $trrow[] = $lastalt;
        }
        else if ($tasHeightBonus == 'on')
        {
            if ($lastalt > 0)
            {
                $habove = $lastalt - $goalalt;
                if ($habove > 400)
                {
                    $habove = 400;
                }
                if ($habove > 50)
                {
                    $trrow[] = round(20.0*pow(($habove-50.0),0.40));
                }
                else
                {
                    $trrow[] = 0;
                }
            }
            else
            {
                $trrow[] = '';
            }
        }
        else
        {
                $trrow[] = '';
        }
        $trrow[] = $tardist;
        $trrow[] = $dep;
        //$trrow[] = $arr;
        $trrow[] = $speed;
        $trrow[] = $dist;
        if ($penalty == 0)
        {
            $trrow[] = '';
        }
        else
        {
            $trrow[] = $penalty;
        }
        $trrow[] = $score;
        $trtab[] = $trrow;
    
        $count++;
    }

    return $trtab;
}


$sorted = task_result($link, $comPk, $comTOffset, $tasPk, $fdhv);
$data = [ 'task' => $tsinfo, 'data' => $sorted ];
//$data = [ 'data' => $sorted, 'extra' => [ 'foo' ] ];
print json_encode($data);

?>
