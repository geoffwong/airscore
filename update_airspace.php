<?php
header('Cache-Control: public, must-revalidate');
header('Expires: ' . gmdate(DATE_RFC2822, time()+1));
header('Content-type: application/json; charset=utf-8');

require 'authorisation.php';
require_once 'dbextra.php';

function create_airspace($link)
{
    $region = reqsval('regname');
    $rlat = reqfval('reglat');
    $rlon = reqfval('reglon');
    $rsize = reqfval('regsize');

    $sql = "insert into tblAirspaceRegion (argRegion, argLatDecimal, argLongDecimal, argSize ) values ('$region', $rlat, $rlon, $rsize)";
    $result = mysql_query($sql, $link) or json_die('AirspaceRegion creation failed: ' . mysql_error());

    $res = [];
    $res['output'] = mysql_insert_id();
    $res['result'] = 'created';
}

function delete_airspace($link, $delPk)
{
    // implement a nice 'confirm'
    $delPk = reqival('delete');
    $query = "select * from tblAirspace where airPk=$delPk";
    $result = mysql_query($query, $link) or json_die('Airspace check failed: ' . mysql_error());
    $row = mysql_fetch_array($result);
    $subregion = $row['airName'];

    #$query = "select * from tblTaskWaypoint T, tblRegionWaypoint W, tblRegion R where R.regPk=W.regPk and R.regPk=$delPk limit 1";
    #$result = mysql_query($query, $link) or json_die('Delete check failed: ' . mysql_error());
    #if (mysql_num_rows($result) > 0)
    #{
    #    echo "Unable to delete $region ($delPk) as it is in use.\n";
    #    return;
    #}

    $query = "delete from tblAirspaceWaypoint where airPk=$delPk";
    $result = mysql_query($query, $link) or json_die('AirspaceWaypoint delete failed: ' . mysql_error());
    $query = "delete from tblAirspace where airPk=$delPk";
    $result = mysql_query($query, $link) or json_die('Airspace delete failed: ' . mysql_error());

    $res = [];
    $res['result'] = 'deleted';

}

function add_airspace($link)
{
    $upfile = $_FILES['waypoints']['tmp_name'];
    $out = '';
    $retv = 0;
    exec(BINDIR . "airspace_openair.pl $upfile", $out, $retv);

    $res = [];
    $output = [];

    if ($retv)
    {
        foreach ($out as $txt)
        {
           $output[] = $txt;
        }
        $res['output'] = $output;
        $res['result'] = 'failed';
    }
    else
    {
        foreach ($out as $row)
        {
            $output[] = $row;
        }
        $res['output'] = $output;
        $res['result'] = 'added';
    }
}

$authorised = check_auth('system');

if (!$authorised)
{
    $res = [];
    $res['result'] = 'unauthorised';
}
else
{
    $argPk = reqival('argPk');
    $link = db_connect();

    $action = reqsval('action');
    if ($action == 'add')
    {
        $res = add_airspace($link);
    }
    else if ($action == 'delete')
    {
        $res = delete_airspace($link);
    }
    else if ($action == 'create')
    {
        $res = create_airspace($link);
    }
}

print json_encode($res);
?>
