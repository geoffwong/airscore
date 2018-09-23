#!/usr/bin/perl

#
# Task distanc / waypoint support routines
# 
# Geoff Wong 2018
#
require Exporter;
require Vector;

#use Math::Trig;
use Time::Local;
use Data::Dumper;
use POSIX qw(ceil floor);
use TrackLib qw(:all);
use strict;

our @ISA       = qw(Exporter);
our @EXPORT = qw{:ALL};

*VERSION=\'0.99';

our $pi = atan2(1,1) * 4;    # accurate PI.

my $debug = 0;
my $wptdistcache;
my $remainingdistcache;
my $total_distance;
my $goal_point;

sub precompute_waypoint_dist
{
    my ($waypoints) = @_;
    my $wcount = scalar @$waypoints;

    my $dist;
    my $remdist;
    my $exdist;
    my (%s1, %s2);
    
    $wptdistcache = [];

    $dist = 0.0;
    $wptdistcache->[0] = 0.0;
    #print Dumper($waypoints);
    for my $i (0 .. $wcount-2)
    {
        $s1{'lat'} = $waypoints->[$i]->{'short_lat'};
        $s1{'long'} = $waypoints->[$i]->{'short_long'};
        $s2{'lat'} = $waypoints->[$i+1]->{'short_lat'};
        $s2{'long'} = $waypoints->[$i+1]->{'short_long'};
        $exdist = distance(\%s1, \%s2);
        if ($debug) { print "exdist $i=$exdist\n"; }
        if ($exdist > 0.0)
        {
            $dist = $dist + $exdist;
        }
        elsif ($waypoints->[$i]->{'how'} eq 'exit' && $waypoints->[$i+1]->{'how'} eq 'exit')
        {
            # Check centres?
            #print Dumper($waypoints->[$i]);
            #print Dumper($waypoints->[$i+1]);
            if ($i > 0 && (ddequal($waypoints->[$i], $waypoints->[$i-1]) && $waypoints->[$i-1]->{'how'} eq 'exit'))
            {
                $dist = $dist + $waypoints->[$i]->{'radius'} - $waypoints->[$i-1]->{'radius'};
            }
            else
            {
                $dist = $dist + $waypoints->[$i]->{'radius'};
            }
        }
        $wptdistcache->[$i+1] = $dist;
        if ($debug) { print "$i: cumdist=$dist\n"; }

        my $sdist = qckdist2(\%s1, $waypoints->[$i]);
        if ($waypoints->[$i]->{'radius'} > $sdist+100)
        {
            $waypoints->[$i]->{'inside'} = 1;
        }
        else
        {
            $waypoints->[$i]->{'inside'} = 0;
        }
    }

    $remainingdistcache = [];
    for my $i (0 .. $wcount-2)
    {
        $remdist = $dist - $wptdistcache->[$i];
        $remainingdistcache->[$i] = $remdist;
        if ($debug) { print "$i: remdist=$remdist\n"; }
    }
    $remainingdistcache->[$wcount-1] = 0.0;

    if ($debug) { print "precompute dist=$dist\n"; }
}

sub remaining_task_dist
{
    my $remdist = 0;
    my ($waypoints, $wmade, $coord) = @_;
    my $nextwpt = $waypoints->[$wmade];
    my %s1;
    my $radius = 0;

    $remdist = $remainingdistcache->[$wmade];

    
    # Special case for entry cylinder on goal
    if ($nextwpt->{'lat'} == $waypoints->[$goal_point]->{'lat'} and $nextwpt->{'long'} == $waypoints->[$goal_point]->{'long'})
    {
        $s1{'lat'} = $nextwpt->{'lat'};
        $s1{'long'} = $nextwpt->{'long'};
        if ($wmade != $goal_point)
        {
            # @todo: handle goal line
            $radius = $nextwpt->{'radius'};
        }
    }
    else
    {
        # Should dynamically work out optimal point to reach waypoint (see Route.pm)
        # Can we make it efficient?
        # (straight line to next if we're inside the waypoint or (radius - centre) if next waypoint is the same,
        #    ie. exit and re-entry)
        $s1{'lat'} = $nextwpt->{'short_lat'};
        $s1{'long'} = $nextwpt->{'short_long'};
    }
    my $rdist = qckdist2($coord, \%s1);

    $remdist = $remdist + $rdist - $radius;
    return $remdist;
}

