<html>
<head>
<link HREF="xcstyle.css" REL="stylesheet" TYPE="text/css">
</head>
<body>
<div id="container">
<div id="vhead"><h1>airScore admin</h1></div>
<?php
require 'authorisation.php';
require 'format.php';
require 'dbextra.php';
$comPk = intval($_REQUEST['comPk']);
adminbar($comPk);

$usePk = auth('system');
$link = db_connect();
$query = "select comName from tblCompetition where comPk=$comPk";
$result = mysql_query($query, $link) or die('Task add failed: ' . mysql_error());
$comName = mysql_result($result,0,0);

echo "<p><h2><a href=\"comp_result.php?comPk=$comPk\">Competition - $comName</a></h2></p>";

// Add a task
if (array_key_exists('add', $_REQUEST))
{
    $Name = reqsval('taskname');
    $Date = reqsval('date');
    $TaskStart = reqsval('taskstart');
    $TaskFinish = reqsval('taskfinish');
    $StartOpen = reqsval('starttime');
    $StartClose = reqsval('startclose');
    $TaskType = reqsval('tasktype');
    $Interval = reqival('interval');
    $regPk = reqsval('region');
    $depart = reqsval('departure');
    $arrival = reqsval('arrival');

    check_admin('admin',$usePk,$comPk);

    if ($TaskType == 'speedrun-interval' && $Interval == 0)
    {
        echo "Unable to add task a speedrun-interval task with no gate interval times<br>";
        exit(1);
    }

    #$query = "select * from tblTask where tasDate='$Date' and comPk=$comPk";
    #$result = mysql_query($query, $link) or die('Task check failed: ' . mysql_error());
    #if (mysql_num_rows($result) > 0)
    #{
    #    echo "Unable to add task with duplicate date: $Date<br>";
    #    exit(1);
    #}

    $query = "insert into tblTask (comPk, tasName, tasDate, tasTaskStart, tasFinishTime, tasStartTime, tasStartCloseTime, tasSSInterval, tasTaskType, regPk, tasDeparture, tasArrival) values ($comPk, '$Name', '$Date', '$Date $TaskStart', '$Date $TaskFinish', '$Date $StartOpen', '$Date $StartClose', $Interval, '$TaskType', $regPk, '$depart', '$arrival')";
    $result = mysql_query($query, $link) or die('Task add failed: ' . mysql_error());

    // Get the task we just inserted
    $tasPk = mysql_insert_id();

    // Now check for pre-submitted tracks ..
    // FIX: check for task / track date match!
    // $query = "select traPk from tblComTaskTrack where comPk=$comPk and tasPk is null";
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

        // Now verify the pre-submitted tracks against the task
        foreach ($tracks as $tpk)
        {
            echo "Verifying pre-submitted track: $tpk<br>";
            $out = '';
            $retv = 0;
            exec(BINDIR . "track_verify.pl $tpk", $out, $retv);
        }
    }
}

// Delete a task
if (array_key_exists('delete', $_REQUEST))
{
    $id = intval($_REQUEST['delete']);
    check_admin('admin',$usePk,$comPk);

    if ($id > 0)
    {
        $query = "delete from tblTask where tasPk=$id";
        $result = mysql_query($query, $link) or die('Task delete failed: ' . mysql_error());
    
        $query = "delete from tblComTaskTrack where tasPk=$id";
        $result = mysql_query($query, $link) or die('Task CTT delete failed: ' . mysql_error());
    
        $query = "delete from tblTaskWaypoint where tasPk=$id";
        $result = mysql_query($query, $link) or die('Task TW delete failed: ' . mysql_error());
    
        $query = "delete from tblTaskResult where tasPk=$id";
        $result = mysql_query($query, $link) or die('Task TR delete failed: ' . mysql_error());

        echo "Task Removed\n";
    }
    else
    {
        echo "Unable to remove task: $id\n";
    }
}

