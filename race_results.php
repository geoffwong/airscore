<?php

function taskcmp($a, $b)
{
    if (!is_array($a)) return 0;
    if (!is_array($b)) return 0;

    if ($a['tname'] == $b['tname']) 
    {
        return 0;
    }
    return ($a['tname'] < $b['tname']) ? -1 : 1;
}

function comp_result($comPk, $cls)
{
    $sql = "select C.* from tblCompetition C where C.comPk=$comPk";
    $result = mysql_query($sql) or json_die('Comp info query failed: ' . mysql_error());
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $how = $row['comOverallScore'];
        $param = $row['comOverallParam'];
    }

    $sql = "select sum(tasQuality) as totValidity, count(*) as tasTotal from tblTask where comPk=$comPk";
    $result = mysql_query($sql) or json_die('Task validity query failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $totalvalidity = round($row['totValidity'] * $param * 10,0);
    $tasktot = $row['tasTotal'];

    if ($how == 'all')
    {
        # total # of tasks
        $param = $tasktot;
    }
    else if ($how == 'round-perc')
    {
        $param = round($tasktot * $comOverallParam / 100, 0);
    }
    else if ($how == 'ftv')
    {
        $param = $totalvalidity;
        if ($tasktot < 2)
        {
            $param = 1000;
        }   

    }

    $results = extract_comp_results($comPk, $how, $param, $cls);
    $structured_result = filter_results($comPk, $how, $param, $results);
    return $structured_result;
}

function extract_comp_results($comPk, $how, $param, $cls)
{

    $sql = "select TK.*,TR.*,P.*,T.traGlider,T.traDHV from tblTaskResult TR, tblTask TK, tblTrack T, tblPilot P, tblCompetition C where C.comPk=$comPk and TK.comPk=C.comPk and TK.tasPk=TR.tasPk and TR.traPk=T.traPk and T.traPk=TR.traPk and P.pilPk=T.pilPk $cls order by P.pilPk, TK.tasPk";
    $result = mysql_query($sql) or json_die('Task result query failed: ' . mysql_error());
    $results = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $score = round($row['tarScore']);
        $validity = $row['tasQuality'] * 1000;
        $pilPk = $row['pilPk'];
        $tasName = $row['tasName'];
        $nation = $row['pilNationCode'];
        $pilnum = $row['pilHGFA'];
        $civlnum = $row['pilCIVL'];
        $glider = $row['traGlider'];
        $dhv = $row['traDHV'];
        $gender = $row['pilSex'];
        $tasPk = 0 + $row['tasPk'];
    
        if (!array_key_exists($pilPk,$results) || !$results[$pilPk])
        {
            $results[$pilPk] = [];
            $results[$pilPk]['name'] = utf8_encode($row['pilFirstName'] . ' ' . $row['pilLastName']);
            $results[$pilPk]['hgfa'] = $pilnum;
            $results[$pilPk]['civl'] = $civlnum;
            $results[$pilPk]['nation'] = $nation;
            $results[$pilPk]['glider'] = utf8_encode(htmlspecialchars($glider));
            $results[$pilPk]['dhv'] = dhv2en($dhv);
            $results[$pilPk]['gender'] = $gender;
            $results[$pilPk]['tasks'] = [];
        }
        #echo "pilPk=$pilPk tasname=$tasName, result=$score<br>\n";
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
        
        $taskid = sprintf("T%06d", $tasPk);
        $results[$pilPk]['tasks']["${perf}${taskid}"] = [ 'score' => $score, 'validity' => $validity, 'tname' => $tasName, 'id' => $tasPk ];
    }

    return $results;
}

