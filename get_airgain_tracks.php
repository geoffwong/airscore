<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 60));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'xcdb.php';
require_once 'track.php';

function get_airgain_tracks($link, $comPk, $pilPk)
{
    # Select tracks where we made a waypoint
    $sql = "SELECT T.traPk FROM tblComTaskTrack CTT, tblAirgainWaypoint A, tblTrack T where CTT.comPk=$comPk and CTT.traPk=T.traPk and T.pilPk=$pilPk and A.traPk=T.traPk group by T.traPk";
    $tracks = [];
    $result = mysql_query($sql,$link) or json_die('Airgain tracks query failed: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $key = $row['traPk'];
        $tracks[] = $key;
    }

    $ret = [];
    $ret['pilPk'] = $pilPk;
    $ret['tracks'] = $tracks;

    return $ret;
}


$usePk = check_auth('system');
$link = db_connect();
$pilPk = reqival('pilPk');
$comPk = reqival('comPk');
$isadmin = is_admin('admin',$usePk,$comPk);
$action = reqsval('action');
$extra = 0;

$result = get_airgain_tracks($link, $comPk, $pilPk);

print json_encode($result);
?>

