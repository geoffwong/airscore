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
        $carr = [ "'floater'", "'kingpost'", "'open'", "'rigid'" ];
    }
    else
    {
        $carr = [ "'1/2'", "'2'", "'2/3'", "'competition'" ];
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
    $result = mysql_query($query, $link) or json_die('Task query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $alltasks[] = $row['tasName'];
    }

    $sorted = comp_result($comPk, $fdhv);
    $civilised = civl_result($alltasks, $sorted);
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