function filter_results($comPk, $how, $param, $results)
{
    // Do the scoring totals (FTV/X or Y tasks etc)
    $sorted = [];
    foreach ($results as $pil => $arr)
    {
        krsort($arr['tasks'], SORT_NUMERIC);

        $pilscore = 0;
        if ($how != 'ftv')
        {
            # Max rounds scoring
            $count = 0;
            foreach ($arr['tasks'] as $perf => $taskresult)
            {
                if ($how == 'all' || $count < $param)
                {
                    $arr['tasks'][$perf]['perc'] = 100;
                    $pilscore = $pilscore + $taskresult['score'];
                }
                else
                {
                    $arr['tasks'][$perf]['perc'] = 0;
                }
                $count++;
                
            }
        }
        else
        {
            # FTV scoring
            $pilvalid = 0;
            foreach ($arr['tasks'] as $perf => $taskresult)
            {
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
                    //echo "pil=$pil valid=$pilvalid cumscore=$pilscore perf=$perf perc=$perc valid=", $taskresult['validity'], " score=", $taskresult['score'], "<br>\n";
                    $arr['tasks'][$perf]['perc'] = $perc * 100;
                }
                else
                {
                    $arr['tasks'][$perf]['perc'] = 0;
                }
            }   
        }
        // resort arr by task?
        uasort($arr['tasks'], "taskcmp");
        #echo "pil=$pil pilscore=$pilscore\n";
        foreach ($arr['tasks'] as $key => $res)
        {
            #echo "key=$key<br>";
            #if ($key != 'name')
            if (ctype_digit(substr($key,0,1)))
            {
                $arr['tasks'][$res['tname']] = $res;
                unset($arr['tasks'][$key]);
            }
        }
        $pilscore = round($pilscore,0);
        $sorted["${pilscore}!${pil}"] = $arr;
    }

    krsort($sorted, SORT_NUMERIC);
    return $sorted;
}

# <th>Name</th> <th>NAT</th> <th>Score</th> <th>Gender</th> <th>Birthdate</th> <th>FAI License</th> <th>Glider</th> <th>Sponsor</th> <th>CIVL ID</th>
function civl_result($tasks, $sorted)
{
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
            $nxt[] = $arr['hgfa'];
            $nxt[] = $arr['civl'];
            $nxt[] = $arr['name'];
            $lastcount = $count;
            $lasttot = $tot;
        }
        else
        {
            $nxt[] = $lastcount; 
            $nxt[] = $arr['hgfa'];
            $nxt[] = $arr['civl'];
            $nxt[] = $arr['name'];
        }
        $nxt[] = $arr['nation'];
        $nxt[] = $arr['gender'];
        $nxt[] = ''; //$arr['sponsor'];
        $nxt[] = $arr['glider'];
        $nxt[] = $arr['dhv'];
        $nxt[] = "<b>$tot</b>";
        
        $taskcount = 0;

        foreach ($tasks as $num => $name)
        { 
            $score = 0;
            $perc = 100;

            if (array_key_exists($name, $arr['tasks']))
            {
                $score = $arr['tasks'][$name]['score'];
                if (array_key_exists('perc', $arr['tasks'][$name]))
                {
                    $perc = round($arr['tasks'][$name]['perc'], 0);
                }
            }
            if (!$score)
            {
                $score = 0;
            }

            if ($perc == 100)
            {
                $nxt[] = $score;
            }
            else if ($perc > 0)
            {
                $nxt[] = "$score <small>$perc%</small>";
            }
            else
            {
                $nxt[] = "<del>$score</del>";
            }

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

function comp_hgfa_result($comPk)
{
    $count = 1;
    $lastcount = 1;
    $lasttot = -1;
    $rtable = [];

    $sorted = comp_result($comPk, '');

    foreach ($sorted as $pil => $arr)
    {
        $nxt = [];
        $tot = $pil;
        if ($tot != $lasttot)
        {
            $nxt[] = $count;
            $hgfa = $arr['hgfa'];
            $nxt[] = $arr['name'];
            $lastcount = $count;
            $lasttot = $tot;
        }
        else
        {
            $nxt[] = $lastcount; 
            $nxt[] = $arr['name'];
        }
        $nxt[] = 0 + $tot;
        
        $taskcount = 0;
        $rtable[$hgfa] = $nxt;
        $count++;
    }

    return $rtable;
}

?>