sub compute_waypoint_dist
{
    my ($waypoints, $wcount) = @_;
    my $dist;
    my $wpdist = -1;
    my (%s1, %s2);
    my $i;

    if (defined($wptdistcache))
    {
        return $wptdistcache->[$wcount];
    }

    if ($debug)
    {
        print "compute_waypoint_dist (wcount=$wcount)\n";
    }

    $dist = 0.0;
    for $i (0 .. $wcount-1)
    {
        $s1{'lat'} = $waypoints->[$i]->{'short_lat'};
        $s1{'long'} = $waypoints->[$i]->{'short_long'};
        $s2{'lat'} = $waypoints->[$i+1]->{'short_lat'};
        $s2{'long'} = $waypoints->[$i+1]->{'short_long'};
        $wpdist = distance(\%s1, \%s2);
        $dist = $dist + $wpdist;

        if ($debug)
        {
            print "   compute_waypoint_dist (wcount=$wcount): $i: dist=$dist ($wpdist)\n";
        }
    }
    
    if ($wcount > -1 && $wpdist == 0)
    {
        $dist = $dist + $waypoints->[$wcount-1]->{'radius'};
    }

    if ($debug)
    {
        print "    compute_waypoint_dist (wcount=$wcount): $i final dist $dist\n";
    }

    return $dist;
}

# Compare the centre of two waypoints.
# If the same return '1'.
sub compare_centres
{
    my ($wp1, $wp2) = @_;

    if ($wp1->{'dlat'} == $wp2->{'dlat'} && 
        $wp1->{'dlon'} == $wp2->{'dlon'})
    {
        return 1;
    }

    return 0;
}

sub init_kmtime
{
    my ($ssdist) = @_;
    my $kmtime = [];

    for my $it ( 0 .. floor($ssdist / 1000.0) )
    {
        $kmtime->[$it] = 0;
    }

    return $kmtime;
}

sub determine_utcmod
{
    my ($task, $coord) = @_;
    my $utcmod;

    $utcmod = 0;
    if ($coord->{'time'} > $task->{'sfinish'})
    {
        if ($debug) { print "utcmod set at 86400\n"; }
        $utcmod = 86400;
    }
    elsif ($coord->{'time'}+43200 < $task->{'sstart'})
    {
        if ($debug) { print "utcmod set at -86400\n"; }
        $utcmod = -86400;
    }

    return $utcmod;
}

# Compute current distance flown
# task_distance - shortest_distance_to_goal
# Note: assumes we're not in goal
sub distance_flown
{
    my ($waypoints, $wmade, $coord) = @_;
    my $nextwpt = $waypoints->[$wmade];
    my $allpoints = scalar @$waypoints;
    my $dist = 0;
    my $cwdist = 0;
    my $nwdist = 0;
    my $exitflag = 0;
    my %s1;

    if ($wmade > 0)
    {
        if ($nextwpt->{'how'} eq 'exit')
        {
            # Move to the end of a sequence of 'exit' cylinders (start / speed / etc)
            my $nxtnxt = $wmade+1;
            while (($nxtnxt < scalar @$waypoints) && ($waypoints->[$nxtnxt]->{'how'} eq 'exit') && ddequal($nextwpt, $waypoints->[$nxtnxt]))
            {
                $nxtnxt++;
            }

            if ($nxtnxt < scalar @$waypoints)
            {
                if ($waypoints->[$nxtnxt]->{'how'} eq 'entry')
                {
                    if (qckdist2($waypoints->[$nxtnxt], $nextwpt) + $waypoints->[$nxtnxt]->{'radius'} < $waypoints->[$nxtnxt-1]->{'radius'})
                    {
                        $exitflag = 1;
                    }
                }
            }
            else
            {
                $exitflag = 1;
            }
        }
    }


    if ($exitflag) 
    {
        # Scoring any exit direction (because we're coming back in)
        $cwdist = compute_waypoint_dist($waypoints, $wmade-1);
        $dist = qckdist2($coord, $nextwpt) + $cwdist;
    }
    else
    {
        my $rdist;
                        
        $nwdist = compute_waypoint_dist($waypoints, $wmade);

        if ($nextwpt->{'type'} ne 'goal' && $nextwpt->{'type'} ne 'endspeed')
        {
            # Distance to shortest route waypoint, but should this be shortest distance to make the waypoint?
            # Note: possibly should dynamically recalculate this point to give shortest distance to fly
            $s1{'lat'} = $nextwpt->{'short_lat'};
            $s1{'long'} = $nextwpt->{'short_long'};
            $rdist = qckdist2($coord, \%s1);
            #my $sdist = qckdist2($coord, $nextwpt) - $nextwpt->{'radius'};
            #if ($sdist < $rdist)
            #{
            #    $rdist = $sdist;
            #}
        }
        else
        {
            # Goal 
            if ($nextwpt->{'shape'} eq 'line')
            {
                # @todo: should really be distance to the goal line (not centre) 
                $rdist = qckdist2($coord, $nextwpt);
            }
            else
            {
                $rdist = qckdist2($coord, $nextwpt) - $nextwpt->{'radius'};
            }
        }

        $dist = $nwdist - $rdist;
        if ($dist < $cwdist)
        {
            $dist = $cwdist;
        }
    }

    if ($debug)
    {
        my $rem = remaining_task_dist($waypoints, $wmade, $coord);
        my $altdist = $total_distance - $rem;
        print "wmade=$wmade cwdist=$cwdist nwdist=$nwdist dist=$dist\n";
        print "altdist=$altdist\n";
    }

    return $dist;
}

1;

