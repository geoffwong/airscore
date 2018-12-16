<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 30*86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

auth('system');
$argPk = reqival('argPk');

function get_region_airspace($link, $argPk)
{
    $sql = "select R.*, AW.* from tblAirspace R, tblAirspaceWaypoint AW 
        where R.airPk in (             
            select airPk from tblAirspaceWaypoint W, tblAirspaceRegion R where
            R.argPk=$argPk and
            W.awpLatDecimal between (R.argLatDecimal-R.argSize) and (R.argLatDecimal+R.argSize) and
            W.awpLongDecimal between (R.argLongDecimal-R.argSize) and (R.argLongDecimal+R.argSize)
            group by (airPk)) and
            R.airPk=AW.airPk
        order by R.airName, R.airPk, AW.airOrder";

    $result = mysql_query($sql,$link);
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
$airspaces = get_region_airspace($link, $argPk);
print json_encode($airspaces);

?>

