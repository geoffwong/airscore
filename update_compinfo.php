<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';

function get_latest_task($link, $comPk)
{
	$tasPk = 0;
 	$sql = "select T.* from tblCompetition C, tblTask T where C.comPk=$comPk and T.comPk=C.comPk order by tasDate desc";
    $result = mysql_query($sql,$link) or die('get_lastest_task (comp) failed: ' . mysql_error());

    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
		$tasPk = $row['tasPk'];
	}
	return $tasPk;
}

function find_presub($link, $comPk)
{
    // find latest $tasPk (if there is one)
    $tasPk = get_latest_task($link, $comPk);
    if ($tasPk == 0) return;

    $query = "select CTT.traPk from tblComTaskTrack CTT, tblTask T, tblTrack TR, tblCompetition C where T.tasPk=$tasPk and CTT.comPk=$comPk and T.comPk=CTT.comPk and C.comPk=CTT.comPk and CTT.traPk=TR.traPk and CTT.tasPk is null and TR.traStart > date_sub(T.tasStartTime, interval C.comTimeOffset+1 hour) and TR.traStart < date_sub(T.tasFinishTime, interval C.comTimeOffset hour)";
    $result = mysql_query($query,$link) or json_die("Presub query failed: $query");
    $tracks = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $tracks[] = $row['traPk'];
    }

    if (sizeof($tracks) > 0)
    {
        // Give them a task number 
        $sql = "update tblComTaskTrack set tasPk=$tasPk where comPk=$comPk and traPk in (" . implode(",",$tracks) . ")";
        $result = mysql_query($sql,$link);

        // Now verify the pre-submitted tracks against the task
        foreach ($tracks as $tpk)
        {
            echo "Verifying pre-submitted track: $tpk<br>";
            $out = '';
            $retv = 0;
            exec(BINDIR . "track_verify_sr.pl $tpk", $out, $retv);
        }
    }
}

$usePk = auth('system');
$link = db_connect();
$res = [];
$comPk = reqival('comPk');

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$comname = reqsval('Name');
$location = reqsval('Location');
$datefrom = reqsval('DateFrom');
$dateto = reqsval('DateTo');
$director = reqsval('MeetDirName');
$sanction = reqsval('Sanction');
$comcode = reqsval('Code');
$contact = reqsval('Contact');
$timeoffset = reqfval('TimeOffset');
$compclass = reqsval('Class');
$rentry = reqsval('EntryRestrict');
$regpk = reqival('Region');

$query = "update tblCompetition set comName='$comname', comLocation='$location', comDateFrom='$datefrom', comDateTo='$dateto', comMeetDirName='$director', comTimeOffset=$timeoffset, comCode='$comcode', comContact='$contact', comClass='$compclass', comEntryRestrict='$rentry', regPk=$regpk where comPk=$comPk";

$result = mysql_query($query, $link) 
    or json_die('Competition update failed ($query): ' . mysql_error());

find_presub($link, $comPk);

$res['result'] = "ok";
print json_encode($res);
?>

