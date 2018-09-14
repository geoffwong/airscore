<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';

$usePk = check_auth('system');
$link = db_connect();
$tasPk = reqival('tasPk');
$comPk = reqival('comPk');
$trackid = reqival('trackid');

function get_pilots_lo($link, $tasPk)
{
    // buld a map of track to name and all tracks in task ..
    $sql = "select CT.traPk, P.pilLastName from tblComTaskTrack CT, tblTrack T, tblPilot P where CT.tasPk=$tasPk and T.traPk=CT.traPk and P.pilPk=T.pilPk";
    $result = mysql_query($sql,$link) or die('Query failed: ' . mysql_error());
    $tracks = [];
    $pilots = [];
    while($row = mysql_fetch_array($result, MYSQL_NUM))
    {
        $tracks[] = $row[0];
        $pilots[$row[0]] = $row[1];
    }
    if (sizeof($tracks) ==  0)
    {
        return json_encode($retarr);
    }
    
    $allt = "(" . implode(",", $tracks) . ")";
    $sql = "select TL.* from tblTrackLog TL where TL.traPk in " . $allt . " group by TL.traPk order by TL.traPk, TL.trlTime desc";
    $sql = "select X.* from (select * from tblTrackLog TL where TL.traPk in " . $allt . " order by TL.traPk, TL.trlTime desc) X group by X.traPk";
    $result = mysql_query($sql,$link) or die('Query failed: ' . mysql_error());
    
    $retarr = [];
    while($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $row['name'] = $pilots[$row['traPk']];
        $retarr[] = $row;
    }

    return $retarr;
}

function get_task_tracks($link, $tasPk, $trackid)
{
    if ($tasPk > 0)
    {
    $sql = "select TR.*, T.*, P.* from tblTaskResult TR, tblTrack T, tblPilot P where TR.tasPk=$tasPk and T.traPk=TR.traPk and P.pilPk=T.pilPk order by TR.tarScore desc limit 20";
    $result = mysql_query($sql,$link) or die('Task Result selection failed: ' . mysql_error());
    $addable = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $addable[$row['pilLastName']] = $row['traPk'];
    }
    }
    else if ($trackid > 0)
    {
        $sql = "select T2.*, P.* from tblTrack T, tblTrack T2, tblPilot P where T2.traStart>date_sub(T.traStart, interval 6 hour) and T2.traStart<date_add(T.traStart, interval 6 hour) and T.traPk=$trackid and P.pilPk=T2.pilPk order by T2.traLength desc limit 10";
        $result = mysql_query($sql,$link) or die('Task Result selection failed: ' . mysql_error());
        $addable = [];
        while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
        {
            $addable[$row['pilLastName']] = $row['traPk'];
        }
    }

    return $addable;
}

$tracks = get_task_tracks($link, $tasPk, $trackid);

if ($tasPk > 0)
{
    $lopilots = get_pilots_lo($link, $tasPk);
}
print json_encode($tracks);
?>

