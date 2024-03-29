<html>
<head>
<link HREF="xcstyle.css" REL="stylesheet" TYPE="text/css">
</head>
<body>
<div id="container">
<div id="vhead"><h1>airScore admin</h1></div>
<?php
require_once 'authorisation.php';
require_once 'format.php';
require_once 'dbextra.php';

$comPk = reqival('comPk');
adminbar($comPk);
?>
<p><h2>Pilot Administration</h2></p>
<?php

$usePk = auth('system');
$link = db_connect();

$cat = reqsval('cat');
if ($cat == '')
{
    $cat = 'A';
}
$ozimp = reqsval('ozimp');

if (reqexists('addcomp'))
{
    $comp = reqival('compid');
    $paid = reqsval('paidonly');
    $handicap = reqsval('handicap');
    echo "addcomp $comp $paid $handicap<br>";
    $xmltxt = file_get_contents("http://ozparaglidingcomps.com/scoringExport.php?password=$ozimp&comp_id=$comp");
    $xml = new SimpleXMLElement($xmltxt);
    foreach ($xml->pilot as $pilot)
    {
        $pilarr = [];
        $namarr = explode(" ", $pilot->name, 2);
        if (sizeof($namarr) < 2)
        {
            echo "Skipping " . $pilot->name . "<br>";
            continue;
        }
        $pil['pilLastName'] = mysql_real_escape_string($namarr[1]);
        $pil['pilFirstName'] = mysql_real_escape_string($namarr[0]);
        $pil['pilHGFA'] = $pilot->fai_id;
        $pil['pilCIVL'] = $pilot->civl_id;
        $pil['pilBirthdate'] = $pilot->birthday;
        $pil['pilSex'] = $pilot->gender;
        $pil['pilNationCode'] = $pilot->nation;
        //$pil['regHours'] = $pilot->xc_hours;
        // $pilot->glider_make

        #echo "hours=" . $pilot->xc_hours . "\n";
        $hours = intval($pilot->xc_hours);
        if ($hours < 50)
        {
            $handi = 3;
        }
        elseif ($hours < 150)
        {
            $handi = 2;
        }
        else
        {
            $handi = 1;
        }

        if ($paid == "on" && $pilot->paid != "1") 
        {
            // skip non-payers
            continue;
        }

        if ($pilot->fai_id != '')
        {
            $clause = "pilHGFA=" . quote($pilot->fai_id) . " and pilLastName=" . quote(mysql_real_escape_string($namarr[1]));
            $pilPk = insertnullup($link, 'tblPilot', 'pilPk', $clause, $pil);
            echo "insertup: $clause<br>";

            if ($comPk > 0)
            {
                $regarr = [];
                $regarr['pilPk'] = $pilPk;
                $regarr['comPk'] = $comPk;
                $regarr['regHours'] = $pilot->xc_hours;
                $clause = "comPk=$comPk and pilPk=$pilPk";
                insertup($link, 'tblRegistration', 'regPk', $clause, $regarr);
                if ($handicap == "on")
                {
                    $handarr = [];
                    $handarr['pilPk'] = $pilPk;
                    $handarr['comPk'] = $comPk;
                    $handarr['hanHandicap'] = $handi;
                    $clause = "comPk=$comPk and pilPk=$pilPk";
                    insertup($link, 'tblHandicap', 'hanPk', $clause, $handarr);
                }
            }
        }
        elseif ($pilot->name != '')
        {
            if (0 + $pilot->civl_id > 0)
            {
                $pil['pilHGFA'] = 1000000 + $pilot->civl_id;
            }
            $clause = " pilLastName=" . quote(mysql_real_escape_string($namarr[1])) . " and pilFirstName=" . quote(mysql_real_escape_string($namarr[0]));
            echo "insertup: $clause<br>";
            $pilPk = insertnullup($link, 'tblPilot', 'pilPk', $clause, $pil);

            if ($comPk > 0)
            {
                $regarr = [];
                $regarr['pilPk'] = $pilPk;
                $regarr['comPk'] = $comPk;
                $regarr['regHours'] = $pilot->xc_hours;
                $clause = "comPk=$comPk and pilPk=$pilPk";
                insertup($link, 'tblRegistration', 'regPk', $clause, $regarr);
                if ($handicap == "on")
                {
                    $handarr = [];
                    $handarr['pilPk'] = $pilPk;
                    $handarr['comPk'] = $comPk;
                    $handarr['hanHandicap'] = $handi;
                    $clause = "comPk=$comPk and pilPk=$pilPk";
                    insertup($link, 'tblHandicap', 'hanPk', $clause, $handarr);
                }
            }
        }
        else
        {
            echo "Unable to add (no id): " . $pilot->name . " id=" . $pilot->fai_id . "<br>";
        }
    }
}

if (reqexists('addpilot'))
{
    $fai = reqival('fai');
    $civl = reqival('civl');
    $lname = reqsval('lname');
    $fname = reqsval('fname');
    $sex = reqsval('sex');

    $query = "select * from tblPilot where pilHGFA=$fai";
    $result = mysql_query($query, $link) or die('Pilot select failed: ' . mysql_error());
    if ($fai < 1000 or mysql_num_rows($result) > 0)
    {
        echo "Pilot insert failed, HGFA/FAI number ($fai) already exists or is too low (<1000) <br>";
    }
    else
    {
        $query = "insert into tblPilot (pilHGFA, pilCIVL, pilLastName, pilFirstName, pilSex, pilNationCode) value ($fai, $civl,'$lname','$fname','$sex','AUS')";
        $result = mysql_query($query, $link) or die('Pilot insert failed: ' . mysql_error());
    }
}

