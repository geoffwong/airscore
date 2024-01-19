<?php
require 'authorisation.php';
require 'xcdb.php';

function find_pilot($hgfanum, $hgfa)
{
    $query = "select pilPk from tblPilot where pilHGFA=$hgfanum";
    $result = mysql_query($query) or json_die("Can't find pilot $hgfa: " . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        $pilPk=mysql_result($result,0,0);
        if ($pilPk > 0) return $pilPk;
    }

    $query = "select pilPk from tblPilot where pilCIVL=$hgfanum";
    $result = mysql_query($query) or json_die("Can't find pilot $hgfa: " . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        $pilPk=mysql_result($result,0,0);
        if ($pilPk > 0) return $pilPk;
    }

    $query = "select pilPk from tblPilot where pilLastName like '%$hgfa%'";
    $result = mysql_query($query) or json_die("Can't find pilot $hgfa: " . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        $pilPk=mysql_result($result,0,0);
        if ($pilPk > 0) return $pilPk;
    }

    json_die("Can't add pilot $hgfa - check pilot database: " . mysql_error());
}

$usePk = auth('system');
$link = db_connect();
$res = [];
$comPk = reqival('comPk');
$tasPk = reqival('tasPk');

if (!is_admin('admin',$usePk,$comPk))
{
   $res['result'] = 'unauthorised';
   print json_encode($res);
   return;
}

$tarPk = reqival('tarpk');
$glider = reqsval("glider");
$dhv = en2dhv(reqsval("enrating"));
$hgfa = reqsval("hgfa");
$hgfanum = reqival("hgfa");

$dist = reqfval("dist");
$penalty = reqival("penalty");
if ($dist > 0)
{
    $resulttype = 'lo';
    $dist = $dist * 1000;
}
else
{
    $resulttype = reqsval("result");
}

if ($tarPk > 0)
{
    $query = "update tblTaskResult set tarDistance=$dist, tarPenalty=$penalty, tarResultType='$resulttype' where tarPk=$tarPk";
    $result = mysql_query($query) or json_die('Task Result update failed: ' . mysql_error());
    $query = "select traPk from tblTaskResult where tarPk=$tarPk";
    $result = mysql_query($query) or json_die('Track query failed: ' . mysql_error());
    $traPk=mysql_result($result,0,0);
    $query = "update tblTrack set traGlider='$glider', traDHV='$dhv' where traPk=$traPk";
    $result = mysql_query($query) or json_die("Glider update failed ($query): " . mysql_error());
}
else
{
    // add result - 
    $pilPk = find_pilot($hgfanum, $hgfa);

    if ($resulttype == 'dnf' || $resulttype == 'abs')
    {
        $dist = 0.0;
    }

    $query = "select tasDate from tblTask where tasPk=$tasPk";
    $result = mysql_query($query) or json_die('Task date failed: ' . mysql_error());
    $tasDate = mysql_result($result,0,0);

    $query = "insert into tblTrack (pilPk,traGlider,traDHV,traDate,traStart,traLength) values ($pilPk,'$glider','$dhv','$tasDate','$tasDate',$dist)";
    $result = mysql_query($query) or json_die('Insert track result failed: ' . mysql_error());

    $maxPk = mysql_insert_id();
    $query = "insert into tblTaskResult (tasPk,traPk,tarDistance,tarPenalty,tarResultType) values ($tasPk,$maxPk,$dist,$penalty,'$resulttype')";
    $result = mysql_query($query) or json_die("Insert result failed ($query): " . mysql_error());

   $res['result'] = 'ok';
   $res['pilPk'] = $pilPk;
   $res['tasPk'] = $tasPk;
   $res['traPk'] = $maxPk;
   print json_encode($res);
   return;
}

# recompute every time?
$out = '';
$retv = 0;
exec(BINDIR . "task_score.pl $tasPk", $out, $retv);

if ($retv == 0)
{
   $res['result'] = 'ok';
   print json_encode($res);
   return;
}
else
{
   $res['result'] = 'error';
   $res['output'] = $out;
   print json_encode($res);
}
?>
