<?php

function add_task_result($tasPk, $resulttype, $fai, $penalty, $glider, $dhv)
{
    if ($fai > 0)
    {
        $query = "select pilPk from tblPilot where pilHGFA=$fai";
        $result = mysql_query($query) or die('Query pilot (fai) failed: ' . mysql_error());
    }

    // @todo - check if pilot already has a flight in the task

    if (mysql_num_rows($result) == 0)
    {
        $fai = addslashes($_REQUEST['fai']);
        $query = "select P.pilPk from tblComTaskTrack T, tblTrack TR, tblPilot P 
                    where T.comPk=$comPk 
                        and T.traPk=TR.traPk 
                        and TR.pilPk=P.pilPk 
                        and P.pilLastName='$fai'";
        $result = mysql_query($query) or die('Query pilot (name) failed: ' . mysql_error());
    }

    if (mysql_num_rows($result) > 0)
    {
        $pilPk = mysql_result($result,0,0);
        $flown = floatval($_REQUEST["flown"]) * 1000;

        if ($resulttype == 'dnf' || $resulttype == 'abs')
        {
            $flown = 0.0;
        }

        $query = "select tasDate from tblTask where tasPk=$tasPk";
        $result = mysql_query($query) or die('Task date failed: ' . mysql_error());
        $tasDate=mysql_result($result,0,0);

        $query = "insert into tblTrack (pilPk,traGlider,traDHV,traDate,traStart,traLength) values ($pilPk,'$glider','$dhv','$tasDate','$tasDate',$flown)";
        $result = mysql_query($query) or die('Track Insert result failed: ' . mysql_error());

        $maxPk = mysql_insert_id();

        #$query = "select max(traPk) from tblTrack";
        #$result = mysql_query($query) or die('Max track failed: ' . mysql_error());
        #$maxPk=mysql_result($result,0);

        $query = "insert into tblTaskResult (tasPk,traPk,tarDistance,tarPenalty,tarResultType) values ($tasPk,$maxPk,$flown,$penalty,'$resulttype')";
        $result = mysql_query($query) or die('Insert result failed: ' . mysql_error());

        $out = '';
        $retv = 0;
        exec(BINDIR . "task_score.pl $tasPk", $out, $retv);
    }
    else
    {
        echo "Unknown pilot: $fai<br>";
    }
}

$tasPk = reqival("tasPk");
$resulttype = reqsval("resulttype");
$fai = reqival('fai');
$penalty = reqival("penalty");
$glider = reqsval("glider");
$dhv = reqsval("dhv");

add_task_result($tasPk, $resulttype, $fai, $penalty, $glider, $dhv);


?>

