<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require 'authorisation.php';
require 'hc.php';
require 'format.php';
require 'xcdb.php';

function get_actual_glider($glider, $pilsize, $weight)
{
    $sql = "select gliManufacturer from tblGlider group by gliManufacturer";
    $result = mysql_query($sql,$link) or json_die('Manufacturer selection failed: ' . mysql_error());
    $manufacturers = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $manufacturers[] = strtolower($row['gliManufacturer']);
    }

    $arr = explode(" ", $glider, 2);
    if (in_array(strtolower($arr[0]), $manufacturers))
    {
        $glider = $arr[1];
    }

    $sql = "select * from tblGlider";
    $result = mysql_query($sql,$link) or json_die('Glider selection failed: ' . mysql_error());
    $gliders = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $gliders[] = $row;
    }

    // find a match ..
    foreach ($gliders as $row)
    {
        $alt = strtolower(str_replace(' ', '', $row['gliName']));
        $altglider = strtolower(str_replace(' ', '', $glider));

        if (($altglider == $alt) && ($pilsize == $row['gliSize']))
        {
            return $row;
        }

        if (($altglider == $alt) && ($weight > $row['gliBottomWeight']) && ($weight < $row['gliTopWeight']))
        {
            return $row;
        }
    }

    return 0; 
}

function timeoffset($str)
{
    $hhmmss = substr($str, 11); 
    $arr = split($hhmmss, ':');
    $result = $arr[0] * 3600 + $arr[1] * 60 + $arr[2];
    return $result;
}

function timeinss($row, $task)
{
    if ($row['tarES'] > 0)
    {
        // goal speed: tarES-tarSS / tarSSDistance
        $timeoncourse = $row['tarES'] - $row['tarSS'];
    }
    else
    {
        $timeoncourse = $row['traDuration'];
        $startoff = timeoffset($row['traStartTime']) - $row['tarSS'];
        if ($startoff > 0)
        {
            $timeoncourse = $row['traDuration'] - $startoff;
        }
    }

    if ($timeoncourse < 0)
    {
        $timeoncourse = 0;
    }

    return $timeoncourse;
}

function get_speed($row, $task, $glider_details)
{
    $speed = $task['tasSSDistance'] * 3.6 / timeinss($row,$task);
}

function pilot_speed($Tmin, $piltime, $Aspeed)
{
    # print $pil->{'tarPk'}, " speed: ", $pil->{'time'}, ", $Tmin\n";
    $Pspeed = 0;
    if ($piltime > 0)
    {
        $Pspeed = $Aspeed * (1-(($piltime-$Tmin)/3600/sqrt($Tmin/3600))**(2/3));
    }

    if ($Pspeed < 0 || is_nan($Pspeed))
    {
        $Pspeed = 0;
    }
    # print "$Tmin $piltime sp=$Pspeed";

    return $Pspeed;
}

function pilot_distance($task, $pildist, $Adistance)
{

    $Pdist = $Adistance * ($pildist/($task['tasMaxDistance']/1000));

    # print $pil->{'tarPk'}, " lin dist: ", $Adistance * ($pil->{'distance'}/$taskt->{'maxdist'}) * $formula->{'lineardist'}, " dif dist: ", $Adistance * $kmdiff->[floor($pil->{'distance'}/100.0)] * (1-$formula->{'lineardist'});

    return $Pdist;
}

function points_weight($task)
{
	$gapval = [];

    $quality = $task['tasQuality'];
    $x = $task['tasPilotsGoal'] / $task['tasPilotsLaunched'];

    $distweight = 0.9-1.665*$x+1.713*$x*$x-0.587*$x*$x*$x;

    # 1998 - 1999 - (speed 6 / start 1 / arrival 1) / 8
    # 2000 - 2007 - (5.6 / 1.4  1) / 8
    $gapval['Adistance'] = 1000 * $quality * $distweight;
    $gapval['Aspeed'] = 1000 - $gapval['Adistance'];

    # print "points_weight: (", $formula->{'version'}, ") Adist=$Adistance, Aspeed=$Aspeed, Astart=$Astart, Aarrival=$Aarrival\n";
    # print json_encode($gapval); exit(1);
    return $gapval;
}

function pilcmp($a, $b)
{
    if (0+$a[9] == 0+$b[9])
    {
        if ($a[7] == $b[7]) 
        {
            return 0;
        }
        return ($a[7] < $b[7]) ? -1 : 1;
    }
    return (0+$a[9] > 0+$b[9]) ? -1 : 1;
}