if (reqexists('bulkadd'))
{
    $out = '';
    $retv = 0;
    $copyname = tempnam(FILEDIR, $name . "_");
    copy($_FILES['bulkpilots']['tmp_name'], $copyname);
    //echo "bulk_pilot_import.pl $copyname<br>";
    chmod($copyname, 0644);

    exec(BINDIR . "bulk_pilot_import.pl $copyname 2>&1", $out, $retv);
    foreach ($out as $txt)
    {  
        echo "$txt<br>\n";
    }
}

if (reqexists('update'))
{
    $id = reqival('update');
    $fai = reqival("fai$id");
    $lname = reqsval("lname$id");
    $fname = reqsval("fname$id");
    $sex = reqsval("sex$id");
    $nat = reqsval("nation$id");
    $query = "select * from tblPilot where pilHGFA=$fai and pilPk<>$id";
    $result = mysql_query($query, $link) or die('Pilot update select failed: ' . mysql_error());
    if ($fai < 1000 or mysql_num_rows($result) > 0)
    {
        echo "Pilot update failed, HGFA/FAI number ($fai) already exists or is too low (<1000) <br>";
    }
    else
    {
        $query = "update tblPilot set pilHGFA=$fai, pilLastName='$lname', pilFirstName='$fname', pilSex='$sex', pilNationCode='$nat' where pilPk=$id";
        $result = mysql_query($query, $link) or die('Pilot update failed: ' . mysql_error());
    }
}

if (reqexists('delete'))
{
    check_admin('admin',$usePk,-1);
    $id = reqival('delete');
    $query = "select count(*) as numtracks from tblTrack where pilPk=$id";
    $result = mysql_query($query, $link) or die('Pilot delete check failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    if ((0+$row['numtracks']) > 0)
    {
        echo 'Unable to delete pilot as they have associated tracks<br>';
    }
    else
    {
        $query = "delete from tblPilot where pilPk=$id";
        $result = mysql_query($query, $link) or die('Pilot delete failed: ' . mysql_error());
    }
}

if ($ozimp)
{
    echo "<form enctype=\"multipart/form-data\" action=\"pilot_admin.php?comPk=$comPk&ozimp=$ozimp\" name=\"trackadmin\" method=\"post\">";
}
else
{
    echo "<form enctype=\"multipart/form-data\" action=\"pilot_admin.php?comPk=$comPk&cat=$cat\" name=\"trackadmin\" method=\"post\">";
}

echo "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"1000000000\">";
echo "HGFA/FAI: " . fin('fai', '', 5);
echo "CIVL: " . fin('civl', '', 5);
echo "LastName: " . fin('lname', '', 10); 
echo "FirstName: " . fin('fname', '', 10); 
echo "Sex: " . fselect('sex', 'M', [ 'M' => 'M', 'F' => 'F' ]);
echo fis('addpilot', "Add Pilot", 5); 
echo "<br>";
echo "CSV (Last,First,HGFA#,Sex,CIVL#): <input type=\"file\" name=\"bulkpilots\">";
echo fis('bulkadd', 'Bulk Submit', 5);

if ($ozimp)
{
    echo "<p>";
    $comparr = [];
    $xmltxt = file_get_contents("http://ozparaglidingcomps.com/compListXml.php");
    $xml = new SimpleXMLElement($xmltxt);
    foreach ($xml->comp as $comp)
    {
        $comparr["" . $comp->comp_name] = $comp->comp_id;
    }
    echo fselect('compid', '', $comparr);
    echo fib('checkbox', 'paidonly', '', '') . "Paid Only";
    echo fib('checkbox', 'handicap', '', '') . "Generate Handicap";
    echo fis('addcomp', 'Import Comp', '');
}

echo "<hr>";

echo "<h2>Pilots by Name: $cat</h2><p>";
$letters = [  'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
        'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
        'Y', 'Z' ];
echo "<table><tr>";
$count = 0;
foreach ($letters as $let)
{
    $count++;
    echo "<td><a href=\"pilot_admin.php?cat=$let\">$let</a>&nbsp;</td>";
    if ($count % 26 == 0)
    {
            echo "</tr><tr>";
    }
}
echo "</tr></table>";
if ($cat != '')
{
    echo "<ol>";
    $count = 1;
    $sql = "SELECT P.* FROM tblPilot P where P.pilLastName like '$cat%' order by P.pilLastName";
    $result = mysql_query($sql,$link) or die('Pilot select failed: ' . mysql_error());

    while($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['pilPk'];
        $lname = $row['pilLastName'];
        $fname = $row['pilFirstName'];
        $hgfa = $row['pilHGFA'];
        $civlid = $row['pilCIVL'];
        $sex = $row['pilSex'];
        $nat = $row['pilNationCode'];
        echo "<li><button type=\"submit\" name=\"delete\" value=\"$id\">del</button>";
        echo "<button type=\"submit\" name=\"update\" value=\"$id\">up</button>";
        //echo " $hgfa $name ($sex).<br>\n";
        echo "<input type=\"text\" name=\"fai$id\" value=\"$hgfa\" size=7>";
        echo "<input type=\"text\" name=\"civl$id\" value=\"$civlid\" size=5>";
        echo "<input type=\"text\" name=\"lname$id\" value=\"$lname\" size=10>";
        echo "<input type=\"text\" name=\"fname$id\" value=\"$fname\" size=10>";
        echo "<input type=\"text\" name=\"sex$id\" value=\"$sex\" size=3>";
        echo "<input type=\"text\" name=\"nation$id\" value=\"$nat\" size=3> <br>";
        # echo a delete button ...
    
        $count++;
    }
    echo "</ol>";
}


echo "</form>";
?>
</div>
</body>
</html>

