<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';

// in future limit to "nearby" airspace ..

function get_nearby_airspace($link, $rwpPk)
{
    $airarr = [];
    if ($rwpPk > 0)
    {
        $query = "select * from tblAirspace R 
            where R.airPk in (
                select airPk from tblAirspaceWaypoint W, tblRegionWaypoint R where
                R.rwpPk=$rwpPk and
                W.awpLatDecimal between (R.rwpLatDecimal-1.5) and (R.rwpLatDecimal+1.5) and
                W.awpLongDecimal between (R.rwpLongDecimal-1.5) and (R.rwpLongDecimal+1.5)
                group by (airPk))
            order by R.airName";
    }
    else
    {
        $query = "select * from tblAirspace R order by R.airName";
    }
    $result = mysql_query($query) or json_die('Airspace select failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $airarr[] = [ $row['airName'],  $row['airPk'] ];
    }

    return $airarr;
}


$link = db_connect();
$regPk = reqival('regPk');
$tasPk = reqival('tasPk');
$rwpPk = 0;

if ($tasPk > 0)
{
    $sql = "select R.rwpPk from tblTaskWaypoint T, tblRegionWaypoint R where T.tasPk=$tasPk and  T.rwpPk = R.rwpPk order by T.tawNumber limit 1";
    $result = mysql_query($sql, $link) or json_die('TaskWaypoint select failed: ' . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        $rwpPk = 0+mysql_result($result, 0, 0);
    }

}
else if ($regPk > 0)
{
    $sql = "select regCentre from tblRegion where regPk=$regPk";
    $result = mysql_query($sql, $link) or json_die('Region centre select failed: ' . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        $rwpPk = 0+mysql_result($result, 0, 0);
    }
}
else
{
    json_die('get_nearby_airspace: No region or task specified');
}

$retarr = get_nearby_airspace($link, $rwpPk);
print json_encode($retarr);
?>

