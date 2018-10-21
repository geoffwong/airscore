#!/usr/bin/perl -I..

use Test::More;
use Data::Dumper;
use Route qw(:all);
use strict;

# Helper function tests

#is(round(1.3), 1.0, "Rounding 1");
#is(round(1.5), 2.0, "Rounding 2");
#is(round(1.8), 2.0, "Rounding 3");
sub fix_task
{
    my ($task) = @_;
    my $wpts = $task->{'waypoints'};

    for my $wpt (@$wpts)
    {
        if (!(exists $wpt->{'dlat'}))
        {
            $wpt->{'dlat'} = $wpt->{'lat'} * 180 / PI();
        }
        if (!(exists $wpt->{'dlon'}))
        {
            $wpt->{'dlong'} = $wpt->{'long'} * 180 / PI();
        }
    }

    my $sr1 = find_shortest_route($task);
    for (my $i = 0; $i < scalar @$sr1; $i++)
    {
        $task->{'waypoints'}->[$i]->{'short_lat'} = $sr1->[$i]->{'lat'};
        $task->{'waypoints'}->[$i]->{'short_long'} = $sr1->[$i]->{'long'};
    }
}

my $task1 = 
    { 
        'tasPk' => 1,
        'waypoints' =>
        [
            { 'key'=> 1, 'number' => 1, 'type' => 'start', 'how' => 'exit',  'shape' => 'circle', radius => 1000, name => 'test1', 'lat' => -36.5 * PI() / 180, 'long' => 110.0 * PI() / 180 },
            { 'key'=> 3, 'number' => 3, 'type' => 'waypoint', 'how' => 'entry', 'shape' => 'circle', radius => 1000, name => 'test3', 'lat' => -37.0 * PI() / 180, 'long' => 110.0 * PI() / 180 },
            { 'key'=> 5, 'number' => 5, 'type' => 'goal', 'how' => 'entry', 'shape' => 'circle', radius => 1000, name => 'test5', 'lat' => -36.5 * PI() / 180, 'long' => 110.5 * PI() / 180 },
        ]
    };


my $task2 = 
    { 
        'tasPk' => 2,
        'waypoints' => 
        [
            { 'key'=> 1, 'number' => 1, 'type' => 'start',    'how' => 'exit',  'shape' => 'circle', radius => 400, name => 'test1', 'lat' => -36.5 * PI() / 180, 'long' => 110.0 * PI() / 180 },
            { 'key'=> 2, 'number' => 2, 'type' => 'speed',    'how' => 'exit',  'shape' => 'circle', radius => 5000, name => 'test2', 'lat' => -36.5 * PI() / 180, 'long' => 110.0 * PI() / 180 },
            { 'key'=> 3, 'number' => 3, 'type' => 'waypoint', 'how' => 'entry', 'shape' => 'circle', radius => 1000, name => 'test3', 'lat' => -37.0 * PI() / 180, 'long' => 110.0 * PI() / 180 },
            { 'key'=> 4, 'number' => 4, 'type' => 'endspeed', 'how' => 'entry', 'shape' => 'circle', radius => 2000, name => 'test4', 'lat' => -36.5 * PI() / 180, 'long' => 110.5 * PI() / 180 },
            { 'key'=> 5, 'number' => 5, 'type' => 'goal',     'how' => 'entry', 'shape' => 'circle', radius => 1000, name => 'test5', 'lat' => -36.5 * PI() / 180, 'long' => 110.5 * PI() / 180 },
        ]
    };


