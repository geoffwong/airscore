<?php
require_once 'authorisation.php';

function award_waypoint($link, $comPk, $tawPk, $trackid, $wptime)
{
    // Did we award from turnpoints?
    $goaltime = 0;

    if (strchr($wptime, ":"))
    {
        $timarr = split(':', $wptime);
        $goaltime = $timarr[0] * 3600 + $timarr[1] * 60 + $timarr[2];
    }

    # Award the waypoint ..
    $sql = "insert into tblTaskAward (tawPk, traPk, tadTime) values ($tawPk, $trackid, $goaltime)";
    $result = mysql_query($sql,$link) or die('Award waypoint failed: ' . mysql_error());
    $sql = "select tasPk from tblComTaskTrack where traPk=$trackid";
    $result = mysql_query($sql,$link) or die('Selec task failed: ' . mysql_error());
    $tasPk = mysql_result($result, 0, 0);

    # Re-verify with new awarded waypoint(s)
    $out = '';
    $retv = 0; 
    exec( BINDIR . "track_verify.pl $trackid $tasPk", $out, $retv);
    return 0;
}

$comPk = reqival('comPk');

$usePk = check_auth('system');
$link = db_connect();
$isadmin = is_admin('admin',$usePk,$comPk);
if (!$isadmin) 
{
    return 0;
}

$tawPk = reqival('tawPk');
$trackid = reqival('trackid');
$wptime = reqsval('wptime');
award_waypoint($link,$comPk,$tawPk,$trackid,$wptime);
?>

