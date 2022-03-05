<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 3600));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

if ($usePk == 0)
{
    $res['result'] = 'unauthorised';
    print json_encode($res);
    return;
}

function get_admin_comps($link, $usePk)
{
    if (is_admin('admin', $usePk, -1))
    {
        $sql = "select C.comPk, C.comName, C.comLocation, C.comType, C.comClass, C.comDateFrom, C.comDateTo, count(T.tasPk) as numTasks from tblCompetition C left outer join tblTask T on T.comPk=C.comPk group by C.comPk order by C.comName like '%test%', C.comDateTo desc";
    }
    else
    {
        $sql = "select C.comPk, C.comName, C.comLocation, C.comType, C.comClass, C.comDateFrom, C.comDateTo, count(T.tasPk) as numTasks FROM tblCompAuth A left join tblCompetition C on (A.comPk=C.comPk and A.usePk=$usePk) or (C.comName like '%test%') left outer join tblTask T on T.comPk=C.comPk group by C.comPk order by C.comName like '%test%', C.comDateTo desc";
    }

    $result = mysql_query($sql,$link) or json_die('get_all_tasks failed: ' . mysql_error());

    $comps = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['comPk'];
        #$row['comName'] = "<a href=\"competition.php?comPk=$id\">" . $row['comName'] . '</a>';
        $row['comDateTo'] = substr($row['comDateTo'], 0, 11);
        $row['comDateFrom'] = substr($row['comDateFrom'], 0, 11); 
        #$row['comType'] = $row['comType'] . '-' . $row['comClass'];
        #unset($row['comPk']);
        unset($row['comType']);
        unset($row['comClass']);
        $comps[] = array_values($row);
    }

    return $comps;
}

$comps = get_admin_comps($link, $usePk);
$data = [ 'data' => $comps ];
print json_encode($data);
?>

