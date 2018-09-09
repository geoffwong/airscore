<?php
require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();
$trackid = reqival('trackid');
$comPk = reqival('comPk');
$isadmin = is_admin('admin',$usePk,$comPk);
$interval = reqival('int');
$action = reqsval('action');
$extra = 0;

function get_track_body($link, $trackid, $interval)
{
    $body = [];
    $ret = [];
    
    // track info ..
    $offset = 0;
    $sql = "SELECT T.*, P.*, TR.*, TK.* FROM tblPilot P, tblTrack T 
            left outer join tblTaskResult TR on TR.traPk=T.traPk 
            left outer join tblTask TK on TK.tasPk=TR.tasPk 
            where T.pilPk=P.pilPk and T.traPk=$trackid order by TK.tasPk desc limit 1";
    $result = mysql_query($sql,$link) or die('Track info query failed: ' . mysql_error());
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $name = $row['pilFirstName'] . " " . $row['pilLastName'];
        $date = $row['traDate'];
        $glider = $row['traGlider'];
        $tasPk = $row['tasPk'];
        $comPk = $row['comPk'];
        $tasname = $row['tasName'];
        $comment = $row['tarComment'];
        $turnpoints = $row['tarTurnpoints'];
        $gtime = 0;
        $ggoal = 0;
        if ($glider == '')
        {
            $glider = 'unknown glider';
        }
        $gtime = $row['traDuration'];
        $dist = round($row['traLength'] / 1000, 2);
        $date = $row['traStart'];
        if ($tasPk > 0)
        {
            if ($row['tarES'] > 0)
            {
                $ggoal = $row['tarES'] - $row['tarSS'];
                $gtime = $ggoal;
            }
            $dist = round($row['tarDistance'] / 1000, 2);
            $date = $row['tasDate'];

            $dt1 = new DateTime($row["traDate"]);
            $dt2 = new DateTime($row["tasDate"]);
            if ($dt1 < $dt2) 
            { 
                $offset = -86400; 
            }
        }
        $ghour = floor($gtime / 3600);
        $gmin = floor($gtime / 60) - $ghour*60;
        $gsec = $gtime % 60;

        $body['name'] = $name;
        $body['date'] = $date;
        $body['startdate'] = $row["traDate"];
        $body['dist'] = $dist;
        $body['tasPk'] = $tasPk;    # get task name ...
        if ($ggoal && ($gtime > 0))
        {
            $body['goal'] = sprintf("%d:%02d:%02d", $ghour,$gmin,$gsec);
        }
        else
        {
            $body['duration'] = sprintf("%02d:%02d:%02d", $ghour,$gmin,$gsec);
        }
        $body['glider'] = $glider;
        $body['comment'] = $comment;
        $body['initials'] = substr($row['pilFirstName'],0,1) . substr($row['pilLastName'],0,1);

        $sql = "select C.comClass from tblCompetition C, tblComTaskTrack T where C.comPk=T.comPk and T.traPk=$trackid";
        $result = mysql_query($sql,$link) or die('Com class query failed: ' . mysql_error());
        $srow = mysql_fetch_array($result, MYSQL_ASSOC);
        if ($srow['comClass'] == 'sail')
        {
            $body['class'] = 'sail';
        }
        else
        {
            $body['class'] = 'pger';
        }
    }

    if ($interval < 1)
    {
        $interval = 5;
    }
    if ($interval == 1)
    {
        $sql = "SELECT *, trlTime as bucTime FROM tblTrackLog where traPk=$trackid order by trlTime";
    }
    else
    {
        $sql = "SELECT *, trlTime div $interval as bucTime FROM tblTrackLog where traPk=$trackid group by trlTime div $interval order by trlTime";
        $offset = (int) ($offset / $interval);
    }
    
    // Get some track points
    $result = mysql_query($sql,$link);
    while($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $bucTime = $offset + $row['bucTime'];
        $lasLat = 0.0 + $row['trlLatDecimal'];
        $lasLon = 0.0 + $row['trlLongDecimal'];
        $lasAlt = 0 + $row['trlAltitude'];
        $ret[] = array( $bucTime, $lasLat, $lasLon, $lasAlt );
    }
    $body['track'] = $ret;

    return $body;
}

$body = get_track_body($link, $trackid, $interval);
$jret = json_encode($body);

# nuke normal header ..
header("Content-type: text/plain");
header("Cache-Control: no-store, no-cache");

print $jret;
?>

