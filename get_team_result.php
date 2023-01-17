<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';
require_once 'get_olc.php';

function taskcmp($a, $b)
{
    if ($a['tname'] == $b['tname']) 
    {
        return 0;
    }
    return ($a['tname'] < $b['tname']) ? -1 : 1;
}

function team_comp_result($link, $comPk, $how, $param)
{
    $sql = "select TK.*,TR.*,P.* from tblTeamResult TR, tblTask TK, tblTeam P, tblCompetition C where C.comPk=$comPk and TR.tasPk=TK.tasPk and TK.comPk=C.comPk and P.teaPk=TR.teaPk order by P.teaPk, TK.tasPk";
    $result = mysql_query($sql, $link) or json_die('Task result query failed: ' . mysql_error());
    $results = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $score = round($row['terScore']);
        $validity = $row['tasQuality'] * 1000;
        $pilPk = $row['teaPk'];
        $tasName = $row['tasName'];
    
        if (!$results[$pilPk])
        {
            $results[$pilPk] = [];
            $results[$pilPk]['name'] = $row['teaName'];
        }
        $perf = 0;
        if ($how == 'ftv') 
        {
            $perf = 0;
            if ($validity > 0)
            {
                $perf = round($score / $validity, 3) * 1000;
            }
        }
        else
        {
            $perf = round($score, 0);
        }
        $results[$pilPk]["${perf}${tasName}"] = array('score' => $score, 'validity' => $validity, 'tname' => $tasName);
    }
    return $results;

    // Do the scoring totals (FTV/X or Y tasks etc)
    $sorted = [];
    foreach ($results as $pil => $arr)
    {
        krsort($arr, SORT_NUMERIC);

        $pilscore = 0;
        if ($how != 'ftv')
        {
            # Max rounds scoring
            $count = 0;
            foreach ($arr as $perf => $taskresult)
            {
                if ($perf == 'name') 
                {
                    continue;
                }
                if ($count < $param)
                {
                    $arr[$perf]['perc'] = 100;
                    $pilscore = $pilscore + $taskresult['score'];
                }
                else
                {
                    $arr[$perf]['perc'] = 0;
                }
                $count++;
                
            }
        }
        else
        {
            # FTV scoring
            $pilvalid = 0;
            foreach ($arr as $perf => $taskresult)
            {
                if ($perf == 'name') 
                {
                    continue;
                }

                if ($pilvalid < $param)
                {
                    $gap = $param - $pilvalid;
                    $perc = 0;
                    if ($taskresult['validity'] > 0)
                    {
                        $perc = $gap / $taskresult['validity'];
                    }
                    if ($perc > 1)
                    {
                        $perc = 1;
                    }
                    $pilvalid = $pilvalid + $taskresult['validity'] * $perc;
                    $pilscore = $pilscore + $taskresult['score'] * $perc;
                    $arr[$perf]['perc'] = $perc * 100;
                }
            }   
        }
        // resort arr by task?
        uasort($arr, "taskcmp");
        foreach ($arr as $key => $res)
        {
            if ($key != 'name')
            {
                $arr[$res['tname']] = $res;
                unset($arr[$key]);
            }
        }
        $pilscore = round($pilscore,0);
        $sorted["${pilscore}!${pil}"] = $arr;
    }

    krsort($sorted, SORT_NUMERIC);
    return $sorted;
}

