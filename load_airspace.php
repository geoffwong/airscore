<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');


require_once 'authorisation.php';
require_once 'dbextra.php';

$authorised = check_auth('system');
$link = db_connect();

$upfile = $_FILES['waypoints']['tmp_name'];
$out = '';
$retv = 0;
exec(BINDIR . "airspace_openair.pl $upfile", $out, $retv);

if ($retv)
{
    json_die(implode("\n", $out));
    exit(0);
}

$res = [];
$res['result'] = "ok";
$res['output'] = implode("\n", $out);
print json_encode($res);
?>

