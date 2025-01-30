<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 3600));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

function get_admin_regions($link, $usePk)
{
    $sql = "SELECT R.regPk, R.regDescription, count(*) as Numbers from tblRegion R left outer join tblRegionWaypoint RW on RW.regPk=R.regPk where R.regDescription not like 'taskreg%' group by R.regPk order by R.regDescription";
    $result = mysql_query($sql,$link);

    $regions = []; 
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $row['actions'] = '';
        $regions[] = array_values($row);
    }

    return $regions;
}

$regions = get_admin_regions($link, $usePk);
$data = [ 'data' => $regions ];
print json_encode($data);

?>

