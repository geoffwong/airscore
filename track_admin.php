<html>
<head>
<link HREF="xcstyle.css" REL="stylesheet" TYPE="text/css">
</head>
<body>
<div id="container">
<div id="vhead"><h1>airScore admin</h1></div>
<?php
require_once 'format.php';
require 'authorisation.php';
$comPk = reqival('comPk');
$filter = reqsval('filter');
adminbar($comPk);

if ($comPk > 0)
{
    echo "<p><h2>Track Administration ($comPk)</h2></p>";
}
else
{
    echo "<p><h2>Track Administration (global)</h2></p>";
}

$usePk=auth('system');
$link = db_connect();

if (reqexists('delete'))
{
    $id = reqival('delete');
    echo "Delete track: $id<br>";

    $lco = -1;
    $shared = 0;
    $comcl = '';

    # Do we have access to the track?
    if ($comPk > 0)
    {
        $comcl = " and comPk=$comPk";
    }
    $query = "SELECT TK.comPk FROM tblTask TK, tblTaskResult TR where TR.tasPk=TK.tasPk and TR.traPk=$id$comcl";
    $result = mysql_query($query, $link) or die('Cant get track info: ' . mysql_error());
    if (mysql_num_rows($result) == 0)
    {
        $query = "SELECT comPk FROM tblComTaskTrack where traPk=$id$comcl";
        $result = mysql_query($query, $link) or die('Cant get track info: ' . mysql_error());
    }
    #if (mysql_num_rows($result) == 0)
    #{
    #    die("You cannot delete tracks for that competition (usePk=$usePk,comPk=$lco)<br>");
    #    return;
    #}
    if ($comPk > 0)
    {
        $lco = $comPk;
    }
    if (!is_admin('admin',$usePk, $lco))
    {
        die("You cannot delete tracks for that competition (usePk=$usePk,comPk=$comPk)<br>");
        return;
    }

    # Shared track?
    $query = "SELECT TK.comPk FROM tblTask TK, tblTaskResult TR where TR.tasPk=TK.tasPk and TR.traPk=$id";
    $result = mysql_query($query, $link) or die('Cant get track info: ' . mysql_error());
    if (mysql_num_rows($result) > 1 && $comPk > 0)
    {
        $shared = 1;
    }

    $query = "SELECT comPk FROM tblComTaskTrack where traPk=$id";
    $result = mysql_query($query, $link) or die('Cant get track info: ' . mysql_error());
    if (mysql_num_rows($result) > 1 && $comPk > 0)
    {
        $shared = 1;
    }

    if ($shared == 0)
    {
        $query = "delete from tblTaskResult where traPk=$id";
        $result = mysql_query($query, $link) or die('TaskResult delete failed: ' . mysql_error());

        $query = "delete from tblTrack where traPk=$id";
        $result = mysql_query($query, $link) or die('Track delete failed: ' . mysql_error());

        $query = "delete from tblTrackLog where traPk=$id";
        $result = mysql_query($query, $link) or die('Tracklog delete failed: ' . mysql_error());

        $query = "delete from tblWaypoint where traPk=$id";
        $result = mysql_query($query, $link) or die('Waypoint delete update failed: ' . mysql_error());

        $query = "delete from tblBucket where traPk=$id";
        $result = mysql_query($query, $link) or die('Bucket delete failed: ' . mysql_error());
    }
    else
    {
        $query = "delete from tblTaskResult using tblTaskResult, tblTask where tblTaskResult.tasPk=tblTask.tasPk and tblTaskResult.traPk=$id and tblTask.comPk=$comPk";
        $result = mysql_query($query, $link) or die('TaskResult delete failed: ' . mysql_error());
    }

    $query = "delete from tblComTaskTrack where traPk=$id$comcl";
    $result = mysql_query($query, $link) or die('ComTaskTrack delete failed: ' . mysql_error());
}

$fclause = '';

