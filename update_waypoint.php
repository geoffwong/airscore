<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time()+1));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';

$authorised = check_auth('system');

function addup_waypoint($link, $regPk)
{
    $name = reqsval('name');
    $lat = reqfval("lat");
    $lon = reqfval("lon");
    $alt = reqival("alt");
    $rwpPk = reqival("rwpPk");
    $desc = rtrim(reqsval("desc"));

    $map = [ 'regPk' => $regPk, 'rwpPk' => $rwpPk, 'rwpName' => $name, 'rwpLatDecimal' => $lat, 'rwpLongDecimal' => $lon, 'rwpAltitude' => $alt, 'rwpDescription' => $desc ];

    if ($rwpPk > 0)
    {
        $sql = "select count(*) as count from tblTask where rwpPk=$rwpPk";
        $result = mysql_query($sql,$link) or json_die("Failed to count waypoint ($rwpPk): " . mysql_error());
        $row = mysql_fetch_array($result, MYSQL_ASSOC);
        if ($row[count] > 0)
        {
            // don't update a waypoint in use
            return;
        }
    }

    $res = [];
    if ($name != '' && $lat != 0)
    {
        if ($rwpPk > 0)
        {
            $wptid = insertup($link,'tblRegionWaypoint','rwpPk',"rwpPk=$rwpPk", $map);
            $res = $map;
            $res['result'] = 'updated';
        }
        else
        {
            unset($map['rwpPk']);
            $wptid = insertup($link,'tblRegionWaypoint','rwpPk',"rwpName='$name' and regPk=$regPk", $map);

            $res = $map;
            $res['rwpPk'] = $wptid;
            $res['result'] = 'added';
        }
    }
    return $res;
}

function delete_waypoint($link, $regPk)
{
    $delname = reqsval('name');
    $rwpPk = reqival('rwpPk');
    $sql = "select * from tblTaskWaypoint where rwpPk=$rwpPk";
    $result = mysql_query($sql,$link) or json_die("Failed to delete waypoint ($rwpPk): " . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        $ret['result'] = 'failed';
        return $ret;
    }
    
    $sql = "delete from tblRegionWaypoint where regPk=$regPk and rwpPk='$rwpPk'";
    $result = mysql_query($sql,$link) or json_die("Failed to delete waypoint ($delname): " . mysql_error());
    $ret = [];
    $ret['result'] = 'deleted';
    return $ret;
}

$link = db_connect();
$regPk = reqival('regPk');
$action = reqsval('action');

if ($authorised)
{
    if ($action == 'delete')
    {
        $res = delete_waypoint($link, $regPk);
    }
    else 
    {
        $res = addup_waypoint($link, $regPk);
    }
}
else
{
    $res = [];
    $res['result'] = 'unauthorised';

}
print json_encode($res);
?>
