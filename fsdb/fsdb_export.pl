#!/usr/bin/perl -I/home/geoff/bin
#
# Export from Airscore to a FS compatible XML file
# 
# Since FS hasn't bothered to publish a specification for the .fsdb xml format
# This is an exercise in reverse engineering and isn't 100% correct. 
# 
# Geoff Wong 2009
#

use XML::Simple;
use Data::Dumper;
use POSIX qw(ceil floor strftime);
use JSON;

use TrackLib qw(:all);
use Defines qw(:all);
use TrackDb qw(:all);

use strict;

require Gap;

my $pi = atan2(1,1) * 4; 

sub empty
{
    my %h;
    return \%h;
}

sub emarr
{
    my @h;
    return \@h;
}

sub fs_time
{
    my ($dte,$off) = @_;

    if ($dte eq '')
    {
        return $dte;
    }

    $dte =~ s/ /T/;
    if ($off >= 0)
    {
        $dte = $dte . "+" . sprintf("%02d:00", $off);
    }
    else
    {
        $dte = $dte . "-" . sprintf("%02d:00", $off);
    }

    return $dte;
}

sub hms_time
{
    my ($secs) = @_;
    my ($h,$m,$s);

    $h = $secs / 3600;
    $m = ($secs / 60) % 60;
    $s = $secs % 60;

    return sprintf("%02d:%02d:%02d", $h, $m, $s);
}

sub conv_time
{
    my ($tm) = @_;
    my $h;
    my $res;

    if (length($tm) == 0)
    {
        return "1970-01-01 00:00:00";
    }

    $h = 0 + int(substr($tm,11,2));
    $h = $h - int(substr($tm,20,2));

    $res =  sprintf("%s %02d%s", substr($tm,0,10), $h, substr($tm,13,6));
    #print "conv_time=$res\n";
    return $res;
}


my %dud;
my %fsx;
my $fsdb;
my %formula;
my $comformula;
my %intformula;
my %pilmap;
my %taskmap;
my @pilots;
my $task;
my @tasks;
my $count = 1;
my ($dbh, $sth, $ref);
my $utc;
my ($comp, $pilots, $form, $formid, $fparam);

