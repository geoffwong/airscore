<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

function extract_results($result)
{
    $comps = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['comPk'];
        if ($row['comType'] == 'RACE' || $row['comType'] == 'Route')
        {
            $row['comName'] = "<a href=\"task_overview.html?comPk=$id\">" . $row['comName'] . '</a>';
        }
        elseif ($row['comType'] == 'OLC')
        {
            $row['comName'] = "<a href=\"olc_overview.html?comPk=$id\">" . $row['comName'] . '</a>';
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

function get_open_comps($link)
{
    $sql = "select C.comPk, C.comName, C.comLocation, C.comClass, C.comSanction, C.comType, C.comDateFrom, C.comDateTo, count(T.traPk) as numTracks 
    from tblCompetition C 
    left outer join tblComTaskTrack CTT 
        on CTT.comPk=C.comPk 
    left outer join tblTrack T 
        on T.traPk=CTT.traPk 
    where C.comName not like '%test%'
    and C.comDateTo >= curdate() and C.comDateFrom <= curdate()
    group by C.comPk 
    order by C.comType desc, C.comDateFrom desc";

    $result = mysql_query($sql,$link) or die('get_open_comps failed: ' . mysql_error());

    $comps = extract_results($result);
    return $comps;
}

function get_recent_comps($link)
{
    $sql = "select C.comPk, C.comName, C.comLocation, C.comClass, C.comSanction, C.comType, C.comDateFrom, C.comDateTo, count(T.tasPk) as numTasks 
        from tblCompetition C 
        left outer join tblTask T 
            on T.comPk=C.comPk 
        where C.comName not like '%test%' 
        and C.comDateTo > date_sub(curdate(), interval 3 month) and C.comDateTo < curdate()
        group by C.comPk 
        order by C.comType desc, C.comDateFrom desc";

    $result = mysql_query($sql,$link) or die('get_recent_comps failed: ' . mysql_error());
    $comps = extract_results($result);
    return $comps;
}


$data = [];
$recent = get_recent_comps($link);
$data['data'] = $recent;

print json_encode($data);
?>

