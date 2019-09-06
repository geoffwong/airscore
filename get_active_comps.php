<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

function get_active_comps($link, $comPk, $auth)
{
    $restrict = '';
    if ($comPk > 0)
    {
        $restrict = " C.comPk=$comPk and";
    }

    $notest = " and C.comName not like '%test%'";
    if ($auth > 0)
    {
        $notest = '';
    }
    $sql = "select C.comPk, C.comName, C.comClass, T.tasPk, T.tasName, C.comType
        from tblCompetition C left outer join tblTask T on T.comPk=C.comPk and C.comType='Route' 
        where curdate() < date_add(C.comDateTo, interval 3 day) and$restrict C.comDateTo > '0000-00-00'$notest order by C.comName";

    $result = mysql_query($sql,$link) or json_die('get_all_tasks failed: ' . mysql_error());

    $comps = [];
    $last_comp = 0;
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        // build sub struct for tasks ...
        if ($row['comPk'] != $last_comp)
        {
            if ($row['tasPk'])
            {
                $row['tasks'] = [ [ $row['tasPk'], $row['tasName'] ] ];
            }
            else
            {
                $row['tasks'] = [ ];
            }
            unset($row['tasPk']);
            unset($row['tasName']);
            $comps[] = $row;
        }
        else
        {
            $comps[sizeof($comps)-1]['tasks'][] = [ $row['tasPk'], $row['tasName'] ];
        }
        $last_comp = $row['comPk'];
    }

    return $comps;
}

$usePk = check_auth('system');
$link = db_connect();
$comPk = reqival('comPk');

$comps = get_active_comps($link, $comPk, $usePk);
print json_encode($comps);
?>

