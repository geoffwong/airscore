<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require 'authorisation.php';
require 'xcdb.php';

function get_airspace($link, $airPk)
{
    $sql = "select A.*, AW.* from tblAirspace A, tblAirspaceWaypoint AW where A.airPk=$airPk and AW.airPk=A.airPk order by A.airName, A.airPk, AW.airOrder";

    $result = mysql_query($sql,$link) or json_die("get_airspace failed: $sql");
    $airspaces = [];
    $airPk = 0;
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['airPk'];
        if ($id != $airPk)
        {
            $row['waypoints'] = [ [ $row['airOrder'], $row['awpConnect'], round($row['awpLatDecimal'],6), round($row['awpLongDecimal'],6), $row['awpAngleStart'], $row['awpAngleEnd'] ] ];
            $airspaces[$id] = $row;
            $airPk=$id;
        }
        else
        {
            $airspaces[$id]['waypoints'][] = [ $row['airOrder'], $row['awpConnect'], round($row['awpLatDecimal'],6), round($row['awpLongDecimal'],6), $row['awpAngleStart'], $row['awpAngleEnd'] ];
        }

    }
    return $airspaces;
}

$link = db_connect();
$airPk = reqival('airPk');
$retarr = get_airspace($link, $airPk);

print json_encode($retarr);
?>