function regap($results, $task, $formula)
{
    # Find fastest pilot into goal and calculate leading coefficients
    # for each track .. (GAP2002 only?)

    $quality = $task['tasQuality'];
    $Ngoal = $task['tasPilotsGoal'];

    usort($results, pilcmp);   # sort by speed asc, distrance
    #print json_encode($results); exit(1);
    $Tmin = $results[0][7];

    # Get basic GAP allocation values
    # my ($Adistance, $Aspeed, $Astart, $Aarrival) 
    $gapval = points_weight($task, $formula);

    # Score each pilot now 
	$rescore = [];
    $count = 1;

    foreach ( $results as $pil )
    {
        $pil[0] = $count;
        $count++;
        $penalty = $pil[14];

        # Pilot distance score 
        $Pdist = pilot_distance($task, $pil[9], $gapval['Adistance']);

        # Pilot speed score
        $Pspeed = round(pilot_speed($Tmin, $pil[7], $gapval['Aspeed']), 1);

        # Pilot departure/leading points
        $Pdepart = 0;

        # Pilot arrival score
        $Parrival = 0;

        # Penalty for not making goal ..
        #if ($pil->{'goal'} == 0)
        #{
        #    $Pspeed = $Pspeed - $Pspeed * $formula->{'sspenalty'};
        #    $Parrival = $Parrival - $Parrival * $formula->{'sspenalty'}; 
        #}

        # Sanity
        if (($pil[9] == 'dnf') || ($pil[9] == 'abs'))
        {
            $Pdist = 0;
            $Pspeed = 0;
            $Parrival = 0;
            $Pdepart = 0;
        }

        # Total score
        $Pscore = $Pdist + $Pspeed + $Parrival + $Pdepart - $penalty;

        $pil[10] = 0;
        $pil[12] = round($Pspeed,1);
        $pil[13] = round($Pdist,1);
        $pil[15] = round($Pscore, 0);
        $rescore[] = $pil;
    }

	return $rescore;
}


function get_modified_result($row, $task, $glider, $formula)
{
    //
    if ($glider == 0 || $glider['gliEstimatedSpeed'] == 0)
    {
        return 0;
    }

    if ($row['tarES'] > 0)
    {
        // goal speed: tarES-tarSS / tarSSDistance
        $speed = $task['tasSSDistance'] * 3.6 / ($row['tarES'] - $row['tarSS']);
    }
    else
    {
        $timeoncourse = $row['traDuration'];
        $startoff  = timeoffset($row['traStartTime']) - $row['tarSS'];
        if ($startoff > 0)
        {
            $timeoncourse = $row['traDuration'] - $startoff;
        }
        if ($timeoncourse < $formula['nominal_time']*60)
        {
            $timeoncourse = $formula['nominal_time'] * 60;
        }
        $speed = ($row['tarDistance'] - $task['tasStartSSDistance']) * 3.6 / $timeoncourse;
        // non-goal speed:
        //      speed: (traDuration + traStartTime) - tarSS / (tarDistance - tasStartSSDistance)
        //      result = 200 * speed / gliEstimatedSpeed     (as a %)
    }
    
    $result = $speed * 200 / $glider['gliEstimatedSpeed'];

    return round($result, 0);
}