// Update the competition
if (array_key_exists('update', $_REQUEST))
{
    check_admin('admin',$usePk,$comPk);

    $comname = reqsval('comname');
    $datefrom = reqsval('datefrom');
    $dateto = reqsval('dateto');
    $location = reqsval('location');
    $director = reqsval('director');
    $sanction = reqsval('sanction');
    $comptype = reqsval('comptype');
    $comcode = reqsval('code');
    $timeoffset = floatval($_REQUEST['timeoffset']);
    $overallscore = reqsval('overallscore');
    $overallparam = floatval($_REQUEST['overallparam']); 
    $teamscoring = reqsval('teamscoring');
    $teamsize = reqival('teamsize');
    $teamover = reqsval('teamover');
    $compclass = reqsval('compclass');
    $rentry = reqsval('entry');
    $regPk = reqival('region');

    $query = "update tblCompetition set comName='$comname', comLocation='$location', comDateFrom='$datefrom', comDateTo='$dateto', comMeetDirName='$director', comTimeOffset=$timeoffset, comType='$comptype', comCode='$comcode', comOverallScore='$overallscore', comOverallParam=$overallparam, comTeamScoring='$teamscoring', comTeamSize=$teamsize, comTeamOver='$teamover', comClass='$compclass', comEntryRestrict='$rentry', regPk=$regPk where comPk=$comPk";

    # FIX: re-optimise tracks if change from Free to OLC and vice versa

    $result = mysql_query($query, $link) or die('Competition update failed: ' . mysql_error());
}

// Add/update the formula
if (array_key_exists('upformula', $_REQUEST))
{
    $regarr = [];
    $regarr['comPk'] = $comPk;
    $regarr['forClass'] = reqsval('formula');
    $regarr['forVersion'] = reqsval('version');
    $regarr['forNomDistance'] = reqfval('nomdist');
    $regarr['forMinDistance'] = reqfval('mindist');
    $regarr['forNomTime'] = reqfval('nomtime');
    $regarr['forNomGoal'] = reqfval('nomgoal');
    $regarr['forNomLaunch'] = reqfval('nomlaunch');
    $regarr['forGoalSSpenalty'] = reqfval('sspenalty');
    $regarr['forLinearDist'] = reqfval('lineardist');
    $regarr['forDiffDist'] = reqfval('diffdist');
    $regarr['forDiffRamp'] = reqsval('difframp');
    $regarr['forDiffCalc'] = reqsval('diffcalc');
    $regarr['forDistMeasure'] = reqsval('distmeasure');
    $regarr['forArrival'] = reqsval('arrivalmethod');
    if (array_key_exists('weightstart', $_REQUEST))
    {
        $regarr['forWeightStart'] = reqfval('weightstart');
        $regarr['forWeightArrival'] = reqfval('weightarrival');
        $regarr['forWeightSpeed'] = reqfval('weightspeed');
    }
    $regarr['forStoppedGlideBonus'] = reqfval('glidebonus');

    //$forPk = reqival('forPk');
    $clause = "comPk=$comPk";

    $forPk = insertup($link, 'tblFormula', 'forPk', $clause,  $regarr);
    $sql = "update tblCompetition set forPk=$forPk where comPk=$comPk";
    $result = mysql_query($sql,$link);
}

if (array_key_exists('updateadmin', $_REQUEST))
{
    check_admin('admin',$usePk,$comPk);

    $adminPk = reqival('adminlogin');
    if ($adminPk > 0)
    {
        $query = "insert into tblCompAuth (usePk,comPk,useLevel) values ($adminPk,$comPk,'admin')";
        $result = mysql_query($query, $link) or die('Administrator addition failed: ' . mysql_error());
    }
}

$forPk = 0;
$ctype = '';
$sql = "SELECT T.* FROM tblCompetition T where T.comPk=$comPk";
$result = mysql_query($sql,$link);
$row = mysql_fetch_array($result, MYSQL_ASSOC);
if ($row)
{
    echo "<form action=\"competition.php?comPk=$comPk\" name=\"comedit\" method=\"post\">";
    $cname = $row['comName'];
    $cdfrom = substr($row['comDateFrom'],0,10);
    $cdto = substr($row['comDateTo'],0,10);
    $cdirector = $row['comMeetDirName'];
    $clocation = $row['comLocation'];
    $csanction = $row['comSanction'];
    $ctimeoffset = $row['comTimeOffset'];
    $overallscore = $row['comOverallScore'];
    $overallparam = $row['comOverallParam'];
    $teamscoring = $row['comTeamScoring'];
    $teamover = $row['comTeamOver'];
    $teamsize = $row['comTeamSize'];
    $ccode = $row['comCode'];
    $ctype = $row['comType'];
    $forPk = $row['forPk'];
    $entry = $row['comEntryRestrict'];
    $cclass = $row['comClass'];
    $clocked = $row['comLocked'];
    $regPk = $row['regPk'];

    $regarr = [];
    $sql = "SELECT * FROM tblRegion R order by regDescription";
    $result = mysql_query($sql,$link);
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $regDesc = $row['regDescription'];
        $regarr["$regDesc"] = $row['regPk'];
    }

    $out = ftable(
        [
            ['Name:', fin('comname', $cname, 14), 'Type:', fselect('comptype', $ctype, array('OLC', 'RACE', 'Free', 'Route', 'Team-RACE', 'RACE-handicap')), 'Class:', fselect('compclass', $cclass, array('PG','HG','mixed'))],
            ['Date From:', fin('datefrom', $cdfrom, 10), 'Date To:', fin('dateto', $cdto, 10), 'Pilot Entry:', fselect('entry', $entry, array('open', 'registered'))],
            ['Director:', fin('director', $cdirector, 14), 'Location:', fin('location', $clocation, 10)],
            ['Abbrev:', fin('code', $ccode, 10), 'Time Offset:', fin('timeoffset', $ctimeoffset, 10)],
            ['Scoring:', fselect('overallscore', $overallscore, array('all', 'ftv', 'round', 'round-perc' )), 'Score param:', fin('overallparam', $overallparam, 10)],
            ['Team Scoring:', fselect('teamscoring', $teamscoring, array('aggregate', 'team-gap', 'handicap')), 'Team Over:', fselect('teamover', $teamover, array('best', 'selected')), 'Team Size:', fin('teamsize', $teamsize, 4)],
            ['Waypoints:', fselect('region', $regPk, $regarr) ]
        ], '', '', ''
    );

    echo $out;
    echo fis('update', 'Update Competition', '');
    echo "</form>\n";
}

