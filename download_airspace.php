<?php
require 'authorisation.php';
//auth('system');


if (reqexists('download'))
{
    //AC Q
    //AT 
    //AD 
    //AR1 
    //AN PARA 128.55
    //WD 400
    //AL 0
    //AH 40000
    //CO 
    //V D=+
    //V X=53:15.100 N 007:07.333 W
    //DB 53:18.098 N 007:07.333 W , 53:18.098 N 007:07.333 W

    //$format=addslashes($_REQUEST['format']);
    $link = db_connect();
    $argPk = reqival('argPk');

    $sql = "select * from tblAirspaceRegion where argPk=$argPk";
    $result = mysql_query($sql,$link);
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $regname = $row['argRegion'];

    # nuke normal header ..
    header("Content-type: text/air");
    header("Content-Disposition: attachment; filename=\"$regname.air\"");
    header("Cache-Control: no-store, no-cache");

    $sql = "select * from tblAirspace A 
            where A.airPk in (             
                select airPk from tblAirspaceWaypoint W, tblAirspaceRegion R where
                R.argPk=$argPk and
                W.awpLatDecimal between (R.argLatDecimal-R.argSize) and (R.argLatDecimal+R.argSize) and
                W.awpLongDecimal between (R.argLongDecimal-R.argSize) and (R.argLongDecimal+R.argSize)
                group by (airPk))
            order by A.airName";
    $result = mysql_query($sql,$link);

    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $class = $air['airClass'];
        $name = $air['airName'];
        $base = $air['airBase'];
        $tops = $air['airTops'];

        echo "AC $class\n";
        echo "AN $name\n";
        echo "AL $base\n";
        echo "AH $tops\n";
        echo "CO\n";
        // do waypoints ...
    }
}

?>