if ($comPk > 0)
{
    $query = "select comType from tblCompetition where comPk=$comPk";
    $result = mysql_query($query, $link) or die('Com type query failed: ' . mysql_error());
    $comType = mysql_result($result, 0, 0);

    if (sizeof($filter) > 0)
    {
        $fclause = "and P.pilLastName like '%$filter%'";
    }

    if ($comType == 'RACE')
    {
        #$sql = "SELECT T.*, P.* FROM tblTaskResult CTT left join tblTrack T on CTT.traPk=T.traPk left outer join tblPilot P on T.pilPk=P.pilPk where CTT.tasPk in (select tasPk from tblTask TK where TK.comPk=$comPk) order by T.traStart desc";
        $sql = "(
                SELECT T.*, P.* FROM tblTrack T 
                    left outer join tblTaskResult CTT on CTT.traPk=T.traPk 
                    left outer join tblPilot P on T.pilPk=P.pilPk 
                where CTT.tasPk in (select tasPk from tblTask TK where TK.comPk=$comPk) $fclause
            )
            union
            (
                SELECT T.*, P.* FROM tblComTaskTrack CTT 
                    join tblTrack T on CTT.traPk=T.traPk 
                    left outer join tblPilot P on T.pilPk=P.pilPk 
                where CTT.comPk=$comPk $fclause
            ) 
            order by traStart desc";
    }
    else
    {
        $sql = "SELECT T.*, P.* 
            FROM tblComTaskTrack CTT 
            join tblTrack T 
                on CTT.traPk=T.traPk 
            left outer join tblPilot P 
                on T.pilPk=P.pilPk 
            where CTT.comPk=$comPk $fclause order by T.traStart desc";
    }
    echo "<form action=\"track_admin.php?comPk=$comPk\" name=\"trackadmin\" method=\"post\">";
}
else
{
    $limit = '';
    if (!reqexists('limit'))
    {
        $limit = ' limit 100';
    }
    else
    {
        $limval = reqival('limit');
        if ($limval > 0)
        {
            $limit = " limit $limval";
        }
    }
    if (sizeof($filter) > 0)
    {
        $fclause = "where P.pilLastName like '%$filter%'";
    }

    $sql = "SELECT T.*, P.*, CTT.comPk, CTT.tasPk from
        tblTrack T
        left outer join tblPilot P on T.pilPk=P.pilPk
        left outer join tblComTaskTrack CTT on CTT.traPk=T.traPk
        $fclause
        order by T.traPk desc$limit";
    
#    $sql = "SELECT T.*, P.*, CTT.comPk 
#            from tblComTaskTrack CTT 
#            left join tblTrack T on CTT.traPk=T.traPk 
#            left outer join tblPilot P on T.pilPk=P.pilPk 
#            order by T.traPk desc$limit";

    echo "<form action=\"track_admin.php\" name=\"trackadmin\" method=\"post\">";
}

$result = mysql_query($sql,$link) or die ("Track query failed: " . mysql_error());

$count = 1;
echo "&nbsp;&nbsp;&nbsp";
echo "&nbsp;&nbsp;&nbsp";
echo fin('filter','', 30);
echo fis('Filter','Filter Surname', 10);
echo "&nbsp;&nbsp;&nbsp";
echo "<ol>";
while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $id = $row['traPk'];
    $dist = round($row['traLength']/1000,2);
    $name = $row['pilFirstName'] . " " . $row['pilLastName'];
    $date = $row['traStart'];
    $cpk = 0;
    $tpk = '';
    if (array_key_exists('comPk', $row))
    {
        $cpk = $row['comPk'];
    }
    if (array_key_exists('tasPk', $row))
    {
        if ($row['tasPk'])
        {
            $tpk = '&tasPk=' . $row['tasPk'];
        }
    }
    if ($cpk == 0)
    {
        $cpk = $comPk;
    }
    echo "<li><button type=\"submit\" name=\"delete\" value=\"$id\">del</button>";
    echo "<a href=\"tracklog_map.html?trackid=$id&comPk=$cpk$tpk\"> $dist kms by $name at $date.</a><br>\n";
    # echo a delete button ...

    $count++;
}
echo "</ol>";
echo "</form>";
?>
</div>
</body>
</html>

