#!/usr/bin/perl -I/home/geoff/bin
#
# pilot# igc task
#
require DBD::mysql;

use Time::Local;
use Data::Dumper;
use Airspace qw(:all);

use TrackLib qw(:all);
#use strict;

my $traPk;
my $traStart;
my $tasType;
my $comType;
my $pilPk;
my $dbh;
my $sql;
my $sth;
my $ref;
my $res;
my $ex;
my ($glider,$dhv);

my $pil = $ARGV[0];
my $igc = $ARGV[1];
my $comPk = 0 + $ARGV[2];
my $tasPk = 0 + $ARGV[3];

my $forClass = '';
my $forVersion = '';


sub get_pilot_key
{
    my ($dbh, $comPk, $pil) = @_;
    my $sql;
    my $sth;
    my $ref;
    my $pilPk;
    my $glider = 'unknown';
    my $hdglider;
    my $dhv = 'competition';

    # Find the pilPk
    if ((0 + $pil) > 0)
    {
        $sql = "select * from tblPilot where pilHGFA='$pil' or pilCIVL='$pil' order by pilHGFA desc";
    }
    else
    {
        # Guess on last name ...
        $sql = "select * from tblPilot where pilLastName='$pil' order by pilPk desc";
    }

    print($sql, "\n");
    $sth = $dbh->prepare($sql);
    $sth->execute();
    if ($sth->rows() > 1)
    {
        print "Pilot ambiguity for $pil, use pilot HGFA/FAI#\n";
        while  ($ref = $sth->fetchrow_hashref())
        {
            print $ref->{'pilHGFA'}, " ", $ref->{'pilFirstName'}, " ", $ref->{'pilLastName'}, " ", $ref->{'pilBirthdate'}, "\n";
        }
    }

    if ($ref = $sth->fetchrow_hashref())
    {
        $pilPk = $ref->{'pilPk'};
    }
    else
    {
        my $header = read_header($igc);

        my $pilot = $header->{'pilot'};
        $hdglider = $header->{'glider'};

        if (defined($pilot))
        {
            # split lastname(s) and select from database .. pick one?
            my @arr = split(/ /, $pilot, 2);
            my $lastname = $arr[1];
            my $sth = $TrackLib::dbh->prepare("select pilPk from tblPilot where pilLastName='$lastname'");
            $sth->execute();
            my $res = $sth->fetchrow_array();
            if ($sth->rows() == 1)
            {
                # @todo: improve to use firstname if multiple results returned
                $pilPk = $res;
            }
        }

        if ($pilPk == 0)
        {
            print "Unable to identify pilot: $pil\n";
            return 0;
        }
    }


    # get previous track info for pilot
    $sql = "select traGlider, traDHV from tblTrack where pilPk=$pilPk order by traPk desc";
    $sth = $dbh->prepare($sql);
    $sth->execute();
    if  ($ref = $sth->fetchrow_hashref())
    {
        print Dumper($ref);
        $glider = $ref->{'traGlider'};
        $dhv = $ref->{'traDHV'};
    }
    else
    {
        if (defined($hdglider))
        {
            $glider = $hdglider;
        }
        else
        {
            $glider = 'Unknown';
        }
    }

    return $pilPk, $glider, $dhv;
}

sub check_track_time
{
    my ($dbh, $comPk, $traPk) = @_;
    my ($comFrom, $comTo, $comTimeOffset);
    my $traStart = 0;
    my $version;
    my $ref;

    my $sql = "select unix_timestamp(T.traStart) as TStart, unix_timestamp(C.comDateFrom) as CFrom, unix_timestamp(C.comDateTo) as CTo, C.comTimeOffset, F.forClass, F.forVersion from tblTrack T, tblCompetition C, tblFormula F where F.comPk=C.comPk and T.traPk=$traPk and C.comPk=$comPk";
    my $sth = $dbh->prepare($sql);

    $sth->execute();
    if  ($ref = $sth->fetchrow_hashref())
    {
        $traStart = $ref->{'TStart'};
        $comFrom = $ref->{'CFrom'};
        $comTo = $ref->{'CTo'};
        $comTimeOffset = $ref->{'comTimeOffset'};

        $version = $ref->{'forVersion'};

        #$class = $ref->{'forClass'};
        #print "comType=$comType forClass=$forClass\n";
    }

    if ($traStart < ($comFrom-$comTimeOffset*3600))
    {
        print "Track ($traPk) from before the competition opened ($traStart:$comFrom)\n";
        return undef;
    }

    if ($traStart > ($comTo+86400))
    {
        print "Track ($traPk) from after the competition ended ($traStart:$comTo)\n";
        return undef;
    }

    return $version;
}

