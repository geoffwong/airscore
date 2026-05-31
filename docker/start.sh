#!/bin/sh


# git clone airscore
/usr/bin/mysqld_safe --user=mysql &

if [ ! -f INITIALISED ]; then
    touch INITIALISED
    cd /var/www
    mkdir tracks
    mkdir tracks/2024 tracks/2025 tracks/2026 tracks/2027 tracks/2028
    rm -rf html
    git clone https://github.com/geoffwong/airscore.git html
    cd html
    chmod a+x submacro.sh
    chmod a+x *pl *pm
    ./submacro.sh DATABASE xcdb
    ./submacro.sh MYSQLUSER xc
    ./submacro.sh MYSQLPASSWORD dockerpwd
    ./submacro.sh MYSQLHOST localhost
    ./submacro.sh ADMINPASSWORD admin
    ./submacro.sh CGIBIN /var/www/html/
    ./submacro.sh TRACKDIR /var/www/tracks
    ./submacro.sh PERLBIN ,
    export MYSQL_PWD=
    mysql -u root < xcdb.sql
fi

apache2ctl -D FOREGROUND

