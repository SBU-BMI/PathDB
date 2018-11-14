#!/bin/bash

if [ ! -d /data/mysql ]; then
        echo "PathDB not initialized!  Initializing..."
        echo "PathDB not initialized!  Initializing..." > elog
        #mkdir /data/mysql
        #chown -R mysql /data/mysql
        #service mariadb start
        mysql_install_db --user=mysql --ldata=/data/mysql
        /usr/bin/mysqld_safe --datadir='/data/mysql' &
        sleep 10
        mysql -u root -e "create database QuIP"
        cd /data/quip
        /data/quip/vendor/bin/drush -y si standard --db-url=mysql://root:@localhost/QuIP
        /data/quip/vendor/bin/drush -y upwd admin bluecheese2018
        /data/quip/vendor/bin/drush -y pm-enable rest serialization
	/data/quip/vendor/bin/drush -y cset system.site uuid 533fc7cc-82dd-46b2-8d63-160785138977
	/data/quip/vendor/bin/drush -y pmu shortcut
	/data/quip/vendor/bin/drush -y pmu help
	/data/quip/vendor/bin/drush -y pmu contact
	/data/quip/vendor/bin/drush -y pmu search
        /data/quip/vendor/bin/drush -y ev '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'
	/data/quip/vendor/bin/drush -y config:import --source /data/quip/pathdbconfig/
	/data/quip/vendor/bin/drush -y php-eval 'node_access_rebuild();'
	/data/quip/vendor/bin/drush -y cache-rebuild
	httpd -f /etc/httpd/conf/httpd.conf
	sleep 2
	curl --user admin:bluecheese2018 -k -X POST http://localhost/taxonomy/term?_format=json -H "Content-Type: application/json" -d '{"vid": [{"target_id": "collections","target_type": "taxonomy_vocabulary"}],"name": [{"value": "Private"}]}'
	curl --user admin:bluecheese2018 -k -X POST https://localhost/taxonomy/term?_format=json -H "Content-Type: application/json" -d '{"vid": [{"target_id": "collections","target_type": "taxonomy_vocabulary"}],"name": [{"value": "Public"}]}'
else
	httpd -f /etc/httpd/conf/httpd.conf
fi

while true; do sleep 1000; done