sub handle_task_track
{
    my ($dbh, $comPk, $tasPk, $tasType, $traPk) = @_;

    print "Task type: $tasType\n";

    # insert into tblComTaskTrack
    my $sql = "insert into tblComTaskTrack (comPk,tasPk,traPk) values ($comPk,$tasPk,$traPk)";
    $dbh->do($sql);

    if (($tasType eq 'free') or ($tasType eq 'free-pin'))
    {
        `${BINDIR}optimise_flight.pl $traPk $comPk $tasPk 0`;
        # also verify for optional points in 'free' task?
    }
    elsif ($tasType eq 'olc')
    {
        `${BINDIR}optimise_flight.pl $traPk $comPk $tasPk 3`;
    }
    elsif ($tasType eq 'airgain')
    {
        `${BINDIR}optimise_flight.pl $traPk $comPk $tasPk 3`;
        `${BINDIR}airgain_verify.pl $traPk $comPk $tasPk`;
    }
    elsif ($tasType eq 'speedrun' or $tasType eq 'race' or $tasType eq 'speedrun-interval')
    {
        # Optional really ...
        `${BINDIR}optimise_flight.pl $traPk $comPk $tasPk 3`;
        `${BINDIR}track_verify_sr.pl $traPk $tasPk`;

        # Airspace check - do something with a violation (add 1000pt penalty)
        my $res = airspace_check_task_track($dbh, $tasPk, $traPk);
        if ($res->{'result'} eq 'violation')
        {
            my $excess = $res->{'excess'};
            my $sth = $dbh->prepare("update tblTaskResult set tarPenalty=1000, tarComment='Airspace violation=$excess' where tasPk=$tasPk and traPk=$traPk");
            $sth->execute();
        }

        # Delay score - fork and background
        my $pid = fork();
        die "Fork failed: $!" if !defined $pid;
        if ($pid == 0)
        {
             # do this in the child
             open STDIN, "</dev/null";
             open STDOUT, ">/dev/null";
             open STDERR, ">/dev/null";
            `${BINDIR}task_score.pl $tasPk 300`;
             exit;
        }
    }
    else
    {
        print "Unknown task: $tasType\n";
    }
    if ($? > 0)
    {
        print("Flight/task optimisation failed\n");
        exit(1);
    }

}


#
#
#

if (scalar(@ARGV) < 2)
{
    print "add_track.pl <hgfa#> <igcfile> <comPk> [tasPk]\n";
    exit(1);
}

$dbh = db_connect();

($pilPk, $glider, $dhv) = get_pilot_key($dbh, $comPk, $pil);
if ($pilPk == 0)
{
    print "Unable to identify pilot (2): $pil\n";
    exit(1);
}

# Read the track
$res = `${BINDIR}igcreader.pl $igc $pilPk`;
$ex = $?;
print $res;
if ($ex > 0)
{
    print $res;
    exit(1);
}

# Parse for traPk ..
if ($res =~ m/traPk=(.*)/)
{
    $traPk = $1;
}

if (0+$traPk < 1)
{
    print "Unable to determine new track key: $res<br>\n";
    exit(1);
}

# FIX: Copy the track somewhere permanent?
# FIX: Update tblTrack to point to that

$dbh->do("update tblTrack set traGlider=?, traDHV=? where traPk=?", undef, $glider, $dhv, $traPk);

# Try to find an associated task if not specified
if ($tasPk == 0)
{
    #$sql = "select T.tasPk, T.tasTaskType, C.comType, unix_timestamp(C.comDateFrom) as CFrom, unix_timestamp(C.comDateTo) as CTo from tblTask T, tblTrack TL, tblCompetition C where C.comPk=T.comPk and T.comPk=$comPk and TL.traPk=$traPk and TL.traStart > date_sub(T.tasStartTime, interval C.comTimeOffset hour) and TL.traStart < date_sub(T.tasFinishTime, interval C.comTimeOffset hour)";
    $sql = "select T.tasPk, T.tasTaskType, C.comType from tblTask T, tblTrack TL, tblCompetition C where C.comPk=T.comPk and T.comPk=$comPk and TL.traPk=$traPk and TL.traStart > date_sub(T.tasDate, interval C.comTimeOffset hour) and TL.traStart < date_add(T.tasDate, interval (24-C.comTimeOffset) hour)";
    $sth = $dbh->prepare($sql);
    $sth->execute();
    if  ($ref = $sth->fetchrow_hashref())
    {
        print Dumper($ref);
        $tasPk = $ref->{'tasPk'};
        $tasType = $ref->{'tasTaskType'};
        $comType = $ref->{'comType'};
    }
}
else
{
    # For routes
    #print "Task pk: $tasPk\n";
    $sql = "select T.tasTaskType, C.comType, unix_timestamp(C.comDateFrom) as CFrom, unix_timestamp(C.comDateTo) as CTo from tblTask T, tblCompetition C where C.comPk=T.comPk and T.tasPk=$tasPk";
    $sth = $dbh->prepare($sql);
    $sth->execute();
    if  ($ref = $sth->fetchrow_hashref())
    {
        #print Dumper($ref);
        $tasType = $ref->{'tasTaskType'};
        $comType = $ref->{'comType'};
    }
    else
    {
        print "Unable to get task type\n";
    }
}


# Check track time makes sense
$forVersion = check_track_time($dbh, $comPk, $traPk);
if (!defined($forVersion))
{
    exit(1);
}

if ($tasPk > 0)
{
    handle_task_track($dbh, $comPk, $tasPk, $tasType, $traPk);
}
else
{
    $sql = "insert into tblComTaskTrack (comPk,traPk) values ($comPk,$traPk)";
    $dbh->do($sql);

    print "forVersion=$forVersion\n";
    # Nothing else to do but verify ...
    if ($comType eq 'Free' or $forVersion eq 'free')
    {
        `${BINDIR}optimise_flight.pl $traPk $comPk 0 0`;
    }
    elsif ($forVersion eq 'airgain-count')
    {
        `${BINDIR}optimise_flight.pl $traPk $comPk`;
        `${BINDIR}airgain_verify.pl $traPk $comPk`;
    }
    else
    {
        `${BINDIR}optimise_flight.pl $traPk $comPk`;
    }
    if ($? > 0)
    {
        print("Flight (free) optimisation failed\n");
        exit(1);
    }
}

# G-record check
#select correct vali for IGC file.
#$res = `wine $vali $igc`
#if ($res ne 'PASSED')
#{
#}
# From: http://vali.fai-civl.org/webservice.html
# $ /usr/bin/curl -F igcfile=@YourSample.igc vali.fai-civl.org/api/vali/json
# Returns json
#


# stored track pk
print "traPk=$traPk\n";

