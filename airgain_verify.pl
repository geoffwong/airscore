#!/usr/bin/perl

#
# Airgain
# 
# Name: validate_airgain
#
# Put waypoints into km(?) buckets that represent distance from centre
#
# In loop for each trackpoint:
#   * Calculate our dist from centre
#   * check all points in bucket to see if we 'tagged' them
#   * add to our list (only once)
#
# Score based upon waypoint name
# Store it ...
#
# IDEA: could use a 2d tree instead
#
# Geoff Wong 2011
#

require DBD::mysql;

use Math::Trig;
use Data::Dumper;
use POSIX qw(ceil floor);
use TrackLib qw(:all);

use strict;

my $dbh;

sub validate_airgain
{
    my ($task, $flight, $reg) = @_;

    my $wpts = $reg->{'waypoints'};
    my $centre = $reg->{'centre'};
    my $coords = $flight->{'coords'};
    my %accum;
    my @bucket;
    my $sub;
    my $sc;
    my $dist;
    my $score;
    my $utcmod;
    my $coord;
    my $wcount;

    # Check for UTC crossover
    if (defined($task))
    {
        $utcmod = 0;
        $coord = $coords->[0];
        if ($coord->{'time'} > $task->{'sfinish'})
        {  
            $utcmod = 86400;
        }
    }

    for $coord (@$wpts)
    {
        my $buc;
        $dist = floor(distance($centre, $coord)/100);
        # print $coord->{'name'}, " dist to centre: $dist\n";
        if (!defined($bucket[$dist]))
        {
            $bucket[$dist] = [];
        }
        $buc = $bucket[$dist];
        push @$buc, $coord;
    }
    #print "@bucket=", Dumper(\@bucket);

    # Initialise some working variables
    # Go through the coordinates and verify the track against the task
    for $coord (@$coords)
    {
        # Check task start probably a good idea ...
        # Don't start scoring until after start time
        # and we're within centre radius

        # Check the task isn't finished ..
        if (defined($task))
        {
            $coord->{'time'} = $coord->{'time'} - $utcmod;
            if ($coord->{'time'} > $task->{'sfinish'})
            {
                print "Coordinate after task finish: ",
                    $coord->{'time'}, " ", $task->{'sfinish'}, ".\n";
                last;
            }
        }

        $dist = floor(distance($centre, $coord)/100);
        $sub = $bucket[$dist];

        if (defined($sub))
        {
            #print $dist, " ", Dumper($sub);
            for $sc (@$sub)
            {
                #print Dumper($sc);
                $dist = distance($coord, $sc);
                #print "Dist coord to sc=$dist\n";
                if ($dist < 400)
                {
                    print "Inside : ", $sc->{'name'}, "\n";
                    $accum{$sc->{'name'}} = $sc;
                }
            }
        }
    }

    my %result;
    $result{'start'} = 0;
    $result{'goal'} = 0;
    $result{'startSS'} = 0;
    $result{'endSS'} = 0;
    $result{'distance'} = $flight->{'traLength'};
    $result{'penalty'} = 0;
    $result{'comment'} = '';
    $result{'coeff'} = 0;
    $result{'score'} = $score;
    $result{'waypoints_made'} = $wcount;
    $result{'waypoints'} = \%accum;

    return \%result;
}


sub store_task_airgain
{
    my ($track,$res) = @_;
    my ($tasPk,$traPk,$dist,$score,$turnpoints,$penalty,$comment);

    $tasPk = $track->{'tasPk'};
    $traPk = $track->{'traPk'};
    $dist = $res->{'distance'};
    $score = $res->{'score'};
    $turnpoints = $res->{'waypoints_made'};
    $penalty = $res->{'penalty'};
    $comment = $res->{'comment'};

    $dbh->do("delete from tblTaskResult where traPk=? and tasPk=?", undef, $traPk, $tasPk);

    #print("insert into tblTaskResult (tasPk,traPk,tarDistance,tarSpeed,tarStart,tarGoal,tarSS,tarES,tarTurnpoints,tarLeadingCoeff,tarPenalty,tarComment) values ($tasPk,$traPk,$dist, 0, 0, 0, 0, 0,$turnpoints,$score,$penalty,'$comment')\n");

    my $sth = $dbh->prepare("insert into tblTaskResult (tasPk,traPk,tarDistance,tarSpeed,tarStart,tarGoal,tarSS,tarES,tarTurnpoints,tarLeadingCoeff,tarPenalty,tarComment) values ($tasPk,$traPk,$dist, 0, 0, 0, 0, 0,$turnpoints,$score,$penalty,'$comment')");

    $sth->execute();
}

