<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$usePk = check_auth('system');
$link = db_connect();

$pilPk = reqival('pilPk');
$ladPk = reqival('ladPk');

function get_pilot_info($link, $pilPk)
{
    $sql = "select P.pilPk, P.pilFirstName, P.pilLastName, P.pilNationCode, P.pilSex, min(T.traDate) as DateFrom, max(T.traDate) as DateTo, count(T.traPk) as numTracks, sum(T.traDuration) as total_hours from tblPilot P, tblTrack T where P.pilPk=$pilPk and T.pilPk=P.pilPk group by P.pilPk order by P.pilFirstName";

    $result = mysql_query($sql,$link) or json_die('get_pilot_info failed: ' . mysql_error());

    $info = [];
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['pilPk'];
        $row['pilFirstName'] = utf8_encode($row['pilFirstName'] . ' ' . $row['pilLastName']);
        $row['total_hours'] = hhmmss($row['total_hours']);
        $row['DateTo'] = substr($row['DateTo'], 0, 11);
        $row['DateFrom'] = substr($row['DateFrom'], 0, 11);
        unset($row['pilLastName']);
        unset($row['pilPk']);
        $info = $row;
    }

    // get AAA race performance (last 5 years)
    $sql = "select avg(tarPlace) as AvgPlace, min(tarPlace) as BestPlace, count(*) as TotalTasks, count(IF(TR.tarGoal > 0, 1, NULL)) as InGoal, avg(IF(TR.tarGoal > 0, tarSpeed, NULL)) as GoalSpeed from tblComTaskTrack CTT, (select comPk, lcValue from tblLadderComp group by comPk) LC, tblTrack T, tblTaskResult TR where CTT.traPk=T.traPk and LC.comPk=CTT.comPk and LC.lcValue in (360, 450) and TR.traPk=T.traPk and TR.tasPk=CTT.tasPk and T.traDate > date_sub(now(), interval 5 year) and T.pilPk=$pilPk";
    $result = mysql_query($sql,$link) or json_die('get_pilot_info (2) failed: ' . mysql_error());

    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $info['avg_place'] = $row['AvgPlace'];
        $info['best_place'] = $row['BestPlace'];
        $info['tasks'] = $row['TotalTasks'];
        $info['goal_perc'] = 0;
	if ($row['TotalTasks'] > 0)
	{
            $info['goal_perc'] = round($row['InGoal'] * 100 / $row['TotalTasks'],1) . '%';
	}
        $info['goal_speed'] = round($row['GoalSpeed'], 1);
    }

    return $info;
}

function dhv2en($class)
{
    if ($class == '1')
    {
        $class = 'A';
    }
    elseif ($class == '1/2')
    {
        $class = 'B';
    }
    elseif ($class == '2')
    {
        $class = 'C';
    }
    elseif ($class == '2/3')
    {
        $class = 'D';
    }
    elseif ($class == 'competition')
    {
        $class = 'CCC';
    }

    return $class;
}

//    <th>Date</th> <th>Glider</th> <th>EN</th> <th>Start</th> <th>Duration</th> <th>Dist</th> <th>Safe</th>
function get_pilot_tracks($link, $pilPk, $ladPk)
{
    $restrict = '';
    if ($ladPk > 0)
    {
        $restrict = "inner join tblLadderComp LC on LC.ladPk=$ladPk and LC.comPk=CTT.comPk";
    }
    //select T.*, P.*, CTT.*, L.* from tblTrack T, tblPilot P, tblComTaskTrack CTT, tblWaypoint W left outer join tblLaunchSite L on (abs(W.wptLatDecimal-L.lauLatDecimal)+abs(W.wptLongDecimal-L.lauLongDecimal)) < 0.2 where W.wptPosition=0 and T.traPk=W.traPk and CTT.traPk=T.traPk and T.pilPk=P.pilPk $sort group by T.traPk
    $sql = "select T.traPk, CTT.comPk, CTT.tasPk, T.traStart, L.lauLaunch, T.traGlider,T.traClass,T.traDHV,T.traDuration,T.traLength,T.traSafety from tblTrack T left outer join tblComTaskTrack CTT on CTT.traPk=T.traPk left outer join tblWaypoint W on T.traPk=W.traPk and W.wptPosition=0 left outer join tblLaunchSite L on (abs(W.wptLatDecimal-L.lauLatDecimal)+abs(W.wptLongDecimal-L.lauLongDecimal)) < 0.2 $restrict where T.pilPk=$pilPk group by T.traPk order by T.traDate desc";
    //$sql = "select distinct T.traPk, T.traStart, L.lauLaunch, T.traGlider,T.traClass,T.traDHV,T.traDuration,T.traLength,T.traSafety from tblTrack T left outer join tblWaypoint W on T.traPk=W.traPk and W.wptPosition=0 left outer join tblLaunchSite L on (abs(W.wptLatDecimal-L.lauLatDecimal)+abs(W.wptLongDecimal-L.lauLongDecimal)) < 0.1 where T.pilPk=$pilPk order by T.traDate desc";

    $result = mysql_query($sql,$link) or json_die('get_pilot_tracks failed: ' . mysql_error());

    $tracks = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['traPk'];
        $comPk = '';
        if ($row['comPk'] > 0)
        {
            $comPk = '&comPk=' . $row['comPk'];
        }
        $tasPk = '';
        if ($row['tasPk'] > 0)
        {
            $tasPk = '&tasPk=' . $row['tasPk'];
        }
        $start = $row['traStart'];
        $row['traStart'] = "<a href=\"tracklog_map.html?trackid=$id$comPk$tasPk\">$start</a>";
        $row['traDuration'] = hhmmss($row['traDuration']);
        $row['traDHV'] = dhv2en($row['traDHV']);
        $row['traLength'] = round($row['traLength']/1000,1);

        unset($row['traPk']);
        unset($row['comPk']);
        unset($row['tasPk']);
        $tracks[] = array_values($row);
    }

    return $tracks;
}

$pilot = get_pilot_info($link, $pilPk);
$tracks = get_pilot_tracks($link, $pilPk, $ladPk);
$data = [ 'info' => $pilot, 'data' => $tracks ];
$res = json_encode($data);
print $res;
?>

