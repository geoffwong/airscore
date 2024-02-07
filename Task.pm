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
my $last_wpt_update = 0;


#
# Find new P2 given P1 -> P2 -> P3
# P2 should have a radius (P1 & P3 are just points)
#
sub find_closest
{
    my ($P1, $P2, $P3) = @_;
    my ($C1, $C2, $C3, $PR, $D1);
    my ($N, $CL);
    my ($v, $w, $phi, $phideg);
    my ($T, $O, $vl, $wl);
    my ($a, $b, $c);
    my $u;

    $C1 = polar2cartesian($P1);
    $C2 = polar2cartesian($P2);
    $C3 = polar2cartesian($P3);

    $u = (($C2->{'x'} - $C1->{'x'})*($C3->{'x'} - $C1->{'x'}) 
            + ($C2->{'y'} - $C1->{'y'})*($C3->{'y'} - $C1->{'y'}) 
            + ($C2->{'z'} - $C1->{'z'})*($C3->{'z'} - $C1->{'z'})) / 
        (($C3->{'x'} - $C1->{'x'})*($C3->{'x'} - $C1->{'x'}) +
            + ($C3->{'y'} - $C1->{'y'})*($C3->{'y'} - $C1->{'y'}) 
            + ($C3->{'z'} - $C1->{'z'})*($C3->{'z'} - $C1->{'z'})); 
    #print "u=$u cart dist=", vector_length($T), " polar dist=", distance($P1, $P2), "\n";

    $N = $C1 + ($u * ($C3 - $C1));
    $CL = $N;
    $PR = cartesian2polar($CL);
    if (($u >= 0 && $u <= 1)
        && (distance($PR, $P2) <= $P2->{'radius'}))
    {
        my $theta;
        my $db;
        my $vn;

        # Ok - we have a ~180deg? connect
        if ($debug) { print "180 deg connect: u=$u radius=", $P2->{'radius'}, "\n"; }
#        return $P2;
    
#        if ($P2->{'how'} eq 'exit' && $u == 0)
#        {
#            $O = vvminus($C3, $C2);
#            $vl = vector_length($O);
#            print "short route point must be on the cylinder\n";
#            if ($vl > 0)
#            {
#                $O = cvmult($P2->{'radius'} / $vl, $O);
#            }
#            $CL = vvplus($O, $C2);
#            $PR = cartesian2polar($CL);
#        }

        # find the intersection points (maybe in cylinder)
        $v = plane_normal($C1, $C2);
        $w = plane_normal($C3, $C2);

        #print "dot_prod=",dot_product($a,$b), "\n";
        #print "theta=$theta\n";
        $a = $C1 - $C2;
        $vl = $a->length();
        if ($vl > 0)
        {
            $a = $a/$vl;
        }
        $b = $C3 - $C2;
        $vl = $b->length();
        if ($vl > 0)
        {
            $b = $b/$vl;
        }
        $theta = acos($a . $b);

        $vn = $a + $b;
        $vl = $vn->length();
        $vn = $vn/$vl;
        $O = $vn * $P2->{'radius'};
        # print "vec_len=", $O->length(), "\n";
        $CL = $O + $C2;
    }
    else
    {
        my $vla;
        my $vlb;

        # find the angle between in/out line
        $v = plane_normal($C1, $C2);
        $w = plane_normal($C3, $C2);
        $phi = acos($v . $w);
        $phideg = $phi * 180 / $pi;

        if ($debug) { print "Route: angle between in/out=$phideg\n"; }
        
        # div angle / 2 add to one of them to create new
        # vector and scale to cylinder radius for new point 
        $a = $C1 - $C2;
        $vla = $a->length();
        if ($vla > 0)
        {
            $a = $a / $vla;
        }
        $b = $C3 - $C2;
        $vlb = $b->length();
        if ($vlb > 0)
        {
            $b = $b / $vlb;
        }

        $O = $a + $b;
        $vl = $O->length();

        if ($phideg < 180)
        {
            if ($debug) { print "    p2->radius=", $P2->{'radius'}, "\n"; }
            $O = ($P2->{'radius'} / $vl) * $O;
        }
        else
        {
            if ($debug) { print "    -p2->radius=", $P2->{'radius'}, "\n"; }
            $O = (-$P2->{'radius'} / $vl) * $O;
        }

        $CL = $O + $C2;
    }

    my $result = cartesian2polar($CL);
    return $result;
}

