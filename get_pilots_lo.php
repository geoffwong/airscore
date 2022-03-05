<?php
require 'authorisation.php';
require 'xcdb.php';
require 'RJson.php';
header('Cache-Control: no-cache, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 600));
header('Content-type: application/json');

$link = db_connect();
$tasPk = reqival('tasPk');
$retarr = [];

if (!$tasPk) return;

$sql = "select CT.traPk, P.pilLastName from tblComTaskTrack CT, tblTrack T, tblPilot P where CT.tasPk=$tasPk and T.traPk=CT.traPk and P.pilPk=T.pilPk";
$result = mysql_query($sql,$link) or die('Query failed: ' . mysql_error());
$tracks = [];
$pilots = [];
while($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $tracks[] = $row['traPk'];
    $pilots[$row['traPk']] = $row['pilLastName'];
}
if (sizeof($tracks) ==  0)
{
    return json_encode($retarr);
}

$allt = "(" . implode(",", $tracks) . ")";

//$sql = "select TL.* from tblTrackLog TL where TL.traPk in " . $allt . " group by TL.traPk order by TL.traPk, TL.trlTime desc";
$sql = "select TL.* from tblTrackLog TL, (select traPk, max(trlTime) as maxTime from tblTrackLog TL where TL.traPk in " . $allt . " group by traPk) X where X.traPk=TL.traPk and X.maxTime=TL.trlTime";
//$sql = "select T1.* from tblTrackLog T1 left outer join tblTrackLog T2 on T1.traPk in " . $allt . " and T2.traPk in " . $allt . " and T1.traPk=T2.traPk and T1.trlTime > T2.trlTime where T2.trlTime is null";
$result = mysql_query($sql,$link) or die('Query failed: ' . mysql_error());

$retarr = [];
while($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $row['trlLatDecimal'] = round($row['trlLatDecimal'],6);
    $row['trlLongDecimal'] = round($row['trlLongDecimal'],6);
    $row['name'] = $pilots[$row['traPk']];
    $retarr[] = $row;
}


mysql_close($link);
print json_encode($retarr);
?>
