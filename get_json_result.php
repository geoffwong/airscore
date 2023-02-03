<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';
require_once 'get_olc.php';
require_once 'race_results.php';

$link = db_connect();
$comPk = reqival('comPk');
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

$comType=$compinfo['comType'];
if ($comType == 'RACE' || $comType == 'Team-RACE' || $comType == 'Route' || $comType == 'RACE-handicap')
{
    $query = "select T.* from tblTask T where T.comPk=$comPk order by T.tasDate";
    $result = mysql_query($query, $link) or json_die('Task query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $alltasks[] = $row['tasName'];
    }

    $ordered = comp_result($comPk, "");
    //$civilised = civl_result($alltasks, $sorted);
    $sorted = [];
    $count = 1;
    foreach ($ordered as $pil => $arr)
    {
        $score = 0 + $pil;
        $x = strpos($pil, '!');
        $pilPk = substr($pil,$x+1);

        $arr['id'] = $pilPk;
        $arr['rank'] = $count;
        $arr['score'] = $score;
        $tsorted = [];
        foreach ($arr['tasks'] as $name => $tresult)
        {
            $tsorted[] = $tresult;
        }
        $arr['tasks'] = $tsorted;
        $sorted[] = $arr;
        $count++;
    }
}
else
{
    $sorted = get_olc_result($link, $comPk, $compinfo, '');
    $compinfo['forClass'] = 'OLC'; 
    # $compinfo['forVersion'] = ''; 
}


$data = [ 'compinfo' => $compinfo, 'data' => $sorted ];
print json_encode($data);
?>
