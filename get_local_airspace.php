<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require 'authorisation.php';
require 'xcdb.php';

$usePk = check_auth('system');
$link = db_connect();
$tasPk = reqival('tasPk');
$comPk = reqival('comPk');
$trackid = reqival('trackid');

function get_task_airspace($link, $tasPk, $trackid)
{
    if ($tasPk > 0)
    {
        $sql = "select A.*, AW.* from tblTaskAirspace TA, tblAirspace A, tblAirspaceWaypoint AW where TA.tasPk=$tasPk and A.airPk=TA.airPk and A.airPk=AW.airPk order by A.airName,AW.airOrder";
    }
    else
    {
        $sql = "SELECT *, trlTime as bucTime FROM tblTrackLog where traPk=$trackid order by trlTime limit 1";
        $result = mysql_query($sql,$link) or die('Tracklog location failed: ' . mysql_error());
        $row = mysql_fetch_array($result, MYSQL_ASSOC);
        $tracklat = $row['trlLatDecimal'];
        $tracklon = $row['trlLongDecimal'];
    
        $sql = "select R.*, AW.* from tblAirspace R, tblAirspaceWaypoint AW 
                    where R.airPk in (             
                        select airPk from tblAirspaceWaypoint W, tblAirspaceRegion R where
                        $tracklat between (R.argLatDecimal-R.argSize) and (R.argLatDecimal+R.argSize) and
                        $tracklon between (R.argLongDecimal-R.argSize) and (R.argLongDecimal+R.argSize) and
                        W.awpLatDecimal between (R.argLatDecimal-R.argSize) and (R.argLatDecimal+R.argSize) and
                        W.awpLongDecimal between (R.argLongDecimal-R.argSize) and (R.argLongDecimal+R.argSize)
                        group by (airPk)) and
                        R.airPk=AW.airPk 
                    order by R.airName, AW.airOrder";
    }

    $result = mysql_query($sql,$link); // or die('Airspace selection failed: ' . mysql_error());
    $airspaces = [];
    if ($result)
    {
        $addable = [];
        $airPk = 0;
        while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
        {   
            $id = $row['airPk'];
            if ($id != $airPk)
            {
                $row['waypoints'] = [ [ $row['airOrder'], $row['awpConnect'], round($row['awpLatDecimal'],6), round($row['awpLongDecimal'],6), $row['awpAngleStart'], $row['awpAngleEnd'], $row['awpRadius'] ] ];
                $airspaces[$id] = $row;
                $airPk=$id;
            }
            else
            {
                $airspaces[$id]['waypoints'][] = [ $row['airOrder'], $row['awpConnect'], round($row['awpLatDecimal'],6), round($row['awpLongDecimal'],6), $row['awpAngleStart'], $row['awpAngleEnd'], $row['awpRadius'] ];
            }
        }
    }

    return $airspaces;
}

$airspaces = get_task_airspace($link, $tasPk, $trackid);
print json_encode($airspaces);
?>
