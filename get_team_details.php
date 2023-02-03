<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

$link = db_connect();
$usePk = check_auth('system');
$comPk = reqival('comPk');

function get_all_pilots($link, $comPk)
{
    $sql = "select P.pilPk, P.pilLastName, P.pilFirstName, P.pilPk, P.pilSex, P.pilFlightWeight, 1 as tepModifier from tblComTaskTrack CTT, tblTrack T, tblPilot P where CTT.comPk=$comPk and T.traPk=CTT.traPk and P.pilPk=T.pilPk group by pilPk order by P.pilLastName";

    $result = mysql_query($sql,$link) or json_die('get_all_pilots failed: ' . mysql_error());

    $pilots = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $pilots[] = $row;
    }

    return $pilots;
}

function get_all_teams($link,$comPk)
{

    $sql = "select T.teaPk, T.teaName, P.pilPk, P.pilLastName, P.pilFirstName, P.pilSex, P.pilFlightWeight, TP.tepModifier from 
        tblTeam T left outer join tblTeamPilot TP on TP.teaPk=T.teaPk
        left outer join tblPilot P on P.pilPk=TP.pilPk
        where T.comPk=$comPk
        order by T.teaName, P.pilPk";
    $result = mysql_query($sql,$link) or json_die('get_all_teams failed: ' . mysql_error());

    $teams = [];
    $members = [];
    $lastPk = 0;
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        if ($lastPk != $row['teaPk'])
        {
            if ($lastPk != 0)
            {
                $teams[$lastPk] = $members;
            }
            $members = [];
            $lastPk = $row['teaPk'];
        }

        $members[] = $row;
    }
    if ($lastPk != 0)
    {
        $teams[$lastPk] = $members;
    }

    return $teams;
}

$pilots = get_all_pilots($link, $comPk);
$teams = get_all_teams($link, $comPk);
$data = [ 'pilots' => $pilots, 'teams' => $teams ];
$res = json_encode($data);
print $res;
?>

