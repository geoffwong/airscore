<?php
require 'authorisation.php';
require 'xcdb.php';

function get_full_year($year)
{
    $output = system('grep HFDTE *');
}


function get_hfdte($indate)
{
    $date = substr($indate,8,2) . substr($indate,5,2) . substr($indate,2,2);
    return $date;
}

function get_file_date_matches($base, $date)
{
    $output = shell_exec("grep -lE TE$date\|TE:$date $base*");
    $result = explode("\n", $output);
    return $result;
}

function match_file($year, $base, $date)
{
    # get the latest matching submission (or all?)
    $date = get_hfdte($date);
    $matches = get_file_date_matches($base, $date);
    if (count($matches) > 0)
    {
        return $matches[count($matches)-2];
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

    $basename =  strtolower($row['pilLastName']) . '_' . $row['pilHGFA'] . '_';
    $result = match_file($year, $basename, $row['traDate']);
    if (!$result and (0+$row['pilCIVL'] > 0))
    {
        $basename =  strtolower($row['pilLastName']) . '_' . $row['pilCIVL'] . '_';
        $result = match_file($year, $basename, $row['traDate']);
    }
    if ($result)
    {
        $ziplist[] = $result;
    }
}

# zip them up ..
$allfiles = implode(' ', $ziplist);

#print"Download tracks disabled at this time<br>\r\n";
#var_dump($allfiles);
#exit(0);

$bname = strtolower(preg_replace('/[\s+\/]/', '_', $info['comName'] . '_' . $info['tasName'] . ".zip"));
$filename = '/tmp/' . $bname;
#echo ("zip \"$filename\" $allfiles 2>&1 > /dev/null");
system("rm -f \"$filename\" 2>&1 > /dev/null");
system("zip \"$filename\" $allfiles 2>&1 > /dev/null");

header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=\"$bname\"");
header("Content-Length: " . filesize($filename));
readfile($filename);
#unlink($filename);
exit;

?>


