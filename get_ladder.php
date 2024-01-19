<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'format.php';
require_once 'dbextra.php';
require 'hc.php';

function included_comps($link,$ladPk)
{
    if ($ladPk > 0)
    {
        $sql = "select C.comPk, C.comName, LC.lcValue from tblLadderComp LC, tblCompetition C where LC.comPk=C.comPk and ladPk=$ladPk order by LC.lcValue desc, comDateTo";
    }
    else
    {
        $sql = "select distinct C.comPk, C.comName, LC.lcValue from tblLadderComp LC, tblCompetition C where LC.comPk=C.comPk and LC.lcValue > 0 order by comDateTo desc";
    }
    $result = mysql_query($sql,$link);
    $comps = [];
    while($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        // FIX: if not finished & no tracks then submit_track page ..
        // FIX: if finished no tracks don't list!
        $comps[] = $row;
    }
    return $comps;
}
function taskcmp($a, $b)
{
    if (!is_array($a)) return 0;
    if (!is_array($b)) return 0;

    if ($a['name'] == $b['name']) 
    {
        return 0;
    }
    return ($a['name'] < $b['name']) ? -1 : 1;
}

function add_result(&$results, $row, $topnat, $how, $ladPk)
{
    if (!$topnat)
    {
        $topnat = 1000;
    }
    $score = round($row['ladScore'] / $topnat);
    $validity = $row['tasQuality'] * 1000;
    $pilPk = $row['pilPk'];
    // $row['tasName'];
    $tasName = substr($row['comName'], 0, 5) . ' ' . substr($row['comDateTo'],0,4);
    $fullName = substr($row['comName'], 0, 3) . substr($row['comDateTo'],2,2) . '&nbsp;' .  substr(str_replace(' ', '', $row['tasName']), 0, 2);

    if (!array_key_exists($pilPk,$results) || !$results[$pilPk])
    {
        $results[$pilPk] = [];
        $results[$pilPk]['name'] = "<a href=\"comp_pilot.html?pilPk=$pilPk&ladPk=$ladPk\">" . utf8_decode($row['pilFirstName'] . ' ' . $row['pilLastName']) . "</a>";
        $results[$pilPk]['hgfa'] = $row['pilHGFA'];
        $results[$pilPk]['scores'] = [];
        //$results[$pilPk]['civl'] = $civlnum;
    }
    $perf = 0;
    if ($how == 'ftv' or $how == 'ftv-fixed') 
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

    if ($perf >= 1)
    {
        $results[$pilPk]['scores']["${perf}${fullName}"] = [ 'score' => $score, 'validity' => $validity, 'name' => $fullName, 'taspk' => $row['tasPk'], 'compk' => $row['comPk'], 'extpk' => 0 + $row['extPk'] ];
        #$results[$pilPk]['scores'][] = [ $score, $validity, $fullName, $row['tasPk'], 0 + $row['extPk'] ];
    }

    return "${perf}${fullName}";
}

