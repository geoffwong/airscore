<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';


function save_team_members($link, $teaPk, $members)
{
    if (!sizeof($members) || !$teaPk)
    {
        json_die("Can't create a team with no members");
    }

    $query = "delete from tblTeamPilot where teaPk=$teaPk";
    $result = mysql_query($query, $link) or json_die('Delete existing members failed: ' . mysql_error());
    $pilPk = 0;

    $query = "insert into tblTeamPilot (teaPk, pilPk, tepModifier) values ";
    foreach ($members as $row)
    {
        if ($pilPk != 0)
        {
            $query = $query . ",";
        }
        $pilPk = $row[1];
        $modifier = $row[5];
        $query = $query . "($teaPk, $pilPk, $modifier)";
    }
    $result = mysql_query($query, $link) or json_die('Failed to insert new team members: ' . mysql_error());
}

$comPk = reqival('comPk');
$teaPk = reqival('teaPk');
$link = db_connect();
$usePk = check_auth('system');
$res = [];

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$members = json_decode($_REQUEST['members']);
save_team_members($link, $teaPk, $members);

$res['result'] = 'ok';
print json_encode($res);
?>