sub read_airgain_existing
{
	my ($comPk, $flight) = @_;
    my $traPk = $flight->{'traPk'};
    my $pilPk = $flight->{'pilPk'};
	my %waypoints;

    # AirgainWaypoint needs a comp associated with it too (for same track in multiple comps)?
    my $query = qq{select G.rwpPk from tblComTaskTrack CTT, tblTrack T, tblAirgainWaypoint G where CTT.comPk=$comPk and CTT.traPk=T.traPk and G.traPk=T.traPk and T.pilPk=$pilPk and T.traPk<>$traPk};

    my $sth = $dbh->prepare($query);
    my $ref;
    $sth->execute();
    while ($ref = $sth->fetchrow_hashref())
    {
       	$waypoints{$ref->{'rwpPk'}} = 1;
    }

	return \%waypoints;
}

sub store_olc_airgain
{
    my ($track,$res) = @_;
    my ($tasPk,$traPk,$dist,$score,$turnpoints,$comment);
    my @arr;

    $traPk = $track->{'traPk'};
    $score = $res->{'score'};
    $turnpoints = $res->{'waypoints_made'};

    print("traPk=$traPk score=$score\n");
    if ($score > 0)
    {
        $dbh->do("update tblTrack set traScore=? where traPk=?", undef, $score, $traPk);
        $dbh->do("delete from tblAirgainWaypoint where traPk=?", undef, $traPk);

        my $query = qq{insert into tblAirgainWaypoint (traPk, rwpPk) values };
        my $accum = $res->{'waypoints'};
        #print Dumper($accum);

        for my $k (keys %$accum)
        {
            my $rwpPk = $accum->{$k}->{'rwpPk'};
            push @arr, join(",", ( $traPk, $rwpPk ));
        }
        my $rest = '(' . join('),(', @arr) . ')';
    
        print("rest=$rest\n");
        $dbh->do($query . $rest);
    }
    else
    {
        print("Score=0\n");
    }
}


#
# Main program here ..
#

my $flight;
my $wpts;
my $task;
my $tasPk = 0;
my $comPk = 0;
my $regPk;
my $info;
my $duration;
my $score = 0;

if (scalar @ARGV < 1)
{
    print "airgain_verify.pl traPk comPk [tasPk]\n";
    exit 1;
}

$dbh = db_connect();

# Read the flight
$flight = read_track($ARGV[0]);
#print Dumper($flight);

$comPk = 0+$ARGV[1];
if ($comPk < 1)
{
    print "airgain_verify.pl traPk comPk [tasPk]\n";
    exit 1;
}

#print Dumper($wpts);

# Get the waypoints for specific task
if (0+$ARGV[2] > 0)
{
    $tasPk = 0 + $ARGV[2];
    $flight->{'tasPk'} = $tasPk;
    $task = read_task($tasPk);
    #print Dumper($task);
    # Verify and find waypoints / distance / speed / etc

    if ($task->{'type'} ne 'airgain')
    {
        print "Task $tasPk not an airgain type task\n";
        print "airgain_verify.pl traPk comPk [tasPk]\n";
        exit 1;
    }

    $wpts = read_region($task->{'region'});
    $info = validate_airgain($task,$flight,$wpts);

    # score it ..
    my $accum = $info->{'waypoints'};
    for my $k (keys %$accum)
    {
        my $val = 0 + substr($k,3,3);
        $score = $score + $val;
    }
    $info->{'score'} = $score;

    # Store task result in DB
    store_task_airgain($flight,$info);
}
else
{
    my $forVersion;
	my $existing;

    my $sth = $dbh->prepare("select C.*, F.* from tblCompetition C, tblFormula F where F.comPk=C.comPk and C.comPk=$comPk");
    my $ref;
    $sth->execute();
    if ($ref = $sth->fetchrow_hashref())
    {
        $regPk = 0 + $ref->{'regPk'};
        $forVersion = $ref->{'forVersion'};
    }
    else
    {
        print "Unknown competition $comPk\n";
        print "airgain_verify.pl traPk comPk [tasPk]\n";
    }

    print "OLC airgain validate ($regPk)\n";
    $wpts = read_region($regPk);
    $info = validate_airgain(undef,$flight,$wpts);

    if ($forVersion eq 'airgain-count')
	{
		$existing = read_airgain_existing($comPk, $flight);
	}
    
    # score it ..
    my $accum = $info->{'waypoints'};
    for my $k (keys %$accum)
    {
        if ($forVersion eq 'airgain-count')
        {
			if (!exists $existing->{$accum->{$k}->{'rwpPk'}})
			{
        		$score = $score + 1000;
			}
        }
		else
		{
			# score based upon waypoint name
        	my $val = 0 + substr($k,3,3);
        	$score = $score + $val;
		}
    }
    $info->{'score'} = $score;

    # Store somewhere (no task)
    store_olc_airgain($flight,$info);
}