function task_result($link, $comPk, &$tsinfo, $tasPk, $fdhv, $task, $formula)
{
    $safety = 0;
    $sc = 0;
    $conditions = 0;
    $cc = 0;
    $goalalt = 0;
    $maxrow = 0;
    $ngoal = 0;

    foreach ($tsinfo['waypoints'] as $row)
    {
        if ($row['tawType'] == 'goal')
        {
            $goalalt = $row['rwpAltitude'];
        }
    }

    $count = 1;
    $sql = "select TR.*, T.*, P.* from tblTaskResult TR, tblTrack T, tblPilot P where TR.tasPk=$tasPk $fdhv and T.traPk=TR.traPk and P.pilPk=T.pilPk order by TR.tarScore desc, P.pilFirstName";
    $result = mysql_query($sql,$link) or json_die('Task Result selection failed: ' . mysql_error());
    $lastscore = 0;
    $lastplace = 0;
    $hh = 0;
    $mm = 0;
    $ss = 0;
    $rnd = 1;
    $offset = $tsinfo['offset'];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $name = utf8_encode($row['pilFirstName'] . ' ' . $row['pilLastName']);
        $nation = $row['pilNationCode'];
        $tarPk = $row['tarPk'];
        $traPk = $row['traPk'];
        $dist = round($row['tarDistanceScore'], $rnd);
        $dep = round($row['tarDeparture'], $rnd);
        $arr = round($row['tarArrival'], $rnd);
        $speed = round($row['tarSpeedScore'], $rnd);
        $score = round($row['tarScore'], 0);
        $lastalt = round($row['tarLastAltitude']);
        $resulttype = $row['tarResultType'];
        $start = $row['tarSS'];
        $end = $row['tarES'];
        $endf = "";
        $startf = "";
        $timeinair = "";
        if ($row['traConditions'] > 0)
        {
            $conditions += $row['traConditions'];
            $cc++;
        }
        if ($row['traSafety'] == 'safe')
        {
            $safety++;
            $sc++;
        }
        else if ($row['traSafety'] == 'maybe')
        {
            $safety+=2;
            $sc++;
        }
        else if ($row['traSafety'] == 'unsafe')
        {
            $safety+=3;
            $sc++;
        }

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
            if ($tsinfo['task_type'] == 'speedrun-interval')
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
        $weight = $row['pilFlightWeight'];
        $glider_details = get_actual_glider($glider, $row['pilGliderSize'], $weight);
        if ($glider_details)
        {
            $glider = $glider_details['gliManufacturer'] . ' ' . $glider_details['gliName'] . " " . $glider_details['gliSize'];
        }
        else
        {
            $glider = "Unknown";
        }
        if ($maxrow == 0)
        {
            $maxrow = $glider_details;
        }
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
        elseif ($dhv == '2/3' || $dhv == "rigid")
        {
            $trrow[] = 'D';
        }
        elseif ($dhv == '2' || $dhv == "open")
        {
            $trrow[] = 'C';
        }
        elseif ($dhv == '1/2' || $dhv == "kingpost")
        {
            $trrow[] = 'B';
        }
        else
        {
            $trrow[] = 'A';
        }
        $trrow[] = $startf;
        $trrow[] = $endf;
        $trrow[] = $end - $start; #7

    
        if ($tsinfo['stopped'] != '')
        {
            $trrow[] = $lastalt;
        }
        else if ($tsinfo['hbess'] == 'on')
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
        $newdist = $tardist;
        $newtime = 0;
        $boost = 1.05;
        if ($tardist > 0 && $glider_details['gliEstimatedSpeed'] > 0)
        {
            if ($maxrow['gliEstimatedSpeed'] > $glider_details['gliEstimatedSpeed'])
            {
                $newdist = $tardist * ($maxrow['gliEstimatedSpeed'] * $boost / $glider_details['gliEstimatedSpeed']);
            }
            else
            {
                $newdist = $tardist * ($maxrow['gliEstimatedSpeed'] / $glider_details['gliEstimatedSpeed'] / $boost);
            }
        }
        if ($newdist >= $tsinfo['task_dist'])
        {
            # give a speed
            $ngoal++;
            $newtime = timeinss($row, $tsinfo) * $tsinfo['task_dist'] / $newdist;
            $newdist = $tsinfo['task_dist'];
        }
        else if (($end - $start > 0) && $newdist < $tsinfo['task_dist'])
        {
            $ngoal++;
            $newtime = ($end - $start) * ($glider_details['gliEstimatedSpeed'] * $boost / $maxrow['gliEstimatedSpeed']);
            $newdist = $tsinfo['task_dist'];
        }
        if ($newtime < 0)
        {
            $newtime = 0;
        }
        $trrow[7] = round($newtime,0);
        $trrow[] = round($newdist,1); #9

        $trrow[] = $dep;
        $trrow[] = $arr;
        $trrow[] = 0;
        $trrow[] = $dist;
        if ($penalty == 0)
        {
            $trrow[] = '';
        }
        else
        {
            $trrow[] = $penalty;
        }
        $trrow[] = get_speed($row, $task, $glider_details); #13
        $trtab[] = $trrow;
    
        $count++;
    }

    $newresults = regap($trtab, $task, $formula);

    # reallocate points

    $tsinfo['safety'] = 1;
    if ($sc > 0)
    {
        $tsinfo['safety'] = $safety / $sc;
    }

    $tsinfo['conditions'] = 1.0;
    if ($cc > 0)
    {
        $tsinfo['conditions'] = 2 * $conditions / $cc;
    }

    return $newresults;
}


