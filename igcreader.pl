#!/usr/bin/perl -I/home/geoff/bin

#
# Reads in an IGC file
#
#
# Notes: UTC is 13 seconds later than GPS time (!)
#        metres, kms, kms/h, DDMMYY HHMMSSsss, true north,
#        DDMM.MMMM (NSEW designators), hPascals
#
# Geoff Wong 2007.
#
require DBD::mysql;

use Time::Local;
use Data::Dumper;
use IGC qw(:all);
use strict;

#
# Main program here ..
#

my $flight;
my $coords;
my $numc;
my $duration;
my $pilPk;
my $earlyexit;
my $flightstart;
my $ftype;
my $notrim = 0;
my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst);

# Options ..

if (scalar @ARGV < 1)
{
    print "igcreader.pl [-d|-x|-j|-t] <file> [pilPk]\n";
    exit 0;
}

if ($ARGV[0] eq '-d')
{
    $IGC::debug = 1;
    shift @ARGV;
}

if ($ARGV[0] eq '-j')
{
    $IGC::allowjumps = 1;
    shift @ARGV;
}

if ($ARGV[0] eq '-x')
{
    $earlyexit = 1;
    shift @ARGV;
}

if ($ARGV[0] eq '-t')
{
    $notrim = 1;
    shift @ARGV;
}

# Read the flight
$pilPk = 0 + $ARGV[1];

$ftype = determine_filetype($ARGV[0]);

if ($ftype eq "igc")
{
    $flight = read_igc($ARGV[0]);
}
elsif ($ftype eq "live")
{
    $flight = read_live($ARGV[0]);
    #print Dumper($flight);
}
elsif ($ftype eq "kml")
{
    $flight = read_kml($ARGV[0]);
}
else
{
    print "Unsupported file type detected: $ftype\n";
    print "Please submit an IGC file\n";
    exit 1;
}

if (!defined($flight))
{
    print "No flight defined.\n";
    exit 1;
}

$coords = $flight->{'coords'};
$numc = scalar @$coords;
if ($IGC::debug) { print "num coords=$numc\n"; }

# Trim off silly points ...
if ($notrim == 0)
{
    $flight = trim_flight($flight);
}

# Is it a duplicate (and other checks)?
($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = gmtime($flight->{'header'}->{'start'});
$flightstart = sprintf("%04d-%02d-%02d %02d:%02d:%02d", $year+1900, $mon+1, $mday, $hour, $min, $sec);
#print "flightstart=$flightstart\n";

$TrackLib::dbh = db_connect();
my $sth = $TrackLib::dbh->prepare("select traPk from tblTrack where pilPk=$pilPk and traStart='$flightstart'");
$sth->execute();
my $traPk = $sth->fetchrow_array();
if (defined($traPk))
{
    # it's a duplicate ...
    print "Duplicate track found ($traPk) from $flightstart\n";
    exit(1);
}

# Work out flight duration
$flight->{'duration'} = flight_duration($flight->{'coords'});

if ($flight->{'duration'} == 0)
{
    print "flight of 0 duration found, the track contains no flight, track rejected.\n";
    exit(1);
}

# glider?
if (!defined($flight->{'header'}->{'glider'}))
{
    $flight->{'glider'} = 'unknown';
}
else
{
    $flight->{'glider'} = $flight->{'header'}->{'glider'};
}

if ($earlyexit == 1)
{
    exit 1;
}

# if ($pilPk == 0) - guess from name / gid?
# select pilPk based on gid
# if ($pilPk == 0) - split lastname(s) and select from database .. pick one?

# store the trimmed track ...
$traPk = store_track($flight, $pilPk);

# stored track pk
# print pilot=
# print gid=
print "traPk=$traPk\n";


