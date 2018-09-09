<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

function get_all_comps($link)
{
    $sql = "select C.comPk, C.comName, C.comType, C.comLocation, C.comClass, C.comDateFrom, C.comDateTo, count(T.tasPk) as numTasks from tblCompetition C left outer join tblTask T on T.comPk=C.comPk where C.comName not like '%test%' and C.comDateTo > '0000-00-00' group by C.comPk order by C.comDateTo desc";

    $result = mysql_query($sql,$link) or die('get_all_tasks failed: ' . mysql_error());

    $comps = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['comPk'];
        if ($row['comType'] == 'RACE')
        {
            $row['comName'] = "<a href=\"task_overview.html?comPk=$id\">" . $row['comName'] . '</a>';
        }
        else
        {
            $row['comName'] = "<a href=\"comp_result.php?comPk=$id\">" . $row['comName'] . '</a>';
        }
        $row['comDateTo'] = substr($row['comDateTo'], 0, 11);
        $row['comDateFrom'] = substr($row['comDateFrom'], 0, 11); 
        unset($row['comPk']);
        unset($row['comType']);
        $comps[] = array_values($row);
    }

    return $comps;
}

$comps = get_all_comps($link);
$data = [ 'data' => $comps ];
print json_encode($data);
?>

