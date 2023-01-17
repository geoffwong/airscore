<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';
require_once 'get_olc.php';

function  handicap_result($tasPk, $link)
{
    $query = "select max(TR.tarScore) as maxScore from tblTaskResult TR where TR.tasPk=$tasPk";
    $result = mysql_query($query, $link) or json_die('Team handicap query failed: ' . mysql_error());
    $maxscore = 1000;
    $row = mysql_fetch_array($result);
    if ($row)
    {
        $maxscore = $row['maxScore'];
    }

    $query = "select TM.teaPk,TM.teaName,P.pilLastName,P.pilFirstName,P.pilPk,TR.tarScore-H.hanHandicap*$maxscore as handiscore from tblTaskResult TR, tblTask TK, tblTrack K, tblPilot P, tblTeam TM, tblTeamPilot TP, tblHandicap H, tblCompetition C where TP.teaPk=TM.teaPk and P.pilPk=TP.pilPk and H.comPk=C.comPk and C.comPk=TK.comPk and K.traPk=TR.traPk and K.pilPk=P.pilPk and H.pilPk=P.pilPk and TK.tasPk=$tasPk and TR.tasPk=TK.tasPk and TM.comPk=C.comPk order by TM.teaPk";
    $result = mysql_query($query, $link) or json_die('Team handicap query failed: ' . mysql_error());
    $row = mysql_fetch_array($result);
    $htable = [];
    $hres = [];
    $teaPk = 0;
    $total = 0;

    // FIX: sort team results ..
    while ($row)
    {
        if ($teaPk != $row['teaPk'])
        {
            if ($teaPk == 0)
            {
                $teaPk = $row['teaPk'];
            }
            else
            {
                // wrap up last one
                $htable[] = [ 'Total', "$total"];
                $htable[] = [ '', ''];
                $team = $row['teaName'];
                $hres["${total}${team}"] = $htable;
                $total = 0;
                $htable = [];
                $teaPk = $row['teaPk'];
            }
        }

        if ($row['handiscore'] > $maxscore)
        {
            $row['handiscore'] = $maxscore;
        }
        if ($total == 0)
        {
            $htable[] = [ $row['teaName'],  $row['pilFirstName'] . ' ' . $row['pilLastName'], round($row['handiscore'],2)];
        }
        else
        {
            $htable[] = [ '',  $row['pilFirstName'] . ' ' . $row['pilLastName'], round($row['handiscore'],2)];
        }
        $total = round($total + $row['handiscore'],2);
        $row = mysql_fetch_array($result, MYSQL_ASSOC);
    }

    $htable[] = ['', 'Total', "$total"];
    $htable[] = ['', '', ''];
    $team = $row['teaName'];
    $hres["${total}${team}"] = $htable;
    krsort($hres, SORT_NUMERIC);

    $htable = [];
    foreach ($hres as $res => $pils)
    {
        foreach ($pils as $row)
        {
            $htable[] = $row;
        }
    }

    return $htable;
}

