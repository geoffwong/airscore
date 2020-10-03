#!/usr/bin/perl

require DBD::mysql;

use Airspace qw(:all);
use Math::Trig;
use Data::Dumper;
use POSIX qw(ceil floor);

use strict;

#
# Delete some airspace ..
#

my @airs;
my $sth;
my $dbh;
my $class;
my $listonly = 0;

if ($#ARGV < 1)
{
    print "airspace_check [-c airspace class] [-n airspace name]\n";
    exit 1;
}

$dbh = db_connect();

while ($#ARGV > 0 and substr($ARGV[0], 0, 1) eq '-')
{
    my $ref;
    $class = $ARGV[1];
    if (substr($ARGV[0], 0, 2) eq '-c')
    {
        $sth = $dbh->prepare("select * from tblAirspace where airClass like ?");
        $sth->execute($class);
        while ($ref = $sth->fetchrow_hashref())
        {
            printf("Delete: %s\n", $ref->{'airName'});
            push @airs, $ref->{'airPk'};
        }

    }
    elsif (substr($ARGV[0], 0, 2) eq '-n')
    {
        $sth = $dbh->prepare("select * from tblAirspace where airName like ?");
        $sth->execute($class);
        while ($ref = $sth->fetchrow_hashref())
        {
            printf("Delete: %s\n", $ref->{'airName'});
            push @airs, $ref->{'airPk'};
        }

    }
    elsif (substr($ARGV[0], 0, 2) eq '-l')
    {
        $listonly = 0 + $ARGV[1];
    }
    else
    {
        print "airspace_check [-c airspace class] [-n airspace name]\n";
        exit 1;
    }

    shift @ARGV;
    shift @ARGV;
}

#$id = $ARGV[0];
#print Dumper(\@airs);

if (scalar @airs == 0)
{
    print "Nothing to delete\n";
    exit 0;
}

if ($listonly > 0)
{
    print "List only\n";
    exit 0;
}

delete_airspace($dbh, \@airs);

