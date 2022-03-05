<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time() + 86400));
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'format.php';
require_once 'dbextra.php';
require_once 'get_olc.php';
require_once 'race_results.php';
require 'hc.php';

function included_comps($link,$ladPk)
{
    if ($ladPk > 0)
    {
        $sql = "select C.comPk, C.comName, LC.lcValue from tblLadderComp LC, tblCompetition C where LC.comPk=C.comPk and ladPk=$ladPk order by LC.lcValue desc, comDateTo";
    }
    else
    {
        $sql = "select distinct C.comPk, C.comName, LC.lcValue from tblLadderComp LC, tblCompetition C where LC.comPk=C.comPk and LC.lcValue > 0 order by comDateTo desc";
    }
    $result = mysql_query($sql,$link);
    $comps = [];
    while($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        // FIX: if not finished & no tracks then submit_track page ..
        // FIX: if finished no tracks don't list!
        $comps[] = $row;
    }
    return $comps;
}

function ranking_result($ladPk, $ladder, $restrict, $altval, $comPk)
{
    $start = $ladder['ranDateFrom'];
    $end = $ladder['ranDateTo'];
    $nat = $ladder['ranNationCode'];
    $name = $ladder['ranName'];

    $topnat = [];

    if ($comPk)
    {
        $sql = "select R.*, P.* from tblRankingResult R, tblPilot P where R.ranPk=$ladPk and P.pilPk=R.pilPk and P.pilPk in (select P.pilPk from tblComTaskTrack CTT, tblTrack T, tblPilot P where CTT.traPk=T.traPk and P.pilPk=T.pilPk and CTT.comPk=$comPk group by pilPk) order by R.rrPosition";
    }
    else
    {
        $sql = "select R.*, P.* from tblRankingResult R, tblPilot P where R.ranPk=$ladPk and P.pilPk=R.pilPk and P.pilNationCode='$nat' order by R.rrPosition";
    }
    $result = mysql_query($sql) or json_die('Ranking result query: ' . mysql_error());
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $pilPk=$row['pilPk'];
        $row['name'] = "<a href=\"pilot.html?pilPk=$pilPk\">" . utf8_decode($row['pilFirstName'] . ' ' . $row['pilLastName']) . "</a>";
        $topnat[] = $row;
    }

    // Work out how much validity we want (not really generic)

    return $topnat;
}

// Clean up the structure into one suitable for jquery datatable display
function datatable_clean($sorted)
{
    $result = [];
    $pos = 1;
    foreach ($sorted as $row)
    {
        $newrow = [];
        $newrow[] = $pos;
        $newrow[] = $row['name'];
        $newrow[] = $row['pilHGFA'];
        $newrow[] = $row['rrPoints'];
        $newrow[] = $row['rrSigma'];
        $newrow[] = '';
        $result[] = $newrow;
        $pos++;
    }

    return $result;
}


function get_ranking($ranPk)
{
    $query = "SELECT L.* from tblRanking L where L.ranPk=$ranPk";
    $result = mysql_query($query) or json_die('Ranking info query failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $ladder = [];
    if ($row)
    {
        $ladder = $row;
    }
    return $ladder;
}


//
// Main Body Here
//

$ladPk = reqival('ranPk');
$usePk = check_auth('system');
$comPk = reqival('comPk');
$link = db_connect();
$isadmin = is_admin('admin',$usePk,-1);
$title = 'highcloud.net';

#$result = mysql_query("charset utf8") or json_die('UTF-8 setting failed: ' . mysql_error());
ini_set("default_charset", 'utf-8');



$fdhv= '';
$classstr = '';

$clean = [];
$rankinfo = [];
$included = [];

if ($ladPk > 0)
{
    $rankinfo = get_ranking($ladPk);
    $sorted = ranking_result($ladPk, $rankinfo, $fdhv, $altval, $comPk);
    // $included = included_comps($link, $ladPk);
    $clean = datatable_clean($sorted, $ladPk);
}

if ($comPk > 0)
{
    $iresult = comp_hgfa_result($comPk);
    foreach ($clean as &$row)
    {
        $who = $row[2];
        $compos = $iresult[$who][0];
        $diff = $row[0] - $compos;
        if ($row[4] > 2.0)
        {
            $row[5] = '';
        }
        else
        {
            $row[5] = $diff;
        }
    }
}


$data = [ 'ladder' => $rankinfo, 'inc' => $included, 'data' => $clean ];

$msg = json_encode($data, JSON_UNESCAPED_UNICODE);
if ($msg)
{
    echo $msg;
}
else
{
    echo json_last_error_msg();
}
?>
