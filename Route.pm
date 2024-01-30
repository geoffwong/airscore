#!/usr/bin/perl 
##!/usr/bin/perl -I/home/geoff/bin
#
# Determine the shortest route using cartesian coords
# 
# Geoff Wong 2008
#
# Determine closest points on cylinders using line segments (P1,P3) to find P2,
# then (P2,P4) to find (new) P3, etc. Then repeat until change each time is reduced below a threshold
# (or just repeat a fixed number of times?).
# 
# Determining intersection / closest point sphere / line (3d)
# 
# Points P (x,y,z) on a line defined by two points P1 (x1,y1,z1) and P2 (x2,y2,z2) is described by
# P = P1 + u (P2 - P1)
# 
# or in each coordinate
# x = x1 + u (x2 - x1)
# y = y1 + u (y2 - y1)
# z = z1 + u (z2 - z1)
# 
# A sphere centered at P3 (x3,y3,z3) with radius r is described by
# (x - x3)2 + (y - y3)2 + (z - z3)2 = r2
# 
# Substituting the equation of the line into the sphere gives a quadratic equation of the form
# a u2 + b u + c = 0
# 
# where:
# 
# a = (x2 - x1)2 + (y2 - y1)2 + (z2 - z1)2
# b = 2[ (x2 - x1) (x1 - x3) + (y2 - y1) (y1 - y3) + (z2 - z1) (z1 - z3) ]
# c = x32 + y32 + z32 + x12 + y12 + z12 - 2[x3 x1 + y3 y1 + z3 z1] - r2
# 
# The solutions to this quadratic are described by
# 
# The exact behaviour is determined by the expression within the square root
# 
# b * b - 4 * a * c
# 
#     * If this is less than 0 then the line does not intersect the sphere.
#     * If it equals 0 then the line is a tangent to the sphere intersecting it at one point, namely at u = -b/2a.
#     * If it is greater then 0 the line intersects the sphere at two points. 
# 
# To apply this to two dimensions, that is, the intersection of a line and a circle simply remove the z component from the above mathematics. 
# When dealing with a line segment it may be more efficient to first determine whether the line actually intersects the sphere or circle. This is achieved by noting that the closest point on the line through P1P2 to the point P3 is along a perpendicular from P3 to the line. In other words if P is the closest point on the line then
# 
# (P3 - P) dot (P2 - P1) = 0
# 
# Substituting the equation of the line into this
# 
# [P3 - P1 - u(P2 - P1)] dot (P2 - P1) = 0
# 
# Solving the above for u =
# (x3 - x1)(x2 - x1) + (y3 - y1)(y2 - y1) + (z3 - z1)(z2 - z1)
# -----------------------------------------------------------
# (x2 - x1)(x2 - x1) + (y2 - y1)(y2 - y1) + (z2 - z1)(z2 - z1)
# 
# If u is not between 0 and 1 then the closest point is not between P1 and P2
# 
# Given u, the intersection point can be found, it must also be less than the radius r. 
# If these two tests succeed then the earlier calculation of the actual intersection point can be applied. 
# 

require Exporter;
our @ISA       = qw(Exporter);
our @EXPORT = qw{:ALL};

use POSIX qw(ceil floor);
use TrackLib qw(:all);
use strict;

my $pi = atan2(1,1) * 4; 

my $dbh;
my $sth;
my $debug = 1;


# Check if waypoints short positions are at the same location
sub sllequal
{
    my ($wp1, $wp2) = @_;

    if (abs($wp1->{'short_lat'} - $wp2->{'short_lat'}) < 0.0000001 and
        abs($wp1->{'short_long'} - $wp2->{'short_long'}) < 0.0000001)
    {  
        return 1;
    }

    return 0;
}

