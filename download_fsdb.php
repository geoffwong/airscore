<?php
require 'authorisation.php';
require 'xcdb.php';

$link = db_connect();
$comPk = reqival('comPk');

$authorised = check_auth('system');
if (!$authorised)
{
    $res = [];
    $res['result'] = 'unauthorised';
    print json_encode($res);
    return;
}

$tmpfile = tempnam("/tmp", "fsdb");
$compinfo = get_comformula($link, $comPk);
$comname =  $compinfo['comName'];

exec(BINDIR . "fsdb_export.pl $comPk $tmpfile", $out, $retv);

header("Content-type: text/xml");
header("Content-Disposition: attachment; filename=\"$comname.fsdb\"");
header("Cache-Control: no-store, no-cache");

$out = file_get_contents($tmpfile);

print $out;
?>
