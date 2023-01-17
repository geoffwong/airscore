<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 60));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'xcdb.php';
require_once 'track.php';

function get_pilot($link, $pilPk)
{
    $sql = "select P.pilFirstName, P.pilLastName, P.pilNationCode, P.pilSex from tblPilot P where P.pilPk=$pilPk";

    $result = mysql_query($sql,$link) or json_die('get_pilot_info failed: ' . mysql_error());
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        return [ $row['pilFirstName'], $row['pilLastName'], $row['pilNationCode'], $row['pilSex'] ];
    }

    return ['Unknown', 'Pilot', 'NONE', 'M' ];
}

function get_airgain_wpts($link, $comPk, $pilPk)
{
    $sql = "select regPk from tblCompetition where comPk=$comPk";
    $result = mysql_query($sql,$link) or json_die('Region query failed: ' . mysql_error());
    if (!$row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        json_die('No region associated with competition');
        return;
    }
    $regPk = $row['regPk'];
    $missed = get_region($link, $regPk, 0);

    $small = [];
    foreach ($missed as $key => $row)
    {
        $small[$key] = [ round($missed[$key]['rwpLatDecimal'],6), round($missed[$key]['rwpLongDecimal'],6), $missed[$key]['rwpName'] ];
    }

    $sql = "SELECT A.* FROM tblComTaskTrack CTT, tblAirgainWaypoint A, tblTrack T where CTT.comPk=$comPk and CTT.traPk=T.traPk and T.pilPk=$pilPk and A.traPk=T.traPk";
    $made = [];
    $result = mysql_query($sql,$link) or json_die('Airgain made waypoint query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $key = $row['rwpPk'];
        if (!array_key_exists($row['rwpPk'], $made))
        {
            $made[$key] = $small[$key];
            unset($small[$key]);
        }
    }

    $ret = [];
    $ret['made'] = $made;
    $ret['missed'] = $small;

    return $ret;
}


$usePk = check_auth('system');
$link = db_connect();
$pilPk = reqival('pilPk');
$comPk = reqival('comPk');
$isadmin = is_admin('admin',$usePk,$comPk);
$action = reqsval('action');
$extra = 0;

$result = get_airgain_wpts($link, $comPk, $pilPk);
$result['pilot'] = get_pilot($link, $pilPk);

print json_encode($result);
?>

