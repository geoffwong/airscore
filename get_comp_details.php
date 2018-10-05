<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';
require_once 'get_olc.php';

$link = db_connect();
$comPk = reqival('comPk');
$class = reqival('class');
$carr = [];


function get_compinfo($link, $comPk)
{
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

    return $compinfo;
}

function get_taskinfo($link, $comPk)
{
    $query = "select T.* from tblTask T where T.comPk=$comPk order by T.tasDate";
    $result = mysql_query($query, $link) or die('Task query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $alltasks[] = $row['tasName'];
    }
    return $alltasks;
}

$compinfo = get_compinfo($link, $comPk);

$taskinfo = [];
$comType=$compinfo['comType'];
if ($comType == 'RACE' || $comType == 'Team-RACE' || $comType == 'Route' || $comType == 'RACE-handicap')
{
    $taskinfo = get_taskinfo($link, $comPk);
}


$data = [ 'compinfo' => $compinfo, 'taskinfo' => $alltasks ];
print json_encode($data);
?>
