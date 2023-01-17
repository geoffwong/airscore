<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

function get_all_comps($link)
{
    $sql = "select C.comPk, C.comName from tblCompetition C where CURRENT_DATE()  between C.comDateFrom and C.comDateTo";

    $result = mysql_query($sql,$link) or die('get_all_comps failed: ' . mysql_error());

    $comps = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $comps[$row['comName']] = $row['comPk'];
    }

    return $comps;
}

$comps = get_all_comps($link);
print json_encode($comps);
?>