function ladder_result($ladPk, $ladder, $restrict, $altval)
{
    $class = $ladder['ladClass'];
    $start = $ladder['ladStart'];
    $end = $ladder['ladEnd'];
    $how = $ladder['ladHow'];
    $nat = $ladder['ladNationCode'];
    $ladParam = $ladder['ladParam'];

    if ($end == "" or $end == "null")
    {
        $end = "CURDATE()";
    }
    else
    {
        $end = "'$end'";
    }

    $topnat = [];
    $sql = "select T.tasPk, max(T.tarScore) as topNat 
            from tblTaskResult T, tblTrack TL, tblPilot P
            where T.traPk=TL.traPk and TL.pilPk=P.pilPk and P.pilNationCode='$nat'
            group by tasPk";
    $result = mysql_query($sql) or json_die('Top National Query: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $topnat[$row['tasPk']] = $row['topNat'];
    }

    // Select from the main database of results
    //TR.tarScore * LC.lcValue * power(L.ladDepreciation, TIMESTAMPDIFF(YEAR, '$end', CURDATE())) * TK.tasQuality as ladScore,
    $sql = "select 0 as extPk, TR.tarScore,
        TP.pilPk, TP.pilLastName, TP.pilFirstName, TP.pilNationCode, TP.pilHGFA, TP.pilSex,
        TK.tasPk, TK.tasName, TK.tasDate, TK.tasQuality, 
        C.comPk, C.comName, C.comDateTo, LC.lcValue, 
        TR.tarScore * LC.lcValue * power(L.ladDepreciation, TIMESTAMPDIFF(YEAR, C.comDateTo, $end)) * TK.tasQuality as ladScore,
        (TR.tarScore * LC.lcValue * power(L.ladDepreciation, TIMESTAMPDIFF(YEAR, C.comDateTo, $end)) / (TK.tasQuality * LC.lcValue)) as validity
from    tblLadderComp LC 
        join tblLadder L on L.ladPk=LC.ladPk
        join tblCompetition C on LC.comPk=C.comPk
        join tblTask TK on C.comPk=TK.comPk
        join tblTaskResult TR on TR.tasPk=TK.tasPk
        join tblTrack TT on TT.traPk=TR.traPk
        join tblPilot TP on TP.pilPk=TT.pilPk
WHERE LC.ladPk=$ladPk and TK.tasDate > '$start' and TK.tasDate <= $end
        $restrict
    and TP.pilNationCode=L.ladNationCode 
    order by TP.pilPk, C.comPk, (TR.tarScore * LC.lcValue * TK.tasQuality) desc";

    $result = mysql_query($sql) or json_die('Ladder query failed: ' . mysql_error());
    $results = [];

    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        add_result($results, $row, $topnat[$row['tasPk']], $how, $ladPk);
    }

    // Work out how much validity we want (not really generic)

    if ($how == 'ftv')
    {
        $sql = "select sum(tasQuality)*1000 from tblLadderComp LC 
        join tblLadder L on L.ladPk=LC.ladPk and LC.lcValue=450
        join tblCompetition C on LC.comPk=C.comPk
        join tblTask TK on C.comPk=TK.comPk
        WHERE LC.ladPk=$ladPk and TK.tasDate > '$start' and TK.tasDate <= $end";

        $result = mysql_query($sql) or json_die('Total quality query failed: ' . mysql_error());
        $param = mysql_result($result,0,0) * $ladParam / 100 ;
    }
    else 
    {
        $param = $ladParam;
    }
    if ($altval > 0)
    {
        $param = $altval;
    }

    // Add external task results (to 1/3 of validity)
    if ($ladder['ladIncExternal'] > 0)
    {
        $sql = "select TK.extPk, TK.extURL as tasPk,
        TP.pilPk, TP.pilLastName, TP.pilFirstName, TP.pilNationCode, TP.pilHGFA, TP.pilSex,
        TK.tasName, TK.tasQuality, TK.comName, TK.comDateTo, TK.lcValue, TK.tasTopScore,
        ER.etrScore * TK.lcValue * power(TK.extDepreciation, TIMESTAMPDIFF(YEAR, TK.comDateTo, $end)) * TK.tasQuality as ladScore,
        (ER.etrScore * TK.lcValue * power(TK.extDepreciation, TIMESTAMPDIFF(YEAR, TK.comDateTo, $end)) / (TK.tasQuality * TK.lcValue)) as validity
        from tblExtTask TK
        join tblExtResult ER on ER.extPk=TK.extPk
        join tblPilot TP on TP.pilPk=ER.pilPk
        WHERE TK.extClass='$class' and TK.comDateTo > '$start' and TK.comDateTo < $end
        $restrict
        order by TP.pilPk, TK.extPk, (ER.etrScore * TK.lcValue * TK.tasQuality) desc";
        $result = mysql_query($sql) or json_die('Ladder query failed: ' . mysql_error());
        while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
        {
            $xres = add_result($results, $row, $row['tasTopScore'], $how, $ladPk);
        }

        $filtered = filter_results($ladPk, $how, $param, $param * $ladder['ladIncExternal'] / 100, $ladder['ladTaskLimit'], $results);
    }
    else
    {
        $filtered = filter_results($ladPk, $how, $param, 0, $ladder['ladTaskLimit'], $results);
    }

    $res = [];
    $res['filtered'] = $filtered;
    $res['validity'] = $param;
    $res['sql'] = $filtered;

    return $res;
}

