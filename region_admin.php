<html>
<head>
<link HREF="xcstyle.css" REL="stylesheet" TYPE="text/css">
</head>
<body>
<div id="container">
<div id="vhead"><h1>airScore admin</h1></div>
<?php
require 'authorisation.php';
require 'format.php';

function parse_latlong($str, $neg)
{
    $val = floatval($str);

    $ns = substr($str, -1,1);
    if ($ns == $neg)
    {
        $val = -$val;
    }
    // radians? $loc{'lat'} = $loc{'lat'} * $pi / 180;

    return $val;
}

function parse_gpsd_latlong($str, $neg)
{
    $fields = explode(" ", $str);
    $val = floatval($fields[1]) + floatval($fields[2] + floatval($fields[3])/60) / 60;

    $ns = $fields[0];
    if ($ns == $neg)
    {
        $val = -$val;
    }

    return $val;
}

# Add support for:
# OziExplorer Waypoint File Version 1.0
# WGS 84
# Reserved 2
# Reserved 3
#    1,03CARS        , -28.293917, 152.413861,40427.5383565,0, 1, 3, 0, 65535,CARRS LOOKOUT                           , 0, 0, 0, 3117
#
function parse_oziwpts($regPk, $link, $lines)
{
    $count = 1;
    for ($i = 0; $i < count($lines); $i++)
    {
        $fields = explode(",", $lines[$i]);
        if (0 + $fields[0] < $count) 
            continue;

        $count++;

        // waypoint
        $name = addslashes($fields[1]);
        $lat = parse_latlong($fields[2], "S");
        $long = parse_latlong($fields[3], "W");
        $alt = floatval($fields[14]) / 3.281;
        $desc = rtrim(addslashes($fields[10]));
        echo "$name $lat $long $alt $desc<br>";
        $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
        $result = mysql_query($sql,$link) or die('Insert RegionWaypoint failed: ' . mysql_error());
    }

    return $count;
}

function parse_gpsdump($regPk, $link, $lines)
{
    $count = 1;
    echo "region=$regPk<br>";
    for ($i = 0; $i < count($lines); $i++)
    {
        // waypoint
        $fields = $lines[$i];
        $name = addslashes(rtrim(substr($fields,0, 10)));
        $lat = parse_gpsd_latlong(substr($fields,10,13), "S");
        $long = parse_gpsd_latlong(substr($fields,27,14), "W");
        $alt = floatval(substr($fields,43,5));
        $desc = addslashes(rtrim(substr($fields,49)));
        echo "$name,$lat,$long,$alt,$desc<br>";
        if ($lat != 0.0)
        {
            $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
            $result = mysql_query($sql,$link) or die('Insert RegionWaypoint failed: ' . mysql_error());
            $count++;
        }
    }

    return $count;
}

function parse_kml($regPk, $link, $xml)
{
    for ($n = 0; $n < count($xml->Document->Folder->Placemark); $n++)
    {
        $placemark = $xml->Document->Folder->Placemark[$n];
        $name = $placemark->name;
        $arr = explode(",",$placemark->Point->coordinates);
        $lat = $arr[1];
        $long = $arr[0];
        $alt = $arr[2];
        $desc = $placemark->description;
        echo "$name $lat $long $alt $desc<br>";
        $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
        $result = mysql_query($sql,$link) or die('Insert RegionWaypoint failed: ' . mysql_error());
    }

    return $n;
}


function parse_waypoints($filen, $regPk, $link)
{
    $count = 0;
    $fh = fopen($filen, 'r');
    if (!$fh)
    {
        echo "Unable to read file<br>";
        return;
    }
    clearstatcache();
    $sz = filesize($filen);
    // echo "file=$filen filesize=$sz<br>";
    $data = fread($fh, $sz);
    fclose($fh);

    $lines = explode("\n", $data);
    if (substr($lines[0],0,10) == '$FormatGEO')
    {
        return parse_gpsdump($regPk, $link, array_slice($lines,1));
    }

    if (substr($lines[0],0,3) == "Ozi")
    {
        return parse_oziwpts($regPk, $link, $lines);
    }

    if (substr($lines[0],0,5) == "<?xml")
    {
        $xml = new SimpleXMLElement($data);
        return parse_kml($regPk, $link, $xml);
    }

    $param = [];
    for ($i = 0; $i < count($lines); $i++)
    {
        $fields = explode(" ", $lines[$i]);
        if ($fields[0] == "W")
        {
            // waypoint
            $name = addslashes($fields[2]);
            $lat = parse_latlong($fields[4], "S");
            $long = parse_latlong($fields[5], "W");
            $alt = floatval($fields[8]);
            $desc = rtrim(addslashes($fields[9]));
            echo "$name $lat $long $alt $desc<br>";
            $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
            $result = mysql_query($sql,$link) or die('Insert RegionWaypoint failed: ' . mysql_error());
            $count++;
        }
        else if ($fields[0] == "G")
        {
            // geodesic
            if (!($fields[1] == "WGS" && $fields[2] == "84"))
            {
                // ignore for now?
            }
        }
        else if ($fields[0] == "w")
        {
            // ignore?
        }
    }

    return $count;
}

