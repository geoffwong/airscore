#!/usr/bin/perl -I../../bin

#
# Verify a track against a task
# Used for Race competitions and Routes.
#
# Geoff Wong 2007
#

require DBD::mysql;

#use Math::Trig;
#use Data::Dumper;
use POSIX qw(ceil floor);
use Route qw(:all);
use Task qw(:all);
use strict;

my $dbh;
my $debug = 0;
my $wptdistcache;
my $remainingdistcache;
my $total_distance;

sub validate_olc
{
    my ($flight, $task, $tps) = @_;
    my $traPk;
    my $tasPk;
    my $comPk;
    my %result;
    my $dist;
    my $out;
    
    $traPk = $flight->{'traPk'};
    $tasPk = $task->{'tasPk'};
    $comPk = $task->{'comPk'};
    $out = `optimise_flight.pl $traPk $comPk $tasPk $tps`;

    my $sth = $dbh->prepare("select * from tblTrack where traPk=$traPk");
    my $ref;
    $sth->execute();
    if ($ref = $sth->fetchrow_hashref())
    {
        $dist = $ref->{'traLength'};
    }

    $result{'start'} = $task->{'sstart'};
    $result{'goal'} = 0;
    $result{'startSS'} = $task->{'sstart'};
    $result{'endSS'} = $task->{'sstart'};
    $result{'distance'} = $dist;
    $result{'closest'} = 0;
    $result{'coeff'} = 0;
    $result{'waypoints_made'} = 0;

    return \%result;
}

sub made_entry_waypoint
{
    my ($waypoints, $wmade, $coord, $dist, $awarded) = @_;
    my $made = 0;
    my $wpt = $waypoints->[$wmade];

    if ($awarded)
    {
        return 1;
    }

    if ($wpt->{'shape'} eq 'circle')
    {
        if ($dist < ($wpt->{'radius'}+$wpt->{'margin'})) 
        {
            $made = 1;
        }
    }
    elsif ($wmade > 0 && $wpt->{'shape'} eq 'line')
    {
        # does the track intersect with the semi-circle
        if ($dist < ($wpt->{'radius'}+$wpt->{'margin'}) and (in_semicircle($waypoints, $wmade, $coord)))
        {
            $made = 1;
        }
    }
    
    if ($made == 0) 
    {
        return 0;
    }

    if ($debug)
    {
        print "made entry waypoint ", $wpt->{'number'}, "(", $wpt->{'type'}, ") radius ", $wpt->{'radius'}, " at ", $coord->{'time'}, "\n";
    }

    return 1;
}

sub made_exit_waypoint
{
    my ($wpt, $rpt, $ept, $coords, $nc, $dist, $awarded, $wcount, $lastin) = @_;
    my $made_time = 0;
    my $entry_time = 0;
    my $coord = $coords->[$nc];

    if ($awarded)
    {
        return 1;
    }

    if (($dist > ($wpt->{'radius'}-$wpt->{'margin'})) and ($lastin == $wcount)) 
    {
        if ($debug)
        {
            print "made_exit_waypoint ", $wpt->{'number'}, "(", $wpt->{'type'}, ") radius ", $wpt->{'radius'}, " at ", $made_time, "\n";
        }
        $made_time = $coord->{'time'};
        $entry_time = $coord->{'time'};
        if (($wpt == $rpt) or ($wpt == $ept))
        {
            # timing should be actual crossing time (if available) or closest too ..
            while ($dist > ($wpt->{'radius'}-$wpt->{'margin'}) and ($dist <= ($wpt->{'radius'})))
            {
                $nc++;
                if ($nc > scalar @{ $coords })
                {
                    last;
                }
                $made_time = $coord->{'time'};
                $dist = distance($coords->[$nc], $wpt);
            }
            
            if ($dist > ($wpt->{'radius'}) and ($wpt == $ept))
            {
                # waypoint after we exit for ess
                $made_time = $coord->{'time'};
            }
            elsif ($dist < ($wpt->{'radius'}-$wpt->{'margin'}))
            {
                # we didn't actually cross the cylinder
                $made_time = ($entry_time + $made_time) / 2;
            }
            # otherwise we take the time from the last time inside the cylinder
        }
    }
    

    return $made_time;
}

