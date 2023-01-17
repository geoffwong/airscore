<?php
require 'authorisation.php';
require 'xcdb.php';

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
if ($tarPk < 1)
{
   $res['result'] = 'error';
   $res['output'] = 'Unknown task result';
   print json_encode($res);
}

$query = "select traPk from tblTaskResult where tarPk=$tarPk";
$result = mysql_query($query) or json_die('Track query failed: ' . mysql_error());
$traPk=mysql_result($result,0,0);

$query = "select tarPk from tblTaskResult where traPk=$traPk";
$result = mysql_query($query) or json_die('Multi-result query failed: ' . mysql_error());

$nrows = mysql_num_rows($result);
if ($nrows == 1)
{
    $out = '';
    $retv = 0;
    exec(BINDIR . "del_track.pl $traPk", $out, $retv);

    # recompute every time?
    if ($retv != 0)
    {
        $res['result'] = 'error';
        $res['output'] = $out;
        print json_encode($res);
    }
}
else if ($nrows > 1)
{
    $query = "delete from tblTaskResult where tarPk=$tarPk";
    $result = mysql_query($query) or json_die('Task result removal failed: ' . mysql_error());
}

$out = '';
$retv = 0;
exec(BINDIR . "task_score.pl $tasPk", $out, $retv);# recompute every time?

if ($retv != 0)
{
   $res['result'] = 'error';
   $res['output'] = $out;
   print json_encode($res);
}
else
{
   $res['result'] = 'ok';
   print json_encode($res);
   return;
}

?>
