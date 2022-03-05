<?php

header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 3600));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

function get_all_regions($link)
{
    $sql = "SELECT * from tblAirspaceRegion R order by R.argRegion";
    $result = mysql_query($sql,$link) or json_die("get_all_regions() failed:" . mysql_error());
    $regions = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = ['argPk'];
        $regions[] = array_values($row);
    }

    return $regions;
}

$airspace = get_all_regions($link, $usePk);
$data = [ 'data' => $airspace ];
print json_encode($data);
?>

