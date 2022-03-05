<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

function get_all_pilots($link)
{
    $sql = "select P.pilPk, P.pilFirstName, P.pilLastName, P.pilNationCode, P.pilSex, min(T.traDate) as DateFrom, max(T.traDate) as DateTo, count(T.traPk) as numTracks from tblPilot P, tblTrack T where T.pilPk=P.pilPk group by P.pilPk order by P.pilFirstName";

    $result = mysql_query($sql,$link) or json_die('get_all_pilots failed: ' . mysql_error());

    $pilots = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['pilPk'];
        $row['pilFirstName'] = "<a href=\"pilot.html?pilPk=$id\">" . utf8_encode($row['pilFirstName'] . ' ' . $row['pilLastName']) . '</a>';
        $row['DateTo'] = substr($row['DateTo'], 0, 11);
        $row['DateFrom'] = substr($row['DateFrom'], 0, 11); 
        unset($row['pilLastName']);
        unset($row['pilPk']);
        $pilots[] = array_values($row);
    }

    return $pilots;
}

$pilots = get_all_pilots($link);
$data = [ 'data' => $pilots ];
$res = json_encode($data);
print $res;
?>

