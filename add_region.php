<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';
require_once 'xcdb.php';

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

// 4119.343S
// 17313.917E

function parse_cu_latlong($str, $neg)
{
    if ($neg == "E" || $neg == "W")
    {
        $adeg = floatval(substr($str, 0, 3));
        $ammm = floatval(substr($str, 3));
    }
    else
    {
        $adeg = substr($str, 0, 2);
        $ammm = floatval(substr($str, 2));
    }

    $val = $adeg + $ammm / 60;

    $ns = substr($str, -1,1);
    if ($ns == $neg)
    {
        $val = -$val;
    }

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

# CUP
#name,code,country,lat,lon,elev,style,rwdir,rwlen,freq,desc
#"010 OCTPUSS PARK",010OCT,,4119.343S,17313.917E,30.0m,1,,,,
function parse_cup($regPk, $link, $lines)
{
    $count = 1;
    for ($i = 1; $i < count($lines); $i++)
    {
        $fields = explode(",", $lines[$i]);
        if (strlen($fields[0]) == 0)
            continue;

        $count++;

        // waypoint
        $name = addslashes($fields[1]);
        $lat = parse_cu_latlong($fields[3], "S");
        $long = parse_cu_latlong($fields[4], "W");
        $alt = floatval($fields[5]);
        $desc = rtrim(addslashes(substr($fields[0],1,01)));
        // echo "$name $lat $long $alt $desc<br>";
        $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
        $result = mysql_query($sql,$link) or json_die('Insert RegionWaypoint (cup) failed: ' . mysql_error());
    }

    return $count;
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
        if (0 + $fields[0] == 0)
            continue;

        $count++;

        // waypoint
        $name = addslashes($fields[1]);
        $lat = parse_latlong($fields[2], "S");
        $long = parse_latlong($fields[3], "W");
        $alt = floatval($fields[14]) / 3.281;
        $desc = rtrim(addslashes($fields[10]));
        // echo "$name $lat $long $alt $desc<br>";
        $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
        $result = mysql_query($sql,$link) or json_die('Insert RegionWaypoint failed: ' . mysql_error());
    }

    return $count;
}

function parse_gpsdump($regPk, $link, $lines)
{
    $count = 1;
    //echo "region=$regPk<br>";
    for ($i = 0; $i < count($lines); $i++)
    {
        // waypoint
        $fields = $lines[$i];
        $name = addslashes(rtrim(substr($fields,0, 10)));
        $lat = parse_gpsd_latlong(substr($fields,10,13), "S");
        $long = parse_gpsd_latlong(substr($fields,27,14), "W");
        $alt = floatval(substr($fields,43,5));
        $desc = addslashes(rtrim(substr($fields,49)));
        // echo "$name,$lat,$long,$alt,$desc<br>";
        if ($lat != 0.0)
        {
            $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
            $result = mysql_query($sql,$link) or json_die('Insert RegionWaypoint failed: ' . mysql_error());
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
        // echo "$name $lat $long $alt $desc<br>";
        $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
        $result = mysql_query($sql,$link) or json_die('Insert RegionWaypoint failed: ' . mysql_error());
    }

    return $n;
}


function parse_waypoints($filen, $regPk, $link)
{
    $count = 0;
    $fh = fopen($filen, 'r');
    if (!$fh)
    {
        json_die("Unable to read file");
        return 0;
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

    if (substr($lines[0],0,4) == "name")
    {
        return parse_cup($regPk, $link, $lines);
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
            // echo "$name $lat $long $alt $desc<br>";
            $sql = "insert into tblRegionWaypoint (regPk, rwpName, rwpLatDecimal, rwpLongDecimal, rwpAltitude, rwpDescription) values ($regPk,'$name',$lat,$long,$alt,'$desc')";
            $result = mysql_query($sql,$link) or json_die('Insert RegionWaypoint failed: ' . mysql_error());
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

function accept_waypoints($regPk, $link)
{
    $name = 'waypoints';

    // Copy the upload so I can use it later ..
    if ($_FILES['userfile']['tmp_name'] != '')
    {
        $dte = date("Y-m-d_Hms");
        $yr = date("Y");
        $copyname = tempnam(FILEDIR . $yr, $name . "_" . $dte);
        copy($_FILES['userfile']['tmp_name'], $copyname);
        chmod($copyname, 0644);

        // Process the file
        if (!parse_waypoints($copyname, $regPk, $link))
        {
            json_die("Failed to upload your waypoints correctly.");
            exit(0);
        }

        $lastid = mysql_insert_id();
        $query = "update tblRegion set regCentre=$lastid where regPk=$regPk";
        $result = mysql_query($query, $link) or json_die('Centre update failed: ' . mysql_error());
    }

    return $regPk;
}


function create_region($region, $link)
{
	# check not a dupe ..
    $query = "select * from tblRegion where regDescription like '$region'";
    $result = mysql_query($query, $link) or json_die('Find existing region failed: ' . mysql_error());
    if (mysql_num_rows($result) > 0)
	{
		json_die("Region ($region) already exists with that name.");
		return 0;
	}

    $query = "insert into tblRegion (regDescription) values ('$region')";
    $result = mysql_query($query, $link) or json_die('Create region failed: ' . mysql_error());
    $regPk = mysql_insert_id();

	return $regPk;
}


function delete_region($regPk, $link)
{
    // implement a nice 'confirm'
    $query = "select * from tblRegion where regPk=$regPk";
    $result = mysql_query($query) or json_die('Region check failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $region = $row['regDescription'];
    $query = "select * from tblTaskWaypoint T, tblRegionWaypoint W, tblRegion R where T.rwpPk=W.rwpPk and R.regPk=W.regPk and R.regPk=$regPk limit 1";
    $result = mysql_query($query, $link) or json_die('Delete check failed: ' . mysql_error());
    if (mysql_num_rows($result) > 0)
    {
        json_die("Unable to delete $region ($regPk) as it is in use.\n");
        return;
    }

    $query = "delete from tblRegionWaypoint where regPk=$regPk";
    $result = mysql_query($query, $link) or json_die('RegionWaypoint delete failed: ' . mysql_error());
    $query = "delete from tblRegion where regPk=$regPk";
    $result = mysql_query($query, $link) or json_die('Region delete failed: ' . mysql_error());
}

$usePk = auth('system');
$link = db_connect();

$cgi = var_export($_REQUEST,true);
error_log($cgi);

$res = [];
$region = reqsval('region');

if (!$_FILES)
{
    global $argv;
    $_FILES = [];
    $_FILES['userfile'] = [];
    $_FILES['userfile']['tmp_name'] = $argv[2];
}

// create region ..
$regPk = create_region($region, $link);
if ($regPk > 0)
{
    accept_waypoints($regPk, $link);
}

$res['result'] = 'ok';
$res['regPk'] = $regPk;

print json_encode($res);
?>
