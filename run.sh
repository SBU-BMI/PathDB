echo "CREATING FOLDERS"

mkdir -p /data/tmp && chmod a=rwx,o+t /data/tmp

mkdir -p /data/pathdb && \
mkdir -p /data/pathdb/config/sync && \
mkdir -p /data/pathdb/files && \
mkdir -p /data/pathdb/files/wsi && \
mkdir -p /data/pathdb/logs && \
touch /data/pathdb/logs/error_log && \
touch /data/pathdb/logs/access_log 

#create tmp directory if missing
if [ ! -d /data/tmp ]; then
	echo "/data/tmp DOES NOT EXIST"
	mkdir -p /data/tmp
	chmod a=rwx,o+t /data/tmp
fi
# clear any stale httpd.pid files
FILE=/var/run/httpd/httpd.pid
if [ -f "$FILE" ]; then
    echo "$FILE exists"
    rm -f $FILE
fi
# check to see if custom theme file exists
if [ ! -f "/config/pathdb/w3-theme-custom.css" ]; then
        cp /build/w3-theme-custom.css /config/pathdb/w3-theme-custom.css
fi
# check to see of PathDB MySQL defaults file exists
if [ ! -f "/config/pathdbmysql.cnf" ]; then
	cp /build/pathdbmysql.cnf /config/pathdbmysql.cnf