// main mess - should be mostly in a function
$usePk = check_auth('system');
$link = db_connect();
$tasPk = reqival('tasPk');
$comPk = reqival('comPk');

#$comPk=293;
#$tasPk=1305;

$rnd = 0;
if (reqexists('rnd'))
{
    $rnd = reqival('rnd');
}

$fdhv= '';
$classfilter = '';

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
    $tasQuality = round($row['tasQuality'],3);
    $tasComment = $row['tasComment'];
    $tasDistQuality = round($row['tasDistQuality'],3);
    $tasTimeQuality = round($row['tasTimeQuality'],3);
    $tasLaunchQuality = round($row['tasLaunchQuality'],3);
    $tasStopQuality = round($row['tasStopQuality'],3);
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
$tsinfo["comp_class"] = $comClass;
$tsinfo["task_name"] = $tasName;
$tsinfo["date"] = $tasDate;
$tsinfo["task_type"] = strtoupper($tasTaskType);
$tsinfo["class"] = $classfilter;
$tsinfo["start"] = $tasStartTime;
$tsinfo["end"] = $tasFinishTime;
$tsinfo["stopped"] = $tasStoppedTime;
$tsinfo["wp_dist"] = $tasDistance;
$tsinfo["task_dist"] = $tasShortest;
$tsinfo["quality"] = number_format($tasQuality,3);
$tsinfo["dist_quality"] = number_format($tasDistQuality,3);
$tsinfo["time_quality"] = number_format($tasTimeQuality,3);
$tsinfo["launch_quality"] = number_format($tasLaunchQuality,3);
$tsinfo["stop_quality"] = number_format($tasStopQuality,3);
$tsinfo["comment"] = $tasComment;
$tsinfo["offset"] = $comTOffset;
$tsinfo["hbess"] = $tasHeightBonus;
$tsinfo["waypoints"] = $waypoints;

# Pilot Info
$pinfo = [];
# total, launched, absent, goal, es?

# Formula / Quality Info
$finfo = [];
# gap, min dist, nom dist, nom time, nom goal ?

// FIX: Print out task quality information.
// add in country from tblCompPilot if we have entries ...

$comf = get_comformula($link, $comPk);
$formula = [];
$formula['formula'] = $comf['forClass'] . '-' . $comf['forVersion'];
$formula['goal_penalty'] = $comf['forGoalSSpenalty'];
$formula['nominal_goal'] = $comf['forNomGoal'] . '%';
$formula['minimum_distance'] = $comf['forMinDistance'] . ' km';
$formula['nominal_distance'] = $comf['forNomDistance'] . ' km';
$formula['nominal_time'] = $comf['forNomTime'] . ' mins';
$formula['arrival_scoring'] = $comf['forArrival'];
$formula['departure'] = $comf['forDeparture'];
//$formula['linear_distance'] = $comf['forLinearDist'];
$formula['stop_glide_bonus'] = $comf['forStoppedGlideBonus'];
$formula['start_weight'] = $comf['forWeightStart'];
$formula['arrival_weight'] = $comf['forWeightArrival'];
$formula['speed_weight'] = $comf['forWeightSpeed'];
$formula['scale_to_validity'] = $comf['forScaleToValidity'];
$formula['error_margin'] = $comf['forErrorMargin']/100;
$formula['arrival'] = $tasArrival;
$formula['height_bonus'] = $tasArrival;
$formula['departure'] = $depcol;

$sorted = task_result($link, $comPk, $tsinfo, $tasPk, $fdhv, $row, $formula);

$metric = [];
$metric["day quality"] = number_format($tasQuality,3);
$metric["dist_quality"] = number_format($tasDistQuality,3);
$metric["time_quality"] = number_format($tasTimeQuality,3);
$metric["launch_quality"] = number_format($tasLaunchQuality,3);
if ($tasStoppedTime > 0)
{
    $metric["stop_quality"] = number_format($tasStopQuality,3);
}
$metric["pilot_safety"] = round($tsinfo['safety'], 1);
$metric["pilot_quality"] = round($tsinfo['conditions']/10, 2);

$data = [ 'task' => $tsinfo, 'formula' => $formula, 'metrics' => $metric, 'data' => $sorted ];
//$data = [ 'data' => $sorted, 'extra' => [ 'foo' ] ];
print json_encode($data);

?>
