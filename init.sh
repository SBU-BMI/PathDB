#!/bin/bash
if [ ! -d /data/mysql ]; then
        echo "PathDB not initialized!  Initializing..."
	echo "PathDB not initialized!  Initializing..." > elog
        mkdir /data/mysql
        chown -R mysql /data/mysql
        service mariadb start
        mysql -u root -e "create database QuIP"
        cd /data/quip
        /data/quip/vendor/bin/drush -y si standard --db-url=mysql://root:@localhost/QuIP
        /data/quip/vendor/bin/drush upwd admin bluecheese2018
        /data/quip/vendor/bin/drush -y pm-enable rest serialization
fi

