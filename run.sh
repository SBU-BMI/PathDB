#!/bin/bash

if [ ! -d /data/mysql ]; then

# PathDB not initialized.  Create default MySQL database and make PathDB changes

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

# create private and public security taxonomy items
	curl --user admin:bluecheese2018 -k -X POST http://localhost/taxonomy/term?_format=json -H "Content-Type: application/json" -d '{"vid": [{"target_id": "collections","target_type": "taxonomy_vocabulary"}],"name": [{"value": "Private"}]}'
	curl --user admin:bluecheese2018 -k -X POST https://localhost/taxonomy/term?_format=json -H "Content-Type: application/json" -d '{"vid": [{"target_id": "collections","target_type": "taxonomy_vocabulary"}],"name": [{"value": "Public"}]}'
# create PathDB menu items
	curl --user admin:bluecheese2018 -k -X POST https://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Get a JWT!"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/jwt/token","title": "","options": []}],"weight": [{"value": 3}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST https://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Images"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/listofimages","title": "","options": []}],"weight": [{"value": 2}],"expanded": [{"value": true}]}'
	curl --user admin:bluecheese2018 -k -X POST https://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Collections"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/collections","title": "","options": []}],"weight": [{"value": 1}],"expanded": [{"value": true}]}'
else
	httpd -f /etc/httpd/conf/httpd.conf
fi

while true; do sleep 1000; done

