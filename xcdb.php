<?php
function get_all_tasks($link,$comPk)
{
    $ret = [];
    $task = [];

    $sql = "select C.* from tblCompetition C where C.comPk=$comPk";
    $result = mysql_query($sql,$link) or die('get_all_tasks (comp) failed: ' . mysql_error());

    if ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $row['comDateFrom'] = substr($row['comDateFrom'],0,10);
        $row['comDateTo'] = substr($row['comDateTo'],0,10);
        $ret['comp'] = $row;
    }


    $sql = "select T.*,SR.*,TW.*, W.* from tblTask T, tblTaskWaypoint TW, tblShortestRoute SR, tblRegionWaypoint W  where T.comPk=$comPk and TW.tasPk=T.tasPk and SR.tawPk=TW.tawPk and W.rwpPk=TW.rwpPk order by T.comPk, T.tasPk, TW.tawNumber";

    $result = mysql_query($sql,$link) or die('get_all_tasks failed: ' . mysql_error());

    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $tasPk = $row['tasPk'];
        if (!$row['tasComment'] || $row['tasComment'] == 'null')
        {
            $row['tasComment'] = '';
        }
        if (!array_key_exists($tasPk, $task))
        {
            $task[$tasPk]['task'] =  
                [ 
                    'tasPk' => $row['tasPk'],
                    'tasName' => $row['tasName'],
                    'tasDate' => $row['tasDate'],
                    'tasTaskType' => $row['tasTaskType'],
                    'tasStartTime' => substr($row['tasStartTime'],11),
                    'tasFinishTime' => substr($row['tasFinishTime'],11),
                    'tasDistance' => round($row['tasDistance']/1000,2),
                    'tasSSDistance' => round($row['tasSSDistance']/1000,2),
                    'tasShortest' => round($row['tasShortRouteDistance']/1000,2),
                    'tasQuality' => round($row['tasQuality'],2),
                    'tasComment' => utf8_encode($row['tasComment']),
                    'tasDistQuality' => round($row['tasDistQuality'],2),
                    'tasTimeQuality' => round($row['tasTimeQuality'],2),
                    'tasLaunchQuality' => round($row['tasLaunchQuality'],2),
                    'tasArrival' => $row['tasArrival'],
                    'tasHeightBonus' => $row['tasHeightBonus'],
                    'tasStoppedTime' => substr($row['tasStoppedTime'],11)
                ];
            $task[$tasPk]['waypoints'] =  [];
        }
        array_push($task[$tasPk]['waypoints'], 
            [
                'rwpPk' => $row['rwpPk'], 
                'regPk' => $row['regPk'],         
                'rwpName' => $row['rwpName'],       
                'rwpLatDecimal' => $row['rwpLatDecimal'], 
                'rwpLongDecimal' => $row['rwpLongDecimal'], 
                'ssrLatDecimal' => $row['ssrLatDecimal'], 
                'ssrLongDecimal' => $row['ssrLongDecimal'], 
                'rwpAltitude' => $row['rwpAltitude'],  
                'rwpDescription' => $row['rwpDescription'],
                'tawRadius' => $row['tawRadius'],
                'tawShape' => $row['tawShape']
            ]);
    }

    $ret['tasks'] = $task;
    return $ret;
}

function get_comtask($link,$tasPk)
{
    $query = "select C.*, T.* from tblCompetition C, tblTask T where T.tasPk=$tasPk and T.comPk=C.comPk";
    $result = mysql_query($query,$link) or die('get_comtask failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    return $row;
}

function get_comformula($link,$comPk)
{
    $query = "select C.*, F.*, sum(T.tasQuality) as TotalValidity from tblCompetition C left outer join tblFormula F on C.forPk=F.forPk left outer join tblTask T on T.comPk=C.comPk where C.comPk=$comPk group by C.comPk";
    $result = mysql_query($query,$link) or die('get_comformulatask failed: ' . mysql_error());
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    return $row;
}

function get_taskwaypoints($link,$tasPk)
{
    $sql = "SELECT T.*,SR.*,W.* FROM tblTaskWaypoint T, tblShortestRoute SR, tblRegionWaypoint W where T.tasPk=$tasPk and SR.tawPk=T.tawPk and W.rwpPk=T.rwpPk order by T.tawNumber";
    $result = mysql_query($sql,$link) or die('get_task failed: ' . mysql_error());

    $ret = array();
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $ret[] = $row;
    }

    return $ret;
}

function get_all_regions($link)
{
    $regarr = [];
    $sql = "SELECT * FROM tblRegion R order by regDescription";
    $result = mysql_query($sql,$link);
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
    	$desc = $row['regDescription'];
    	$regarr[$desc] = $row['regPk'];
    }
    
    return $regarr;
}

function get_comp_region($link, $comPk)
{
    $sql = "select regPk from tblCompetition where comPk=$comPk";
    $result = mysql_query($sql,$link) or die('Region query failed: ' . mysql_error());
    if (!$row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        die('No region associated with competition');
        return;
    }
    return get_region($link, $row['regPk'], 0);
}

function get_region($link, $regPk, $trackid)
{
    // task info ..
    if ($trackid > 0)
    {
        $sql = "SELECT max(T.trlLatDecimal) as maxLat, max(T.trlLongDecimal) as maxLong, min(T.trlLatDecimal) as minLat, min(T.trlLongDecimal) as minLong from tblTrackLog T where T.traPk=$trackid";
        $result = mysql_query($sql,$link) or die('Track query failed: ' . mysql_error());
        $row = mysql_fetch_array($result, MYSQL_ASSOC);
    
        $maxLat = $row['maxLat'] + 0.02;
        $maxLong = $row['maxLong'] + 0.02;
        $minLat = $row['minLat'] - 0.02;
        $minLong = $row['minLong'] - 0.02;
        $crad = 400;
    
        $sql = "SELECT W.* FROM tblRegionWaypoint W where W.regPk=$regPk and W.rwpLatDecimal between $minLat and $maxLat and W.rwpLongDecimal between $minLong and $maxLong";
    }
    else
    {
        $crad = 0;
        $sql = "SELECT W.* FROM tblRegionWaypoint W where W.regPk=$regPk";
    }
    $result = mysql_query($sql,$link) or die('Region waypoint query failed: ' . mysql_error());
    $ret = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $id = $row['rwpPk'];
        unset($row['rwpPk']);
        unset($row['regPk']);
        $ret[$id] = $row;
    }

    return $ret;
}
function en2dhv($en)
{
    $dispclass =  [ 'A' => '1', 'B' => '1/2', 'C' => '2', 'D' => '2/3', 'CCC' => 'competition',
                        'floater' => 'floater', 'kingpost' => 'kingpost', 'open' => 'open', 'rigid' => 'rigid' ];

    return $dispclass[$en];
}
?>