if ($#ARGV < 1)
{
    print "fsdb_export.pl <comPk> <outputdb>\n";
    exit 1;
}

my $comPk = 0 + $ARGV[0];
my $outfile = $ARGV[1];

if (0+$comPk < 1)
{
    print "Bad comPk=$comPk\n";
    exit 1;
}


#my $comp_result=`php -q ../get_json_result.php \"comPk=$comPk\"`;
#my $overall_result_data = from_json($comp_result, {utf8 => 1});
#print Dumper($overall_result_data);
#exit(1);

$fsdb = empty();
$fsdb->{'FsCompetition'} = empty();

$fsx{'Fs'} = $fsdb;
$fsx{'Fs'}->{'version'} = "3.4";
$fsx{'Fs'}->{'comment'} = "Supports only a single Fs element in a .fsdb file which must be the root element.";

$dbh = db_connect();

my $comp = read_competition($comPk);
$fsdb->{'FsCompetition'}->{'id'} = $comp->{'comPk'};
$fsdb->{'FsCompetition'}->{'name'} = $comp->{'name'};
$fsdb->{'FsCompetition'}->{'location'} = $comp->{'location'};
$fsdb->{'FsCompetition'}->{'from'} = substr($comp->{'datefrom'},0,10);
$fsdb->{'FsCompetition'}->{'to'} = substr($comp->{'dateto'},0,10);
$utc = $comp->{'timeoffset'};
$fsdb->{'FsCompetition'}->{'utc_offset'} = $utc;
$fsdb->{'FsCompetition'}->{'discipline'} = lc($comp->{'class'});
if ($comp->{'overallscore'} ne 'ftv')
{
    $fsdb->{'FsCompetition'}->{'ftv_factor'} = '0';
}
else
{
    $fsdb->{'FsCompetition'}->{'ftv_factor'} = 1 - ($comp->{'overallparam'} / 100);
}
$fsdb->{'FsCompetition'}->{'fai_sanctioning'} = '2';
$fsdb->{'FsCompetition'}->{'categories'} = 'filter';


$fsdb->{'FsCompetition'}->{'FsCompetitionNotes'} = empty();
$fsdb->{'FsCompetition'}->{'FsScoreFormula'} = \%formula;

$ref = read_formula($comPk);
$comformula = $ref;
$formula{'id'} = uc($ref->{'class'}) . $ref->{'version'};
$formula{'use_distance_points'} = '1';
$formula{'use_time_points'} = '1';
$formula{'use_departure_points'} = '0';
$formula{'use_leading_points'} = '1';
$formula{'nom_time'} = $ref->{'nomtime'} / 60;
$formula{'nom_goal'} = $ref->{'nomgoal'} / 100;
$formula{'time_points_if_not_in_goal'} = 1 - (0+$ref->{'sspenalty'});
$formula{'use_1000_points_for_max_day_quality'} = '1';
$formula{'time_validity_based_on_pilot_with_speed_rank'} = '1';

$formula{'min_dist'} = $ref->{'mindist'};
$formula{'nom_dist'} = $ref->{'nomdist'};
$formula{'nom_launch'} = $ref->{'nomlaunch'};
$formula{'day_quality_override'} = "0";
$formula{'bonus_gr'} = $ref->{'glidebonus'};
$formula{'jump_the_gun_factor'} = '0';
$formula{'jump_the_gun_max'} = '0';
$formula{'normalize_1000_before_day_quality'} = '0';
if ($ref->{'sspenalty'} == 1.0)
{
    $formula{'time_points_if_not_in_goal'} = '0';
}
else
{
    $formula{'time_points_if_not_in_goal'} = '1';
}
$formula{'use_1000_points_for_max_day_quality'} = '0';
if ($ref->{'forArrival'} eq 'place')
{
    $formula{'use_arrival_position_points'} ='1';
    $formula{'use_arrival_time_points'} ='0';
}
else
{
    $formula{'use_arrival_position_points'} = '1';
    $formula{'use_arrival_time_points'} = '0';
}
if ($formula{'LinearDist'} == 1.0)
{
    $formula{'use_difficulty_for_distance_points'} = '0';
}
else
{
    $formula{'use_difficulty_for_distance_points'} = '1';
}
$formula{'use_distance_points'} = '1';
$formula{'use_distance_squared_for_LC'} = '0';
if ($ref->{'departure'} eq 'leadout')
{
    $formula{'use_leading_points'} = '1';
}
elsif ($ref->{'forDeparture'} eq 'departure')
{
    $formula{'use_departure_points'} = '1';
}
$formula{'use_semi_circle_control_zone_for_goal_line'} = '1';
$formula{'use_time_points'} = '1';
$formula{'scoring_altitude'} = 'GPS';
$formula{'final_glide_decelerator'} = 'none';
$formula{'no_final_glide_decelerator_reason'} = '';
$formula{'min_time_span_for_valid_task'} = '60';
$formula{'score_back_time'} = '5';
$formula{'use_proportional_leading_weight_if_nobody_in_goal'} = '1';
$formula{'leading_weight_factor'} = '1';
$formula{'turnpoint_radius_tolerance'} = '0.0005';
$formula{'turnpoint_radius_minimum_absolute_tolerance'} = '5';
$formula{'number_of_decimals_task_results'} = '2';
$formula{'number_of_decimals_competition_results'} = '1';
$formula{'redistribute_removed_time_points_as_distance_points'} = '0';
$formula{'use_best_score_for_ftv_validity'} = '1';
$formula{'use_constant_leading_weight'} = '0';
$formula{'use_pwca2019_for_lc'} = '0';
$formula{'use_flat_decline_of_timepoints'} = '0';

$intformula{'arrival_weight'} = $ref->{'weightarrival'};
$intformula{'departure_weight'} = 0;
$intformula{'leading_weight'} = $ref->{'weightstart'}; 
$intformula{'time_weight'} = $ref->{'weightspeed'};
$intformula{'distance_weight'} = 1 - $ref->{'weightstart'} - $ref->{'weightspeed'};

$fsdb->{'FsCompetition'}->{'FsParticipants'}->{'FsParticipant'} = \@pilots;
$sth = $dbh->prepare("select P.* from tblPilot P, tblTaskResult TR, tblTrack TK, tblTask T where P.pilPk=TK.pilPk and TK.traPk=TR.traPk and TR.tasPk=T.tasPk and T.comPk=$comPk group by P.pilPk");
$sth->execute();
$ref = $sth->fetchrow_hashref();
while (defined($ref))
{
    my $pilot;

    $pilot = empty();
    $pilot->{'FsParticipant'} = empty();
    $pilot->{'FsParticipant'}->{'id'} = $count; #$ref->{'pilPk'};
    $pilot->{'FsParticipant'}->{'name'} = $ref->{'pilFirstName'} . ' ' . $ref->{'pilLastName'};
    $pilot->{'FsParticipant'}->{'nat_code_3166_a3'} = $ref->{'pilNationCode'};
    if ($ref->{'pilSex'} eq 'F')
    {
        $pilot->{'FsParticipant'}->{'female'} = 1;
    }
    else
    {
        $pilot->{'FsParticipant'}->{'female'} = 0;
    }
    $pilot->{'FsParticipant'}->{'birthday'} = $ref->{'pilBirthdate'};
    $pilot->{'FsParticipant'}->{'glider'} = $ref->{'traGlider'};
    $pilot->{'FsParticipant'}->{'color'} = '';
    $pilot->{'FsParticipant'}->{'sponsor'} = $ref->{'pilSponsor'};
    $pilot->{'FsParticipant'}->{'CIVLID'} = $ref->{'pilCIVL'};
    $pilot->{'FsParticipant'}->{'fai_license'} = '1';
    $pilot->{'FsParticipant'}->{'FsCustomAttributes'} = empty();

    #<xs:attribute type="xs:string" name="glider_main_colors" use="optional"/>

    $pilmap{$ref->{'pilPk'}} = $count;
    push @pilots, $pilot;
    $count++;
    $ref = $sth->fetchrow_hashref();
}

$count = 1;
my $rankings = emarr();
my $participants = emarr();
$fsdb->{'FsCompetition'}->{'FsCompetitionResults'} = empty();
$fsdb->{'FsCompetition'}->{'FsCompetitionResults'}->{'FsCompetitionResult'} = $rankings;
$task->{'FsParticipants'}->{'FsParticipant'} = $participants;
$fsdb->{'FsCompetition'}->{'FsTasks'}->{'FsTask'} = \@tasks;

# Tasks
my @alltasks;
my $task_totals;

$sth = $dbh->prepare("select tasPk from tblTask TK where TK.comPk=$comPk order by TK.tasPk");
$sth->execute();
$ref = $sth->fetchrow_hashref();
while (defined($ref))
{
    push @alltasks, $ref->{'tasPk'};
    $ref = $sth->fetchrow_hashref();
}

foreach my $tasPk (@alltasks)
{
    my $gap = Gap->new();
    $ref = read_task($tasPk);
    print Dumper($ref);
    $task_totals = $gap->task_totals($dbh,$ref,$comformula);
    my ($Adistance, $Aspeed, $Astart, $Aarrival) = $gap->points_weight($ref, $task_totals, \%formula);

    my @tps;
    $task = empty();
    $task->{'id'} = $tasPk;
    $task->{'name'} = $ref->{'name'};
    $task->{'tracklog_folder'} =  'none';
    $task->{'FsScoreFormula'} = \%formula;
    #$task->{'FsParticipants'}->{'FsParticipant'} = \@pilots;
    #$task->{'FsTaskScoreParams'} = empty();
    $task->{'FsTaskScoreParams'}->{'ss_distance'} = sprintf("%.4f", ($ref->{'ssdistance'}/1000));
    $task->{'FsTaskScoreParams'}->{'task_distance'} = sprintf("%.4f", ($ref->{'short_distance'}/1000));
    $task->{'FsTaskScoreParams'}->{'launch_to_ess_distance'} = sprintf("%.4f", ($ref->{'endssdistance'}/1000));
    $task->{'FsTaskScoreParams'}->{'no_of_pilots_present'} = $task_totals->{'pilots'};
    $task->{'FsTaskScoreParams'}->{'no_of_pilots_flying'} = $task_totals->{'launched'};
    $task->{'FsTaskScoreParams'}->{'no_of_pilots_lo'} = $task_totals->{'launched'} - $task_totals->{'goal'};
    $task->{'FsTaskScoreParams'}->{'no_of_pilots_reaching_nom_dist'} = $task_totals->{'launched'};	# fixme
    $task->{'FsTaskScoreParams'}->{'no_of_pilots_reaching_es'} = $task_totals->{'ess'};
    $task->{'FsTaskScoreParams'}->{'no_of_pilots_reaching_goal'} = $task_totals->{'goal'};
    $task->{'FsTaskScoreParams'}->{'no_of_pilots_in_competition'} = $task_totals->{'pilots'};
    $task->{'FsTaskScoreParams'}->{'sum_dist_over_min'} = sprintf("%.3f", $task_totals->{'distance'}/1000);
    $task->{'FsTaskScoreParams'}->{'max_time_to_get_time_points'} = '0';
    $task->{'FsTaskScoreParams'}->{'no_of_pilots_with_time_points'} = $task_totals->{'ess'};  # @fixme
    #$task->{'FsTaskScoreParams'}->{'k'} = '';
    #$task->{'FsTaskScoreParams'}->{'arrival_weight'} = 
    $task->{'FsTaskScoreParams'}->{'best_dist'} = sprintf("%.4f", $task_totals->{'maxdist'}/1000);
    $task->{'FsTaskScoreParams'}->{'best_time'} = $task_totals->{'fastest'};
    $task->{'FsTaskScoreParams'}->{'worst_time'} = '0';
    $task->{'FsTaskScoreParams'}->{'sum_flown_distance'} = sprintf("%.2f", $task_totals->{'avdist'} * $task_totals->{'launched'} / 1000);
    $task->{'FsTaskScoreParams'}->{'sum_dist_over_min'} = sprintf("%.2f", ($task_totals->{'avdist'} - $formula{'min_dist'}) * $task_totals->{'launched'} / 1000);
    $task->{'FsTaskScoreParams'}->{'sum_real_dist_over_min'} = $task->{'FsTaskScoreParams'}->{'sum_dist_over_min'}; # ??
    $intformula{'arrival_weight'} = $ref->{'forWeightArrival'};
    $task->{'FsTaskScoreParams'}->{'departure_weight'} = $intformula{'departure_weight'};
    $task->{'FsTaskScoreParams'}->{'leading_weight'} = $intformula{'leading_weight'};
    $task->{'FsTaskScoreParams'}->{'time_weight'} = $intformula{'time_weight'};
    $task->{'FsTaskScoreParams'}->{'distance_weight'} = $intformula{'distance_weight'};
    $task->{'FsTaskScoreParams'}->{'smallest_leading_coefficient'} = $task_totals->{'mincoeff'};
    $task->{'FsTaskScoreParams'}->{'available_points_distance'} = $Adistance;
    $task->{'FsTaskScoreParams'}->{'available_points_time'} = $Aspeed;
    #$task->{'FsTaskScoreParams'}->{'available_points_departure'} = '';
    $task->{'FsTaskScoreParams'}->{'available_points_leading'} = $Astart;
    $task->{'FsTaskScoreParams'}->{'available_points_arrival'} = $Aarrival;
    #$task->{'FsTaskDistToTp'} = '';
    my ($distance,$time,$launch,$stopped) = $gap->day_quality($task_totals,$comformula);
    $task->{'FsTaskScoreParams'}->{'time_validity'} = $time;
    $task->{'FsTaskScoreParams'}->{'launch_validity'} = $launch;
    $task->{'FsTaskScoreParams'}->{'distance_validity'} = $distance;
    $task->{'FsTaskScoreParams'}->{'stop_validity'} = $stopped;
    $task->{'FsTaskScoreParams'}->{'day_quality'} = $time * $launch * $distance * $stopped;
    $task->{'FsTaskScoreParams'}->{'ftv_day_validity'} = $time * $launch * $distance * $stopped;
    $task->{'FsTaskScoreParams'}->{'time_points_stop_correction'} = 0;
    # taskdisttotp list
    $taskmap{$ref->{'tasPk'}} = $task;

    # fix - check if stopped
    $task->{'FsTaskState'} = empty();
    $task->{'FsTaskState'}->{'task_state'} = 'REGULAR';
    $task->{'FsTaskState'}->{'score_back_time'} = '5';
    $task->{'FsTaskState'}->{'cancel_reason'} = '';
    $task->{'FsTaskState'}->{'stop_time'} = $ref->{'finish'};

    $count++;
    push @tasks, $task;
}


# Waypoints
my ($ss, $es);
my $tps = emarr();
my $startg = emarr();
my $sroute = emarr();
my $turn;
my $cnt = 1;
my $lastPk = 0;
my $p1;
my $p2;
my $disxml;
my $gate;
my $dist = 0;

$es = 0;
$sth = $dbh->prepare("select TK.*, TW.*, R.*, SR.* from tblTask TK, tblTaskWaypoint TW, tblRegionWaypoint R, tblShortestRoute SR where TW.tasPk=TK.tasPk and SR.tawPk=TW.tawPk and R.rwpPk=TW.rwpPk and TK.comPk=$comPk order by TK.tasPk,TW.tawNumber");
$sth->execute();
$ref = $sth->fetchrow_hashref();
while (defined($ref))
{
    if ($lastPk != $ref->{'tasPk'} && $lastPk != 0)
    {
        $task = $taskmap{$lastPk};
        $task->{'FsTaskDefinition'}->{'goal'} = 'CIRCLE';
        $task->{'FsTaskDefinition'}->{'ss'} = $ss;
        $task->{'FsTaskDefinition'}->{'es'} = $es;
        $task->{'FsTaskDefinition'}->{'groundstart'} = '0';
        $task->{'FsTaskDefinition'}->{'qnh_settings'} = '1013.25';
        $task->{'FsTaskDefinition'}->{'FsTurnpoint'} = $tps;
        $task->{'FsTaskDefinition'}->{'FsTurnpoint'} = $tps;
        $startg = emarr();
        $gate = empty();
        $gate->{'open'} = $turn->{'open'};
        push @$startg, $gate; # should find all for speedrun
        $task->{'FsTaskDefinition'}->{'FsStartGate'} = $startg;
        $task->{'FsTaskScoreParams'}->{'FsTaskDistToTp'} = $sroute;
        $tps = emarr();
        $sroute = emarr();
        $cnt = 1;
    }
    $lastPk = $ref->{'tasPk'};
    $turn = empty();
    $turn->{'id'} = $ref->{'rwpName'};
    $turn->{'lat'} = sprintf("%.5f", $ref->{'rwpLatDecimal'});
    $turn->{'lon'} = sprintf("%.5f", $ref->{'rwpLongDecimal'});
    $turn->{'altitude'} = $ref->{'rwpAltitude'};
    $turn->{'radius'} = $ref->{'tawRadius'};
    $turn->{'open'} = fs_time($ref->{'tasStartTime'}, $utc);
    $turn->{'close'} = fs_time($ref->{'tasFinishTime'}, $utc);

    if ($ref->{'tawType'} eq 'start')
    {
        $ss = $cnt;
        $turn->{'open'} = fs_time($ref->{'tasTaskStart'}, $utc);
        $turn->{'close'} = fs_time($ref->{'tasStartCloseTime'}, $utc);
    }
    if ($ref->{'tawType'} eq 'speed')
    {
        $ss = $cnt;
        $turn->{'close'} = fs_time($ref->{'tasStartCloseTime'}, $utc);
    }
    if ($ref->{'tawType'} eq 'endspeed')
    {
        $es = $cnt;
    }
    if ($es == 0)
    {
        if ($ref->{'tawType'} eq 'goal')
        {
            $es = $cnt;
        }
    }
    push @$tps, $turn;

    if (defined($p1))
    {
        $p2->{'lat'} = $p1->{'lat'};
        $p2->{'long'} = $p1->{'long'};
    }
    $p1->{'lat'} = (0.0 + $ref->{'ssrLatDecimal'}) * $pi / 180;
    $p1->{'long'} = (0.0 + $ref->{'ssrLongDecimal'}) * $pi / 180;
    if (defined($p2))
    {
        $dist += (distance($p1, $p2) / 1000);
    }
    $disxml = empty();
    $disxml->{'tp_no'} = $cnt;
    $disxml->{'distance'} = $dist;
    push @$sroute, $disxml;

    $cnt++;
    $ref = $sth->fetchrow_hashref();
}
$task = $taskmap{$lastPk};
$task->{'FsTaskDefinition'}->{'goal'} = 'CIRCLE';
$task->{'FsTaskDefinition'}->{'ss'} = $ss;
$task->{'FsTaskDefinition'}->{'es'} = $es;
$task->{'FsTaskDefinition'}->{'FsTurnpoint'} = $tps;
$startg = emarr();
$gate = empty();
$gate->{'open'} = $turn->{'open'};
push @$startg, $gate; # should find all for speedrun
$task->{'FsTaskDefinition'}->{'FsStartGate'} = $startg;
$task->{'FsTaskScoreParams'}->{'FsTaskDistToTp'} = $sroute;

# Add start gates <FsStartGate open="">

# Results
my $tpresult;
my $taskr;
my $taskresults = emarr();
my $taskoverall = empty();
my $partinfo = emarr();
my $partresults = emarr();
my $lastPk = 0;
my $place = 1;

$taskoverall->{'id'} = 'overall';
$taskoverall->{'title'} = 'Overall';
#$taskoverall->{'ts'} = $ref->{'finish'};
$taskoverall->{'result_pattern'} = '#0';
$taskoverall->{'FsTaskResultParticipants'} = empty();
$taskoverall->{'FsTaskResultParticipants'}->{'FsTaskParticipantResult'} = $partresults;  # omg - fucking insane naming
push @$taskresults, $taskoverall;

$sth = $dbh->prepare("select TK.*, TR.*, TL.pilPk, TL.traStart, date_add(TK.tasDate, INTERVAL TR.tarStart SECOND) as Start, date_add(TK.tasDate, INTERVAL TR.tarSS SECOND) as Sss, date_add(TK.tasDate, INTERVAL TR.tarES SECOND) as Ess, date_add(TL.traStart, INTERVAL TL.traDuration SECOND) as FinishTime from tblTaskResult TR, tblTask TK, tblTrack TL  where TR.tasPk=TK.tasPk and TL.traPk=TR.traPk and TK.comPk=$comPk order by TK.tasPk, TR.tarScore desc");
$sth->execute();
$ref = $sth->fetchrow_hashref();
while (defined($ref))
{
    if (($lastPk != $ref->{'tasPk'}) && ($lastPk != 0))
    {
        # insert results into tasks
        $task = $taskmap{$lastPk};
        $task->{'FsParticipants'}->{'FsParticipant'} = $partinfo;
        $task->{'FsTaskResults'} = empty();
        $task->{'FsTaskResults'}->{'FsTaskResult'} = $taskresults;
        $taskoverall->{'FsTaskScoreParams'} = $task->{'FsTaskScoreParams'};
        $taskoverall->{'ts'} = fs_time($ref->{'tasFinishTime'}, 0); # strftime("%Y-%m-%sT%H:%M:%S", localtime);
        # clean up for new task
        $taskr = empty();
        $taskresults = emarr();
        $taskoverall = empty();
        $partinfo = emarr();
        $partresults = emarr();
        $taskoverall->{'id'} = 'overall';
        $taskoverall->{'title'} = 'Overall';
        $taskoverall->{'result_pattern'} = '#0';
        $taskoverall->{'FsTaskResultParticipants'}->{'FsTaskParticipantResult'} = $partresults;
        push @$taskresults, $taskoverall;
        $participants = emarr();
        $place = 1;
    }
    $lastPk = $ref->{'tasPk'};
    $taskr = empty();
    $taskr->{'id'} = $pilmap{$ref->{'pilPk'}};
    $taskr->{'FsFlightData'} = empty();
    $taskr->{'FsFlightData'}->{'distance'} = sprintf("%.3f", $ref->{'tarDistance'} / 1000);
    $taskr->{'FsFlightData'}->{'bonus_distance'} = sprintf("%.3f", $ref->{'tarDistance'} / 1000);
    $taskr->{'FsFlightData'}->{'started_ss'} = fs_time($ref->{'Sss'}, 0);
    if ($ref->{'tarES'} > 0)
    {
        $taskr->{'FsFlightData'}->{'finished_ss'} = fs_time($ref->{'Ess'}, 0);
    }
    else
    {
        $taskr->{'FsFlightData'}->{'finished_ss'} = "00:00:00";
    }
    $taskr->{'FsFlightData'}->{'finished_task'} = fs_time($ref->{'FinishTime'}, 0);
    $taskr->{'FsFlightData'}->{'tracklog_filename'} = 'none.igc';
    $taskr->{'FsFlightData'}->{'lc'} = sprintf("%.1f", $ref->{'tarLeadingCoeff'});
    $taskr->{'FsFlightData'}->{'iv'} = 0;
    $taskr->{'FsFlightData'}->{'ts'} = fs_time($ref->{'Start'}, 0);
    $taskr->{'FsFlightData'}->{'alt'} = $ref->{'tarLastAltitude'};
    $taskr->{'FsFlightData'}->{'bonus_alt'} = '0'; # stopped: lastalt - goal_alt
    $taskr->{'FsFlightData'}->{'max_alt'} = '2000'; # why??
    $taskr->{'FsFlightData'}->{'landed_before_deadline'} = '1';
    if ($ref->{'tarGoal'} > 0)
    {
        $taskr->{'FsFlightData'}->{'reachedGoal'} = '1';
    }
    else
    {
        $taskr->{'FsFlightData'}->{'reachedGoal'} = '0';
    }
    if ($ref->{'tarPenalty'} != 0)
    {
        # <FsResultPenalty penalty="9" penalty_points="900" penalty_reason="" />
        $taskr->{'FsResultPenalty'} = empty();
        $taskr->{'FsResultPenalty'}->{'penalty'} = '0';
        $taskr->{'FsResultPenalty'}->{'penalty_points'} = sprintf("%d", int($ref->{'tarPenalty'}));
        $taskr->{'FsResultPenalty'}->{'penalty_reason'} = $ref->{'tarComment'};
    }
    #$taskr->{'FsFlightData'}->{'last_tracklog_point_distance'} = sprintf("%.3f", $ref->{'tarDistance'} / 1000);
    #$taskr->{'FsFlightData'}->{'bonus_last_tracklog_point_distance'} = sprintf("%.3f", $ref->{'tarDistance'} / 1000);
    #$taskr->{'FsFlightData'}->{'last_tracklog_point_time'} = sprintf("%.3f", $ref->{'tarDistance'} / 1000);
    #$taskr->{'FsFlightData'}->{'last_tracklog_point_alt'} = sprintf("%.3f", $ref->{'tarDistance'} / 1000);

    # <FsFlightData distance="77.405" bonus_distance="77.405" started_ss="2020-01-19T12:45:00-05:00" finished_ss="2020-01-19T14:43:50-05:00" altitude_at_ess="1293" finished_task="2020-01-19T14:46:08-05:00" tracklog_filename="0715.igc" lc="1.35638034196738" iv="9372132" ts="2020-05-06T21:52:47+02:00" alt="1017" bonus_alt="1017" max_alt="2429" last_tracklog_point_distance="77.405" bonus_last_tracklog_point_distance="77.405" last_tracklog_point_time="2020-01-19T14:46:08-05:00" last_tracklog_point_alt="1017" landed_before_deadline="1" reachedGoal="1" />

    $taskr->{'FsResult'} = empty();
    $taskr->{'FsResult'}->{'rank'} = $place;
    $taskr->{'FsResult'}->{'finished_ss_rank'} = $place;    # NFI
    $taskr->{'FsResult'}->{'points'} = sprintf("%.0f", $ref->{'tarScore'});
    $taskr->{'FsResult'}->{'distance_points'} = sprintf("%.1f", $ref->{'tarDistanceScore'});
    $taskr->{'FsResult'}->{'time_points'} = sprintf("%.1f", $ref->{'tarSpeedScore'});
    $taskr->{'FsResult'}->{'arrival_points'} = sprintf("%.1f", $ref->{'tarArrival'});
    $taskr->{'FsResult'}->{'departure_points'} = '0';
    $taskr->{'FsResult'}->{'leading_points'} = sprintf("%.1f", $ref->{'tarDeparture'});;
    #$taskr->{'FsResult'}->{'penalty_points_auto'} = sprintf("%d", int($ref->{'tarPenalty'}));
    #$taskr->{'FsResult'}->{'penalty_reason_auto'} = '';
    $taskr->{'FsResult'}->{'penalty_min_dist_points'} = '0';
    $taskr->{'FsResult'}->{'goat_time_but_not_goal_penalty'} = 'False';
    $taskr->{'FsResult'}->{'ss_time_dec_hours'} = '';
    $taskr->{'FsResult'}->{'altitude_bonus_seconds'} = '0';
    $taskr->{'FsResult'}->{'altitude_bonus_time'} = '00:00:00';
    $taskr->{'FsResult'}->{'ts'} = fs_time($ref->{'Ess'}, 0); # now()?
    $taskr->{'FsResult'}->{'altitude_at_ess'} = $ref->{'tarLastAltitude'};
    $taskr->{'FsResult'}->{'started_ss'} = fs_time($ref->{'Sss'}, 0);
    $taskr->{'FsResult'}->{'finished_ss'} = fs_time($ref->{'Ess'}, 0);
    if ($ref->{'tarES'} > 0)
    {
        $taskr->{'FsResult'}->{'ss_time_dec_hours'} = sprintf("%.4f", ($ref->{'tarES'} - $ref->{'tarSS'}) / 3600);
        $taskr->{'FsResult'}->{'ss_time'} = hms_time($ref->{'tarES'} - $ref->{'tarSS'});
        $taskr->{'FsResult'}->{'scored_ss_time'} = hms_time($ref->{'tarES'} - $ref->{'tarSS'});
    }
    else
    {
        $taskr->{'FsResult'}->{'ss_time_dec_hours'} = '0';
        $taskr->{'FsResult'}->{'ss_time'} = '00:00:00';
        $taskr->{'FsResult'}->{'scored_ss_time'} = '00:00:00';
    }
    $taskr->{'FsFlightData'}->{'landed_before_stopped'} = 'False';  # @fixme

    #<FsResult rank="85" points="757" distance="77.404" ss_time="01:58:50" finished_ss_rank="91" distance_points="365.4" linear_distance_points="365.41" difficulty_distance_points="0" time_points="321.8" arrival_points="0" departure_points="0" leading_points="69.6" penalty="0" penalty_points="0" penalty_reason="" penalty_points_auto="0" penalty_reason_auto="" penalty_min_dist_points="0" got_time_but_not_goal_penalty="False" started_ss="2020-01-19T12:45:00-05:00" finished_ss="2020-01-19T14:43:50-05:00" ss_time_dec_hours="1.9806" ts="2020-05-06T21:53:02+02:00" real_distance="77.404" last_distance="77.404" last_altitude_above_goal="0" altitude_bonus_seconds="0" altitude_bonus_time="00:00:00" altitude_at_ess="353" scored_ss_time="01:58:50" landed_before_stop="False" />

    ## add overall task result ordering
    # <FsTaskResult id="overall" title="Overall" ts="2020-05-06T21:57:00+02:00" result_pattern="#0">
    #   FsTaskScoreParams (as above) + [ FsTaskDistToTp ] 
    #   FsTaskResultParticipants = emarr(); # [ FsTaskParticipant ]  (in ranked order)
    #   <FsTaskParticipantResult id="422" rank="1" distance_points="389.1" departure_points="0" arrival_points="0" leading_points="95.8" time_points="504" points="989" /> *
    # Mindless duplication of data
    $tpresult = empty();
    $tpresult->{'id'} = $taskr->{'id'};
    $tpresult->{'rank'} = $place;
    $tpresult->{'distance_points'} = $taskr->{'FsResult'}->{'distance_points'};
    $tpresult->{'departure_points'} =$taskr->{'FsResult'}->{'departure_points'};
    $tpresult->{'arrival_points'} = $taskr->{'FsResult'}->{'arrival_points'};
    $tpresult->{'leading_points'} = $taskr->{'FsResult'}->{'leading_points'};
    $tpresult->{'time_points'} = $taskr->{'FsResult'}->{'time_points'};
    $tpresult->{'points'} = $taskr->{'FsResult'}->{'points'};
    push @$partresults, $tpresult;

    $place++;
    
    # push on tasks results
    push @$partinfo, $taskr;

    $ref = $sth->fetchrow_hashref();
}
$task = $taskmap{$lastPk};
$task->{'FsParticipants'}->{'FsParticipant'} = $partinfo;
$task->{'FsTaskResults'} = empty();
$task->{'FsTaskResults'}->{'FsTaskResult'} = $taskresults;
$taskoverall->{'FsTaskScoreParams'} = $task->{'FsTaskScoreParams'};
$taskoverall->{'ts'} = $task->{'finish'};

# results
my %overall;
my $ranked = emarr();

$overall{'id'} = 'overall';
$overall{'title'} = 'Overall';
$overall{'top'} = 'all';
$overall{'ts'} = strftime("%Y-%m-%dT%H:%M:%S+00:00", localtime);
$overall{'task_result_pattern'} = '#0';
$overall{'comp_result_pattern'} = '#0';
$overall{'comp_result_pattern'} = '#0';
$overall{'FsParticipant'} = $ranked;
push @$rankings, \%overall;

my $comp_result=`php -q ../get_json_result.php \"comPk=$comPk\"`;
my $overall_result_data = from_json($comp_result, {utf8 => 1});

my $rank_data = $overall_result_data->{'data'};
foreach my $rank_result (@$rank_data)
{
    my $fsp = empty();
    #print Dumper($rank_result);

    $fsp->{'id'} = $pilmap{$rank_result->{'id'}};
    $fsp->{'rank'} = $rank_result->{'rank'};
    $fsp->{'points'} = $rank_result->{'score'};
    my $tasks = $rank_result->{'tasks'};
    my $fstask = emarr();
    $fsp->{'FsTask'} = $fstask;
    foreach my $task_result (@$tasks)
    {
        my $fst = empty();
        $fst->{'id'} = $task_result->{'id'};    
        $fst->{'points'} = $task_result->{'score'};
        $fst->{'counting_points'} = $task_result->{'score'} * $task_result->{'perc'} / 100;
        $fst->{'counts'} = '1';
        push @$fstask, $fst;
    }

    push @$ranked, $fsp;
}

#print Dumper(\%fsx);
#
#header("Content-type: text/fsdb");
#header("Content-Disposition: attachment; filename=\"" . $fsdb->{'FsCompetition'}->{'name'} . ".fsdb\"");
#header("Cache-Control: no-store, no-cache");

my $xml = XMLout(\%fsx,  XMLDecl => "<?xml version='1.0' encoding='utf-8' ?>", KeyAttr => [ 'id' ], RootName => undef);
#$xml->addAttribute('encoding', 'utf-8');
open(FH, '>', $outfile) or die $!;
print FH $xml;