sub re_entered_start
{
    my ($waypoints, $coord, $lastin, $reflag) = @_;
    my $wcount = undef;
    my $reset = undef;
    my $newreflag = undef;

    # Might re-enter start/speed section for elapsed time tasks 
    # Check if we did re-enter and set the task "back"
    my $rpt = $waypoints->[$lastin];

    # Re-entered start cyclinder?
    my $rdist = distance($coord, $rpt);
    if ($rpt->{'how'} eq 'entry')
    {
        #print "Repeat check ", $rpt->{'type'}, " (reflag=$reflag lastin=$lastin): dist=$rdist\n";
        if (($rdist < ($rpt->{'radius'}+$rpt->{'margin'}))) # and ($reflag == $lastin))
        {
            # last point inside ..
            #$starttime = 0 + $coord->{'time'};
            #if (($task->{'type'} eq 'race') && ($starttime > $task->{'sstart'}))
            #{
            #    $starttime = 0 + $task->{'sstart'};
            #}
            #if (($task->{'type'} eq 'speedrun-interval') && ($starttime > $taskss))
            #{
            #    $starttime = 0 + $taskss + floor(($starttime-$taskss)/$interval)*$interval;
            #}
            #$startss = $starttime;
            #$coeff = 0; $coeff2 = 0; 
            #if ($debug)
            #{
            #    print "made startss(entry)=$startss\n";
            #}
            $newreflag = -1;
            $reset = 1;
        }
        elsif ($rdist >= ($rpt->{'radius'}-$rpt->{'margin'}))
        {
            if ($debug)
            {
                print "(Re)exited entry start/speed cylinder\n";
            }
            $newreflag = $lastin;
        }
    }

    if ($rpt->{'how'} eq 'exit') 
    {
        if (($rdist < ($rpt->{'radius'}+$rpt->{'margin'}))) # and ($reflag == $lastin))
        {
            #print "re-entered (exit) speed/startss ($lastin) at " . $coord->{'time'} . " maxdist=$maxdist\n";
            $wcount = $lastin;
            #$wpt = $waypoints->[$wcount];
            $newreflag = -1;
        }
        elsif ($rdist >= ($rpt->{'radius'}) and ($reflag == -1))
        {
            # No margin here because of timing issues ..
            #print "exited (exit) speed/startss ($lastin) at " . $coord->{'time'} . " maxdist=$maxdist\n";
            $newreflag = $lastin;
            $reset = 1;
        }
    }
    return ($reset, $newreflag, $wcount);
}

#
# Name: validate_task
#
# Sequentially work through the track / waypoints 
# to find start / end / # of waypoints made / dist made / time if completed
# task spec:
#       type of task (free,elapsed,elapsed-interval,race)
#       start gate / start type / start time / [ waypoint ]* / 
#           end of SS / goal (shape circle/semi circle?)
#       % reduction on SS if made and not eot
#       use minimum task dist or centre of waypoints?
#   
# interpolate end times and making end sector
# Assumptions: 
#   there is never a waypoint after goal
#   once you take the waypoint (after start time) after start/speed you can't restart
#       
# FIX: Function is too big and should be broken down ...