// Administrators 
$sql = "select U.*, A.* FROM tblCompAuth A, tblUser U where U.usePk=A.usePk and A.comPk=$comPk";
$result = mysql_query($sql,$link);
$admin = [];
while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $admin[] = $row['useLogin'];
}
echo "<hr><h3>Administrators: ", implode(", ", $admin), "</h3>";
echo "<form action=\"competition.php?comPk=$comPk\" name=\"adminedit\" method=\"post\">";

echo 'Add Administrator: ';
$sql = "select U.usePk as user, U.*, A.* FROM tblUser U left outer join tblCompAuth A on A.usePk=U.usePk where A.comPk is null or A.comPk<>$comPk group by U.useLogin order by U.useLogin";
$admin = [];
$result = mysql_query($sql,$link);
while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $admin[$row['useLogin']] = intval($row['user']);
}
echo fselect('adminlogin', '', $admin);
echo fis('updateadmin', 'Add', '');
echo "</form>\n";


// Formula
$version = 0;
$has_formula = array('RACE', 'Team-RACE', 'Route', 'RACE-handicap');

if (in_array($ctype, $has_formula))
{
    $sql = "SELECT F.* FROM tblFormula F where F.comPk=$comPk";
    $result = mysql_query($sql,$link);
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    if ($row)
    {
        $class = $row['forClass'];
        $version = $row['forVersion'];
        $nomdist = $row['forNomDistance'];
        $mindist = $row['forMinDistance'];
        $nomtime = $row['forNomTime'];
        $nomgoal = $row['forNomGoal'];
        $sspenalty = $row['forGoalSSpenalty'];
        $lineardist = $row['forLinearDist'];
        $diffdist = $row['forDiffDist'];
        $difframp = $row['forDiffRamp'];
        $diffcalc = $row['forDiffCalc'];
        $distmeasure = $row['forDistMeasure'];
        $weightstart = $row['forWeightStart'];
        $weightarrival = $row['forWeightArrival'];
        $weightspeed = $row['forWeightSpeed'];
        $glidebonus = $row['forStoppedGlideBonus'];
        $nomlaunch = $row['forNomLaunch'];
        $arrival = $row['forArrival'];
    }
    echo "<hr><h3>RACE Formula</h3>";
    echo "<form action=\"competition.php?comPk=$comPk\" name=\"formulaadmin\" method=\"post\">";
    $out = ftable(
        array(
          array('Formula:', fselect('formula', $class, array('gap', 'ozgap', 'pwc', 'sahpa', 'nzl', 'ggap', 'nogap', 'jtgap', 'rtgap', 'tmgap' )), 'Year:', fin('version', $version, 4)),
          array('Nom Dist (km):', fin('nomdist',$nomdist,4), 'Min Dist (km):', fin('mindist', $mindist, 4), 'Distance Measure:', fselect('distmeasure', $distmeasure, array('average', 'median'))),
          array('Nom Time (min):', fin('nomtime', $nomtime, 4), 'Goal/SS Penalty (0-1):', fin('sspenalty', $sspenalty, 4), 'Nom Goal (%):', fin('nomgoal',$nomgoal,4), 'Nom Launch (0-1):', fin('nomlaunch', $nomlaunch, 4)),
          array('Linear Dist (0-1):', fin('lineardist', $lineardist, 4),'Diff Dist (km):', fin('diffdist', $diffdist, 4), 'Diff Ramp:', fselect('difframp', $difframp, array('fixed', 'flexible')), 'Diff Calc:', fselect('diffcalc', $diffcalc, array('all', 'lo'))),
          array('Speed weighting:', fin('weightspeed', $weightspeed, 4), 'Start weighting:', fin('weightstart', $weightstart, 4), 'Arrival weighting:', fin('weightarrival', $weightarrival, 4), 'Method:', fselect('arrivalmethod', $arrival, [ 'place', 'timed' ])),
          array('Stopped Glide Bonus:', fin('glidebonus', $glidebonus, 4))
        ), '', '', ''
      );
    echo $out;
    echo "<input type=\"hidden\" name=\"forPk\" value=\"$forPk\">";
    echo "<input type=\"submit\" name=\"upformula\" value=\"Update Formula\">";
    echo "</form>";
}


