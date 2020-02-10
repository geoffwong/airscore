<?php

require_once 'authorisation.php';

$comPk = reqival('comPk');
$limit = reqival('limit');
if ($limit == 0)
{
    $limit = 500;
}

$usePk=auth('system');
$link = db_connect();

function get_tracks($link, $comPk, $limit)
{
    if ($comPk > 0)
    {
        $query = "select comType from tblCompetition where comPk=$comPk";
        $result = mysql_query($query, $link) or json_die('Com type query failed: ' . mysql_error());
        $comType = mysql_result($result, 0, 0);

        if ($comType == 'RACE')
        {
            #$sql = "SELECT T.*, P.* FROM tblTaskResult CTT left join tblTrack T on CTT.traPk=T.traPk left outer join tblPilot P on T.pilPk=P.pilPk where CTT.tasPk in (select tasPk from tblTask TK where TK.comPk=$comPk) order by T.traStart desc";
            $sql = "(
                    SELECT T.traPk, T.traStart, T.traDHV, P.pilFirstName, P.pilLastName, T.traDuration, T.traLength, $comPk as comPk, CTT.tasPk 
                        from tblTrack T
                        left outer join tblTaskResult CTT on CTT.traPk=T.traPk 
                        left outer join tblPilot P on T.pilPk=P.pilPk 
                    where CTT.tasPk in (select tasPk from tblTask TK where TK.comPk=$comPk) 
                )
                union
                (
                    SELECT T.traPk, T.traStart, T.traDHV, P.pilFirstName, P.pilLastName, T.traDuration, T.traLength, CTT.comPk, CTT.tasPk 
                    FROM tblComTaskTrack CTT 
                        join tblTrack T on CTT.traPk=T.traPk 
                        left outer join tblPilot P on T.pilPk=P.pilPk 
                    where CTT.comPk=$comPk 
                ) 
                order by traStart desc limit $limit";
        }
        else
        {
            $sql = "SELECT T.traPk, T.traStart, T.traDHV, P.pilFirstName, P.pilLastName, T.traDuration, T.traLength, CTT.comPk, CTT.tasPk
                FROM tblComTaskTrack CTT 
                join tblTrack T 
                    on CTT.traPk=T.traPk 
                left outer join tblPilot P 
                    on T.pilPk=P.pilPk 
                where CTT.comPk=$comPk order by T.traStart desc limit $limit";
        }
    }
    else
    {
        $sql = "SELECT T.traPk, T.traStart, T.traDHV, P.pilFirstName, P.pilLastName, T.traDuration, T.traLength, CTT.comPk, CTT.tasPk from
            tblTrack T
            left outer join tblPilot P on T.pilPk=P.pilPk
            left outer join tblComTaskTrack CTT on CTT.traPk=T.traPk
            order by T.traPk desc limit $limit";
    }
    $result = mysql_query($sql,$link) or json_die ("Track query failed: " . mysql_error());

    $all_tracks = [];
    $dispclass =  [ '1' => 'A', '1/2' => 'B', '2' => 'C', '2/3' => 'D', 'competition' => 'CCC', 
                    'floater' => 'floater', 'kingpost' => 'kingpost', 'open' => 'HG-open', 'rigid' => 'rigid' ];

    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $tasPk = '';
        $comPk = '';
        if ($row['comPk'])
        {
            $comPk = '&comPk=' . $row['comPk'];
        }
        if ($row['tasPk'])
        {
            $tasPk='&tasPk=' . $row['tasPk'];
        }
        $row['traDHV'] = $dispclass[$row['traDHV']];
        $row['traStart'] = "<a href=\"tracklog_map.html?trackid=" . $row['traPk'] . $comPk . $tasPk . "\">" . $row['traStart'] . "</a>";
        $row['pilFirstName'] = utf8_encode($row['pilFirstName'] . ' ' . $row['pilLastName']);
        $row['traDuration'] = hhmmss($row['traDuration']);
        $row['traLength'] = round($row['traLength']/1000,1);
        $row['cross'] = 0;
        unset($row['comPk']);
        unset($row['tasPk']);
        unset($row['pilLastName']);
        $all_tracks[] = array_values($row);
    }

    return $all_tracks;
}

$comp = [];
$tracks = get_tracks($link, $comPk, $limit);
$data = [ 'info' => $comp, 'data' => $tracks ];
print json_encode($data);

?>