sub validate_task
{
    my ($flight, $task, $formula) = @_;
    my ($wpt, $rpt);
    my $coord;
    my ($lastcoord, $preSScoord);
    my $lastmaxcoord;
    my ($awarded,$awtime);
    my ($dist, $rdist, $edist);
    my $penalty;
    my $comment = '';
    my ($wasinstart, $wasinSS);
    my $kmtime = [];
    my $kmmark;
    my %result;
    my (%s1, %s2, %s3);

    # Initialise some working variables
    my $closest = 9999999999;
    my $wcount = 0;
    my $wmade = 0;
    my $stopalt = 0;
    my $stoptime = 0;
    my $starttime = undef;
    my $goaltime = undef;
    my $startss = 0;
    my $endss = undef;
    my $lastin = -1;
    my $reflag = 0;

    if ($debug)
    {
        print Dumper($task);
    }

    # Get some key times
    my $taskss = 0 + $task->{'sstart'};
    my $finish = 0 + $task->{'sfinish'};
    my $interval = 0 + $task->{'interval'};

    my $waypoints = $task->{'waypoints'};
    my ($spt, $ept, $gpt, $essdist, $startssdist, $endssdist, $totdist) = precompute_waypoint_dist($waypoints, $formula);
    $dist = compute_waypoint_dist($waypoints, $wcount-1);
    my $coords = $flight->{'coords'};
    my $awards = $flight->{'awards'};
    my $allpoints = scalar @$waypoints;

    # Next waypoint
    $wpt = $waypoints->[$wcount];

    # Closest waypoint
    my $closestwpt = 0;
    my $closestcoord = 0;

    # Stuff for leadout coeff calculation
    # against starttime (rather than first start time)
    my ($maxdist, $coeff, $coeff2);
    $coeff = 0; $coeff2 = 0; 
    $kmtime = init_kmtime($task->{'ssdistance'});
    $maxdist = 0;

    # Check for UTC crossover
    my $utcmod = determine_utcmod($task, $coords->[0]);

    # Determine the start gate type and ESS dist
    #my ($spt, $ept, $gpt, $essdist, $startssdist, $endssdist, $totdist) = task_distance($task);
    $total_distance = $totdist;
    $rpt = $waypoints->[$spt];

    if ($debug)
    {
        print "spt=$spt ept=$ept gpt=$gpt, essdist=$essdist startssdist=$startssdist endssdist=$endssdist totdist=$totdist utcmod=$utcmod\n";
    }

    # Go through the coordinates and verify the track against the task

    for my $coord_count (0 .. scalar @{$coords} - 1)
    {
        $coord = $coords->[$coord_count];

        # Check the task isn't finished ..
        $coord->{'time'} = $coord->{'time'} - $utcmod;
        # print "Coordinate time=: ", $coord->{'time'}, " sstopped=", $task->{'sstopped'}, " laststart=", $task->{'laststart'},  ".\n";
        if (defined($task->{'sstopped'}) && !defined($endss)) 
        {
            my $maxtime;

            # PWC elapsed scoring (everyone gets same time on course)
            if ($formula->{'stoppedelapsedcalc'} eq 'shortesttime')
            {
                $maxtime = $task->{'sstopped'} - $task->{'laststart'};
                if ($debug)
                {
                    print "elapsed maxtime=$maxtime\n";
                }
            }

            if (($coord->{'time'} > $task->{'sstopped'}) or
                (defined($maxtime) && ($coord->{'time'} > $starttime + $maxtime)))
            {
                print "Coordinate after stopped time: ", $coord->{'time'}, " ", $task->{'sstopped'}, ".\n";
                $stopalt = $coord->{'alt'};
                $stoptime = $coord->{'time'};
                last;
            }
        }
        if ($coord->{'time'} > $task->{'sfinish'})
        {
            print "Coordinate after task finish: ", $coord->{'time'}, " ", $task->{'sfinish'}, ".\n";
            last;
        }

        # Might re-enter start/speed section for elapsed time tasks 
        # Check if we did re-enter and set the task "back"
        if (($lastin >= $spt) and 
            ((($task->{'type'} eq 'race') and ($starttime < $task->{'sstart'}) and ($maxdist - $startssdist < 15000)) or 
            (($task->{'type'} ne 'race') and ($wmade < $spt+2) and ($maxdist - $startssdist < 15000)) or
            (($task->{'type'} ne 'race') and ($wmade < $spt+3) and ($starttime < $taskss))
            )
           )
        {
            # Re-entered start cyclinder?
            $rdist = distance($coord, $rpt);
            if ($rpt->{'how'} eq 'entry')
            {
                print "Repeat check @ ", $coord->{'time'}, " type=",  $rpt->{'type'}, " (reflag=$reflag lastin=$lastin/$spt): dist=$rdist ($maxdist-$startssdist) (wmade=$wmade ($spt))\n";
                # reflag 
                #  -1 - within margin boundary
                #  0  - haven't entered (no time)
                #  1 -  outside outer margin bounary
                #  2  - inside inner margin boundary
                if (($reflag == 1) and ($rdist < ($rpt->{'radius'}+$rpt->{'margin'}))) 
                {
                    # last point inside ..
                    $wcount = $spt;
                    $wmade = $wcount;
                    $wpt = $waypoints->[$wcount];
                    $reflag = -1;
                    print "re-entered speed/startss (enter spt=$spt wmade=$wmade reflag to -1)\n";
                }
                elsif (($reflag == 2) and ($rdist > ($rpt->{'radius'}-$rpt->{'margin'}))) 
                {
                    # last point inside ..
                    $reflag = -1;
                    print "re-exited speed/startss (enter) from inner to reflag=-1 (ouside boundary)\n";
                }
                elsif ($reflag == -1) 
                {
                    if ($rdist > ($rpt->{'radius'}+$rpt->{'margin'}))
                    {
                        print "from reflag -1 (inside boundary) to reflag 1 (outside)\n";
                        $reflag = 1;
                    }
                    elsif ($rdist < ($rpt->{'radius'}-$rpt->{'margin'}))
                    {
                        print "from reflag -1 (inside boundary) to reflag 2 (inner)\n";
                        $reflag = 2;
                    }
                }
                elsif ($rdist < ($rpt->{'radius'}+$rpt->{'margin'}))
                {
                    print "enable re-entry (reflag=-1)\n";
                    $reflag = -1;
                }
            }

            if ($rpt->{'how'} eq 'exit') 
            {
                # print "Repeat check @ ", $coord->{'time'}, " type=",  $rpt->{'type'}, " (reflag=$reflag lastin=$lastin/$spt): rdist=$rdist ($maxdist-$startssdist) (wmade=$wmade ($spt))\n";
                if (($rdist < ($rpt->{'radius'}+$rpt->{'margin'})) 
                    and ($rdist > ($rpt->{'radius'}/2)) 
                    and ($reflag == -1))
                {
                    print "enable re-exit (reflag=1)\n";
                    $reflag = 1;
                }
                elsif ($rdist >= ($rpt->{'radius'}-$rpt->{'margin'}) and ($reflag == 1))
                {
                    #print "exited (exit) speed/startss ($lastin) at " . $coord->{'time'} . " maxdist=$maxdist\n";
                    print "re-exited rdist=$rdist speed/startss (" . $rpt->{'type'} . ":" . $rpt->{'radius'} . ") at " . $coord->{'time'} . " maxdist=$maxdist\n";
                    $wcount = $spt;
                    $wmade = $wcount;
                    $wpt = $waypoints->[$wcount];
                    $reflag = 0;
                }
                elsif ($rdist > ($rpt->{'radius'}+$rpt->{'margin'}))
                {
                    print "reflag=-1 (setting to 0) rdist=$rdist\n";
                    $reflag = -1;
                }
            }
        } # re-enter start
        
        # Get the distance flown
        my $newdist = distance_flown($waypoints, $wmade, $coord);

        # print "wcount=$wcount wmade=$wmade newdist=$newdist maxdist=$maxdist starttime=$starttime time=", $coord->{'time'}, "\n";

        # Work out leadout coeff / maxdist if we've moved on
        if ($debug)
        {
            print "newdist=$newdist maxdist=$maxdist wmade=$wmade time=", ($coord->{'time'} - $startss), " distrem=", ($essdist - $maxdist), " ncoeff=$coeff\n";
        }
        if ($newdist > $maxdist)
        {
            if (!defined($endss))
            {
                if (defined($lastmaxcoord) && !defined($endss))
                {
                    $coeff = $coeff + ($coord->{'time'} - $taskss) * ( ($essdist - $maxdist) - ($essdist - $newdist) );
                    $coeff2 = $coeff2 + ($coord->{'time'} - $startss) * ( ($essdist - $maxdist)*($essdist - $maxdist) - ($essdist - $newdist)*($essdist - $newdist) );
                }
                $lastmaxcoord = $coord;
            }

            $maxdist = $newdist;
            if (($maxdist >= $startssdist) && (!defined($endss)) 
                && ($kmtime->[floor(($maxdist-$startssdist)/1000)] == 0))
            {
                $kmtime->[floor(($maxdist-$startssdist)/1000)] = $coord->{'time'};
                # print "kmtime ($maxdist): ", floor(($maxdist-$startssdist)/1000), ":", $coord->{'time'}, "\n";
            }
            # else { print "new max ($maxdist)\n"; }

            # @todo: Do closestwpt / closestcoord here too?
        }

        # Was the next point awarded via management interface?
        $awarded = 0;
        if (defined($awards) && defined($awards->{$wpt->{'key'}}))
        {
            if ($debug)
            {
                print "waypoint ($wcount) awarded\n";
            }
            $awarded = 1;
            $awtime = $awards->{$wpt->{'key'}}->{'tadTime'};
        }

        # Ok - work out if we're in cylinder
        $dist = distance($coord, $wpt);
        if ($dist < $wpt->{'radius'} and ($wpt->{'type'} eq 'start'))
        {
            $wasinstart = $wcount;
        }
        if ($dist < $wpt->{'radius'} and ($wpt->{'type'} eq 'speed'))
        {
            #print "wasinSS=$wcount\n";
            $wasinSS = $wcount;
        }
        if ($dist < ($wpt->{'radius'}+$wpt->{'margin'}) || $awarded)
        {
            if ($debug)
            {
                print "lastin=$wcount\n";
            }
            $lastin = $wcount;
        }

        #
        # Handle Entry Cylinder
        #
        if ($wpt->{'how'} eq 'entry')
        {
            if (made_entry_waypoint($waypoints, $wmade, $coord, $dist, $awarded))
            {
                # Do task timing stuff
                if (($wpt->{'type'} eq 'start') and (!defined($starttime)) or
                    ($wpt->{'type'} eq 'speed'))
                {
                    # get last start time ..
                    # and in case they were to lazy too put in startss ...
                    $starttime = 0 + $coord->{'time'};
                    $preSScoord = $lastcoord;
                    if (($task->{'type'} eq 'race') && ($starttime > $task->{'sstart'}))
                    {
                        $starttime = 0 + $task->{'sstart'};
                    }
                    if ($awarded == 1 && $awtime > 0)
                    {
                        $starttime = $awtime;
                    }
                    $startss = $starttime;
                    $coeff = 0; $coeff2 = 0; 
                    if ($debug)
                    {
                        print "1st ", $wpt->{'type'}, "(ent) startss=$startss\n";
                    }
                }
    
                # Goal and speed section checks
                #if (($wpt->{'type'} eq 'goal') and (!defined($goaltime)))
                if ($wcount == $gpt and (!defined($goaltime)))
                {
                    # @todo time should be estimated for the actual
                    # line crossing on speed from last two waypoints ..
                    #print "goal: lat = ", $coord->{'lat'} * 180 / $pi;
                    #print " long = ", $coord->{'long'} * 180 / $pi, "\n";
    
                    $goaltime = 0 + $coord->{'time'};
                    if ($stopalt == 0)
                    {
                        $stopalt = $coord->{'alt'};
                    }
                    if ($awarded == 1 && $awtime > 0)
                    {
                        print "goaltime=$goaltime awtime=$awtime\n";
                        $goaltime = $awtime;
                    }
                }
    
                if ($wcount == $ept and (!defined($endss)))
                {
                    # @todo time should be estimated for the actual
                    # line crossing on speed from last two waypoints ..
                    $endss = 0 + $coord->{'time'};
                    if ($stopalt == 0)
                    {
                        $stopalt = $coord->{'alt'};
                    }
                    if ($awarded == 1 && $awtime > 0)
                    {
                        $endss = $awtime;
                    }
                    print $wpt->{'name'}, " endss=$endss at $dist\n";
                }
    
                $wcount++;
                $wmade = $wcount;
                if ($debug)
                {
                    print "inc entry wmade=$wmade\n";
                }
                #and (($task->{'type'} eq 'race') or ($task->{'type'} eq 'speedrun') or ($task->'type' eq 'speedrun-interval')))
                if ($wcount == $allpoints) 
                {
                    # and not free bearing?
                    $closestcoord = 0;
                    $result{'time'} = $endss - $startss;
                    last;
                }
                $wpt = $waypoints->[$wcount];
    
                if ($closestwpt < $wcount)
                {
                    $closest = 9999999999;
                    $closestcoord = $waypoints->[$wcount-1];
                    $closestwpt = $wcount;
                }
            }
            elsif (($dist < $closest) && ($wcount >= $closestwpt))
            {
                # Entry - check distance to current waypoint
                $closest = $dist;
                $closestcoord = $coord;
                $closestwpt = $wcount;
                if ($debug)
                {
                    print "new closest (entry) $closestwpt:$closest, distance: $dist\n";
                }
            }
            if ($debug)
            {
                print "closest (entry) check $closestwpt:$closest, distance: $dist\n";
            }
        } # entry
        else 
        {
            # Handle exit cylinder
            #print "exit waypoint dist=$dist for ", $wpt->{'number'}, "(", $wpt->{'type'}, ") radius ", $wpt->{'radius'}, " @ ", $coord->{'time'}, "\n";

            my $extime = made_exit_waypoint($wpt, $rpt, $waypoints->[$ept], $coords, $coord_count, $dist, $awarded, $wcount, $lastin);
            if ($extime)
            {
                #print "exited waypoint ($wasinstart,$wasinSS) ", $wpt->{'number'}, "(", $wpt->{'type'}, ") radius ", $wpt->{'radius'}, "\n";
                if ($wpt == $rpt)
                {
                    #print "exit waypoint ", $wpt->{'number'}, "(", $wpt->{'type'}, ") radius ", $wpt->{'radius'}, " @ ", $coord->{'time'}, "\n";
                    $starttime = $extime;
                    if ($awarded == 1 && $awtime > 0)
                    {
                        $starttime = $awtime;
                    }
                    $startss = $starttime;
                    $coeff = 0; $coeff2 = 0;
                    $reflag = -1;
                    if (($task->{'type'} eq 'race') && ($starttime > $task->{'sstart'}))
                    {
                        $startss = 0 + $task->{'sstart'};
                    }
                    if ($debug)
                    {
                        print "made startss=$startss\n";
                    }
                }
    
                # Goal and speed section checks
                if (($wcount == $gpt) and (!defined($goaltime)))
                {
                    # @todo time should be estimated for the actual
                    # line crossing on speed from last two waypoints ..
                    $goaltime = $extime;
                    if ($stopalt == 0)
                    {
                        $stopalt = $coord->{'alt'};
                    }
                    if ($awarded == 1 && $awtime > 0)
                    {
                        print "awarded goaltime=$goaltime awtime=$awtime\n";
                        $goaltime = $awtime;
                    }
                }
    
                if (($wcount == $ept) and (!defined($endss)))
                {
                    # @todo time should be estimated for the actual
                    # line crossing on speed from last two waypoints ..
                    $endss = $extime;
                    if ($stopalt == 0)
                    {
                        $stopalt = $coord->{'alt'};
                    }
                    if ($awarded == 1 && $awtime > 0)
                    {
                        $endss = $awtime;
                    }
                }
    
                # Ok - we were in and now we're out ...
                $wcount++;
                $wmade = $wcount;
                if ($wcount == $allpoints)
                {
                    # Completed task
                    $closestcoord = 0;
                    $result{'time'} = $endss - $startss;
                    last;
                }
    
                # Are we any closer?
                $wpt = $waypoints->[$wcount];
                if ($closestwpt < $wcount)
                {
                    $closest = 9999999999;
                    $closestcoord = $waypoints->[$wcount-1];
                    $closestwpt = $wcount;
                    #print "closestwpt $closestwpt:$losest\n";
                }
            }
            else
            {
                # Exit - so check the distance to the next waypoint with a different centre ..
                my $nextwp;

                if ($wcount > 0)
                {
                    $nextwp = $wcount;
                    while ($nextwp <= $gpt)
                    {
                        # @todo - should only check exit cylinders and handle an entry with same centre separately?
                        if (compare_centres($waypoints->[$nextwp-1], $waypoints->[$nextwp]) == 0)
                        {
                            last;
                        }
                        $nextwp++;
                    }
                    #print "wcount=$wcount nextwp=$nextwp\n";

                    $edist = distance($coord, $waypoints->[$nextwp]);
                    if (($edist < $closest) && ($wcount >= $closestwpt))
                    {
                        $closest = $edist;
                        $closestcoord = $coord;
                        $closestwpt = $wcount;
                        if ($debug)
                        {
                            print "Exit(edist=$edist): new closest closestwpt=$closestwpt:closest=$closest\n";
                        }
                    }
                }
            }
        } # exit

        $lastcoord = $coord;
    } # end of main coordinate loop

    # Some startss corrections and checks
    #   starttime -  actual start time
    #   startss - start of task time (back to gate)
    print "wasinSS=$wasinSS taskss=$taskss startss=$startss starttime=$starttime interval=$interval\n";

    # Elapsed-interval - pick previous gate
    if ($task->{'type'} eq 'speedrun-interval')
    {
        print "speedrun-interval .. startss=$startss taskss=$taskss\n";
        if ($startss > $taskss) 
        {
            $startss = 0 + $taskss + floor(($starttime-$taskss)/$interval)*$interval;
        }
    }

    # Can't start later than start close time
    print "sstartclose=", $task->{'sstartclose'}, " sstart=", $task->{'sstart'}, "\n";
    if (($task->{'sstartclose'} > $task->{'sstart'}) and ($startss > $task->{'sstartclose'}))
    {
        $startss = $task->{'sstartclose'};
    }

    # Sanity
    if ($startss > $finish) 
    {
        $startss = $finish;
    }

    # Jumped the start/speedss?
    if (($task->{'type'} ne 'route') and defined($starttime) and (($starttime < $startss) or ($starttime < $taskss)) and ($wmade > $spt))
    {
        my $jump;
        print "Jumped the start gate ($spt) (taskss=$taskss finish=$finish) (startss=$startss: $starttime)\n";
        $jump = $taskss - $startss;
        $comment = "jumped $jump secs";
        # clear leadout markers
        #$kmtime = init_kmtime($task->{'ssdistance'});
        my $kmsize = scalar @$kmtime;
        for my $it (0..$kmsize)
        {
            if ($kmtime->[$it] != 0)
            {
                $kmtime->[$it] += $jump;
            }
        }
        #$kmtime = undef;
        print Dumper($task);
        if ($task->{'type'} eq 'race' or (($task->{'type'} eq 'speedrun-interval') and ($startss - $starttime < 300)))
        {
            print "Race/initial start jump: $comment\n";
            # Store # of seconds jumped in penalty
            $penalty = $jump
            # shift leadout graph so it doesn't screw other lo points
            # Should be covered by using starttime elsewhere now ..
            #$coeff = $coeff + $task->{'ssdistance'}*($startss-$starttime);
        }
        else
        {
            # Otherwise it's a zero for elapsed (?)
            $coeff = $coeff + $essdist*($startss-$taskss);
            $coeff2 = $coeff2 + $essdist*$essdist*($startss-$taskss);
            if ($waypoints->[$spt]->{'how'} eq 'entry')
            {
                print "Elasped entry jump: $comment\n";
                $wcount = $spt - 1;
                if ($wmade >= $spt)
                {
                    $closestwpt = $spt;
                    $closestcoord = $preSScoord;
                    #print "preSScoord=", Dumper($preSScoord);
                }
                $wmade = $spt;
                if ($wmade < 0)
                {
                    $wmade = 0;
                }
            }
            else
            {
                # exit jump
                print "Exit gate jump ($starttime vs $taskss): $comment\n";
                $wcount = $spt;
                if ($wmade > $spt)
                {
                    $closestwpt = $spt;
                    $closestcoord = $waypoints->[$spt];
                }
                $wmade = $spt;
                $closestwpt = $spt;
                $closestcoord = $waypoints->[$spt];
            }
            $result{'time'} = 0;
            $goaltime = 0;
            $endss = 0;
        }
    }

    #
    # Now compute our distance
    #
    my $dist_flown;
    print "wcount=$wcount wmade=$wmade\n";
    
    if ($wcount < $wmade)
    {
        $wcount = $wmade;
    }

    if ($wmade == 0)
    {
        print "Didn't make the start\n";
        $s2{'lat'} = $waypoints->[$wcount+1]->{'short_lat'};
        $s2{'long'} = $waypoints->[$wcount+1]->{'short_long'};
        if ($closestcoord != 0)
        {
            $dist_flown = short_dist($waypoints->[$wcount], $waypoints->[$wcount+1]) - distance($closestcoord, \%s2);
            if ($dist_flown > $waypoints->[0]->{'radius'})
            {
                $dist_flown = $waypoints->[0]->{'radius'};
            }
        }
        else
        {
            $dist_flown = 0;
        }
        if ($dist_flown < 0)
        {
            print "No distance achieved\n";
            $dist_flown = 0;
        }
        print "wcount=0 dist=$dist_flown\n";
        $coeff = $essdist * ($task->{'sfinish'}-$task->{'sstart'});
        $coeff2 = $essdist * $essdist * ($task->{'sfinish'}-$task->{'sstart'});
    }
    elsif ($wcount == 0)
    {
        print "Didn't make startss ($maxdist), closest wpt=$closestwpt\n";
        $dist_flown = $maxdist; # short_dist($waypoints->[$wcount], $waypoints->[$wcount+1]); # - distance($closestcoord, \%s2);
        print "wcount=0 dist=$dist_flown\n";
        $coeff = $essdist * ($task->{'sfinish'}-$task->{'sstart'});
        $coeff2 = $essdist * $essdist * ($task->{'sfinish'}-$task->{'sstart'});
    }
    elsif ($wcount < $allpoints)
    {
        # we didn't make it into goal
        my $remainingss = 0;
        my $cwclosest;

        $dist_flown = $maxdist; # distance_flown($waypoints, $wmade, $closestcoord);
        if (!defined($endss))
        {
            $remainingss = $essdist - $dist_flown + $startssdist;
        }

        # add rest of (distance_short * $task->{'sfinish'}) to coeff
        print "Didn't make goal endss=$endss count=$wcount dist=$dist_flown remainingss=$remainingss: ", $remainingss*($task->{'sfinish'}-$startss), "\n";
        print "Closest to $wcount, distance=", distance($closestcoord, $waypoints->[$closestwpt]), "\n";
        if (!defined($endss))
        {
            #$coeff = $coeff + $essdist * ($startss - $taskss) + $remainingss * ($task->{'sfinish'}-$coord->{'time'});
            #$coeff2 = $coeff2 + $essdist * $essdist * ($startss - $taskss) / 2 + $remainingss * $remainingss * ($task->{'sfinish'}-$coord->{'time'});
            $coeff = $coeff + $essdist * ($startss - $taskss) + $remainingss * ($task->{'sfinish'}-$taskss);
            $coeff2 = $coeff2 + $essdist * $essdist * ($startss - $taskss) / 2 + $remainingss * $remainingss * ($task->{'sfinish'}-$taskss);
        }
    }
    else
    {
        # Goal
        print "goal (dist=$totdist)\n";
        $coeff = $coeff + $essdist * ($startss - $taskss);
        $coeff2 = $coeff2 + $essdist * $essdist * ($startss - $taskss) / 2;
        $dist_flown = $totdist; # compute_waypoint_dist($waypoints, $wcount-1);
    }

    # sanity ..
    if ($dist_flown < 0)
    {
        printf "Warning: somehow the distance ($dist_flown) is < 0\n";
        $dist_flown = 0;
    }
    if ($dist_flown < $startssdist)
    {
        $coeff = 0;
        $coeff2 = 0;
    }

    $result{'start'} = 0+$starttime;
    $result{'goal'} = $goaltime;
    $result{'startSS'} = $startss;
    $result{'endSS'} = $endss;
    $result{'distance'} = $dist_flown;
    $result{'penalty'} = $penalty;
    $result{'comment'} = $comment;
    $result{'stopalt'} = $stopalt;
    if ($stoptime > 0)
    {
        $result{'stoptime'} = $stoptime;
    }
    else
    {
        $result{'stoptime'} = $lastcoord->{'time'};
    }
    print "## coeff=$coeff essdist=$essdist\n";
    $result{'coeff'} = $coeff / 1800 / $essdist;
    $result{'coeff2'} = $coeff2 / 1800 / $essdist;
    print "    coeff=", $result{'coeff'}, " coeff2=", $result{'coeff2'}, "\n";
    if ($closestcoord)
    {
        # FIX: arc it back to the course line to get distance?
        $result{'closest'} = distance($closestcoord, $waypoints->[$closestwpt]);
    }
    else
    {
        $result{'closest'} = 0;
    }
    $result{'kmtime'} = $kmtime;
    $result{'waypoints_made'} = $wcount;

    #print Dumper($kmtime);

    return \%result;
}

