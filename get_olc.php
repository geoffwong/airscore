<?php

function dhv2en($dhv)
{
    $res = 'CCC';

    if ($dhv == 'competition')
    {
        $res = 'CCC';
    }
    elseif ($dhv == '2/3')
    {
        $res = 'D';
    }
    elseif ($dhv == '2')
    {
        $res = 'C';
    }
    elseif ($dhv == '1/2')
    {
        $res = 'B';
    }
    elseif ($dhv == "floater")
    {
        $res = 'F';
    }
    elseif ($dhv == "kingpost")
    {
        $res = 'G';
    }
    elseif ($dhv == "open")
    {
        $res = 'H';
    }
    elseif ($dhv == "rigid")
    {
        $res = 'I';
    }

    return $res;
}

function olc_sort($result,$info)
{
    $lastpil = 0;
    $topscores = [];
    $toptasks = [];
    $top = $info['comOverallParam'];

    // fetch the rows from the db
    while ($row = mysql_fetch_array($result,MYSQL_ASSOC))
    {
        // another pilot .. finish it off
        $pilPk = $row['pilPk'];
        if (!array_key_exists($pilPk, $toptasks))
        {
            $toptasks[$pilPk] = [];
        }
        array_push($toptasks[$pilPk], $row);
    }

    // do the totals ...
    foreach ($toptasks as $pilPk => $scores)
    {
        // cut to max ..
        if ($top != 0)
        {
            $scores = array_slice($scores,0,$top);
        }
        $total = 0;
        foreach ($scores as $row)
        {
            $total = $total + $row['adjScore'];
            $first = utf8_encode($row['pilFirstName']);
            $last = utf8_encode($row['pilLastName']);
        }

        $total = "$total" . $last;
        $topscores[$total] = [
                    'total' => $total,
                    'tasks' => $scores,
                    'pilpk' => $pilPk,
                    'nation' => $row['pilNationCode'],
                    'gender' => $row['pilSex'],
                    'firstname' => $first,
                    'lastname' => $last,
                    'dhv' => dhv2en($row['traDHV'])
            ];
    }

    krsort($topscores, SORT_NUMERIC);

    return $topscores;
}
function olc_result($link,$comPk,$info,$restrict)
{
    $sql = "SELECT P.*, T.traPk, T.traScore as adjScore, T.traDHV FROM tblTrack T, tblPilot P, tblComTaskTrack CTT where CTT.comPk=$comPk and CTT.traPk=T.traPk and T.pilPk=P.pilPk and T.traScore is not null $restrict order by P.pilPk, T.traScore desc";
    $result = mysql_query($sql,$link) or die('olc_result: ' . mysql_error());

    return olc_sort($result,$info);
}
function olc_handicap_result($link,$info,$restrict)
{
    $sql = "SELECT P.*, T.traPk, (T.traScore * H.hanHandicap) as adjScore FROM 
                tblTrack T, tblPilot P, tblComTaskTrack CTT, 
                tblCompetition C, tblHandicap H
            where 
                H.comPk=C.comPk and H.pilPk=P.pilPk and
                CTT.comPk=C.comPk and CTT.traPk=T.traPk and T.pilPk=P.pilPk 
                and T.traScore is not null $restrict 
            order by P.pilPk, T.traScore desc";
    $result = mysql_query($sql,$link) or die('Top score: ' . mysql_error());

    return olc_sort($result,$info);
}
function get_olc_result($link, $comPk, $info, $restrict)
{
    $sorted = olc_result($link, $comPk, $info, $restrict);

    $count = 1;
    $lastcount = 1;
    $lasttot = -1;
    $rtable = [];
    foreach ($sorted as $pil => $arr)
    {
        $nxt = [];
        $tot = 0 + $pil;
        if ($tot != $lasttot)
        {
            $nxt[] = $count;
            $nxt[] = ''; //$arr['hgfa'];
            $nxt[] = ''; //$arr['civl'];
            $nxt[] = "<a href=\"pilot.html?pilPk=" . $arr['pilpk'] . "\">" . $arr['firstname'] . ' ' . $arr['lastname'] . "</a>";
            $lastcount = $count;
        }
        else
        {
            $nxt[] = $lastcount; 
            $nxt[] = ''; //$arr['hgfa'];
            $nxt[] = ''; //$arr['civl'];
            $nxt[] = "<a href=\"pilot.html?pilPk=" . $arr['pilpk'] . "\">" . $arr['firstname'] . ' ' . $arr['lastname'] . "</a>";
        }
        $nxt[] = $arr['nation'];
        $nxt[] = $arr['gender'];
        $nxt[] = ''; //$arr['sponsor'];
        $nxt[] = ''; //$arr['glider'];
        $nxt[] = $arr['dhv'];
        if ($info['forVersion'] == 'airgain-count')
        {
            $nxt[] = "<a href=\"airgain_map.html?comPk=$comPk&pilPk=" . $arr['pilpk'] . "\">" . round(0+$arr['total']/1000, 1) . "</a>";
        }
        else
        {
            $nxt[] = "<b>" . round(0+$arr['total']/1000, 1) . "</b>";
        }
        
        $taskcount = 0;

        foreach ($arr['tasks'] as $task)
        { 
            $score = round($task['adjScore']/1000,1);
            $id = $task['traPk'];
            $nxt[] = "<a href=\"tracklog_map.html?comPk=$comPk&trackid=$id\">$score</a>";
            $taskcount++;
        }

        for (; $taskcount < 16; $taskcount++)
        {
            $nxt[] = '';
        }
        $rtable[] = $nxt;
        $count++;
    }

    return $rtable;
}
function display_olc_result($comPk, $rtable, $sorted, $top, $count)
{
    $rdec = [];
    $rdec[] = 'class="h"';
    $rdec[] = 'class="h"';

    foreach ($sorted as $total => $row)
    {
        $nxt = [];
        if ($count % 2)
        {
            $rdec[] = 'class="d"';
        }
        else
        {
            $rdec[] = 'class="l"';
        }
        $name = $row['firstname'] . " " . $row['lastname'];
        $key = $row['pilpk'];
        $total = round($total/1000,0);
        $nxt[] = $count;
        $nxt[] = "<a href=\"pilot.php?pil=$key\">$name</a>";
        $nxt[] = "<b>$total</b>";
        foreach ($row['tasks'] as $task)
        {
            $score = round($task['adjScore']/1000,1);
            $id = $task['traPk'];
            $nxt[] = "<a href=\"tracklog_map.php?comPk=$comPk&trackid=$id\">$score</a>";
        }
        $count++;
        if ($count >= $top + $start + 2) 
        {
            break;
        }
        $rtable[] = $nxt;
    }
    echo ftable($rtable, "class=\"olc\" alternate-colours=\"yes\" align=\"center\"", $rdec, '');

    return $count;
}
function olc_team_result($link,$top,$restrict)
{
    $sql = "SELECT M.teaPk as pilPk, M.teaName as pilFirstName, T.traPk, T.traScore as adjScore FROM tblTrack T, tblComTaskTrack CTT, tblCompetition C, tblTeam M, tblTeamPilot TP, tblPilot P where M.comPk=C.comPk and TP.teaPk=M.teaPk and P.pilPk=TP.pilPk and CTT.comPk=C.comPk and CTT.traPk=T.traPk and T.pilPk=P.pilPk and T.traScore is not null $restrict order by M.teaPk, T.traScore desc";
    $result = mysql_query($sql,$link) or die('olc_result: ' . mysql_error());

    return olc_sort($result,$top);
}
?>
