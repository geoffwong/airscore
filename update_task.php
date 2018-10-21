<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';


function update_task($link,$tasPk, $old)
{
    $out = '';
    $retv = 0;

    // Get the old values
    $oldstart = $old['tasStartTime'];
    $oldclose = $old['tasStartCloseTime'];
    $oldfinish = $old['tasFinishTime'];
    $oldstop = $old['tasStoppedTime'];
    $oldtype = $old['tasTaskType'];

    $sql = "select T.*, TW.* from tblTask T left outer join tblTaskWaypoint TW on T.tasPk=TW.tasPk and TW.tawType='goal' where T.tasPk=$tasPk";
    $result = mysql_query($sql,$link) 
        or die('Task not associated correctly with a competition: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);

    # we should re-verify all tracks if start/finish changed!
    $newstart = $row['tasStartTime'];
    $newclose = $row['tasStartCloseTime'];
    $newfinish = $row['tasFinishTime'];
    $newstop = $row['tasStoppedTime'];
    $newtype = $row['tasTaskType'];
    $goal = $row['tawType'];

    # FIX: how about Free-bearing?
    if ($oldtype == 'olc' && ($newtype == 'free' || $newtype == 'free-pin'))
    {
        $out = '';
        $retv = 0;
        exec(BINDIR . "task_up.pl $tasPk 0", $out, $retv);
    }
    elseif (($oldtype == 'free' || $newtype == 'free-pin') && $newtype == 'olc')
    {
        $out = '';
        $retv = 0;
        exec(BINDIR . "task_up.pl $tasPk 3", $out, $retv);
    }
    elseif ($goal == 'goal' && ($newstart != $oldstart or $newfinish != $oldfinish or $oldtype != $newtype or $oldclose != $newclose or $oldstop != $newstop))
    {
        $out = '';
        $retv = 0;
        exec(BINDIR . "task_up.pl $tasPk", $out, $retv);
    }
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

    $query = "select tasStartTime, tasStartCloseTime, tasFinishTime, tasTaskType from tblTask where tasPk=$tasPk";
    $result = mysql_query($query) 
        or die('Task not associated with a competition: ' . mysql_error());
    $old = mysql_fetch_array($result);

    $Name = reqsval('taskname');
    $Date = reqsval('date');
    if (!sane_date($Date))
    {
        die("Unable to update task with illegal date: $Date");
    }

    // Task Start/Finish
    $TaskStart = reqsval('taskstart');
    if (strlen($TaskStart) < 10)
    {
        $TaskStart = $Date . ' ' . $TaskStart;
    }
    $FinishTime = reqsval('taskfinish');
    if (strlen($FinishTime) < 10)
    {
        $FinishTime = $Date . ' ' . $FinishTime;
    }

    // FIX: Launch Close
    // Start gate open/close
    $StartTime = reqsval('starttime');
    if (strlen($StartTime) < 10)
    {
        $StartTime = $Date . ' ' . $StartTime;
    }
    $StartClose = reqsval('startclose');
    if (strlen($StartClose) < 10)
    {
        $StartClose = $Date . ' ' . $StartClose;
    }

    $TaskType = reqsval('tasktype');
    $Interval = reqival('interval');
    $regPk = addslashes($_REQUEST['region']);
    $departure = addslashes($_REQUEST['departure']);
    $arrival = addslashes($_REQUEST['arrival']);
    $height = addslashes($_REQUEST['height']);
    $comment = reqsval('taskcomment');

    $query = "update tblTask set tasName='$Name', tasDate='$Date', tasTaskStart='$TaskStart', tasStartTime='$StartTime', tasStartCloseTime='$StartClose', tasFinishTime='$FinishTime', tasTaskType='$TaskType', regPk=$regPk, tasSSInterval=$Interval, tasDeparture='$departure', tasArrival='$arrival', tasHeightBonus='$height', tasComment='$comment' where tasPk=$tasPk";
    $result = mysql_query($query) or die('Task add failed: ' . mysql_error());

    $TaskStopped = reqsval('taskstopped');
    if (strlen($TaskStopped) < 10 && strlen($TaskStopped) > 2)
    {
        $TaskStopped = $Date . ' ' . $TaskStopped;
    }

    if (strlen($TaskStopped) > 2)
    {
        $query = "update tblTask set tasStoppedTime='$TaskStopped' where tasPk=$tasPk";
        $result = mysql_query($query) or die('Task add failed: ' . mysql_error());
    }

    update_task($link, $tasPk, $old);
    #update_tracks($link,$tasPk);

$res['result'] = "ok";
print json_encode($res);
?>