function filter_results($ladPk, $how, $param, $extpar, $tasklimit, $results)
{
    // Do the scoring totals (FTV/X or Y tasks etc)
    $sorted = [];
    foreach ($results as $pil => $arr)
    {
        krsort($arr['scores'], SORT_NUMERIC);

        $pilscore = 0;
        if ($how == 'fixed' || $how == 'comp')
        {
            # Max rounds scoring
            $count = 0;
            foreach ($arr['scores'] as $perf => $taskresult)
            {
                if ($count < $param)
                {
                    $arr['scores'][$perf]['perc'] = 100;
                    $pilscore = $pilscore + $taskresult['score'];
                }
                else
                {
                    $arr['scores'][$perf]['perc'] = 0;
                }
                $count++;
                
            }
        }
        else
        {
            # FTV scoring
            $lastcompk = 0;
            $pilvalid = 0;
            $pilext = 0;
            $comcount = [];
            $compk = 0;
            foreach ($arr['scores'] as $perf => $taskresult)
            {
                $compk = $taskresult['compk'];
                $comcount[$compk] = $comcount[$compk] + 1;
                if ($tasklimit > 0 and $comcount[$compk] > $tasklimit)
                {
                    continue;
                }
                if ($pilvalid < $param)
                {
                    // if external
                    if (0+$taskresult['extpk'] > 0) 
                    {
                        if ($pilext < $extpar)
                        {
                            $gap = $extpar - $pilext;
                            if ($gap > $param - $pilvalid)
                            {
                              $gap = $param - $pilvalid;
                            }
                            $perc = 0;
                            if ($taskresult['validity'] > 0)
                            {
                                $perc = $gap / $taskresult['validity'];
                            }
                            if ($perc > 1)
                            {
                                $perc = 1;
                            }
                            $pilext = $pilext + $taskresult['validity'] * $perc;
                            $pilvalid = $pilvalid + $taskresult['validity'] * $perc;
                            $pilscore = $pilscore + $taskresult['score'] * $perc;
                            $arr['scores'][$perf]['perc'] = $perc * 100;
                        }
                        else
                        {
                            // ignore
                        }
                    }
                    else
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
                        $arr['scores'][$perf]['perc'] = $perc * 100;
                    }
                }
            }   
        }

        foreach ($arr['scores'] as $key => $res)
        {
            #if ($key != 'name')
            if (ctype_digit(substr($key,0,1)))
            {
                $arr['scores'][$res['name']] = $res;
                unset($arr['scores'][$key]);
            }
        }
        $pilscore = round($pilscore,0);
        $arr['total'] = $pilscore;
        $sorted["${pilscore}!${pil}"] = $arr;
        $sorted["99999!${pil}"] = $comcount;
    }
    
    krsort($sorted, SORT_NUMERIC);
    //var_dump($sorted);
    return $sorted;
}


// Clean up the structure into one suitable for jquery datatable display
function datatable_clean($sorted)
{
    $result = [];
    $pos = 1;
    $lastscore = 0;
    $lastpos = 0;
    foreach ($sorted as $row)
    {
        if ($row['total'] < 1) 
        {
            continue;
        }
        $newrow = [];
        if ($row['total'] != $lastscore)
        {
            $newrow[] = $pos;
            $lastpos = $pos;
        }
        else
        {
            $newrow[] = $lastpos;
        }
        $lastscore = $row['total'];
        $newrow[] = $row['name'];
        $newrow[] = $row['hgfa'];
        $newrow[] = '<b>' . $row['total'] . '</b>';

        $count = 0;
        foreach ($row['scores'] as $sarr)
        {
            $score = $sarr['score'];
            $tname = $sarr['name'];
            $tpk = $sarr['taspk'];
            $perc = 0;
            if (array_key_exists('perc', $sarr))
            {
                $perc = round($sarr['perc'], 0);
            }
            if (!$score)
            {
                $score = 0;
            }
            if ($perc == 100)
            {
                if ($tpk > 0)
                {
                    $newrow[] = "<a href=\"task_result.html?tasPk=$tpk\">$tname</a> $score";
                }
                else
                {
                    $newrow[] = "<a href=\"$tpk\">$tname</a> $score";
                }
                $count++;
            }
            else if ($perc > 0)
            {
                if ($tpk > 0)
                {
                    $newrow[] = "<a href=\"task_result.html?tasPk=$tpk\">$tname</a> $score <small>$perc%</small>";
                }
                else
                {
                    $newrow[] = "<a href=\"$tpk\">$tname</a> $score <small>$perc%</small></a>";
                }
                $count++;
            }
        }
        for (; $count < 20; $count++)
        {
            $newrow[] = '';
        }
        $result[] = $newrow;
        $pos++;
    }

    return $result;
}


//
// Main Body Here
//

$ladPk = reqival('ladPk');
$start = reqival('start');
$altval = reqival('validity');
error_log("altval=$altval");
if ($start < 0)
{
    $start = 0;
}
$usePk = check_auth('system');
$link = db_connect();
$isadmin = is_admin('admin',$usePk,-1);
$title = 'highcloud.net';

