<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 30*86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'track.php';

$usePk = check_auth('system');
$link = db_connect();
$trackid = reqival('trackid');
$comPk = reqival('comPk');
$isadmin = is_admin('admin',$usePk,$comPk);
$interval = reqival('int');
$action = reqsval('action');
$extra = 0;

$result = [];
$body = get_track_body($link, $trackid, $interval);
$body['trackid'] = $trackid;
$result['track'] = $body;
if ($body['tasPk'] == 0)
{
    if ($body['formula_class'] == 'olc' and $body['formula_version'] == 'airgain-count')
    {
        $wps = get_airgain_wp($link, $trackid);
        $result['points'] = $wps;
    }
    else
    {
        $wps = get_track_wp($link, $trackid);
        $result['points'] = $wps;
    }
}
print json_encode($result);
?>

