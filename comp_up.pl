#!/usr/bin/perl

#
# Update OLC comps
# 
# Geoff Wong 2007-2019
#

require DBD::mysql;

use POSIX qw(ceil floor);
#use Data::Dumper;
use TrackLib qw(:all);
use Defines qw(:all);
use strict;

my $dbh;

sub round 
{
    my ($number) = @_;
    return int($number + .5);
}
#
# Find the task totals and update ..
#   tasTotalDistanceFlown, tasPilotsLaunched, tasPilotsTotal
#   tasPilotsGoal, tasPilotsLaunched, 
#
sub track_update
{
    my ($comPk, $ctype, $opt) = @_;
    my @tracks;
    my $flag;
    my $out;
    my $ref;
    my $retv;

    # Now check for pre-submitted tracks ..
    my $sth = $dbh->prepare("select traPk from tblComTaskTrack where comPk=$comPk order by traPk");
    $sth->execute();

    my $tracks = ();
    $flag = 1;
    while  ($ref = $sth->fetchrow_hashref()) 
    {
        push @tracks, $ref->{'traPk'};
    }

    # Re-optimising pre-submitted tracks against the task
    for my $tpk (@tracks)
    {
        print "Re-optimise pre-submitted track ($ctype): $tpk\n";
        $out = '';
        $retv = 0;
        $out = `${Defines::BINDIR}optimise_flight.pl $tpk $comPk 0 $opt`;
        print $out;
        if ($ctype eq 'airgain-count')
        {
            $out = `${Defines::BINDIR}airgain_verify.pl $tpk $comPk`;
            print $out;
        }
    }

    return $flag;
}

#
# Main program here ..
#

my $dist;
my $comPk;
my $task;
my $quality;
my $pth;
my $out;
my $formula;
my $comp;


if (scalar @ARGV < 1)
{
    print "comp_up.pl <comPk>\n";
    exit 1;
}

$comPk = 0 + $ARGV[0];

if ($comPk < 1)
{
    print "comp_up.pl <comPk>\n";
    exit 1;
}


# Work out all the task totals to make it easier later
$dbh = db_connect();
$comp = read_competition($comPk);
$formula = read_formula($comPk);

if ($comp->{'type'} ne 'OLC')
{
    print "Not an OLC type competition\n";
    exit 1;
}

if (track_update($comPk, $formula->{'version'}, $formula->{'olcpoints'}) == 1)
{
    print("tracks re-verified - now rescore.");
    #$pth = $BINDIR . 'task_score.pl';
    #$out = `$pth $tasPk`;
    #print $out;
}

#print "Task dist=$dist\n";


