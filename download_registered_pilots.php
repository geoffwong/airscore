<?php

require 'authorisation.php';
require 'xcdb.php';

$comPk=reqival('comPk');

$link = db_connect();
$usePk = check_auth('system');
if (!is_admin('admin',$usePk,$comPk))
{
    echo "unauthorised";
    return;
}

$sql = "SELECT p.* FROM tblRegistration r JOIN tblPilot p ON r.pilPk = p.pilPk WHERE r.comPk = $comPk";
$result = mysql_query($sql,$link) or die("Unable to find pilots: " . mysql_error() . "\n");

header("Content-type: text/igc");
header("Cache-Control: no-store, no-cache");
header("Content-Disposition: attachment; filename=registered-pilots-$comPk.csv");
print "Last,First,HGFA#,Sex,CIVL#\r\n";

while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    print $row['pilLastName'];
    print ",";
    print $row['pilFirstName'];
    print ",";
    print $row['pilHGFA'];
    print ",";
    print $row['pilSex'];
    print ",";
    print $row['pilCIVL'];
    print "\r\n";
}

?>