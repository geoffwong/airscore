<?php

function get_all_tasks($link,$comPk)
{
    $sql = "select C.*,T.*,SR.*,TW.*, W.* from tblCompetition C, tblTask T, tblTaskWaypoint TW, tblShortestRoute SR, tblRegionWaypoint W  where C.comPk=$comPk and TW.tasPk=T.tasPk and T.comPk=C.comPk and SR.tawPk=TW.tawPk and W.rwpPk=TW.rwpPk order by C.comPk, T.tasPk, TW.tawNumber";

    $result = mysql_query($sql,$link) or die('get_all_tasks failed: ' . mysql_error());

    $ret = [];
    $task = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
        $tasPk = $row['tasPk'];
        if (!$row['tasComment'])
        {
            $row['tasComment'] = '';
        }
        if (sizeof($ret) == 0)
        {
            $ret['comp'] = [
                'comPk' => $row['comPk'],
                'comName' => $row['comName'],
                'comClass' => $row['comClass'],
                'comLocation' => $row['comLocation'],
                'comTimeOffset' => $row['comTimeOffset']
                ];
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
                    'tasComment' => $row['tasComment'],
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
?>
