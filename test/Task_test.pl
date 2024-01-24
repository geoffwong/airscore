#!/usr/bin/perl -I..

require Task;
require Route;
use Test::More;
use Data::Dumper;
use strict;

# Setup some tasks ..

sub fix_sr
{
    my ($task, $sr) = @_;
    for (my $i = 0; $i < scalar @$sr; $i++)
    {
        $task->{'waypoints'}->[$i]->{'short_lat'} = $sr->[$i]->{'lat'};
        $task->{'waypoints'}->[$i]->{'short_long'} = $sr->[$i]->{'long'};
    }
}

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
    $wpts = find_shortest_route($task);
    fix_sr($task, $wpts);
}

sub fix_sr
{
    my ($task, $sr) = @_;
    for (my $i = 0; $i < scalar @$sr; $i++)
    {
        $task->{'waypoints'}->[$i]->{'short_lat'} = $sr->[$i]->{'lat'};
        $task->{'waypoints'}->[$i]->{'short_long'} = $sr->[$i]->{'long'};
    }
}

sub make_coord
{
    my ($dlat, $dlong) = @_;
    my %coord;

    $coord{'time'} = time();
    $coord{'dlat'} = $dlat;
    $coord{'dlong'} = $dlong;
    $coord{'lat'} = $dlat * PI() / 180;
    $coord{'long'} = $dlong * PI() / 180; 

    return \%coord;
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


my $dist;
my $sr;
my $wpts;
my $coord;

# Test computed waypoint distance (various scenarios)
fix_task($task6);
$wpts = $task6->{'waypoints'};

# without cache
$dist = compute_waypoint_dist($wpts, 2);
is(sprintf("%.1f", $dist), "3086.6", "task 6 - wpt 2");
$dist = compute_waypoint_dist($wpts, 3);
is(sprintf("%.1f", $dist), "15185.1", "task 6 - wpt 3");
$dist = compute_waypoint_dist($wpts, 4);
is(sprintf("%.1f", $dist), "27650.4", "task 6 - wpt 4");
$dist = compute_waypoint_dist($wpts, 5);
is(sprintf("%.1f", $dist), "39447.0", "task 6 - wpt 5");

# with cache
precompute_waypoint_dist($wpts);

$dist = compute_waypoint_dist($wpts, 2);
is(sprintf("%.1f", $dist), "3086.6", "task 6 - wpt 2");
$dist = compute_waypoint_dist($wpts, 3);
is(sprintf("%.1f", $dist), "15185.1", "task 6 - wpt 3");
$dist = compute_waypoint_dist($wpts, 4);
is(sprintf("%.1f", $dist), "27636.9", "task 6 - wpt 4");
$dist = compute_waypoint_dist($wpts, 5);
is(sprintf("%.1f", $dist), "39447.0", "task 6 - wpt 5");

# Test remaining task distance
$coord = make_coord(-33.64532, 150.25388);
$dist = remaining_task_dist($wpts, 2, $coord);
is(sprintf("%.1f", $dist), "38012.4", "task 6 remaining c1 - 2 made");
$dist = remaining_task_dist($wpts, 3, $coord);
is(sprintf("%.1f", $dist), "37928.6", "task 6 remaining c1 - 3 made");

$coord = make_coord(-33.64896, 150.27239);
$dist = remaining_task_dist($wpts, 3, $coord);
is(sprintf("%.1f", $dist), "38973.9", "task 6 remaining c2 - 2 made");
$coord = make_coord(-33.64896, 150.27439);
$dist = remaining_task_dist($wpts, 3, $coord);
is(sprintf("%.1f", $dist), "39056.6", "task 6 remaining c2 - 3 made");

fix_task($task5);
$wpts = $task5->{'waypoints'};
precompute_waypoint_dist($wpts);

$dist = compute_waypoint_dist($wpts, 2);
is(sprintf("%.1f", $dist), "5314.9", "task 5 - wpt 2");
$dist = compute_waypoint_dist($wpts, 3);
is(sprintf("%.1f", $dist), "41027.7", "task 5 - wpt 3");
$dist = compute_waypoint_dist($wpts, 4);
is(sprintf("%.1f", $dist), "66100.4", "task 5 - wpt 4");
$dist = compute_waypoint_dist($wpts, 5);
is(sprintf("%.1f", $dist), "68586.9", "task 5 - wpt 5");

fix_task($task2);
$wpts = $task2->{'waypoints'};
precompute_waypoint_dist($wpts);

$dist = compute_waypoint_dist($wpts, 1);
is(sprintf("%.1f", $dist), "4985.5", "task 2 - wpt 1");
$dist = compute_waypoint_dist($wpts, 2);
is(sprintf("%.1f", $dist), "54541.0", "task 2 - wpt 2");
$dist = compute_waypoint_dist($wpts, 3);
is(sprintf("%.1f", $dist), "122823.4", "task 2 - wpt 3");
$dist = compute_waypoint_dist($wpts, 4);
is(sprintf("%.1f", $dist), "123817.2", "task 2 - wpt 4");

# Test distance flown

done_testing;

