<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

function get_admin_pilots($link)
{
    $sql = "select P.pilPk, P.pilHGFA, P.pilCIVL, P.pilFirstName, P.pilLastName, P.pilSex, P.pilNationCode, P.pilSex from tblPilot P order by P.pilFirstName";
    $result = mysql_query($sql,$link) or die('get_admin_pilots failed: ' . mysql_error());

    $pilots = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $row['pilLastName'] = utf8_encode($row['pilLastName']);
        $row['pilFirstName'] = utf8_encode($row['pilFirstName']);
        $pilots[] = array_values($row);
    }

    return $pilots;
}

$pilots = get_admin_pilots($link);
$data = [ 'data' => $pilots ];
$res = json_encode($data);
print $res;
?>

