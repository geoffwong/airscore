<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

function upload_track($hgfa, $file, $comid, $tasPk)
{
    # Let the Perl program do it!
    $out = '';
    $retv = 0;
    $traPk = 0;
    #exec(BINDIR . "igcreader.pl $file $pilPk", $out, $retv);
    exec(BINDIR . "add_track.pl $hgfa $file $comid $tasPk", $out, $retv);

    if ($retv)
    {
        if ($out)
        {
            if (!(strpos($out[0], 'Duplicate') === false))
            {
                $res['result'] = "duplicate";
                $res['output'] = $out[0];
            }
            else
            {
                $res['result'] = "failed";
                $res['output'] = serialize($out);
            }
            print json_encode($res);
            exit(0);
        }
        else
        {
            $res['result'] = "failed";
            print json_encode($res);
            exit(0);
        }
    }

    foreach ($out as $row)
    {
        if (substr_compare("traPk=6", $row, 0, 6) == 0)
        {
            $traPk = 0 + substr($row, 6);
            break;
        }
    }


    return $traPk;
}

function accept_track($comPk, $until, $restrict)
{
    //$file = addslashes($_REQUEST['userfile']);
    $hgfa = trim(reqsval('hgfanum'));
    $name = strtolower(trim(reqsval('lastname')));
    $route = reqival('route');

    $member = 1;

    $link = db_connect();

    $query = "select pilPk, pilHGFA from tblPilot where pilLastName='$name'";
    $result = mysql_query($query) or json_die('Query failed: ' . mysql_error());
    while ($row=mysql_fetch_array($result, MYSQL_ASSOC))
    {
        if ($hgfa == $row['pilHGFA'])
        {
            $pilPk = $row['pilPk'];
            $member = 1;
        }
    }

    if ($restrict == 'registered')
    {
        $query = "select * from tblRegistration where comPk=$comPk and pilPk=$pilPk";
        $result = mysql_query($query) or json_die('registration error');
        if (mysql_num_rows($result) == 0)
        {
            $member = 0;
        }
    }


    $gmtimenow = time() - (int)substr(date('O'),0,3)*60*60;
    if ($gmtimenow > ($until + 7*24*3600))
    {
        $res = [];
        $res['result'] = "closed";
        print json_encode($res);
        exit(0);
    }

    if ($member == 0)
    {
        $res = [];
        $res['result'] = "unregistered";
        $res['command'] = $query;
        print json_encode($res);
        exit(0);
    }

    // Copy the upload so I can use it later ..
    $dte = date("Y-m-d_Hms");
    $yr = date("Y");
    $copyname = FILEDIR . $yr . "/" . $name . "_" . $hgfa . "_" . $dte;
    copy($_FILES['userfile']['tmp_name'], $copyname);
    chmod($copyname, 0644);

    // Process the file
    //$maxPk = upload_track($_FILES['userfile']['tmp_name'], $pilPk, $comContact);
    $maxPk = upload_track($hgfa, $_FILES['userfile']['tmp_name'], $comPk, $route);

    $tasPk = 'null';
    $tasType = '';
    $comType = '';
    $turnpoints = '';

    $out = '';
    $retv = 0;

    $glider = reqsval('glider');
    $dhv = reqsval('dhv');
    $safety = reqsval('pilotsafety');
    $quality = reqival('pilotquality');
    if ($safety == "none")
    {
        $query = "update tblTrack set traGlider='$glider', traDHV='$dhv', traConditions='$quality' where traPk=$maxPk";
    }
    else
    {
        $query = "update tblTrack set traGlider='$glider', traDHV='$dhv', traSafety='$safety', traConditions='$quality' where traPk=$maxPk";
    }
    $result = mysql_query($query) or json_die('Update tblTrack failed: ' . mysql_error());

    return $maxPk;
}


#
# Main
#

$comPk = reqival('comid');
if ($comPk == 0)
{
    $comPk = reqival('comPk');
}

$link = db_connect();
$offerall = 0;

if ($comPk < 2)
{
    $offerall = 1;
    $comPk=1;
}


$comUnixTo = time() - (int)substr(date('O'),0,3)*60*60;
$query = "select *, unix_timestamp(date_sub(comDateTo, interval ComTimeOffset hour)) as comUnixTo  from tblCompetition where comPk=$comPk";
$result = mysql_query($query, $link) or json_die('Query failed: ' . mysql_error());
$comEntryRestrict = 'open';
if ($row=mysql_fetch_array($result, MYSQL_ASSOC))
{
    $comType = $row['comType'];
    $comClass = $row['comClass'];
    $comUnixTo = $row['comUnixTo'];
    $comEntryRestrict = $row['comEntryRestrict'];
}

$freepin = 0;
$query = "select * from tblTask where comPk=$comPk and tasTaskType='free-pin'";
$result = mysql_query($query, $link);
if (mysql_num_rows($result) > 0)
{
   $freepin = 1; 
}

$id = accept_track($comPk, $comUnixTo, $comEntryRestrict);

$query = "select tasPk from tblComTaskTrack where traPk=$id";
$result = mysql_query($query, $link);
if (mysql_num_rows($result) > 0)
{
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $tasPk = $row['tasPk'];
}

$res = [];
$res['result'] = 'ok';
$res['comPk'] = $comPk;
$res['traPk'] = $id;
if ($tasPk > 0)
{
    $res['tasPk'] = $tasPk;
}

print json_encode($res);
//redirect("tracklog_map.html?trackid=$id&comPk=$comPk&ok=1");
?>

