<?php
require 'authorisation.php';
require 'xcdb.php';

function get_full_year($year)
{
    $output = system('grep HFDTE *');
}

function match_file($year, $base, $date)
{
    # get the latest matching submission (or all?)
    $matches = glob($base . '*');
    #print "match_file: $year $base"; 
    #var_dump($matches); 
    #echo "<br>";
    if (sizeof($matches) > 0)
    {
        return $matches[sizeof($matches)-1];
    }
    else
    {
       return 0;
    }
}

$tasPk=reqival('tasPk');
$comPk=reqival('comPk');

$usePk = check_auth('system');

$link = db_connect();
$isadmin = is_admin('admin', $usePk, $comPk);
$info = [];
$task_clause = "";
$year = "2020";

if ($tasPk > 0)
{
    $task_clause = "C.tasPk=$tasPk and";
    $info = get_comtask($link, $tasPk);
    $year = substr($info['tasDate'], 0, 4);
}
else
{
    $task_clause = "C.tasPk is null and";
    $info = get_comformula($link, $comPk);
    $info['tasName'] = "all";
}

$sql = "select T.traPk, T.traDate, P.pilLastName, P.pilHGFA from tblComTaskTrack C, tblTrack T, tblPilot P where $task_clause C.comPk=$comPk and C.traPk=T.traPk and P.pilPk=T.pilPk";
$result = mysql_query($sql, $link) or die("Can't get tracks associated with task");
$tracks = [];

while($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
    $tracks[] = $row;
}


$ziplist = [];
chdir("../tracks/$year");

foreach ($tracks as $row)
{
    # Find the original tracks ..
    if ($tasPk == 0)
    {
        $altyear = substr($row['traDate'], 0, 4);
        if ($altyear != $year)
        {
            chdir("../tracks/$year");
            $year = $altyear;
        }
    }

    $basename =  strtolower($row['pilLastName']) . '_' . $row['pilHGFA'] . '_' . $row['traDate'];
    $result = match_file($year, $basename, $row['traDate']);
    if ($result)
    {
        $ziplist[] = $result;
    }
}

#print "Download tracks disabled at this time<br>\r\n";
#exit(0);

# zip them up ..
$allfiles = implode(' ', $ziplist);

$bname = strtolower(preg_replace('/[\s+\/]/', '_', $info['comName'] . '_' . $info['tasName'] . ".zip"));
$filename = '/tmp/' . $bname;
#echo ("zip \"$filename\" $allfiles 2>&1 > /dev/null");
system("zip \"$filename\" $allfiles 2>&1 > /dev/null");

header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=\"$bname\"");
header("Content-Length: " . filesize($filename));
readfile($filename);
#unlink($filename);
exit;

?>