# tasPk=1097 HC
my $task3 = 
    { 
        'tasPk' => 3,
        'waypoints' => 
[
{ 'key' => 3383, 'number' => 1, 'type' => 'start', 'how' => 'exit', 'shape' => 'circle', 'radius' => 400, name => 'wk2', 'lat' => -44.63759987 * PI() / 180, 'long' => 168.90910026 * PI() / 180 },
{ 'key' => 3378, 'number' => 2, 'type' => 'speed', 'how' => 'exit', 'shape' => 'circle', 'radius' => 1000, name => 'wk2', 'lat' => -44.63759987 * PI() / 180, 'long' => 168.90910026 * PI() / 180 },
{ 'key' => 3379, 'number' => 3, 'type' => 'waypoint', 'how' => 'exit', 'shape' => 'circle', 'radius' => 8000, name => 'wk2', 'lat' => -44.63759987 * PI() / 180, 'long' => 168.90910026 * PI() / 180 },
{ 'key' => 3380, 'number' => 4, 'type' => 'waypoint', 'how' => 'entry', 'shape' => 'circle', 'radius' => 400, name => 'wk2', 'lat' => -44.63759987 * PI() / 180, 'long' => 168.90910026 * PI() / 180 },
{ 'key' => 3381, 'number' => 5, 'type' => 'waypoint', 'how' => 'entry', 'shape' => 'circle', 'radius' => 1000, name => 'wk3', 'lat' => -44.59199997 * PI() / 180, 'long' => 169.33140015 * PI() / 180 },
{ 'key' => 3382, 'number' => 6, 'type' => 'goal', 'how' => 'entry', 'shape' => 'circle', 'radius' => 1000, name => 'wk4', 'lat' => -44.68069995 * PI() / 180, 'long' => 169.19040011 * PI() / 180 }
]
    };

my $task4 =
{
        'tasPk' => 4,
        'waypoints' => 
[
{ 'key' => 1, 'number' => 1, 'type' => 'start', 'how' => 'exit', 'shape' => 'circle', 'radius' => 5000, name => 'ELLIOT', 'lat' => -36.185833 * PI() / 180, 'long' => 147.976667 * PI() / 180 },
{ 'key' => 2, 'number' => 2, 'type' => 'goal', 'how' => 'entry', 'shape' => 'circle', 'radius' => 1000, name => 'KHANCO', 'lat' => -36.216217 * PI() / 180, 'long' => 148.109783 * PI() / 180 }
]
};

my $task5 =
{
    'tasPk' => 5,
    'waypoints' =>
[
{ 'key' => '36', 'number' => '10', 'radius' => '400', 'lat' => '-0.641546052379078', 'long' => '2.56502999548934', 'how' => 'exit', 'shape' => 'circle', 'type' => 'start', 'name' => 'mys080' },
{ 'key' => '37', 'number' => '20', 'radius' => '7000', 'lat' => '-0.642631371708502', 'long' => '2.56455616565003', 'how' => 'entry', 'shape' => 'circle', 'name' => 'dem102', 'type' => 'speed' },
{ 'key' => '38', 'number' => '30', 'radius' => '2000', 'lat' => '-0.642631371708502', 'long' => '2.56455616565003', 'name' => 'dem102', 'type' => 'waypoint', 'shape' => 'circle', 'how' => 'entry' },
{ 'key' => '41', 'number' => '35', 'radius' => '13000', 'lat' => '-0.634883222805168', 'long' => '2.56638767501934', 'type' => 'waypoint', 'name' => '7C-025', 'shape' => 'circle', 'how' => 'entry' },
{ 'key' => '39', 'number' => '40', 'radius' => '2000', 'lat' => '-0.64075822274863', 'long' => '2.56811819708995', 'name' => '8E-042', 'type' => 'endspeed', 'shape' => 'circle', 'how' => 'entry' },
{ 'key' => '42', 'number' => '50', 'radius' => '1000', 'lat' => '-0.641087855513216', 'long' => '2.56856853764241', 'type' => 'goal', 'name' => '8F-034', 'shape' => 'circle', 'how' => 'entry' }
]
};


