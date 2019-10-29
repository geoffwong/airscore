<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';


function add_ladder($link, $usePk)
{
    $lname = reqsval('laddername');
    $nation = reqsval('nation');
    $start = reqsval('dateto');
    $end = reqsval('datefrom');
    $method = reqsval('method');
    $param = reqival('param');
    $cclass = reqival('compclass');

    if ($lname == '')
    {
   		$res['result'] = 'error';
   		$res['reason'] = 'Can\'t create a ladder with no name';    
   		print json_encode($res);
   		return;
    }

    $query = "insert into tblLadder (ladName, ladNationCode, ladStart, ladEnd, ladHow, ladParam, ladClass) value ('$lname','$nation', '$start', '$end', '$method', $param, '$cclass')";
    $result = mysql_query($query) or die('Ladder insert failed: ' . mysql_error());
    $ladPk = mysql_insert_id();

    return $ladPk;
}

$usePk = auth('system');
$link = db_connect();
$res = [];
$comPk = reqival('comPk');

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$ladPk = add_ladder($link, $usePk);

$res['result'] = 'ok';
$res['comPk'] = $ladPk;

print json_encode($res);
?>