sub apply_handicap
{
    my ($task, $flight, $result) = @_;
    my $handicap = 0;
    my $ref;
    my $origdist = $result->{'distance'};
    
    my $sth = $dbh->prepare("select hanHandicap from tblHandicap where pilPk=? and comPk=?");
    $sth->execute($flight->{'pilPk'}, $task->{'comPk'});
    if ($ref = $sth->fetchrow_hashref())
    {
        $handicap = 0.0 + $ref->{'hanHandicap'};
    }

    #if ($debug)
    {
        print "    handicap=$handicap, ", $flight->{'pilPk'}," ",  $task->{'comPk'}, "\n";
    }

    if ($handicap == 0)
    {
        return $result;
    }

    if ($result->{'endSS'} > 0)
    {
        my $tmdif = $result->{'endSS'} - $result->{'startSS'} - 3600;
        if ($tmdif > 0)
        {
            $tmdif = $tmdif / $handicap + 3600;
            $result->{'endSS'} = $result->{'startSS'} + $tmdif;
        }

        if ($result->{'distance'} < $task->{'short_distance'})
        {
            $result->{'distance'} *= $handicap ;
        }
    }
    else
    {
        $result->{'distance'} *= $handicap;
        $result->{'coeff'} *= $handicap;
    }

    if ($result->{'distance'} > $task->{'short_distance'})
    {
        my ($spt, $ept, $gpt, $essdist, $startssdist, $endssdist, $totdist) = task_distance($task);
        my $ssdist = $essdist - $startssdist;

        print "    handicap essdist=$essdist startssdist=$startssdist ssdist=$ssdist result dist=", $result->{'distance'}, "\n";
        my $multi =  $ssdist / ($origdist - $startssdist);

        $result->{'distance'} = $task->{'short_distance'};
        if ($result->{'endSS'} == 0)
        {
            # Calculate a time
            print "    handicap time multi=$multi stoptime=", $result->{'stoptime'}, " startSS=", $result->{'startSS'}, "\n";
            $result->{'endSS'} = ($result->{'stoptime'} - $result->{'startSS'}) * $multi + $result->{'startSS'};
            $result->{'goal'} = $result->{'endSS'};
        }
    }

    # No leadout 
    $result->{'kmtime'} = undef;

#    $result{'closest'} = distance($closestcoord, $waypoints->[$closestwpt]);
#    $result{'waypoints_made'} = $wcount;

    return $result;
}