# tasPk=1093 HC
my $task6 =
{
    'tasPk' => 6,
    'waypoints' =>
[
{ 'key' => 12219, 'number' => '10', 'radius' => 100, 'lat' => -33.643726 * PI() / 180, 'long' => 150.244876 * PI() / 180, 'how' => 'exit', 'shape' => 'circle', type => 'start', 'name' => 'lblack' },
{ 'key' => 12199, 'number' => '20', 'radius' => 2500, 'lat' => -33.647819 * PI() / 180,'long' => 150.288735 * PI() / 180, 'how' => 'entry', 'shape' => 'circle', type => 'speed', 'name' => 'bkgolf' },
{ 'key' => 12219, 'number' => '30', 'radius' => 200, 'lat' =>  -33.643726 * PI() / 180,'long' => 150.244876 * PI() / 180, 'how' => 'entry', 'shape' => 'circle', type => 'waypoint'|  1032.002438281, 'name' => 'lblack' },
{ 'key' => 12203, 'number' => '40', 'radius' => 7000, 'lat' => -33.47665 * PI() / 180,'long' => 150.223125 * PI() / 180, 'how' => 'entry', 'shape' => 'circle', type => 'waypoint', 'name' => 'clarnc' },
{ 'key' => 12212, 'number' => '50', 'radius' => 7000, 'lat' => -33.646 * PI() / 180,'long' => 150.048227 * PI() / 180, 'how' => 'entry', 'shape' => 'circle', type => 'waypoint', 'name' => 'hamptn' }, 
{ 'key' => 12223, 'number' => '60', 'radius' => 1000, 'lat' => -33.632263 * PI() / 180,'long' => 150.255737 * PI() / 180, 'how' => 'entry', 'shape' => 'circle', type => 'endspeed', 'name' => 'lzblac' },
{ 'key' => 12223, 'number' => '70', 'radius' => 100, 'lat' => -33.632263 * PI() / 180,'long' => 150.255737 * PI() / 180, 'how' => 'entry', 'shape' => 'line', 'type' => 'goal', 'name' => 'lzblac' }
]
};

# tasPk=1100 CAN T2 2018



my $task7 =
{
    'tasPk' => 7,
    'waypoints' =>
[
  { 'name' => 'LTAMB',  'key' => 4424, 'number' => 5,  'type' => 'start', 'how' => 'entry', 'shape' => 'circle', 'radius' => 100, 'lat' => -27.950278 * PI() / 180, 'long' => 153.181194 * PI() / 180  },
  { 'name' => '101STH', 'key' => 4418, 'number' => 10, 'type' => 'speed', 'how' => 'exit', 'shape' => 'circle', 'radius' => 19500, 'lat' => -27.925889 * PI() / 180, 'long' => 153.369256 * PI() / 180  },
  { 'name' => '84FLIN', 'key' => 4419, 'number' => 20, 'type' => 'waypoint', 'how' => 'entry', 'shape' => 'circle', 'radius' => 37000 , 'lat' => -27.812958 * PI() / 180, 'long' => 152.81003033862 * PI() / 180 },
  { 'name' => '84FLIN', 'key' => 4420, 'number' => 30, 'type' => 'waypoint', 'how' => 'exit', 'shape' => 'circle', 'radius' => 44000 , 'lat' => -27.812958 * PI() / 180, 'long' => 152.81003033862 * PI() / 180 },
  { 'name' => '13RATH', 'key' => 4421, 'number' => 40, 'type' => 'waypoint', 'how' => 'entry', 'shape' => 'circle', 'radius' => 15000 , 'lat' => -28.212227 * PI() / 180, 'long' => 152.864441 * PI() / 180 },
  { 'name' => '84FLIN', 'key' => 4422, 'number' => 50, 'type' => 'endspeed', 'how' => 'entry', 'shape' => 'circle', 'radius' => 14000 , 'lat' => -27.812958 * PI() / 180, 'long' => 152.81003033862 * PI() / 180 },
  { 'name' => '21COUL', 'key' => 4423, 'number' => 60, 'type' => 'goal', 'how' => 'entry', 'shape' => 'circle', 'radius' => 400 , 'lat' => -27.949383 * PI() / 180, 'long' => 152.7209 * PI() / 180 }
]
};


my ($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist);

#####

fix_task($task1);

($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist) = task_distance($task1);

