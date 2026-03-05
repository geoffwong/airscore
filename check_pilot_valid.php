<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

function check_pilot_exists($link, $lastname, $civlid)
{
    $idselect = reqsval('idselect');
    $name = strtolower(trim($lastname));
    $pilPk = 0;

    $member = 1;
    $link = db_connect();

    $query = "select pilPk, pilHGFA, pilCIVL from tblPilot where pilLastName='$name'";
    $result = mysql_query($query) or json_die('Query failed: ' . mysql_error());
    while ($row=mysql_fetch_array($result, MYSQL_ASSOC))
    {
        if ($civlid == $row['pilCIVL'])
        {
            $pilPk = $row['pilPk'];
            $idselect = 'CIVL';
        }
        elseif ($civlid == $row['pilHGFA'])
        {
            $pilPk = $row['pilPk'];
            $idselect = 'HGFA';
        }
    }

    return ($pilPk != 0);
}

$link = db_connect();

$lastname = reqsval('lastname');
$civlid = reqival('civlid');
$pilot_valid = false;

// check pilot exists..
if ($civlid != 0)
{
    $pilot_valid = check_pilot_exists($link, $lastname, $civlid);
}

$data = [ 'valid' => $pilot_valid ];
$res = json_encode($data);
print $res;
?>

