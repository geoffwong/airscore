<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';

function get_region_centre($comPk)
{
    $sql = "SELECT RW.* FROM tblCompetition C, tblRegion R, tblRegionWaypoint RW WHERE C.comPk=$comPk and C.regPk=R.regPk and R.regCentre=RW.rwpPk";
    $result = mysql_query($sql, $link) or json_die('Region centre query failed: ' . mysql_error());
    return  mysql_fetch_array($result, MYSQL_ASSOC);
}

function submit_pin($link)
{
    $hgfa = reqsval('hgfanum');
    $name = strtolower(reqsval('lastname'));

    $lat = reqsval("lat");
    $lon = reqsval("lon");

    $query = "select pilPk, pilHGFA from tblPilot where pilLastName='$name'";
    $result = mysql_query($query, $link) or json_die('Pilot query failed: ' . mysql_error());

    $member = 0;
    while ($row=mysql_fetch_array($result, MYSQL_ASSOC))
    {
        if ($hgfa == $row['pilHGFA'])
        {
            $pilPk = $row['pilPk'];
            $member = 1;
        }
    }

#    if ($restrict == 'registered')
#    {
#        $query = "select * from tblRegistration where comPk=$comid and pilPk=$pilPk";
#        $result = mysql_query($query) or die('Registration query failed: ' . mysql_error());
#        if (mysql_num_rows($result) == 0)
##        {
#            $member = 0;
#        }
#    }
##

#    $gmtimenow = time() - (int)substr(date('O'),0,3)*60*60;
#    if ($gmtimenow > ($until + 7*24*3600))
#    {
#        echo "<b>The submission period for tracks has closed ($until).</b><br>\n";
#        echo "Contact $contact if you're having an access problem.<br>\n";
#        echo "</div></body></html>\n";
#        exit(0);
#    }

    if ($member == 0)
    {
        json_die("Only registered pilots may submit tracks\n");
    }

    // add two point track (start+end).
    $task = reqsval('tasPk');
    $query = "select tasDate from tblTask where tasPk=$task";
    $result = mysql_query($query, $link) or json_die('Task date failed: ' . mysql_error());
    if (mysql_num_rows($result) == 0)
    {
        json_die("Unable to submit pin to unknown task<br>\n");
    }
    $tasDate=mysql_result($result,0,0);
    $glider = reqsval('glider');
    $dhv = reqsval('dhv');

    $query = "insert into tblTrack (pilPk,traGlider,traDHV,traDate,traStart,traLength) values ($pilPk,'$glider','$dhv','$tasDate','$tasDate',0)";
    $result = mysql_query($query, $link) or json_die('Track Insert result failed: ' . mysql_error());
    $maxPk = mysql_insert_id();

    $safety = reqsval('pilotsafety');
    $quality = reqival('pilotquality');
    if ($safety == "none" && $quality > 0)
    {
        $query = "update tblTrack set traConditions='$quality' where traPk=$maxPk";
    }
    elseif ($safety != "none" && $quality > 0)
    {
        $query = "update tblTrack set traSafety='$safety', traConditions='$quality' where traPk=$maxPk";
    }
    $result = mysql_query($query, $link) or json_die('Update tblTrack failed: ' . mysql_error());

    $t1 = 43200;
    $t2 = 46800;

    $comPk = reqival("comPk");
    $centre = get_region_centre($comPk);
    $xlat = $centre['rwpLatDecimal'];
    $xlon = $centre['rwpLongDecimal'];
    $query = "insert into tblTrackLog (traPk, trlLatDecimal, trlLongDecimal, trlTime) VALUES ($maxPk,$xlat,$xlon,$t1),($maxPk,$lat,$lon,$t2)";
    $result = mysql_query($query, $link) or json_die('Tracklog insert failed: ' . mysql_error());

    $query = "insert into tblWaypoint (traPk, wptLatDecimal, wptLongDecimal, wptTime, wptPosition) VALUES ($maxPk,$xlat,$xlon,$t1,1),($maxPk,$lat,$lon,$t2,2)";
    $result = mysql_query($query, $link) or json_die('Waypoint insert failed: ' . mysql_error());


    $query = "insert into tblComTaskTrack (comPk,tasPk,traPk) values ($comPk,$task,$maxPk)";
    $result = mysql_query($query, $link) or json_die('ComTaskTrack failed: ' . mysql_error());

    $out = '';
    $retv = 0;
    exec(BINDIR . "optimise_flight.pl $maxPk $comPk $task 0", $out, $retv);

    #$query = "select * from tblTrack where traPk=$maxPk";
    #$result = mysql_query($query) or json_die('Select length failed: ' . mysql_error());
    #$row=mysql_fetch_array($result);
    #$flown = $row['traLength'];
    #$query = "insert into tblTaskResult (tasPk,traPk,tarDistance,tarPenalty,tarResultType) values ($tasPk,$maxPk,$flown,0,'lo')";
    #$result = mysql_query($query) or die('Result insert failed: ' . mysql_error());

    $res = [];
    $res['result'] = 'ok';
    $res['comPk'] = $comPk;
    $res['traPk'] = $maxPk;
    if ($task > 0)
    {
        $res['tasPk'] = $task;
    }

    print json_encode($res);
}

$link = db_connect();
submit_pin($link);

?>

