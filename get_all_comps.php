<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

function get_all_comps($link)
{
    $sql = "select C.comPk, C.comName, C.comLocation, C.comClass, C.comSanction, C.comType, C.comDateFrom, C.comDateTo, count(T.tasPk) as numTasks from tblCompetition C left outer join tblTask T on T.comPk=C.comPk where C.comName not like '%test%' and C.comDateTo > '0000-00-00' group by C.comPk order by C.comDateTo desc";

    $result = mysql_query($sql,$link) or die('get_all_tasks failed: ' . mysql_error());

    $comps = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['comPk'];
        if ($row['comType'] == 'RACE' || $row['comType'] == 'Route')
        {
            $row['comName'] = "<a href=\"task_overview.html?comPk=$id\">" . $row['comName'] . '</a>';
        }
        else
        {
            $row['comName'] = "<a href=\"comp_overall.html?comPk=$id\">" . $row['comName'] . '</a>';
        }
        $row['comDateTo'] = substr($row['comDateTo'], 0, 11);
        $row['comDateFrom'] = substr($row['comDateFrom'], 0, 11); 
        if ($row['comClass'] == "PG")
        {
            $row['comClass'] = '<img src="images/pg_symbol.png"></img>';
        }
        elseif ($row['comClass'] == "HG")
        {
            $row['comClass'] = '<img src="images/hg_symbol.png"></img>';
        }
        else 
        {
            $row['comClass'] = '';
        }
        if ($row['comSanction'])
        {
            $row['comType'] = $row['comType'] . ' ' . $row['comSanction'];
        }
        unset($row['comPk']);
        unset($row['comSanction']);
        $comps[] = array_values($row);
    }

    return $comps;
}

$comps = get_all_comps($link);
$data = [ 'data' => $comps ];
print json_encode($data);
?>

