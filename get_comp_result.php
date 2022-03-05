<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'format.php';
require_once 'olc.php';
require_once 'team.php';
require 'hc.php';

function overall_handicap($comPk, $how, $param, $cls)
{
    $sql = "select T.tasPk, max(TR.tarScore) as maxScore from tblTask T, tblTaskResult TR where T.tasPk=TR.tasPk and T.comPk=$comPk group by T.tasPk";
    $result = mysql_query($sql) or die('Handicap maxscore failed: ' . mysql_error());
    $maxarr = [];
    while ($row = mysql_fetch_array($result))
    {
        $maxarr[$row['tasPk']] = $row['maxScore'];
    }

    $sql = "select P.*,TK.*, TR.*, H.* from tblTaskResult TR, tblTask TK, tblTrack K, tblPilot P, tblHandicap H, tblCompetition C where H.comPk=C.comPk and C.comPk=TK.comPk and K.traPk=TR.traPk and K.pilPk=P.pilPk and H.pilPk=P.pilPk and H.comPk=TK.comPk and TK.comPk=$comPk and TR.tasPk=TK.tasPk order by P.pilPk, TK.tasPk";
    #$sql = "select TK.*,TR.*,P.* from tblTaskResult TR, tblTask TK, tblTrack T, tblPilot P, tblCompetition C where C.comPk=$comPk and TK.comPk=C.comPk and TK.tasPk=TR.tasPk and TR.traPk=T.traPk and T.traPk=TR.traPk and P.pilPk=T.pilPk $cls order by P.pilPk, TK.tasPk";

    $result = mysql_query($sql) or die('Task result query failed: ' . mysql_error());
    $results = [];
    while ($row = mysql_fetch_array($result))
    {
        $tasPk = $row['tasPk'];
        if ($row['tasTaskType'] == 'free-pin')
        {
            $score = round($row['tarScore']);
            $validity = 1000;
        }
        else
        {
            $score = round($row['tarScore'] - $row['hanHandicap'] * $maxarr[$tasPk]);
            $validity = $row['tasQuality'] * 1000;
        }
        if ($row['tarResultType'] == 'abs' || $row['tarResultType'] == 'dnf')
        {
            $score = 0;
        }
        $pilPk = $row['pilPk'];
        $tasName = $row['tasName'];
    
        if (!$results[$pilPk])
        {
            $results[$pilPk] = [];
            $results[$pilPk]['name'] = $row['pilFirstName'] . ' ' . $row['pilLastName'];
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

    return filter_results($comPk, $how, $param, $results);
}

function comp_result($comPk, $how, $param, $cls, $tasktot)
{
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

    if ($how == 'ftv' && $tasktot < 2)
    {
        $param = 1000;
    }

    return filter_results($comPk, $how, $param, $results);
}

function filter_results($comPk, $how, $param, $results)
{
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
                //if ($perf == 'name') 
                if (ctype_alpha($perf))
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
                //if ($perf == 'name') 
                if (ctype_alpha($perf))
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
            #if ($key != 'name')
            if (ctype_digit(substr($key,0,1)))
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


$comPk = reqival('comPk');
$start = reqival('start');
$class = reqsval('class');
if ($start < 0)
{
    $start = 0;
}
$link = db_connect();
$compinfo = [];

$row = get_comformula($comPk);

if ($row)
{
    $row['comDateFrom'] = substr($row['comDateFrom'],0,10);
    $row['comDateTo'] = substr($row['comDateTo'],0,10);
    $comOverall = $row['comOverallScore'];
    $comOverallParam = $row['comOverallParam'];
    $comOverall = $row['comOverallScore'];
    $comTeamScoring = $row['comTeamScoring'];
    $comClass = $row['comClass'];
    $comType = $row['comType'];
    $forDiscreteClasses = $row['forDiscreteClasses'];

    $compinfo = $row;
}

$fdhv= '';
$classstr = '';
if (array_key_exists('class', $_REQUEST))
{
    $cval = intval($_REQUEST['class']);
    if ($comClass == "HG")
    {
        $carr = [ "'floater'", "'kingpost'", "'open'", "'rigid'"       ];
        $cstr = [ "Floater", "Kingpost", "Open", "Rigid", "Women", "Seniors", "Juniors" ];
    }
    else
    {
        $carr = [ "'1/2'", "'2'", "'2/3'", "'competition'"       ];
        $cstr = [ "Fun", "Sport", "Serial", "Open", "Women", "Seniors", "Juniors" ];
    }
    $classstr = "<b>" . $cstr[intval($_REQUEST['class'])] . "</b> - ";
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
        $fdhv = $carr[reqival('class')];
        if ($forDiscreteClasses == 1)
        {
            $fdhv = "and T.traDHV=$fdhv ";
        }
        else
        {
            $fdhv = "and T.traDHV<=$fdhv";
        }
    }
}

// Determine scoring params / details ..
$tasTotal = 0;
$query = "select count(*) from tblTask where comPk=$comPk";
$result = mysql_query($query); // or die('Task total failed: ' . mysql_error());
if ($result)
{
    $tasTotal = mysql_result($result, 0, 0);
}
if ($comOverall == 'all')
{
    # total # of tasks
    $comOverallParam = $tasTotal;
    $overstr = "All rounds";
}
else if ($comOverall == 'round')
{
    $overstr = "$comOverallParam rounds";
}
else if ($comOverall == 'round-perc')
{
    $comOverallParam = round($tasTotal * $comOverallParam / 100, 0);
    $overstr = "$comOverallParam rounds";
}
else if ($comOverall == 'ftv')
{
    $sql = "select sum(tasQuality) as totValidity from tblTask where comPk=$comPk";
    $result = mysql_query($sql) or die('Task validity query failed: ' . mysql_error());
    $totalvalidity = round(mysql_result($result, 0, 0) * $comOverallParam * 10,0);
    $overstr = "FTV $comOverallParam% ($totalvalidity pts)";
    $comOverallParam = $totalvalidity;
}
$compinfo['overall'] = $overstr;

$today = getdate();
$tdate = sprintf("%04d-%02d-%02d", $today['year'], $today['mon'], $today['mday']);
// Fix: make this configurable
if (0 && $tdate == $comDateTo)
{
    $usePk = check_auth('system');
    $link = db_connect();
    $isadmin = is_admin('admin',$usePk,$comPk);
    
    if ($isadmin == 0)
    {
        exit(0);
    }
}

$rtable = [];
$rdec = [];

if ($comClass == "HG")
{
    $classopts = array ( 'open' => '', 'floater' => '&class=0', 'kingpost' => '&class=1', 
        'hg-open' => '&class=2', 'rigid' => '&class=3', 'women' => '&class=4', 'masters' => '&class=5', 'teams' => '&class=8' );
}
else
{
    $classopts = array ( 'open' => '', 'fun' => '&class=0', 'sports' => '&class=1', 
        'serial' => '&class=2', 'women' => '&class=4', 'masters' => '&class=5', 'teams' => '&class=8', 'handicap' => '&class=9' );
}
$cind = '';
if ($class != '')
{
    $cind = "&class=$class";
}
$copts = [];
foreach ($classopts as $text => $url)
{
    if ($text == 'teams' && $comTeamScoring == 'aggregate')
    {
        # Hack for now
        $copts[$text] = "team_comp_result.php?comPk=$comPk";
    }
    else
    {
        $copts[$text] = "comp_result.php?comPk=$comPk$url";
    }
}

$rdec[] = 'class="h"';
$rdec[] = 'class="h"';
if (reqival('id') == 1)
{
    $hdr = array( fb('Res'),  fselect('class', "comp_result.php?comPk=$comPk$cind", $copts, ' onchange="document.location.href=this.value"'), fb('Nation'), fb('Sex'), fb('FAI'), fb('CIVL'), fb('Total') );
    $hdr2 = array( '', '', '', '', '', '', '' );
}
else
{
    $hdr = array( fb('Res'),  fselect('class', "comp_result.php?comPk=$comPk$cind", $copts, ' onchange="document.location.href=this.value"'), fb('Glider'), fb('Total') );
    $hdr2 = array( '', '', '', '' );
}

# find each task details
$alltasks = [];
$taskinfo = [];
$sorted = [];
if ($class == "8")
{
    if ($comTeamScoring == 'handicap')
    {
        team_handicap_result($comPk,$how,$param);
    }
}
else if ($comType == 'RACE' || $comType == 'Team-RACE' || $comType == 'Route' || $comType == 'RACE-handicap')
{
    $query = "select T.* from tblTask T where T.comPk=$comPk order by T.tasDate";
    $result = mysql_query($query) or die('Task query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $alltasks[] = $row['tasName'];
        $taskinfo[] = $row;
    }

    if ($comType == 'Team-RACE')
    {
        $sorted = team_gap_result($comPk, $comOverall, $comOverallParam);
        $subtask = 'team_';
    }
    else if ($class == "9")
    {
        $sorted = overall_handicap($comPk, $comOverall, $comOverallParam, $fdhv);
        $subtask = '';
    }
    else
    {
        $sorted = comp_result($comPk, $comOverall, $comOverallParam, $fdhv, $tasTotal);
        $subtask = '';
    }

    foreach ($taskinfo as $row)
    {
        $tasName = $row['tasName'];
        $tasPk = $row['tasPk'];
        $tasDate = substr($row['tasDate'],0,10);
    }
    foreach ($taskinfo as $row)
    {
        $tasPk = $row['tasPk'];
        if ($row['tasTaskType'] == 'airgain')
        {
            $treg = $row['regPk'];
        }
    }

    $lasttot = 0;
    $count = 1;
    foreach ($sorted as $pil => $arr)
    {
        $nxt = [];
        $tot = 0 + $pil;
        if ($tot != $lasttot)
        {
            $nxt['pos'] = $count;
        }
        else
        {
            $nxt['pos'] = '';
        }
        $nxt['name'] = $arr['name'];
        $nxt['nation'] = $arr['nation'];
        $nxt['gender'] = $arr['gender'];
        $nxt['hgfa'] = $arr['hgfa'];
        $nxt['civl'] = $arr['civl'];
        $nxt['glider'] = $arr['glider'];
        $nxt['total'] = $tot;
        $lasttot = $tot;

        foreach ($alltasks as $num => $name)
        { 
            $nxt['scores'] = $arr;
        }
        $rtable[] = $nxt;
        $count++;
    }
}
else
{
    // OLC Result
    $rtable[] = array( fb('Res'),  fselect('class', "comp_result.php?comPk=$comPk$cind", $copts, ' onchange="document.location.href=this.value"'), fb('Total') );
    $rtable[] = array( '', '', '' );
    $top = 25;
    if (!$comOverallParam)
    {
        $comOverallParam = 4;
    }
    $restrict = '';
    if ($comPk == 1)
    {
        $restrict = " $fdhv";
    }
    elseif ($comPk > 1)
    {
        $restrict = " and CTT.comPk=$comPk $fdhv";
    }
    if ($class == "9")
    {
        $sorted = olc_handicap_result($link, $comOverallParam, $restrict);
    }
    else
    {
        $sorted = olc_result($link, $comOverallParam, $restrict);
    }
    $size = sizeof($sorted);

    $count = $start+1;
    $sorted = array_slice($sorted,$start,$top+2);
    $count = display_olc_result($comPk,$rtable,$sorted,$top,$count);
}


$data = [ 'tasks' => $taskinfo, 'data' => $rtable ];
print json_encode($data);
?>