#
# @param: The waypoints forming line segment P1 -> P2 -> P3
# @param O2 - original 'P2' point (needed for position / radius in some cases)
# @param dirn - suggested direction for next waypoint
# Returns: optimised P2 (to minimise distance)
#
sub find_closest
{
    my ($P1, $P2, $P3, $O2, $dirn) = @_;
    my ($C1, $C2, $C3, $PR, $D1);
    my ($N, $CL);
    my ($v, $w, $phi, $phideg);
    my ($T, $O, $vl, $wl);
    my ($a, $b, $c);
    my $u;

    $C1 = polar2cartesian($P1);
    $C2 = polar2cartesian($P2);

    if (!defined($P2)) 
    {
        if ($debug) { print "Route: P2 & P3 have same centre - straight through case\n"; }
        $C3 = polar2cartesian($P3);
        $O = $C1 - $C3;
        $vl = $O->length();
        if ($vl < 0.01)
        {
            if ($debug) { print "Route: all same centre 1,2,3\n"; }
            # They're all the same point .. not much we can do until next iteration
            # pick an arbitrary point on the radius
            if (defined($dirn))
            {
                $D1 = polar2cartesian($dirn);
                $O = $D1 - $C1;
            }
            else
            {
                # Pick a random direction .. it all starts and ends at the same place
                $O = Vector->new(1000,1000,1000);
            }
            $vl = $O->length();
            $O = ($O2->{'radius'} / $vl) * $O;
        }
        else
        {
            $O = ($O2->{'radius'} / $vl) * $O;
        }
        $CL = $O + $C3;

        my $result = cartesian2polar($CL);
        #$result->{'radius'} = $P2->{'radius'};
        return $result;
    }

    if ($P2->{'shape'} eq 'line')
    {
        if ($debug) { print "Route: line\n"; }
        return $P2;
    }
    
    if (!defined($O2))
    {
        $O2 = $P2;
    }

    if (!defined($P3))
    {
        # End of line case ..
        $O = $C1 - $C2;
        $vl = $O->length();
        if ($debug) 
        { 
            print "Route: EOL case ($vl)\n"; 
            print Dumper($C1);
            print Dumper($C2);
            print Dumper($O);
        }
        if ($vl > 0.01)
        {
            $O = ($P2->{'radius'} / $vl) * $O;
            $CL = $O + $C2;
        }
        else
        {
            if (defined($dirn))
            {
                if ($debug) { print "    Using suggested dirn\n"; }
                $D1 = polar2cartesian($dirn);
                $O = $D1 - $C1;
                $vl = $O->length();
                $O = ($O2->{'radius'} / $vl) * $O;
                $CL = $O + $C2;
                # fall out of this else
            }
            else
            {
                return $P2;
            }
        }

        my $result = cartesian2polar($CL);
        #$result->{'radius'} = $P2->{'radius'};
        return $result;
    }

#    Same point repeated?
#    if (ddequal($P1, $P2))
#    {
#        # same centre .. just do a radius check ..
#        $a = vvminus($C1, $C3);
#        $vl = vector_length($a);
#        $O = vvminus($N, $C3);
#        $vl = vector_length($O);
#        $O = cvmult($P2->{'radius'} / $vl, $O);
#        $CL = vvplus($O, $C3);
#    }

    $C3 = polar2cartesian($P3);

    # What if they have the same centre?
    if ($C1 == $C3)
    {
        #$O = $C1 - $C2;
        $O = $C2 - $C1;
        $vl = $O->length();
        if ($debug) { print "Route: same centre 1,3 case vl=$vl\n"; }
        if ($vl < 0.01)
        {
            if ($debug) { print "Route: all same centre 1,2,3\n"; }
            # They're all the same point .. not much we can do until next iteration
            return $P2;
        }

        $O = ($P2->{'radius'} / $vl) * $O;
        $vl = $O->length();
        if ($debug) { print "New vl=$vl\n"; }
        $CL = $C2 - $O;

        my $result = cartesian2polar($CL);
        # Fix? Should keep radius and centre for next iteration
        #$result->{'radius'} = $P2->{'radius'};
        return $result;
    }


    # What if the 1st and 2nd have the same centre?
    $T = $C1 - $C2;
    if ($T->length() < 0.01)
    {
        if ($debug) { print "Route: same centre 1,2 case\n"; }
        $O = $C3 - $C2;
        $vl = $O->length();
        if ($vl > 0)
        {
            $O = ($P2->{'radius'} / $vl) * $O;
        }
        $CL = $C2 + $O; 

        my $result = cartesian2polar($CL);
        return $result;
    }

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
    if (($u >= 0 and $u <= 1)
        and (distance($PR, $P2) <= $P2->{'radius'}))
    {
        my $theta;
        my $db;
        my $vn;

        # Ok - we have a 180deg? connect
        if ($debug) { print "180 deg connect: u=$u radius=", $P2->{'radius'}, "\n"; }
#        return $P2;
    
#        if ($P2->{'how'} eq 'exit' and $u == 0)
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

    if ($debug)
    {
        # print "Route: Centre=", Dumper($C2), "\n";
        # print "Route: Closest=", Dumper($CL), "\n";
    }
     # Fix? Should keep radius and centre for next iteration
    my $result = cartesian2polar($CL);
    return $result;
}

sub qckdist3
{
    my ($P1, $P2, $P3) = @_;

    my $tot = qckdist2($P1, $P2);
    $tot += qckdist2($P2, $P3);

    return $tot;
}

sub iterate_short_route
{
    my ($orig, $wpts) = @_; 
    my @result;
    my $num = scalar @$wpts;

    push @result, $wpts->[0];
    my $newcl = $wpts->[0];
    for (my $i = 0; $i < $num-2; $i++)
    {
        if ($debug) { print "iterate_short_route: $i: ", $orig->[$i]->{'name'}, "\n"; }
        if (0 and ddequal($orig->[$i+1], $orig->[$i+2]) and ($i < $num-3))
        {
            my $dirn;
            # should find the intersection of the circle/radius of $i+1 & $i+2
            my $j = $i+2;
            while (ddequal($newcl, $orig->[$j]) and $j < $num-1)
            {
                # Target task .. hopefully there's a way out.
                $j++;
            }
            $dirn = $orig->[$j];
            $newcl = find_closest($newcl, undef, $wpts->[$i+2], $orig->[$i+1], $dirn);
            #push @result, $newcl;
            if (qckdist3($wpts->[$i], $newcl, $wpts->[$i+2]) < qckdist3($wpts->[$i], $wpts->[$i+1], $wpts->[$i+2]))
            { 
                push @result, $newcl; 
            } 
            else 
            {
                push @result, $wpts->[$i+1]; 
            }
        }
        else
        {
            $newcl = find_closest($newcl, $orig->[$i+1], $wpts->[$i+2], undef, undef);
            if (qckdist3($wpts->[$i], $newcl, $wpts->[$i+2]) < qckdist3($wpts->[$i], $wpts->[$i+1], $wpts->[$i+2]))
            { 
                push @result, $newcl; 
            } 
            else 
            {
                push @result, $wpts->[$i+1]; 
            }
        }

    }
    push @result, $wpts->[$num-1];

    return \@result;
}

#
# Find the task totals and update ..
#   tasTotalDistanceFlown, tasPilotsLaunched, tasPilotsTotal
#   tasPilotsGoal, tasPilotsLaunched, 
#
sub find_shortest_route
{
    my ($task) = @_;
    my $dist;
    my $i = 0;
    my @it1;
    my $newcl;

    my $tasPk = $task->{'tasPk'};
    my $wpts = $task->{'waypoints'};
    my $num = scalar @$wpts;


    # Ok work out non-optimal distance for now
    print "Task $tasPk with $num waypoints\n";
    #print Dumper($wpts);

    if ($num < 1)
    {
        return undef;
    }

    if ($num == 1)
    {
        my @closearr;
        my $first = cartesian2polar(polar2cartesian($wpts->[0]));
        push @closearr, $first;
        return \@closearr;
    }

    # Work out shortest route!
    push @it1, $wpts->[0];
    $newcl = $wpts->[0];

    # check for boob task
    my $boob = 1;
    for ($i = 0; $i < $num-1; $i++)
    {
        if (!ddequal($wpts->[$i], $wpts->[$i+1]))
        {
            $boob = 0;
            last;
        }
    }

    #if ($boob == 1)
    #{
    #    if ($startssdist < 1 and ($waypoints->[$i]->{'how'} eq 'exit'))
    #    return $closearr;
    #}

    for ($i = 0; $i < $num-2; $i++)
    {
        if ($debug) { print "From pass-1: $i: ", $wpts->[$i]->{'name'}, "\n"; }
        if (ddequal($wpts->[$i+1], $wpts->[$i+2]))
        {
            if ($debug) { print "    FC1\n"; }
            my $dirn;
            # should find the intersection of the circle/radius of $i+1 & $i+2
            my $j = $i+2;
            while (ddequal($newcl, $wpts->[$j]) and $j < $num-1)
            {
                # Target task .. hopefully there's a way out.
                $j++;
            }
            if ($j == $num-1)
            {
                $newcl = find_closest($newcl, undef, $wpts->[$j-1], undef, undef);
            }
            else
            {
                $dirn = $wpts->[$j];
                $newcl = find_closest($newcl, $wpts->[$i+1], undef, undef, $dirn);
            }
        }
        else
        {
            if ($debug) { print "    FC2\n"; }
            $newcl = find_closest($newcl, $wpts->[$i+1], $wpts->[$i+2], undef, undef);
        }
        push @it1, $newcl;
    }
    # FIX: special case for end point ..
    #print "newcl=", Dumper($newcl);
    if ($debug) { print "From (ep) it1: $i: ", $wpts->[$i]->{'name'}, "\n"; }
    $newcl = find_closest($newcl, $wpts->[$num-1], undef, undef, undef);
    push @it1, $newcl;
    #print "IT1=", Dumper(\@it1);
    #return \@it1;

    # Iterate until it doesn't get shorter?
    my $it2 = iterate_short_route($wpts, \@it1);
    my $it3 = iterate_short_route($wpts, $it2);
    return $it3;
    my $closearr = iterate_short_route($wpts, $it3);

    #print "closearr=", Dumper($closearr);
    return $closearr;
}

sub store_short_route
{
    my ($dbh, $task, $closearr) = @_;
    my $totdist = 0.0;
    my $wpts = $task->{'waypoints'};
    my $tasPk = $task->{'tasPk'};
    my $num = scalar @$closearr;
    my $i = 0;
    my $dist;
    my $cdist;

    # Clean up
    $dbh->do("delete from tblShortestRoute where tasPk=$tasPk");

    # Insert each short route waypoint
    # @todo: this function shouldn't calculate the cumulative distance .. just store it
    for ($i = 0; $i < $num-1; $i++)
    {
        $dist = distance($wpts->[$i], $wpts->[$i+1]);
        $cdist = distance($closearr->[$i], $closearr->[$i+1]);

        # Out/in at start
        if (($cdist == 0 or $i == 0) and ddequal($wpts->[$i], $wpts->[$i+1]))
        {
            # print("wpt:$i to wpt:", $i+1, " have same centre (how=", $wpts->[$i+1]->{'how'}, ")\n");
            if ($wpts->[$i+1]->{'how'} eq 'exit')
            {
                if ($i >= 1)
                {
                    $cdist = $wpts->[$i+1]->{'radius'} - $wpts->[$i]->{'radius'}; 
                }
                else
                {
                    $cdist = $wpts->[$i+1]->{'radius'};
                }
            }
            else
            {
                # print("wpt:$i to wpt:", $i+1, " have same centre - subtract: ", $wpts->[$i+1]->{'radius'}, "\n"); 
                $cdist = $wpts->[$i]->{'radius'} - $wpts->[$i+1]->{'radius'}; 
            }
        }

        # Entry -> entry on a radius (not line) at the end 
        if (($i+1 == $num-1) and 
            ($dist == 0) and 
            ($wpts->[$i+1]->{'shape'} eq 'circle') and
            ($wpts->[$i+1]->{'how'} eq 'entry'))
        {
            $cdist = $wpts->[$i]->{'radius'} - $wpts->[$i+1]->{'radius'};
        }

        print "Dist wpt:$i to wpt:", $i+1, " dist=$dist short_dist=$cdist\n";
        $sth = $dbh->do("insert into tblShortestRoute (tasPk,tawPk,ssrLatDecimal,ssrLongDecimal,ssrCumulativeDist,ssrNumber) values (?,?,?,?,?,?)",
            undef,$tasPk,$wpts->[$i]->{'key'}, $closearr->[$i]->{'dlat'}, $closearr->[$i]->{'dlong'}, $totdist,  $wpts->[$i]->{'number'});
        $totdist = $totdist + $cdist;
    }

    $sth = $dbh->do("insert into tblShortestRoute (tasPk,tawPk,ssrLatDecimal,ssrLongDecimal,ssrCumulativeDist,ssrNumber) values (?,?,?,?,?,?)",
            undef, $tasPk,$wpts->[$i]->{'key'}, $closearr->[$i]->{'dlat'}, $closearr->[$i]->{'dlong'}, $totdist,  $wpts->[$i]->{'number'});

    # Store it in tblTask
    print "update tblTask set tasShortRouteDistance=$totdist where tasPk=$tasPk\n";
    $sth = $dbh->do("update tblTask set tasShortRouteDistance=? where tasPk=?", undef, $totdist, $tasPk);
}


sub short_dist
{
    my ($w1, $w2) = @_;
    my $dist;
    my (%s1, %s2);

    $s1{'lat'} = $w1->{'short_lat'};
    $s1{'long'} = $w1->{'short_long'};
    $s2{'lat'} = $w2->{'short_lat'};
    $s2{'long'} = $w2->{'short_long'};

    $dist = distance(\%s1, \%s2);
    return $dist;
}

#
# Determine the distances to startss / endss / ss distance
# Returns a tuple containing:
#   $spt - index of start speed point
#   $ept - index of end speed point
#   $gpt - index of goal point
#   $ssdist - speed section distance
#   $startssdist - distance to start of speed section
#   $endssdist - distane to end of speed section
#   $totdist - total task distance
#
sub task_distance
{
    my ($task) = @_;
    my ($spt, $ept, $gpt);
    my $ssdist;
    my $endssdist;
    my $startssdist;
    my $wpt;

    my $waypoints = $task->{'waypoints'};
    my $allpoints = scalar @$waypoints;
    my $cwdist = 0;


    # Work out key task points
    for (my $i = 0; $i < $allpoints; $i++)
    {
        $wpt = $waypoints->[$i];
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

    # Catch-alls for poorly defined task
    if (!defined($gpt))
    {
        $gpt = $allpoints-1;
    }
    if (!defined($ept))
    {
        $ept = $gpt;
    }

    # Do distances
    for (my $i = 0; $i < $allpoints; $i++)
    {
        # Margins
        my $margin = $waypoints->[$i]->{'radius'} * 0.0005;
        if ($margin < 5.0)
        {
            $margin = 5.0;
        }
        # $waypoints->[$i]->{'margin'} = $margin;

        print "wpt $i: $cwdist\n";

        # Start SS dist
        if ($i == $spt)
        {
            $startssdist = $cwdist;
            if ($startssdist < 1 and ($waypoints->[$i]->{'how'} eq 'exit'))
            {
                $startssdist += $waypoints->[$i]->{'radius'};
            }
        }

        # End SS dist
        if ($i == $ept)
        {
            $endssdist = $cwdist;

            # End speed and goal the same and it's an exit cylinder?
            #    if (ddequal($waypoints->[$i], $waypoints->[$i-1])) - let's assume we don't have the same point multiple times ..
            if ($ept == $gpt)
            {
                if ($waypoints->[$gpt]->{'how'} eq 'exit')
                {   
                    $endssdist += $waypoints->[$gpt]->{'radius'};
                }   
            }

        }
        if ($i < $allpoints-1)
        {
            #if (ddequal($waypoints->[$i], $waypoints->[$i+1]) and ($waypoints->[$i+1]->{'how'} eq 'exit') and ($waypoints->[$i]->{'type'} eq 'start'))
            if (ddequal($waypoints->[$i], $waypoints->[$i+1]) and ($waypoints->[$i+1]->{'how'} eq 'exit'))
            {
                $cwdist = $cwdist + $waypoints->[$i+1]->{'radius'};
                if ($waypoints->[$i]->{'type'} ne 'start')
                {
                    $cwdist = $cwdist - $waypoints->[$i]->{'radius'};
                }
            }
            else
            {
                my $sdist = short_dist($waypoints->[$i], $waypoints->[$i+1]);

                if (($i+1 != $gpt) or ($i+1 == $gpt and $waypoints->[$gpt]->{'shape'} ne 'circle'))
                {
                    $cwdist = $cwdist + $sdist;
                }
                elsif (($i+1 == $gpt) and 
                    ($waypoints->[$gpt]->{'shape'} eq 'circle') and 
                    (ddequal($waypoints->[$i], $waypoints->[$i+1]) and ($waypoints->[$i+1]->{'how'} eq 'entry')))
                {
                    $cwdist = $cwdist + $waypoints->[$i]->{'radius'} - $waypoints->[$i+1]->{'radius'};
                }
                print("allpoints=$allpoints i=$i gpt=$gpt newcwdist=$cwdist\n");
            }
        }
    }

    $ssdist = $endssdist - $startssdist;
    return ($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $cwdist);
}

sub in_semicircle
{
    my ($waypoints, $wmade, $coord) = @_;
    my ($bvec, $pvec);
    my $wpt = $waypoints->[$wmade];

    my $prev = $wmade - 1;
    while ($prev > 0 and ddequal($wpt, $waypoints->[$prev]))
    {
        $prev--;
    }
    
    my $c = polar2cartesian($wpt); 
    my $p = polar2cartesian($waypoints->[$prev]); 

    # vector that bisects the semi-circle pointing into occupied half plane
    $bvec = $c - $p;
    $pvec = $coord->{'cart'} - $c;

    # dot product 
    my $dot = $bvec . $pvec;
    if ($dot > 0)
    {
        return 1;
    }

    return 0;
}

1;
