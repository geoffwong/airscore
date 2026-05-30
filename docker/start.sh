#!/bin/sh


# git clone airscore
/usr/bin/mysqld_safe --user=mysql &

if [ ! -f INITIALISED ]; then
    touch INITIALISED
    cd /var/www
    rm -rf html
    git clone https://github.com/geoffwong/airscore.git html
    cd html
    chmod a+x submacro.sh
    ./submacro.sh MYSQLUSER xc
    ./submacro.sh MYSQLPASSWORD dockerpwd
    ./submacro.sh MYSQLHOST localhost
    ./submacro.sh ADMINPASSWORD admin
    export MYSQL_PWD=
    mysql -u root < xcdb.sql
fi

apache2ctl -D FOREGROUND

