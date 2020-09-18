<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require 'authorisation.php';
require 'hc.php';
require 'format.php';
require 'xcdb.php';
require 'track.php';

function get_track_detail($link, $what, $traPk)
{
    $res = [];
    $res['title'] = $what;
    $res['traPk'] = $traPk;

    $body = get_track_body($link, $traPk, 30);
    $res['detail'] = $body;
    return $res;
}

function get_track_info($link,$comPk)
{
    $ret = [];
    $task = [];

    $sql = "select C.* from tblCompetition C where C.comPk=$comPk";
    $result = mysql_query($sql,$link) or die('get_track_info (comp) failed: ' . mysql_error());

    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $row['comDateFrom'] = substr($row['comDateFrom'],0,10);
        $row['comDateTo'] = substr($row['comDateTo'],0,10);
        $ret['comp'] = $row;
    }

	$tracks = [];

    # Most recent track
	$sql = "select T.traPk, T.traLength, T.traScore from tblComTaskTrack CTT, tblTrack T where CTT.comPk=$comPk and T.traPk=CTT.traPk order by T.traDate desc";
	$result = mysql_query($sql,$link) or json_die('get recent track failed: ' . mysql_error());
    $recent = 0;
    $longest = 0;
    $longestPk = 0;
    $top = 0;
    $topPk = 0;
	while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $traPk = $row["traPk"];
        if ($recent == 0)
        {
            $recent = $traPk;
        }

        if ($row["traLength"] > $longest)
        {
            $longest = $row["traLength"];
            $longestPk = $row["traPk"];
        }

        if ($row["traScore"] > $top)
        {
            $top = $row["traScore"];
            $topPk = $row["traPk"];
        }
    }

    # Longest track
    $tracks[] = get_track_detail($link, "Recent Flight", $recent);
    $tracks[] = get_track_detail($link, "Longest Flight", $longestPk);
    $tracks[] = get_track_detail($link, "Top Flight", $topPk);

	$ret['tracks'] = $tracks;
	return $ret;
}
	
# 1. Find highest scoring, 2. Recent high scoring (or 2nd if == 1), 3. most recent (next most if == 1 or 2), random (not 1,2,3)

# get tracks (interval 30)

# create structure ..
# track type
# pilot
# date
# score
# track points
# waypoints / airgain points

$usePk = check_auth('system');
$link = db_connect();
$comPk = reqival('comPk');

$tracks = get_track_info($link, $comPk);

print json_encode($tracks);
?>

