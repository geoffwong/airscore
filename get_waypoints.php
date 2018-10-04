<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';

$usePk = check_auth('system');
$link = db_connect();
$regPk = reqival('regPk');

function region_waypoints($link, $regPk)
{
    $prefix = 'rwp';
    $first = 0;
    $count = 0;
    $waypoints = [];
    $waylist = [];
    $reginfo = 0;

    $sql = "SELECT R.*, RW.* FROM tblRegion R, tblRegionWaypoint RW WHERE R.regPk=$regPk and RW.regPk=$regPk";
    $result = mysql_query($sql,$link);
    
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        if (!$reginfo)
        {
            $reginfo = [ $row['regCentre'], $row['regDescription'] ];
        }
        $clat = $row["${prefix}LatDecimal"];
        $clon = $row["${prefix}LongDecimal"];
        $calt = $row["${prefix}Altitude"];
        $cname = $row["${prefix}Name"];
        $cdesc = rtrim($row["${prefix}Description"]);
        $crwppk = $row["${prefix}Pk"];
        $count++;
    
        $waylist[] = [ 'rwpPk' => $crwppk, 'name' => $cname, 'lat' => $clat, 'lon' => $clon, 'alt' => $calt, 'desc' => $cdesc ];
    }
    $waypoints['region'] = $reginfo;
    $waypoints['waypoints'] = $waylist;

    return $waypoints;
}

$waypoints = region_waypoints($link, $regPk);
print json_encode($waypoints);
?>

