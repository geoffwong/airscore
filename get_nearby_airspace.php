<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';

// in future limit to "nearby" airspace ..

function get_nearby_airspace($regPk)
{
    $airarr = [];
    $query = "select regCentre from tblRegion where regPk=$regPk";
    $result = mysql_query($query) or die('Region centre select failed: ' . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        $cenPk = 0+mysql_result($result, 0, 0);
        $query = "select * from tblAirspace R 
            where R.airPk in (
                select airPk from tblAirspaceWaypoint W, tblRegionWaypoint R where
                R.rwpPk=$cenPk and
                W.awpLatDecimal between (R.rwpLatDecimal-1.5) and (R.rwpLatDecimal+1.5) and
                W.awpLongDecimal between (R.rwpLongDecimal-1.5) and (R.rwpLongDecimal+1.5)
                group by (airPk))
            order by R.airName";
    }
    else
    {
        $query = "select * from tblAirspace R order by R.airName";
    }
    $result = mysql_query($query) or die('Airspace select failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $airarr[$row['airName']] = $row['airPk'];
    }

    return $airarr;
}


$link = db_connect();
$regPk = reqival('regPk');
$retarr = get_nearby_airspace($link, $regPk);

print json_encode($retarr);
?>