function team_agg_result($link, $comPk, $teamsize)
{
    $query = "select TM.teaPk,TK.tasPk,TK.tasName,TM.teaName,P.pilLastName,P.pilFirstName,P.pilPk,TR.tarScore*TP.tepModifier as tepscore from tblTaskResult TR, tblTask TK, tblTrack K, tblPilot P, tblTeam TM, tblTeamPilot TP, tblCompetition C where TP.teaPk=TM.teaPk and P.pilPk=TP.pilPk and C.comPk=TK.comPk and K.traPk=TR.traPk and K.pilPk=P.pilPk and TR.tasPk=TK.tasPk and TM.comPk=C.comPk and C.comPk=$comPk order by TM.teaPk,TK.tasPk,TR.tarScore*TP.tepModifier desc";
    $result = mysql_query($query, $link) or json_die('Team aggregate query failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $htable = [];
    $hres = [];
    $sorted = [];
    $teaPk = 0;
    $tasPk = 0;
    $tastot = 0;
    $total = 0;
    $size = 0;
    while ($row)
    {
        //$tasName = $row['tasName'];
        if ($tasPk != $row['tasPk'])
        {
            if ($size != 0)
            {
                $arr["${tasName}"] = array('score' => round($tastotal,0), 'perc' => 100, 'tname' => $tasName);
            }
            $tasName = $row['tasName'];
            $size = 0;
            $tastotal = 0;
            $tasPk = $row['tasPk'];
            //$arr = [];
        }
        if ($teaPk != $row['teaPk'])
        {
            if ($teaPk == 0)
            {
                $teaPk = $row['teaPk'];
                $tasPk = $tow['tasPk'];
                $arr = [];
                $arr['name'] = $row['teaName'];
            }
            else
            {
                // wrap up last one
                $total = round($total,0);
                $sorted["${total}!${teaPk}"] = $arr;
                $tastotal = 0;
                $total = 0;
                $size = 0;
                $arr = [];
                $arr['name'] = $row['teaName'];
                $teaPk = $row['teaPk'];
            }
        }

        if ($size < $teamsize)
        {
            if ($row['tepscore'] > 1000)
            {
                $row['tepscore'] = 1000;
            }
            $total = round($total + $row['tepscore'],2);
            $tastotal = round($tastotal + $row['tepscore'],2);
            $size = $size + 1;
        }
        $row = mysql_fetch_array($result, MYSQL_ASSOC);
    }

    // wrap up last one
    $total = round($total,0);
    $arr["${tasName}"] = array('score' => round($tastotal,0), 'perc' => 100, 'tname' => $tasName);
    $sorted["${total}!${teaPk}"] = $arr;

    krsort($sorted, SORT_NUMERIC);
    return $sorted;
}

# find each task details
function team_result($link,$comPk,$comi)
{
    $alltasks = [];
    $taskinfo = [];
    $sorted = [];
    $clean = [];
    $alltasks = [];
    $query = "select T.* from tblTask T where T.comPk=$comPk order by T.tasDate";
    $result = mysql_query($query, $link) or json_die('Task query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $alltasks[] = $row['tasName'];
        $taskinfo[] = $row;
    }

    if (sizeof($alltasks) > 0)
    {
        if ($comi['comTeamScoring'] == "aggregate")
        {
            $sorted = team_agg_result($link, $comPk, $comi['comTeamSize']);
        }
        else
        {
            $sorted = team_comp_result($link, $comPk, $comi['comOverall'], $comi['comOverallParam']);
        }
    
        $count = 1;
        $rtable = [];
        foreach ($sorted as $pil => $arr)
        {
            $rtable[] = $count;
            $rtable[] = $arr['name'];
            $tot = 0 + $pil;
            $rtable[] = "<b>$tot</b>";
            $taskcount = 0;
            foreach ($alltasks as $num => $name)
            {
                $taskcount++;
                $score = $arr[$name]['score'];
                $perc = round($arr[$name]['perc'], 0);
                if (!$score)
                {
                    $score = 0;
                }
                if ($perc == 100)
                {
                    $rtable[] = $score;
                }
                else if ($perc > 0)
                {
                    $rtable[] = "$score $perc%";
                }
                else
                {
                    $rtable[] = "<s>$score</s>";
                }
            }
            for (; $taskcount < 16; $taskcount++)
            {
                $rtable[] = '';
            }
            $count++;
            $clean[] = $rtable;
            $rtable = [];
        }
    }

    return $clean;
}

// comp & formula info
$comPk = reqival('comPk');
$start = reqival('start');
$link = db_connect();

if ($start < 0)
{
    $start = 0;
}

$compinfo = [];
$sorted = [];
$civilised = [];
$row = get_comformula($link, $comPk);
if ($row)
{
    $row['comDateFrom'] = substr($row['comDateFrom'],0,10);
    $row['comDateTo'] = substr($row['comDateTo'],0,10);
    $row['TotalValidity'] = round($row['TotalValidity']*1000,0);
    $compinfo = $row;
}
$comType = $compinfo['comType'];
if ($comType == 'RACE' || $comType == 'Team-RACE' || $comType == 'Route' || $comType == 'RACE-handicap')
{
    # $sorted = team_comp_result($link, $comPk, $fdhv, '', '');
    $civilised = team_result($link,$comPk,$compinfo);
}
else
{
    $civilised = get_olc_result($link, $comPk, $compinfo, '');
    $compinfo['forClass'] = 'OLC';
    # $compinfo['forVersion'] = '';
}


$data = [ 'compinfo' => $compinfo, 'data' => $civilised ];
print json_encode($data);
?>