function accept_waypoints($region,$link)
{
    $name = 'waypoints';

     // add the region ..
    $query = "select * from tblRegion where regDescription like '$region'";
    $result = mysql_query($query, $link) or json_die('Find existing region failed: ' . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        echo "<b>Region ($region) already exists with that name.<br>";
        echo "</div></body></html>\n";
        exit(0);
	}

    $query = "insert into tblRegion (regDescription) values ('$region')";
    $result = mysql_query($query) or die('Query failed: ' . mysql_error());
    $regPk = mysql_insert_id();

    #$query = "select max(regPk) from tblRegion";
    #$result = mysql_query($query) or die('Query failed: ' . mysql_error());
    #$regPk = mysql_result($result,0);

    // Copy the upload so I can use it later ..
    if ($_FILES['waypoints']['tmp_name'] != '')
    {
        $copyname = tempnam(FILEDIR, $name . "_");
        copy($_FILES['waypoints']['tmp_name'], $copyname);
        chmod($copyname, 0644);

        // Process the file
        if (!parse_waypoints($copyname, $regPk, $link))
        {
            echo "<b>Failed to upload your waypoints correctly.</b><br>\n";
            echo "Contact the site maintainer if this was a valid waypoint file.<br>\n";
            echo "</div></body></html>\n";
            exit(0);
        }

        $lastid = mysql_insert_id();
        $query = "update tblRegion set regCentre=$lastid where regPk=$regPk";
        $result = mysql_query($query) or die('Centre update failed: ' . mysql_error());
    }

    return $regPk;
}

adminbar(0);
?>
<p><h2>Waypoint Administration</h2></p>
<?php

auth('system');
$link = db_connect();

if (reqexists('add'))
{
    if (!$_FILES)
    {
        global $argv;
        $_FILES = [];
        $_FILES['waypoints'] = [];
        $_FILES['waypoints']['tmp_name'] = $argv[2];
    }
    $region = reqsval('region');
    accept_waypoints($region,$link);
}

if (reqexists('create'))
{
    $region = reqsval('region');

    $query = "select * from tblRegion where regDescription like '$region'";
    $result = mysql_query($query, $link) or json_die('Find existing region failed: ' . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        echo "<b>Region ($region) already exists with that name.<br>";
        echo "</div></body></html>\n";
        exit(0);
	}

    $query = "insert into tblRegion (regDescription) values ('$region')";
    $result = mysql_query($query) or die('Create region failed: ' . mysql_error());
    $regPk = mysql_insert_id();

    echo "Region $region ($regPk) created<br>";
}

if (reqexists('delete'))
{
    // implement a nice 'confirm'
    $regPk = reqival('delete');
    $query = "select * from tblRegion where regPk=$regPk";
    $result = mysql_query($query) or die('Region check failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $region = $row['regDescription'];
    $query = "select * from tblTaskWaypoint T, tblRegionWaypoint W, tblRegion R where T.rwpPk=W.rwpPk and R.regPk=W.regPk and R.regPk=$regPk limit 1";
    $result = mysql_query($query) or die('Delete check failed: ' . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        echo "Unable to delete $region ($regPk) as it is in use.\n";
        return;
    }

    $query = "delete from tblRegionWaypoint where regPk=$regPk";
    $result = mysql_query($query) or die('RegionWaypoint delete failed: ' . mysql_error());
    $query = "delete from tblRegion where regPk=$regPk";
    $result = mysql_query($query) or die('Region delete failed: ' . mysql_error());
    echo "Region $region deleted\n";
}

if (reqexists('download'))
{
    // implement a nice 'confirm'
    $id=reqival('download');
    redirect("download_waypoints.php?download=$id");
}

echo "<form enctype=\"multipart/form-data\" action=\"region_admin.php\" name=\"waypoint\" method=\"post\">";
echo "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"1000000000\">";
echo "<ol>";
$count = 1;
$sql = "SELECT R.*, RW.rwpName, RW.rwpPk from tblRegion R left outer join tblRegionWaypoint RW on RW.rwpPk=R.regCentre order by R.regDescription";
$result = mysql_query($sql,$link);

while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $id = $row['regPk'];
    $name = $row['regDescription'];
    $centre = $row['regCentre'];
    $centname = $row['rwpName'];
    echo "<li>" . fbut('submit', 'update', $id, 'up'); 
    echo fbut('submit', 'delete', $id, 'del');
    echo fbut('submit', 'download', $id, 'download');
    echo "<a href=\"waypoint_map.html?regPk=$id\">$name</a>";
    //waypoint_select($link, $tasPk, "waypoint$tawPk", $waypt);
    //echo " centre: $centname</a>";
    echo "</li>\n";
    $count++;
}
echo "</ol><hr>";

echo "Load Region: " . fin('region', '', 10);
echo "Waypoints File: <input type=\"file\" name=\"waypoints\">";
echo fis('add', 'Add Waypoints', 10);
echo "<br>";
echo "</form>";
echo "<form enctype=\"multipart/form-data\" name=\"newregion\" onsubmit=\"find_address(this.region.value, this);\">";
echo "New Region: " . fin('region', '', 10);
echo fis('create', 'Create', 10);
echo fib('hidden', 'lat', 0, 3);
echo fib('hidden', 'lon', 0, 3);
echo "</form>";
?>
</div>
</body>
</html>

