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
    $sql = "select avg(tarPlace) as AvgPlace, min(tarPlace) as BestPlace, count(*) as TotalTasks, count(IF(TR.tarGoal > 0, 1, NULL)) as InGoal, avg(IF(TR.tarGoal > 0, tarSpeed, NULL)) as GoalSpeed from tblComTaskTrack CTT, (select comPk, lcValue from tblLadderComp group by comPk) LC, tblTrack T, tblTaskResult TR where CTT.traPk=T.traPk and LC.comPk=CTT.comPk and LC.lcValue>=360 and TR.tarResultType not in ('abs', 'dnf') and TR.traPk=T.traPk and TR.tasPk=CTT.tasPk and T.traDate > date_sub(now(), interval 5 year) and T.pilPk=$pilPk";
    $result = mysql_query($sql,$link) or json_die('get_pilot_info (2) failed: ' . mysql_error());

    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $info['avg_place'] = $row['AvgPlace'];
        $info['best_place'] = $row['BestPlace'];
        $info['tasks'] = $row['TotalTasks'];
        $info['goal_perc'] = round($row['InGoal'] * 100 / $row['TotalTasks'],1) . '%';
        $info['goal_speed'] = round($row['GoalSpeed'], 1);
    }

    return $info;
}

function get_pilot_tracks($link, $pilPk, $ladPk)
{
    $topnat = [];
    $sql = "select T.tasPk, max(T.tarScore) as topNat 
            from tblTaskResult T, tblTrack TL, tblPilot P
            where T.traPk=TL.traPk and TL.pilPk=P.pilPk and P.pilNationCode='AUS'
            group by tasPk";
    $result = mysql_query($sql,$link) or json_die('Top National Query: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $topnat[$row['tasPk']] = $row['topNat'];
    }

    // Select from the main database of results
    $sql = "select 0 as extPk, TR.tarScore,
        TP.pilPk, L.ladIncExternal, L.ladStart, L.ladEnd,
        TK.tasPk, TK.tasName, TK.tasDate, TK.tasQuality, 
        C.comName, C.comDateTo, LC.lcValue, TR.tarScore,
        power(L.ladDepreciation,  TIMESTAMPDIFF(YEAR, C.comDateTo, 
            case when L.ladEnd is not null then L.ladEnd else CURDATE() end)) 
            * TR.tarScore * LC.lcValue * TK.tasQuality as ladScore,
        (TR.tarScore * LC.lcValue * power(L.ladDepreciation,
                TIMESTAMPDIFF(YEAR, C.comDateTo, case when L.ladEnd is not null then L.ladEnd else CURDATE() end)) 
                    / (TK.tasQuality * LC.lcValue)) as validity
from    tblLadderComp LC 
        join tblLadder L on L.ladPk=LC.ladPk
        join tblCompetition C on LC.comPk=C.comPk
        join tblTask TK on C.comPk=TK.comPk
        join tblTaskResult TR on TR.tasPk=TK.tasPk
        join tblTrack TT on TT.traPk=TR.traPk
        join tblPilot TP on TP.pilPk=TT.pilPk
    where LC.ladPk=$ladPk and TK.tasDate > L.ladStart 
    and TK.tasDate < case when L.ladEnd is not null then L.ladEnd else CURDATE() end
    and TP.pilPk=$pilPk
    and TP.pilNationCode=L.ladNationCode 
    order by TP.pilPk, C.comPk, (TR.tarScore * LC.lcValue * TK.tasQuality) desc";

    $result = mysql_query($sql,$link) or json_die('Ladder query failed: ' . mysql_error());
    $tracks = [];
	$incexternal = 0;
    $end = '';
    $start = '';

    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
		$nrow = [];

        $nrow[] = $row['tasDate'];
        $nrow[] = $row['comName'] . ' - ' . $row['tasName'];
        $nrow[] =  $row['lcValue'];
        $nrow[] = round($row['tasQuality'], 2);
        $nrow[] = round($row['tarScore'], 0);
        $nrow[] = round($topnat[$row['tasPk']], 0);
        if ($topnat[$row['tasPk']] != 0)
        {
            $nrow[] = round($row['ladScore']/$topnat[$row['tasPk']], 0);
        }
        else
        {
            $nrow[] = 0;
        }
		$incexternal = $row['ladIncExternal'];
        $start = $row['ladStart'];
        $end = $row['ladEnd'];

        $tracks[] = $nrow;
    }


    // Add external task results (to 1/3 of validity)
    if ($incexternal > 0)
    {
        $sql = "select TK.extPk, TK.extURL as tasPk,
        TP.pilPk, TP.pilLastName, TP.pilFirstName, TP.pilNationCode, TP.pilHGFA, TP.pilSex,
        TK.tasName, TK.tasQuality, TK.comName, TK.comDateTo, TK.lcValue, ER.etrScore, TK.tasTopScore,
        case when date_sub('$end', INTERVAL 365 DAY) > TK.comDateTo 
        then (ER.etrScore * TK.lcValue * 0.90 * TK.tasQuality) 
        else (ER.etrScore * TK.lcValue * TK.tasQuality) end as ladScore, 
        (ER.etrScore * TK.lcValue * (case when date_sub('$end', INTERVAL 365 DAY) > TK.comDateTo 
            then 0.90 else 1.0 end) / (TK.tasQuality * TK.lcValue)) as validity
        from tblExtTask TK
        join tblExtResult ER on ER.extPk=TK.extPk
        join tblPilot TP on TP.pilPk=ER.pilPk
        WHERE TK.comDateTo > '$start' and TK.comDateTo < '$end'
        and TP.pilPk=$pilPk
        order by TP.pilPk, TK.extPk, (ER.etrScore * TK.lcValue * TK.tasQuality) desc";
        $result = mysql_query($sql,$link) or json_die('Ladder (external) query failed: ' . mysql_error());
        while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
        {
			$nrow = [];

        	$nrow[] = $row['comDateTo'];
        	$nrow[] = $row['comName'] . ' - ' . $row['tasName'];
        	$nrow[] = $row['lcValue'];
        	$nrow[] = round($row['tasQuality'],2);
	        $nrow[] = round($row['etrScore'], 0);
	        $nrow[] = $row['tasTopScore'];
            if ($row['tasTopScore'] > 0)
            {
        	    $nrow[] = round($row['ladScore']/$row['tasTopScore'], 0);
            }
            else
            {
        	    $nrow[] = 0;
            }

        	$tracks[] = $nrow;
        }
	}

    return $tracks;
}

$pilot = get_pilot_info($link, $pilPk);
$tracks = get_pilot_tracks($link, $pilPk, $ladPk);
$data = [ 'info' => $pilot, 'data' => $tracks ];
$res = json_encode($data);
print $res;
?>