fi
# clear out other stale processes
rm -rf /run/httpd/* 
# make sure default drupal settings.php file is there
if [ ! -f /quip/web/sites/default/settings.php ]; then
	cp /build/settings.php /quip/web/sites/default
	echo "\$settings['hash_salt'] = '`uuidgen`';" >> /quip/web/sites/default/settings.php
fi
cp /config/pathdb/w3-theme-custom.css /quip/web/themes/contrib/d8w3css/css/w3-css-theme-custom

# NOTE: chown and chmod are commented out to run container 
#		as non-root user. group and mod are set in Dockerfile 
# 		during build. When container is run as non-root user
#		chown and chmod operations fail.

# make sure permissions of pathdb folder are correct
# chown -R apache:apache /quip/web/sites/default
# chmod -R 770 /quip/web/sites/default
# create pathdb directory if missing
if [ ! -d /data/pathdb ]; then
	echo "/data/pathdb DOES NOT EXIST"
    mkdir -p /data/pathdb
fi
# make sure sync folder exists and set permissions
if [ ! -d /data/pathdb/config/sync ]; then
	mkdir -p /data/pathdb/config/sync
	chgrp -R 0 /data/pathdb/config
	chmod -R g=u /data/pathdb/config
fi
#create files directory if missing
if [ ! -d /data/pathdb/files ]; then
        mkdir -p /data/pathdb/files
		chgrp -R 0 /data/pathdb/files
		chmod -R g=u /data/pathdb/files
fi
#create files/wsi directory if missing
if [ ! -d /data/pathdb/files/wsi ]; then
        mkdir -p /data/pathdb/files/wsi
		chgrp -R 0 /data/pathdb/files/wsi
		chmod -R g=u /data/pathdb/files/wsi
fi
# check security
# chown apache:apache /data/pathdb
# chown -R apache:apache /data/pathdb/config
# chown apache:apache /data/pathdb/files
# chown apache:apache /data/pathdb/wsi
# create logs directory if missing
if [ ! -d /data/pathdb/logs ]; then
        mkdir -p /data/pathdb/logs
		chgrp -R 0 /data/pathdb/logs
		chmod -R g=u /data/pathdb/logs
fi

if [ ! -d /data/pathdb/mysql ]; then
	# replace user=mysql with UID of non-root user from container
	sed -i 's/user=mysql/user='"$UID"'/g' /config/pathdbmysql.cnf

	mysql_install_db --force --defaults-file=/config/pathdbmysql.cnf
	/usr/bin/mysqld_safe --defaults-file=/config/pathdbmysql.cnf &
    until mysqladmin status
        do
                sleep 3
        done
	cd /build
    tar xvfz mysql.tgz
    mysql -u root -e "create database QuIP"
    mysql -u root QuIP < mysql

	chgrp -R 0 /data/pathdb/mysql
	chmod -R g=u /data/pathdb/mysql
fi

if [ ! -d /data/pathdb/mysql ]; then
# PathDB not initialized.  Create default MySQL database and make PathDB changes

	# Use $UID instead of mysql (--user=$UID instead of --user=mysql 
	# to run container as non-root user
	# replace user=mysql with UID of non-root user from container
	sed -i 's/user=mysql/user='"$UID"'/g' /config/pathdbmysql.cnf

	mysql_install_db --user=$UID --ldata=/data/pathdb/mysql
    /usr/bin/mysqld_safe --defaults-file=/config/pathdbmysql.cnf &
    sleep 10
    mysql -u root -e "create database QuIP"

	chgrp -R 0 /data/pathdb/mysql
	chmod -R g=u /data/pathdb/mysql

    cd /quip/web
    /quip/vendor/bin/drush -y si standard --db-url=mysql://root:@localhost/QuIP
    /quip/vendor/bin/drush -y upwd admin bluecheese2018
    /quip/vendor/bin/drush -y pm:enable rest serialization
    /quip/vendor/bin/drush -y cset system.site uuid 533fc7cc-82dd-46b2-8d63-160785138977
    /quip/vendor/bin/drush -y ev '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'
    /quip/vendor/bin/drush -y config:import --source /quip/pathdbconfig/
    /quip/vendor/bin/drush -y php-eval 'node_access_rebuild();'
	/quip/vendor/bin/drush -y pm:uninstall toolbar
    /quip/vendor/bin/drush -y pm:uninstall hide_revision_field
    /quip/vendor/bin/drush -y cache-rebuild
    httpd -f /config/httpd.conf
	counter=0;
    wget --spider --quiet http://localhost
    while [ "$?" != 0 ]
    do
		counter=$((counter+1))
		echo "Checked $counter time(s)"
		sleep 1
		wget --spider --quiet http://localhost
	done
	/quip/vendor/bin/drush -y cache-rebuild

	# create REST API System User
	/quip/vendor/bin/drush user:create --password bluecheese2018 archon
	/quip/vendor/bin/drush user:role:add administrator archon
	/quip/vendor/bin/drush user:role:add administrator admin

	# create private and public security taxonomy items
    curl --user admin:bluecheese2018 -k -X POST http://localhost/taxonomy/term?_format=json -H "Content-Type: application/json" -d '{"vid": [{"target_id": "collections","target_type": "taxonomy_vocabulary"}],"name": [{"value": "Public"}]}'

	# create PathDB menu items
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Images"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/listofimages","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Search"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/imagesearch","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Maps"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/maps","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Collections"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/collections","title": "","options": []}],"weight": [{"value": 3}],"expanded": [{"value": true}]}'
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "About"}],"description": [],"menu_name": [{"value": "account"}],"link": [{"uri": "https://sbu-bmi.github.io/quip_distro/","title": "","options": []}],"weight": [{"value": 0}],"expanded": [{"value": true}]}'
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Feedback"}],"description": [],"menu_name": [{"value": "account"}],"link": [{"uri": "https://docs.google.com/forms/d/e/1FAIpQLSdSt7xU4-j2m-JpFRb5nNkOEuBz212i1--svMHyuHKErNkFvA/viewform","title": "","options": []}],"weight": [{"value": 1}],"expanded": [{"value": true}]}'

	# create PathDB admin menu
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Access Control"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/admin/config/people/tac_lite","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Add Collection"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/admin/structure/taxonomy/manage/collections/add","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "List Collections"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/admin/structure/taxonomy/manage/collections/overview","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
    curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "User Administration"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/admin/people","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'

	# create core content
	curl --user admin:bluecheese2018 -k -X POST http://localhost/node?_format=json -H "Content-Type: application/json" --data-binary "@/quip/content/node1"

	chgrp -R 0 /data/pathdb/logs
	chmod -R g=u /data/pathdb/logs
else
	# replace user=mysql with UID of non-root user from container
	sed -i 's/user=mysql/user='"$UID"'/g' /config/pathdbmysql.cnf
    /usr/bin/mysqld_safe --defaults-file=/config/pathdbmysql.cnf &
	until mysqladmin status
	do
    	sleep 3
	done
    httpd -f /config/httpd.conf
	cd /quip/web
	/quip/vendor/bin/drush -y config:import --partial --source /quip/config-update/
	/quip/vendor/bin/drush -y updatedb
	/quip/vendor/bin/drush -y cache-rebuild	
fi
while true; do sleep 1000; done

