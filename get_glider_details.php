<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

require_once 'authorisation.php';
require_once 'xcdb.php';

function get_gliders($link, $write)
{
	$query = "select * from tblGlider G";
	$result = mysql_query($query,$link) or die("Unable to select glider information: " . mysql_error());
    $gliders = [];
    $estspeed = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
	{
     	$keyrow = [];
        //$keyrow[] = $row['gliManufacturer'];
        $keyrow[] = $row['gliName'];
        $keyrow[] = $row['gliBottomWeight'];
        $keyrow[] = $row['gliTopWeight'];
        $mid = ($row['gliBottomWeight'] + $row['gliTopWeight'])/2;
        $keyrow[] = $row['gliProjectedArea'];
        //$keyrow[] = $row['gliFlatArea'];
        $keyrow[] = $row['gliProjectedSpan'];
        //$keyrow[] = $row['gliFlatSpan'];
        //$keyrow[] = $row['gliMaxChord'];
        //$keyrow[] = $row['gliCells'];
        $speed =  round(($mid / $row['gliProjectedArea']) * 8 * log10($row['gliProjectedSpan']) + 20, 0);
        $keyrow[] = $speed;
        $gliders[]= $keyrow;
        $estspeed[] = [ $row['gliPk'], $speed ];
	}

    if ($write)
    {
        foreach ($estspeed as $row)
        {
            $id = $row[0];
            $speed = $row[1];
    	    $query = "update tblGlider set gliEstimatedSpeed=$speed where gliPk=$id";
    	    $result = mysql_query($query,$link) or json_die("Unable to update glider speed: " . $query);
        }
    }

    return $gliders;
}

$link = db_connect();
$write = reqival('write');

$gliders = get_gliders($link, $write);

$data = [ 'data' => $gliders ];
print json_encode($data);
?>