#
# Main program here ..
#

my $flight;
my $task;
my $tasPk;
my $info;
my $duration;

if (scalar @ARGV < 1)
{
    print "track_verify_sr.pl traPk [tasPk]\n";
    exit 1;
}

$dbh = db_connect();

if ($ARGV[0] eq '-d')
{
    $debug = 1;
    shift @ARGV;
}

# Read the flight
$flight = read_track($ARGV[0]);
#print Dumper($flight);

# Get the waypoints for specific task
if (0+$ARGV[1] > 0)
{
    $flight->{'tasPk'} = 0 + $ARGV[1];
}
$task = read_task($flight->{'tasPk'});
#print Dumper($task);

# Verify and find waypoints / distance / speed / etc
if ($task->{'type'} eq 'olc')
{
    $info = validate_olc($flight, $task, 3);

}
elsif ($task->{'type'} eq 'free')
{
    $info = validate_olc($flight, $task, 0);
}
else
{
    my $formula = read_formula($task->{'comPk'});
    $info = validate_task($flight, $task, $formula);
}
#print Dumper($info);

my $comp = read_competition($task->{'comPk'});
if ($comp->{'type'} eq 'RACE-handicap')
{
    $info = apply_handicap($task, $flight, $info);
}

# Store results in DB
store_result($flight,$info);

