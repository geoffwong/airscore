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

$query = "select * from tblTaskAirspace TA, tblAirspace A, tblAirspaceWaypoint AW where TA.tasPk=$tasPk and TA.airPk=A.airPk and AW.airPk=A.airPk order by A.airName, A.airPk, AW.airOrder";
$result = mysql_query($query, $link) or json_die("Failed to find task airspace $tasPk: " . mysql_error());

$airspace = [];
while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    if (!array_key_exists($row['airPk'], $airspace))
    {
        $airspace[$row['airPk']] = [ 'airPk' => $row['airPk'], 'airName' => $row['airName'], 'airClass' => $row['class'], 'airBase' => $row['airBase'], 'airTops' => $row['airTops'] ];
        $airspace[$row['airPk']]['waypoints'] = [];
    }
    array_push($airspace[$row['airPk']]['waypoints'], [ $row['airOrder'], $row['awpConnect'], round($row['awpLatDecimal'],6), round($row['awpLongDecimal'],6), $row['awpAngleStart'], $row['awpAngleEnd'] ]);
}

$res['result'] = 'ok';
$res['airspaces'] = $airspace;
print json_encode($res);
?>
