<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 3600));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

function get_active_tasks($link, $comPk)
{
    $today = getdate();
    $tdate = sprintf("%04d-%02d-%02d", $today['year'], $today['mon'], $today['mday']);

    $query = "select * from tblTask where comPk=$comPk and tasTaskType='free-pin' and tasDate <= '$tdate' order by tasDate desc";
    $result = mysql_query($query,$link) or json_die('get_active_tasks failed: ' . mysql_error());

    $tasks = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $tasks[] = [ $row['tasPk'], $row['tasName'], $row['tasDate'] ];
    }

    return $tasks;
}

$link = db_connect();
$comPk = reqival('comPk');
$comps = get_active_tasks($link, $comPk);
print json_encode($comps);
?>

