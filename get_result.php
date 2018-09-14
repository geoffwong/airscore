<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';
require_once 'get_olc.php';

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
    $result = mysql_query($sql) or die('Comp info query failed: ' . mysql_error());
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $how = $row['comOverallScore'];
        $param = $row['comOverallParam'];
    }

    if ($how == 'all')
    {
        # total # of tasks
        $param = $tasTotal;
    }
    else if ($how == 'round-perc')
    {
        $param = round($tasTotal * $comOverallParam / 100, 0);
    }
    else if ($how == 'ftv')
    {
        $sql = "select sum(tasQuality) as totValidity, count(*) as tasTotal from tblTask where comPk=$comPk";
        $result = mysql_query($sql) or die('Task validity query failed: ' . mysql_error());
        $totalvalidity = round(mysql_result($result, 0, 0) * $param * 10,0);
        $tasktot = mysql_result($result, 0, 1);
        $param = $totalvalidity;
        if ($tasktot < 2)
        {
            $param = 1000;
        }   

    }

    $sql = "select TK.*,TR.*,P.*,T.traGlider from tblTaskResult TR, tblTask TK, tblTrack T, tblPilot P, tblCompetition C where C.comPk=$comPk and TK.comPk=C.comPk and TK.tasPk=TR.tasPk and TR.traPk=T.traPk and T.traPk=TR.traPk and P.pilPk=T.pilPk $cls order by P.pilPk, TK.tasPk";
    $result = mysql_query($sql) or die('Task result query failed: ' . mysql_error());
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
        $gender = $row['pilSex'];
    
        if (!array_key_exists($pilPk,$results) || !$results[$pilPk])
        {
            $results[$pilPk] = [];
            $results[$pilPk]['name'] = $row['pilFirstName'] . ' ' . $row['pilLastName'];
            $results[$pilPk]['hgfa'] = $pilnum;
            $results[$pilPk]['civl'] = $civlnum;
            $results[$pilPk]['nation'] = $nation;
            $results[$pilPk]['glider'] = $glider;
            $results[$pilPk]['gender'] = $gender;
            $results[$pilPk]['tasks'] = [];
        }
        //echo "pilPk=$pilPk tasname=$tasName, result=$score<br>\n";
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
        $results[$pilPk]['tasks']["${perf}${tasName}"] = [ 'score' => $score, 'validity' => $validity, 'tname' => $tasName ];
    }

    return filter_results($comPk, $how, $param, $results);
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
                //echo "pil=$pil perf=$perf valid=", $taskresult['validity'], " score=", $taskresult['score'], "<br>";
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
                    $arr['tasks'][$perf]['perc'] = $perc * 100;
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
        $nxt[] = $arr['sponsor'];
        $nxt[] = $arr['glider'];
        $nxt[] = "<b>$tot</b>";
        
        $taskcount = 0;

        foreach ($tasks as $num => $name)
        { 
            $score = 0;
            $perc = 100;

            if (array_key_exists($name, $arr['tasks']))
            {
                $score = $arr['tasks'][$name]['score'];
                $perc = round($arr['tasks'][$name]['perc'], 0);
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

$link = db_connect();
$comPk = reqival('comPk');
$class = reqival('class');
$carr = [];

// comp & formula info
$compinfo = [];
$row = get_comformula($link, $comPk);
if ($row)
{
    $row['comDateFrom'] = substr($row['comDateFrom'],0,10);
    $row['comDateTo'] = substr($row['comDateTo'],0,10);
    $row['TotalValidity'] = round($row['TotalValidity']*1000,0);
    $compinfo = $row;
}

$fdhv= '';
if ($class > 0)
{
    if ($compinfo['comClass'] == "HG")
    {
        $carr = array ( "'floater'", "'kingpost'", "'open'", "'rigid'"       );
    }
    else
    {
        $carr = array ( "'1/2'", "'2'", "'2/3'", "'competition'"       );
    }
    $classstr = "<b>" . $cstr[reqival('class')] . "</b> - ";
    if ($cval == 4)
    {
        $fdhv = "and P.pilSex='F'";
    }
    else if ($cval == 5)
    {
        $fdhv = "and P.pilBirthdate < date_sub(C.comDateFrom, INTERVAL 50 YEAR)"; 
    }
    else if ($cval == 6)
    {
        $fdhv = "and P.pilBirthdate > date_sub(C.comDateFrom, INTERVAL 35 YEAR)";
    }
    else if ($cval == 9)
    {
        $fdhv = '';
    }
    else
    {
        $fdhv = $carr[$class];
        $fdhv = "and T.traDHV<=$fdhv ";
    }
}

$comType=$compinfo['comType'];
if ($comType == 'RACE' || $comType == 'Team-RACE' || $comType == 'Route' || $comType == 'RACE-handicap')
{
    $query = "select T.* from tblTask T where T.comPk=$comPk order by T.tasDate";
    $result = mysql_query($query, $link) or die('Task query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $alltasks[] = $row['tasName'];
    }

    $sorted = comp_result($comPk, $fdhv);
    $civilised = civl_result($alltasks, $sorted);
}
else
{
    $civilised = get_olc_result($link, $comPk, $compinfo['comOverallParam'], '');
    $compinfo['forClass'] = 'OLC'; 
    $compinfo['forVersion'] = ''; 
}


$data = [ 'compinfo' => $compinfo, 'data' => $civilised ];
print json_encode($data);
?>
