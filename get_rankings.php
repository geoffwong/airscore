<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';

function faidb_connect()
{
    $link = mysql_connect('127.0.0.1', 'root', 'ecit5lo5')
    or die('Could not connect: ' . mysql_error());
    mysql_select_db('fai') or die('Could not select database');
    return $link;
}


function ladder_result($name, $dateto, $offset, $limit)
{
    $sql = "select LR.*,P.* from tblLadder L, tblLadderResult LR, tblPilot P where L.ladName='$name' and L.ladDateTo='$dateto' and LR.ladPk=L.LadPk and P.pilPk=LR.pilPk order by LR.ldrPosition limit $offset, $limit";
    $result = mysql_query($sql) or die('Ladder result query failed: ' . mysql_error());
    $results = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $keyrow = [];
        $keyrow[] = $row['ldrPosition'];
        $keyrow[] = $row['pilLastName'];
        $keyrow[] = $row['pilCIVL'];
        $keyrow[] = $row['pilNation'];
        $keyrow[] = $row['ldrPoints'];
        $keyrow[] = $row['ldrSigma'];
        $results[]= $keyrow;
    }

    return $results;
}

$link = faidb_connect();
$name = reqsval('name');
$dateto = reqsval('date');
$offset = reqival('offset');
$limit = reqival('limit');
if ($limit < 1)
{
    $limit = 100;
}
$carr = [];


$sorted = ladder_result($name, $dateto, $offset, $limit);
$data = array( 'data' => $sorted );
print json_encode($data);
?>