if (in_array($ctype, $has_formula))
{
// Tasks
echo "<hr><h3>Tasks</h3><form action=\"competition.php?comPk=$comPk\" name=\"taskadmin\" method=\"post\">";
echo "<ol>";
$count = 1;
$sql = "SELECT T.*, traPk as Tadded FROM tblTask T left outer join tblComTaskTrack CTT on CTT.tasPk=T.tasPk where T.comPk=$comPk group by T.tasPk order by T.tasDate";
$result = mysql_query($sql,$link);

while($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $tasPk = $row['tasPk'];
    $tasDate = $row['tasDate'];
    $tasName = $row['tasName'];
    $tasDistance = $row['tasDistance'];
    $tasTaskStart = $row['tasTaskStart'];
    $tasStartTime = $row['tasStartTime'];
    $tasFinishTime = $row['tasFinishTime'];
    $tasResultsType = $row['tasResultsType'];
    $tasTaskType = $row['tasTaskType'];
    $tasDistance = $row['tasDistance'];
    $tasSSDistance = $row['tasSSDistance'];
    $tasSSOpen = $row['tasSSOpen'];
    $tasSSClose = $row['tasSSClose'];
    $tasESClose = $row['tasESClose'];
    $tasDistFlown = $row['tasTotalDistanceFlown'];

    echo "<li>";
    if ($row['Tadded'] < 1)
    {
        echo "<button type=\"submit\" name=\"delete\" value=\"$tasPk\">del</button>";
    }
    echo "<a href=\"task.php?comPk=$comPk&tasPk=$tasPk\">$tasName: " . round($tasDistance/1000,1) . " kms on " . $tasDate . " (" . substr($tasTaskStart,11) . " - " . substr($tasFinishTime,11) . ")</a></li>\n";

    $count++;
}
echo "</ol>";

$sql = "SELECT * FROM tblRegion R";
$result = mysql_query($sql,$link);
$regions = [];
while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $regPk = $row['regPk'];
    $regDesc = $row['regDescription'];
    $regions[$regDesc] = $regPk;
}

$sql = "SELECT T.* FROM tblCompetition C, tblTask T where T.comPk=C.comPk and C.comPk=$comPk order by T.tasPk limit 1";
$result = mysql_query($sql,$link);
$defregion = '';
if (mysql_num_rows($result) > 0)
{
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $defregion = $row['regPk'];
}


echo "<hr>";
$depdef = 'on';
if ($version > 2000)
{
   $depdef = 'leadout'; 
}
$out = ftable(
    array(
        array('Task Name:', fin('taskname', '', 10), 'Date:', fin('date', '', 10)),
        array('Region:', fselect('region', $defregion, $regions), 'Task Type:', fselect('tasktype', 'race', array('olc', 'race', 'speedrun', 'speedrun-interval', 'free', 'free-bearing', 'free-pin', 'airgain', 'aat'))),
        array('Task Start:', fin('taskstart', '', 10), 'Task Finish:', fin('taskfinish', '', 10)), 
        array('Start Open:', fin('starttime', '', 10), 'Start Close:', fin('startclose', '', 10), 'Gate Interval:', fin('interval', '', 4)), 
        array('Depart Bonus:', fselect('departure', $depdef, array('on', 'off', 'leadout', 'kmbonus')), 'Arrival Bonus:', fselect('arrival', 'on', array('on', 'off')))
    ), '', '', '');

echo $out;
echo "<input type=\"submit\" name=\"add\" value=\"Add Task\">";

echo "</form>";
}
?>
</div>
</body>
</html>

