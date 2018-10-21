<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';

$usePk = auth('system');
$link = db_connect();

if (reqexists('add'))
{
    $fai = reqival('hgfa');
    $civl = reqival('civl');
    $lname = reqsval('lname');
    $fname = reqsval('fname');
    $sex = reqsval('sex');
    $nat = reqsval('nation');
    $query = "insert into tblPilot (pilHGFA, pilCIVL, pilLastName, pilFirstName, pilSex, pilNationCode) value ($fai,$civl,'$lname','$fname','$sex','$nat')";
    $result = mysql_query($query) or die('Pilot insert failed: ' . mysql_error());
}

if (reqexists('bulkadd'))
{
    $out = '';
    $retv = 0;
    $copyname = tempnam(FILEDIR, $name . "_");
    copy($_FILES['bulkpilots']['tmp_name'], $copyname);
    //echo "bulk_pilot_import.pl $copyname<br>";
    chmod($copyname, 0644);

    exec(BINDIR . "bulk_pilot_import.pl $copyname", $out, $retv);
}

if (reqexists('update'))
{
    $id = reqival('update');
    $fai = reqival("hgfa");
    $civl = reqival("civl");
    $lname = reqsval("lname");
    $fname = reqsval("fname");
    $sex = reqsval("sex");
    $nat = reqsval("nation");
    $query = "update tblPilot set pilHGFA=$fai, pilCIVL=$civl, pilLastName='$lname', pilFirstName='$fname', pilSex='$sex', pilNationCode='$nat' where pilPk=$id";
    $result = mysql_query($query) or die('Pilot update failed: ' . mysql_error());
}

if (reqexists('delete'))
{
    check_admin('admin',$usePk,-1);
    $id = reqival('delete');
    // Need to ensure pilot isn't in track table ..
    $query = "select * from tblTrack where pilPk=$id";
    $result = mysql_query($query) or die('Pilot track check failed: ' . mysql_error());
    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        return;
    }
    $query = "delete from tblPilot where pilPk=$id";
    $result = mysql_query($query) or die('Config update failed: ' . mysql_error());
}

echo "{ \"result\" : \"OK\" }";
?>

