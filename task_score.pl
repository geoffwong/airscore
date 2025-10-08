#!/usr/bin/perl -I/home/geoff/bin

#
# Determines how much of a task (and time) is completed
# given a particular competition / task 
# 
# Geoff Wong 2007
#

require DBD::mysql;
require Gap; # qw(:all);
require OzGap; # qw(:all);
require NoGap; # qw(:all);
require Nzl; # qw(:all);
require JTGap; # qw(:all);
require GGap; # qw(:all);
require RTGap; # qw(:all);
require PWC; # qw(:all);

use POSIX qw(ceil floor);
use TrackLib qw(:all);
use strict;


#
# Main program here ..
#

my $taskt;
my $task;
my $tasPk;
my $formula;
my $quality;
my $scr;
my $sth;
my $sql;
my ($dist,$time,$launch,$stop);

$TrackLib::dbh = db_connect();

# Get the tasPk 
$tasPk = $ARGV[0];
if ($tasPk < 1)
{
    exit 1;
}

my $sleep_time = 0;
if (scalar @ARGV > 1)
{
    $sleep_time = 1+$ARGV[1];
}

# Drop a mutex/lock - only do one at a time
if (-e "/var/lock/score_$tasPk")
{
    print "Scoring $tasPk in progress\n";
    exit 0;
}
`touch /var/lock/score_$tasPk`;

if ($sleep_time > 0)
{
    sleep($sleep_time);
}

# Read the task itself ..
$task = read_task($tasPk);

# Read the formula 
$formula = read_formula($task->{'comPk'});

print Dumper($formula);

# Now create the appropriate scoring object ...
if (($formula->{'class'} eq 'gap') or ($formula->{'class'} eq 'wptgap'))
{
    print "GAP scoring: ", $formula->{'class'}, "\n";
    $scr = Gap->new();
}
elsif ($formula->{'class'} eq 'ozgap')
{
    print "OzGAP scoring\n";
    $scr = OzGap->new();
}
elsif ($formula->{'class'} eq 'nzl')
{
    print "NZL scoring\n";
    $scr = Nzl->new();
}
elsif ($formula->{'class'} eq 'nogap')
{
    print "NoGap scoring\n";
    $scr = NoGap->new();
}
elsif ($formula->{'class'} eq 'jtgap')
{
    print "JTGap scoring\n";
    $scr = JTGap->new();
}
elsif ($formula->{'class'} eq 'ggap')
{
    print "GGap scoring\n";
    $scr = GGap->new();
}
elsif ($formula->{'class'} eq 'rtgap')
{
    print "RTGap scoring\n";
    $scr = RTGap->new();
}
elsif ($formula->{'class'} eq 'timegap')
{
    print "Time-based scoring\n";
    $scr = TMGap->new();
}
elsif ($formula->{'class'} eq 'pwc')
{
    print "PWC scoring\n";
    $scr = PWC->new();
}
else
{
    print "Unknown formula class ", $formula->{'class'}, "\n";
    `rm -f /var/lock/score_$tasPk`;
    exit 1;
}

# Work out all the task totals from task results, must return 
# at least the followint:
#    $taskt->{'pilots'}
#    $taskt->{'maxdist'}
#    $taskt->{'distance'}
#    $taskt->{'launched'} 
#    $taskt->{'goal'} 
#    $taskt->{'fastest'} 
#    $taskt->{'firstdepart'} 
#    $taskt->{'firstarrival'}
#    $taskt->{'mincoeff'}

eval
{
    $taskt = $scr->task_totals($TrackLib::dbh,$task,$formula);
    
    # Store it in tblTask
    $sql = "update tblTask set tasTotalDistanceFlown=" . $taskt->{'distance'} . 
                ", tasPilotsTotal=" . $taskt->{'pilots'} . 
                ", tasPilotsLaunched=" . $taskt->{'launched'} . 
                ", tasPilotsGoal=" . $taskt->{'goal'} . 
                ", tasFastestTime=" . $taskt->{'fastest'} . 
                ", tasMaxDistance=" . $taskt->{'maxdist'} . 
           " where tasPk=$tasPk";
    
    print Dumper($taskt);
    print $sql, "\n";
    $sth = $TrackLib::dbh->prepare($sql);
    $sth->execute();
    
    # Work out the quality factors (distance, time, launch)
    
    ($dist,$time,$launch, $stop) = $scr->day_quality($taskt, $formula);
    $quality = $dist * $time * $launch * $stop;
    if ($quality > 1.0)
    {
        $quality = 1.0;
    }
    $sth = $TrackLib::dbh->prepare("update tblTask set tasQuality=$quality, tasDistQuality=$dist, tasTimeQuality=$time, tasLaunchQuality=$launch, tasStopQuality=$stop where tasPk=$tasPk");
    $sth->execute();
    $taskt->{'quality'} = $quality;
    
    if ($taskt->{'pilots'} > 0)
    {
        # Now allocate points to pilots ..
        $scr->points_allocation($TrackLib::dbh,$task,$taskt,$formula);
    }
};

if ($@) 
{
    print "Task_score error: $@\n";
    # Handle the error, e.g., log it, try a fallback, etc.
    `rm -f /var/lock/score_$tasPk`;
} 
else 
{
    `rm -f /var/lock/score_$tasPk`;
}


