<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'dbextra.php';
require_once 'xcdb.php';


$link = db_connect();
$comPk = reqival('comPk');
$tasPk = reqival('tasPk');
$usePk = auth('system');

$res = [];

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$upfile = $_FILES['userfile']['tmp_name'];

if ($upfile)
{
    $out = '';
    $retv = 0;
    exec(BINDIR . "airspace_openair.pl $upfile $tasPk", $out, $retv);
    
    if ($retv)
    {
        json_die(implode("\n", $out));
        exit(0);
    }

    // add a TaskAirspaceRegion ..

    // attach airspaces
    $ids = [];
    $resair = [];
    foreach ($out as $row)
    {
        $subarr = explode(",", $row);
        $resair[] = $subarr;
        $ids[] = "($tasPk," . $subarr[0] . ")";
    }
    $allvals = implode($ids, ",");
    $query = "insert into tblTaskAirspace (tasPk, airPk) values " . $allvals;
    $result = mysql_query($query, $link) or json_die("Failed to find airspace $airPk: " . mysql_error());

    $res['result'] = 'ok';
    $res['airspace'] = $resair;
    print json_encode($res);
}
else
{
    $airPk = reqival('airPk');

    $query = "select * from tblAirspace where airPk=$airPk";
    $result = mysql_query($query, $link) or json_die("Failed to find airspace $airPk: " . mysql_error());
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $airspace = $row;
    }
    
    $query = "insert into tblTaskAirspace (tasPk, airPk) values ($tasPk, $airPk)";
    $result = mysql_query($query, $link) or json_die("Failed to connect airspace ($airPk) to task ($tasPk): " . mysql_error());
    

    $res['result'] = 'ok';
    $res['airspace'] = [ $airspace['airPk'], $airspace['airName'], $airspace['class'], $airspace['airBase'], $airspace['airTops'] ];
    print json_encode($res);
}
?>