is($spt, 0, "start speed point");
is($ept, 2, "end speed point");
is($gpt, 2, "goal point");
is(sprintf("%.1f", $ssdist), "122820.1", "speed section distance");
is($startssdist, 1000, "start speed distance");
is(sprintf("%.1f", $endssdist), "123820.1", "end speed section distance");
is(sprintf("%.1f", $totdist), "123820.1", "total distance");

#####

fix_task($task2);

($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist) = task_distance($task2);

is($spt, 1, "start speed point");
is($ept, 3, "end speed point");
is($gpt, 4, "goal point");
is(sprintf("%.1f", $ssdist), "117834.7", "speed section distance");
is($startssdist, 5000, "start speed distance");
is(sprintf("%.1f", $endssdist), "122834.7", "end speed section distance");
is(sprintf("%.1f", $totdist), "123831.7", "total distance");

$task2->{'waypoints'}->[4]->{'shape'} = 'line';

#####

my $sr3 = find_shortest_route($task2);
for (my $i = 0; $i < scalar @$sr3; $i++)
{
    $task2->{'waypoints'}->[$i]->{'short_lat'} = $sr3->[$i]->{'lat'};
    $task2->{'waypoints'}->[$i]->{'short_long'} = $sr3->[$i]->{'long'};
}

($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist) = task_distance($task2);

is($spt, 1, "start speed point");
is($ept, 3, "end speed point");
is($gpt, 4, "goal point");
is(sprintf("%.1f", $ssdist), "117837.9", "speed section distance");
is($startssdist, 5000, "start speed distance");
is(sprintf("%.1f", $endssdist), "122837.9", "end speed section distance");
is(sprintf("%.1f", $totdist), "124831.7", "total distance");

#####

fix_task($task3);

($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist) = task_distance($task3);

is($spt, 1, "start speed point");
is($ept, 5, "end speed point");
is($gpt, 5, "goal point");
is(sprintf("%.1f", $ssdist), "60094.2", "speed section distance");
is(sprintf("%.0f", $startssdist), "1000", "start speed distance");
is(sprintf("%.1f", $endssdist), "61094.2", "end speed section distance");
is(sprintf("%.1f", $totdist), "61094.2", "total distance");

# add a test for in_semicircle
# super simple 2 point task

fix_task($task4);

($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist) = task_distance($task4);

is($spt, 0, "start speed point");
is($ept, 1, "end speed point");
is($gpt, 1, "goal point");
is(sprintf("%.1f", $ssdist), "6437.2", "speed section distance");
is($startssdist, 5000, "start speed distance");
is(sprintf("%.1f", $endssdist), "11437.2", "end speed section distance");
is(sprintf("%.1f", $totdist), "11437.2", "total distance");

#####

fix_task($task5);

($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist) = task_distance($task5);
print "($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist)\n";

#####

fix_task($task6);

($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist) = task_distance($task6);
print "($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist)\n";

is($spt, 1, "start speed point");
is($ept, 5, "end speed point");
is($gpt, 6, "goal point");
is(sprintf("%.1f", $ssdist), "37852.0", "speed section distance");
is(sprintf("%.1f", $startssdist), "1595.1", "start speed distance");
is(sprintf("%.1f", $endssdist), "39447.1", "end speed section distance");
is(sprintf("%.1f", $totdist), "40447.1", "total distance");

fix_task($task7);

#print Dumper($task7);
($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist) = task_distance($task7);
print "($spt, $ept, $gpt, $ssdist, $startssdist, $endssdist, $totdist)\n";

is($spt, 1, "start speed point");
is($ept, 5, "end speed point");
is($gpt, 6, "goal point");
is(sprintf("%.1f", $ssdist), "57476.2", "speed section distance");
is(sprintf("%.1f", $startssdist), "2096.1", "start speed distance");
is(sprintf("%.1f", $endssdist), "59572.3", "end speed section distance");
is(sprintf("%.1f", $totdist), "67060.4", "total distance");

done_testing