sub precompute_waypoint_dist
{
    my ($waypoints, $formula) = @_;
    my $wcount = scalar @$waypoints;

    my ($spt, $ept, $gpt) = (0,0,0);
    my $endssdist;
    my $startssdist;

    my $cdist;
    my $remdist;
    my $totdist;
    my (%s1, %s2);
    
    # assume goal is last poin
    $goal_point = $wcount - 1;

    # Work out key task points
    for my $i (0 .. $goal_point)
    {
        my $wpt = $waypoints->[$i];
        if (($wpt->{'type'} eq 'start') or ($wpt->{'type'} eq 'speed'))
        {
            $spt = $i;
        }
        if ($wpt->{'type'} eq 'endspeed')
        {
            $ept = $i;
        }
        if ($wpt->{'type'} eq 'goal')
        {
            $gpt = $i;
        }
    }
    if ($ept == 0) { $ept = $gpt; }
    print("spt=$spt ept=$ept gpt=$gpt\n");

    # Setup error margin
    for my $i (0 .. $goal_point)
    {
        my $errm = $waypoints->[$i]->{'radius'} * $formula->{'errormargin'} / 100;
        if ($errm < 5.0)
        {
            $errm = 5.0;
        }
        $waypoints->[$i]->{'margin'} = $errm;
    }

    $wptdistcache = [];
    $wptdistcache->[0] = 0.0;

    $cdist = 0.0;
    $totdist = 0.0;
    #print Dumper($waypoints);

    for my $i (0 .. $goal_point)
    {
        if (%s2)
        {
            $s1{'lat'} = $s2{'lat'};
            $s1{'long'} = $s2{'long'};
        }
        $s2{'lat'} = $waypoints->[$i]->{'short_lat'};
        $s2{'long'} = $waypoints->[$i]->{'short_long'};
        if ($debug) { print "totdist $i=$totdist\n"; }

        # Start SS dist
        if ($i == $spt)
        {
            $startssdist = $totdist;
            if ($startssdist < 1 and ($waypoints->[$i]->{'how'} eq 'exit'))
            {
                $startssdist += $waypoints->[$i]->{'radius'};
            }
        }

        # End SS dist
        if ($i == $ept)
        {
            $endssdist = $totdist;

            # End speed and goal the same and it's an exit cylinder?
            if ( ($waypoints->[$gpt]->{'how'} eq 'exit') and ddequal($waypoints->[$ept], $waypoints->[$gpt]) )
            {
                $endssdist += $waypoints->[$gpt]->{'radius'};
            }
        }

        # Out/in at start
        $cdist = 0;
        if ($i == 0) 
        {
            if ($waypoints->[$i]->{'how'} eq 'exit')
            {
                $cdist = $waypoints->[$i]->{'radius'};
            }
        }
        elsif ($i == 1) 
        {
            if (($waypoints->[0]->{'how'} eq 'exit')
                and ($waypoints->[$i]->{'how'} eq 'exit'))
            {
                # First point is from the center .. we've already added that distance
                $cdist = distance(\%s1, \%s2) - $waypoints->[$i-1]->{'radius'};
            }
            else
            {
                $cdist = distance(\%s1, \%s2);
            }
        }
        elsif (ddequal($waypoints->[$i-1], $waypoints->[$i]))
        {
            print("wpt:",$i-1," to wpt:", $i, " have same centre (how=", $waypoints->[$i]->{'how'}, ")\n");
            if ($waypoints->[$i]->{'how'} eq 'exit')
            {
                $cdist = $waypoints->[$i]->{'radius'} - $waypoints->[$i-1]->{'radius'};
            }
            else
            {
                if ($waypoints->[$i]->{'shape'} eq 'circle')
                {
                    $cdist = $waypoints->[$i-1]->{'radius'} - $waypoints->[$i]->{'radius'};
                }
                else
                {
                    $cdist = $waypoints->[$i-1]->{'radius'};
                    print("entry circle on non-circle cdist=$cdist");
                }
            }
        }
        else
        {
            $cdist = distance(\%s1, \%s2);
        }

        $totdist = $totdist + $cdist;
        $wptdistcache->[$i+1] = $totdist;

        if ($debug) { print "$i: cumdist=$totdist ($cdist)\n"; }

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

    $total_distance = $totdist;

    $remainingdistcache = [];
    for my $i (0 .. $goal_point)
    {
        $remdist = $totdist - $wptdistcache->[$i];
        $remainingdistcache->[$i] = $remdist;
        if ($debug) { print "$i: remdist=$remdist\n"; }
    }
    $remainingdistcache->[$goal_point+1] = 0.0;

    if ($debug) { print "precompute dist=$totdist\n"; print Dumper($remainingdistcache); }
    my $ssdist = $endssdist - $startssdist;
    return ($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist);
}

sub remaining_task_dist
{
    my $remdist = 0;
    my ($waypoints, $wmade, $coord) = @_;
    my $nextwpt = $waypoints->[$wmade];
    my $lastwpt = $waypoints->[$wmade-1];
    my $nearwpt;
    my %s1;
    my %s2;
    my %se;
    my $radius = 0;


    # Concentric circles
    if (($nextwpt->{'how'} eq 'exit') and ($waypoints->[$goal_point]->{'how'} eq 'exit'))
    {
        my $boob = 1;
        for my $wm ($wmade .. $goal_point)
        {
            if (($waypoints->[$wm]->{'lat'} != $waypoints->[$goal_point]->{'lat'}) or ($waypoints->[$wm]->{'long'} != $waypoints->[$goal_point]->{'long'}))
            {
                $boob = 0;
                last;
            }

        }

        # it's a task all around one waypoint
        if ($boob)
        {
            $s1{'lat'} = $lastwpt->{'lat'};
            $s1{'long'} = $lastwpt->{'long'};
            my $cdist = qckdist2($coord, \%s1);
            # @todo: fix this?
            return $waypoints->[$goal_point]->{'radius'} - $cdist;
        }
    }


    if ($nextwpt->{'type'} eq 'goal')
    {
        # Special goal case
        $remdist = $remainingdistcache->[$wmade];
        if (($nextwpt->{'how'} eq 'exit')
            and (($nextwpt->{'lat'} == $lastwpt->{'lat'}) and ($nextwpt->{'long'} == $lastwpt->{'long'})))
        {
            # Exit goal from same waypoint (ugh)
            $s1{'lat'} = $lastwpt->{'lat'};
            $s1{'long'} = $lastwpt->{'long'};
            my $rdist = qckdist2($coord, \%s1);
            $radius = $nextwpt->{'radius'};
            $remdist = $radius - $rdist;
        }
        else
        {
            $se{'lat'} = $nextwpt->{'lat'};
            $se{'long'} = $nextwpt->{'long'};
            my $rdist = qckdist2($coord, \%se);
            $remdist = $rdist;
            if ($nextwpt->{'shape'} ne 'line')
            {
                $radius = $nextwpt->{'radius'};
                $remdist = $remdist - $radius;
            }
        }
        return $remdist;
    }


    if (($nextwpt->{'how'} eq 'entry') 
        and ($nextwpt->{'lat'} == $waypoints->[$goal_point]->{'lat'}) and ($nextwpt->{'long'} == $waypoints->[$goal_point]->{'long'})
        and ($goal_point == $wmade+1))
    {
        # Special case for entry cylinder on goal
        $remdist = $remainingdistcache->[$wmade];
        if ($debug) { print "wpt centre = goal remdist\n"; }
        $remdist = $remainingdistcache->[$wmade];
        $s1{'lat'} = $nextwpt->{'lat'};
        $s1{'long'} = $nextwpt->{'long'};
        if ($waypoints->[$goal_point]->{'shape'} ne 'line') 
        {
            # yuck
            $radius = $waypoints->[$goal_point]->{'radius'};
            $s1{'lat'} = $waypoints->[$goal_point]->{'lat'};
            $s1{'long'} = $waypoints->[$goal_point]->{'long'};
            my $rdist = qckdist2($coord, \%s1);
            $remdist = $rdist - $radius;
            if ($debug) { print "    ### (Task.pm ess/goal)entry/entry wmade=$wmade remdist=$remdist rdist=$rdist radius=$radius\n"; }
            return $remdist;
        }
    }
    else
    {
        $remdist = $remainingdistcache->[$wmade+2];

        # We dynamically work out optimal point to reach waypoint(see Route.pm) .. updated every 2 minutes (120 seconds)
        # (straight line to next if we're inside the waypoint or (radius - centre) if next waypoint is the same, ie. exit and re-entry)
        if ($coord->{'time'} - $last_wpt_update > 120)
        {
            my %st;

            if ($debug) { print "remdist=$remdist normal next waypoint: ", $nextwpt->{'name'}, "\n"; }
            $st{'lat'} = $waypoints->[$wmade+1]->{'short_lat'};
            $st{'long'} = $waypoints->[$wmade+1]->{'short_long'};
            $last_wpt_update = $coord->{'time'};
            # Find the closest waypoint using the one ahead of the one we're heading to (nextwpt)
            $nearwpt = find_closest($coord, $nextwpt, \%st);
            # Update next waypoint
            # print Dumper($nearwpt);
            $nextwpt->{'short_lat'} = $nearwpt->{'lat'};
            $nextwpt->{'short_long'} = $nearwpt->{'long'};
            if ($waypoints->[$wmade+1]->{'shape'} eq 'line')
            {
                $radius = $nextwpt->{'radius'};
            }
        }

    }

    # next leg after new closest waypoint
    $s1{'lat'} = $nextwpt->{'short_lat'};
    $s1{'long'} = $nextwpt->{'short_long'};
    $s2{'lat'} = $waypoints->[$wmade+1]->{'short_lat'};
    $s2{'long'} = $waypoints->[$wmade+1]->{'short_long'};
    my $rdist = qckdist2($coord, \%s1) + qckdist2(\%s1, \%s2);

    if ($debug) { print "    ### (Task.pm)remaining_task_dist wmade=$wmade remdist=$remdist rdist=$rdist radius=$radius\n"; }
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

    my $rem = remaining_task_dist($waypoints, $wmade, $coord);
    my $altdist = $total_distance - $rem;
    if ($altdist < 0)
    {
        $altdist = 0;
    }
    if ($debug) { print "    ### distance_flown=$altdist ($total_distance-$rem)\n"; }
    return $altdist;
}

1;

