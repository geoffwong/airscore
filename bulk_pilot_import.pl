#!/usr/bin/perl

#
# Reads in a CSV membership list
#
#

require DBD::mysql;
use Data::Dumper;
use Text::CSV qw( csv );
use Scalar::Util qw(looks_like_number);
use TrackLib qw(:all);

#
# Database handles
#

# Extract 'fix' data into a nice record
#
sub read_membership
{
    my ($dbh,$f) = @_;

    my @field;
    my $row;

    # Read whole file in memory
    print "reading: $f\n";
    my $aoa = csv (in => $f);    # as array of array

    # for my $row ( @$aoa )
    open(FD, "$f") or die "can't open $f: $!";
    while (<FD>)
    {
        $row = $_;

        @field = split /,/, $row;
        if ($field[1] eq '')
        {
            next;
        }

        if(!looks_like_number($field[2]) || !looks_like_number($field[4]))
        {
            next;
        }

        print "row=$row";
        print 'add name: ', $field[1], ' ', $field[0], "\n\n";

        # should be an insert/update ..
        insertup('tblPilot', 'pilPk', "pilLastName='" . $field[0] . 
                "' and pilHGFA='" . $field[2] . "'",
            {
                'pilFirstName' => $field[1],
                'pilLastName' => $field[0],
                'pilHGFA' => $field[2],
                'pilSex' => $field[3],
                'pilCIVL' => $field[4]
            });
        #$dbh->do("INSERT INTO tblPilot (pilFirstName,pilLastName,pilHGFA,pilSex) VALUES (?,?,?,?)", undef, $field[1],$field[0],$field[2],'M');
    }

}

#
# Main program here ..
#

my $flight;
my $allflights;
my $traPk;
my $totlen = 0;
my $coords;
my $numc;
my $numb;
my $score;
my $dbh;

$dbh = db_connect();

if (scalar @ARGV < 1)
{
    print "bulk_pilot_import.pl <membershiplist>\n";
    exit(1);
}

# Read the csv members
$members = read_membership($dbh, $ARGV[0]);


