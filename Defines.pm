#!/usr/bin/perl
#
# Some defines
#

package Defines;

require Exporter;
our @ISA       = qw(Exporter);
our @EXPORT = qw{:ALL};

*BINDIR = \'%CGIBIN%';
*FILEDIR = \'%TRACKDIR%';
*DATABASE = \'%DATABASE%';
*MYSQLHOST = \'%MYSQLHOST%';
*MYSQLUSER = \'%MYSQLUSER%';
*MYSQLPASSWORD = \'%MYSQLPASSWORD%';

1;