#$result = mysql_query("charset utf8") or json_die('UTF-8 setting failed: ' . mysql_error());
ini_set("default_charset", 'utf-8');

if (reqexists('addladder'))
{
    check_admin('admin',$usePk,-1);
    $lname = reqsval('lname');
    $nation = reqsval('nation');
    $start = reqsval('sdate');
    $end = reqsval('edate');
    $method = reqsval('method');
    $param = reqival('param');

    $query = "insert into tblLadder (ladName, ladNationCode, ladStart, ladEnd, ladHow, ladParam) value ('$lname','$nation', '$start', '$end', '$method', $param)";
    $result = mysql_query($query) or json_die('Ladder insert failed: ' . mysql_error());
}

if (reqexists('addladcomp'))
{
    check_admin('admin',$usePk,-1);
    $sanction = reqival('sanction');
    $comPk = reqival('comp');

    if ($comPk == 0 || $ladPk == 0)
    {
        echo "Failed: unknown comPk=$comPk ladPk=$ladPk<br>";
    }
    else
    {
        $query = "insert into tblLadderComp (lcValue, ladPk, comPk) value ($sanction, $ladPk, $comPk)";
        $result = mysql_query($query) or json_die('LadderComp insert failed: ' . mysql_error());
    }
}

$fdhv= '';
$classstr = '';
if (reqexists('class'))
{
    $cval = reqival('class');
    if ($comClass == "HG")
    {
        $carr = array ( "'floater'", "'kingpost'", "'open'", "'rigid'"       );
        $cstr = array ( "Floater", "Kingpost", "Open", "Rigid", "Women", "Seniors", "Juniors" );
    }
    else
    {
        $carr = array ( "'1/2'", "'2'", "'2/3'", "'competition'"       );
        $cstr = array ( "Fun", "Sport", "Serial", "Open", "Women", "Seniors", "Juniors" );
    }
    $classstr = "<b>" . $cstr[reqival('class')] . "</b> - ";
    if ($cval == 4)
    {
        $fdhv = "and TP.pilSex='F'";
    }
    else
    {
        $fdhv = $carr[reqival('class')];
        $fdhv = "and TT.traDHV<=$fdhv ";
    }
}

$clean = [];
if ($ladPk < 1)
{
    $query = "SELECT L.* from tblLadder L order by ladEnd desc";
    $result = mysql_query($query) or json_die('Ladder query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        if ($row['ladClass'] == "PG")
        {
            $img = '<img src="images/pg_symbol.png"></img>';
        }
        elseif ($row['ladClass'] == "HG")
        {
            $img = '<img src="images/hg_symbol.png"></img>';
        }

        $param = $row['ladParam'];
        if ($row['ladHow'] == 'ftv')
        {
            $param = $param . '%';
        }
        $clean[] = [ '<a href="ladder_result.html?ladPk=' . $row['ladPk'] . '">' . $row['ladName'] . '</a>', 
            $row['ladNationCode'], $img, $row['ladStart'], $row['ladEnd'], 
            $row['ladHow'] . ' (' . $param . ')' ];
    }
}
else
{
    $query = "SELECT L.* from tblLadder L where ladPk=$ladPk";
    $result = mysql_query($query) or json_die('Ladder query failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    if ($row)
    {
        $ladName = $row['ladName'];
        $title = $ladName;
        $ladder = $row;
    }
}


if ($ladPk > 0)
{
    //output_ladder($ladPk, $ladder, $fdhv, $class);
    $sorted = ladder_result($ladPk, $ladder, $fdhv, $altval);
    if ($ladder['ladClass'] == 'PG' and $ladder['ladIncExternal'] != 0)
    {
        $maxscore = $sorted['validity'] * 0.466;
    }
    else
    {
        $maxscore = $sorted['validity'] * 0.45;
    }


    $ladder['totValidity'] = round($sorted['validity'],0);
    $ladder['maxScore'] = round($maxscore,0);
    $ladder['class'] = $fdhv;
    $ladder['sql'] = $sorted['sql'];
    $included = included_comps($link, $ladPk);
    $clean = datatable_clean($sorted['filtered'], $ladPk);
}


$data = [ 'ladder' => $ladder, 'inc' => $included, 'data' => $clean ];

$msg = json_encode($data, JSON_UNESCAPED_UNICODE);
if ($msg)
{
    echo $msg;
}
else
{
    echo json_last_error_msg();
}
?>