function aggregate_result($link,$tasPk,$compinfo)
{
    $query = "select TM.teaPk,TM.teaName,P.pilLastName,P.pilFirstName,P.pilPk,TR.tarScore*TP.tepModifier as tepscore from tblTaskResult TR, tblTask TK, tblTrack K, tblPilot P, tblTeam TM, tblTeamPilot TP, tblCompetition C where TP.teaPk=TM.teaPk and P.pilPk=TP.pilPk and C.comPk=TK.comPk and K.traPk=TR.traPk and K.pilPk=P.pilPk and TK.tasPk=$tasPk and TR.tasPk=TK.tasPk and TM.comPk=C.comPk order by TM.teaPk,TR.tarScore*TP.tepModifier desc";
    $result = mysql_query($query, $link) or json_die('Team aggregate query failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $htable = [];
    $hres = [];
    $teaPk = 0;
    $total = 0;
    $size = 0;
    while ($row)
    {
        if ($teaPk != $row['teaPk'])
        {
            if ($teaPk == 0)
            {
                $teaPk = $row['teaPk'];
            }
            else
            {
                // wrap up last one
                $htable[] = [$team, '', '', "<b>$total</b>"];
                $hres["${total}${team}"] = $htable;
                $team = $row['teaName'];
                $total = 0;
                $size = 0;
                $htable = [];
                $teaPk = $row['teaPk'];
            }
        }

        if ($row['tepscore'] > 1000)
        {
            $row['tepscore'] = 1000;
        }
        $team = $row['teaName'];
        $htable[] = [ $team,  $row['pilFirstName'] . ' ' . $row['pilLastName'], round($row['tepscore'],2), ''];
        // $htable[] = [ '',  $row['pilFirstName'] . ' ' . $row['pilLastName'], round($row['tepscore'],2)];
        if ($size < $compinfo['comTeamSize'])
        {
            $total = round($total + $row['tepscore'],2);
            $size = $size + 1;
        }
        $row = mysql_fetch_array($result, MYSQL_ASSOC);
    }

    $htable[] = [$team, '', '', "<b>$total</b>"];
    $hres["${total}${team}"] = $htable;
    krsort($hres, SORT_NUMERIC);

    $htable = [];
    foreach ($hres as $res => $pils)
    {
        foreach ($pils as $row)
        {
            $htable[] = $row;
        }
    }

    return $htable;
}


function get_task_team($link, $comPk, $tasPk, $compinfo)
{
    if ($compinfo['comTeamScoring'] == "handicap")
    {
        return handicap_result($tasPk, $link);
    }
    else if ($compinfo['comTeamScoring'] == "aggregate")
    {
        return aggregate_result($link,$tasPk,$compinfo);
    }


    $htable = [];
    $count = 1;

    $sql = "select TR.*, P.*, L.*, TTR.* from tblTeamResult TR, tblTeam P, tblTeamPilot TP, tblPilot L, tblTaskResult TTR, tblTrack TK where TP.teaPk=P.teaPk and TP.pilPk=L.pilPk and TR.tasPk=$tasPk and TTR.traPk=TK.traPk and P.teaPk=TR.teaPk and TK.pilPk=TP.pilPk and TTR.tasPk=TR.tasPk and TK.traPk=TTR.traPk order by TR.terScore desc, P.teaName";

    $result = mysql_query($sql,$link) or json_die('Team Result Selection failed: ' . mysql_error());
    $lastscore = 0;
    $lasttm = 0;
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $traPk = $row['traPk'];
        if ($row['teaPk'] != $lasttm)
        {
            $tm = '';
            if ($row['terES'] > 0)
            {
                $tm = ftime($row['terES'] - $row['terSS']);
            }
            $htable[] = [ $row['teaName'], '', $tm, round($row['terDistance']/1000,2), round($row['terScore']) ];
            $lasttm = $row['teaPk'];
        }
        if ($row['tarScore'] > 0)
        {
        if ($row['tarES'] > 0)
            {
                $tm = ftime($row['tarES'] - $row['tarSS']);
            }
            else
            {
                $tm = '';
            }
            $htable[] = [ '', "<a href=\"tracklog_map.php?trackid=$traPk&comPk=$comPk\">" . $row['pilFirstName'] . ' ' . $row['pilLastName'] . "</a>", $tm, round($row['tarDistance']/1000,2) ];
        }
    
        $count++;
    }
    return $htable;
}
    
// comp & formula info
$comPk = reqival('comPk');
$tasPk = reqival('tasPk');

$usePk = check_auth('system');
$isadmin = is_admin('admin',$usePk,$comPk);

$link = db_connect();

if ($isadmin && array_key_exists('score', $_REQUEST))
{
    $out = '';
    $retv = 0;
    exec(BINDIR . "team_score.pl $tasPk", $out, $retv);
}

$compinfo = [];
$sorted = [];
$civilised = [];

$row = get_comtask($link,$tasPk);
$waypoints = get_taskwaypoints($link,$tasPk);
$goalalt = 0;
$tsinfo = [];
$tsinfo["comp_name"] = $row['comName'];
$tsinfo["comp_class"] = $row['comClass'];
$tsinfo["task_name"] = $row['tasName'];
$tsinfo["date"] = $row['tasDate'];
$tsinfo["task_type"] = strtoupper($row['tasTaskType']);
$tsinfo["class"] = $classfilter;
$tsinfo["start"] = $row['tasStartTime'];
$tsinfo["end"] = $row['tasFinishTime'];
$tsinfo["stopped"] = $row['tasStoppedTime'];
$tsinfo["wp_dist"] = $row['tasDistance'];
$tsinfo["task_dist"] = $row['tasShortest'];
$tsinfo["quality"] = number_format($row['tasQuality'],3);
$tsinfo["dist_quality"] = number_format($row['tasDistQuality'],3);
$tsinfo["time_quality"] = number_format($row['tasTimeQuality'],3);
$tsinfo["launch_quality"] = number_format($row['tasLaunchQuality'],3);
$tsinfo["stop_quality"] = number_format($row['tasStopQuality'],3);
$tsinfo["comment"] = $row['tasComment'];
$tsinfo["offset"] = $row['comTOffset'];
$tsinfo["hbess"] = $row['tasHeightBonus'];
$tsinfo["waypoints"] = $waypoints;

$row = get_comformula($link, $comPk);
if ($row)
{
    $row['comDateFrom'] = substr($row['comDateFrom'],0,10);
    $row['comDateTo'] = substr($row['comDateTo'],0,10);
    $row['TotalValidity'] = round($row['TotalValidity']*1000,0);
    $compinfo = $row;
}
$comType = $compinfo['comType'];
$civilised = get_task_team($link,$comPk,$tasPk,$compinfo);

$data = [ 'compinfo' => $compinfo, 'task' => $tsinfo, 'data' => $civilised ];
print json_encode($data);
?>
