<?php
require 'authorisation.php';
//auth('system');
$link = db_connect();
// Ozi Explorer format
//OziExplorer Waypoint File Version 1.1
//WGS 84
//Reserved 2
//Reserved 3
//1,ave016,-36.908967,145.245433,25569.00000,0,1,3,0,65535,Avenel,0,0,0,492,6,0,17
//2,ben016,-36.580660,145.991478,25569.00000,0,1,3,0,65535,Benalla,0,0,0,525,6,0,17
//3,bfd009,-37.191502,145.072060,25569.00000,0,1,3,0,65535,Broadford airspace,0,0,0,295,6,0,17
//4,bon030,-37.028333,145.850000,25569.00000,0,1,3,0,65535,Bonnie Doon,0,0,0,984,6,0,17
// etc
function ozi_header()
{
    return "OziExplorer Waypoint File Version 1.1\r\nWGS 84\r\nReserved 2\r\nReserved 3\r\n";
}
// CompeGPS format
//G  WGS 84
//U  1
//W  1D-027 A 36.4402500000ºS 146.3607333333ºE 27-MAR-62 00:00:00 885.000000 WHITF RD/SNO
//W  1G-020 A 36.5787000000ºS 146.3786833333ºE 27-MAR-62 00:00:00 656.000000 MOYHU
//W  6S-034 A 36.7462000000ºS 146.9788500000ºE 27-MAR-62 00:00:00 1115.000000 MYSTIC LZ
// etc.
function compegps_header()
{
    return "G  WGS 84\r\nU  1\r\n";
}

// Convert WGS84 Latitude and Longitude to UTM
function latlon2UTM($latd, $lngd)
{
    //print "latd=$latd lngd=$lngd\n";

    $a = 6378137.0;
    $f = 1/298.257223563;
    $b = 6356752.3142;
    $drad = M_PI/180.0; //Convert degrees to radians)
    $k0 = 0.9996;       //scale on central meridian
    $e = sqrt(1 - ($b/$a)*($b/$a)); //eccentricity

    //Decimal Degree Option
    if ($latd <-90 || $latd> 90)
    {
        print("Latitude must be between -90 and 90");
        return undefined;
    }
    if ($lngd <-180 || $lngd > 180)
    {
        print("Latitude must be between -180 and 180");
        return undefined;
    }

    $phi = $latd*$drad; // Convert latitude to radians
    $lng = $lngd*$drad; // Convert longitude to radians
    $utmz = 1 + floor(($lngd+180)/6);//calculate utm zone
    $latz = 0;//Latitude zone: A-B S of -80, C-W -80 to +72, X 72-84, Y,Z N of 84
    if ($latd > -80 && $latd < 72) { $latz = floor(($latd + 80)/8)+2;}
    if ($latd > 72 && $latd < 84) { $latz = 21;}
    if ($latd > 84) { $latz = 23;}
        
    $zcm = 3 + 6*($utmz-1) - 180;   //Central meridian of zone

    //Calculate Intermediate Terms
    $e0 = $e/sqrt(1 - $e*$e);//Called e prime in reference
    $esq = (1 - ($b/$a)*($b/$a));//e squared for use in expansions
    $e0sq = $e*$e/(1-$e*$e);// e0 squared - always even powers
    $N = $a/sqrt(1-pow($e*sin($phi),2));
    $T = pow(tan($phi),2);
    $C = $e0sq*pow(cos($phi),2);
    $A = ($lngd-$zcm)*$drad*cos($phi);
    $M = $phi*(1 - $esq*(1/4 + $esq*(3/64 + 5*$esq/256)));
    $M = $M - sin(2*$phi)*($esq*(3/8 + $esq*(3/32 + 45*$esq/1024)));
    $M = $M + sin(4*$phi)*($esq*$esq*(15/256 + $esq*45/1024));
    $M = $M - sin(6*$phi)*($esq*$esq*$esq*(35/3072));
    $M = $M*$a;//Arc length along standard meridian

    $M0 = 0;//M0 is M for some origin latitude other than zero. Not needed for standard UTM

    //Calculate UTM Values
    $x = $k0*$N*$A*(1 + $A*$A*((1-$T+$C)/6 + $A*$A*(5 - 18*$T + $T*$T + 72*$C -58*$e0sq)/120));//Easting relative to CM
    $x = $x+500000;//Easting standard 
    $y = $k0*($M - $M0 + $N*tan($phi)*($A*$A*(1/2 + $A*$A*((5 - $T + 9*$C + 4*$C*$C)/24 + $A*$A*(61 - 58*$T + $T*$T + 600*$C - 330*$e0sq)/720))));//Northing from equator
    $yg = $y + 10000000; //yg = y global, from S. Pole
    if ($y < 0){$y = 10000000+$y;}

    //Output into UTM Boxes
    $easting = round(10*($x))/10;  // easting
    $northing = round(10*$y)/10;    // northing
    $DigraphLetrsE = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    $zone = substr($DigraphLetrsE, $latz, 1);
    $zone = "${utmz}$zone";

    return $zone . " " . $easting . "E " . $northing . "N";
}

if (array_key_exists('download', $_REQUEST))
{
    $regPk=intval($_REQUEST['download']);
    $format=addslashes($_REQUEST['format']);

    $sql = "SELECT N.*, R.* from tblRegion N, tblRegionWaypoint R where N.regPk=R.regPk and R.regPk=$regPk order by R.rwpName";

    $result = mysql_query($sql,$link);
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $regname = $row['regDescription'];
    $regname = preg_replace('/\s+/', '', $regname);

    # nuke normal header ..
    header("Content-type: text/wpt");
    header("Content-Disposition: attachment; filename=\"$regname.wpt\"");
    header("Cache-Control: no-store, no-cache");

    if ($format == 'ozi' || $format == "ozilinux" )
    {
        print ozi_header();
    }
    else
    {
        print compegps_header();
    }

    $count = 1;
    do 
    {
        $name = $row['rwpName'];
        $lat = floatval($row['rwpLatDecimal']);
        $lon = floatval($row['rwpLongDecimal']);
        $alt = $row['rwpAltitude'];
        $falt = round($alt * 3.281);
        $desc = $row['rwpDescription'];
        $date = '01-JAN-00 00:00:00';
        # convert lat/lon to NESW appropriately
        # get some decent date ..
        if ($format == 'ozi')
        {
            $lat = round($lat,6);
            $lon = round($lon,6);
            print "$count,$name,$lat,$lon,25569.00000,0,1,3,0,65535,$desc,0,0,0,$falt,6,0,17\r\n";
        }
        elseif ($format == 'ozilinux')
        {
            $lat = round($lat,6);
            $lon = round($lon,6);
            print "$count,$name,$lat,$lon,25569.00000,0,1,3,0,65535,$name $desc,0,0,0,$falt,6,0,17\r\n";
        }
        elseif ($format == 'utm')
        {
            $utm = latlon2UTM($lat, $lon);
            print "W  $name $utm $alt $desc\r\n";
        }
        else
        {
            // compegps
            if ($lat < 0)
            {
                $alat = abs($lat);
                $slat = "$alat" . "\272S";
            }
            else
            {
                $slat = "$lat" . "\272N";
            }
            if ($lon < 0)
            {
                $alon = abs($lon);
                $slon = "$alon" . "\272W";
            }
            else
            {
                $slon = "$lon" . "\272E";
            }
            print "W  $name A $slat $slon $date $alt $desc\r\n";
        }

        $count++;
    } while ($row = mysql_fetch_array($result, MYSQL_ASSOC));
}
?>
