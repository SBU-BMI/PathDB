#create tmp directory if missing
if [ ! -d /data/tmp ]; then
	mkdir -p /data/tmp
	chmod a=rwx,o+t /data/tmp
fi
# clear any stale httpd.pid files
FILE=/var/run/httpd/httpd.pid
if [ -f "$FILE" ]; then
    echo "$FILE exists"
    rm -f $FILE
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
# make sure permissions of pathdb folder are correct
chown -R apache:apache /quip/web/sites/default
chmod -R 770 /quip/web/sites/default
#create pathdb directory if missing
if [ ! -d /data/pathdb ]; then
        mkdir -p /data/pathdb
fi
# make sure sync folder exists and set permissions
if [ ! -d /data/pathdb/config/sync ]; then
	mkdir -p /data/pathdb/config/sync
	chown -R apache:apache /data/pathdb/config/sync
	chmod -R 770 /data/pathdb/config/sync
fi
#create files directory if missing
if [ ! -d /data/pathdb/files ]; then
        mkdir -p /data/pathdb/files
fi
#create files/wsi directory if missing
if [ ! -d /data/pathdb/files/wsi ]; then
        mkdir -p /data/pathdb/files/wsi
fi
# check security
chown apache:apache /data/pathdb
chown -R apache:apache /data/pathdb/config
chown apache:apache /data/pathdb/files
chown apache:apache /data/pathdb/wsi
#create logs directory if missing
if [ ! -d /data/pathdb/logs ]; then
        mkdir -p /data/pathdb/logs
	chown -R apache:apache /data/pathdb/logs
fi
if [ ! -d /data/pathdb/mysql ]; then
	mysql_install_db --force --defaults-file=/config/pathdbmysql.cnf
	/usr/bin/mysqld_safe --defaults-file=/config/pathdbmysql.cnf &
        until mysqladmin status
        do
                sleep 3
        done
	cd /build
        tar xvfz mysql.tgz
        mysql -u root -e "create database QuIP"
        mysql QuIP < mysql
fi
        /usr/bin/mysqld_safe --defaults-file=/config/pathdbmysql.cnf &
	until mysqladmin status
	do
        	sleep 3
	done
        #if [ ! -d /quip/config-local ]; then
        #  mkdir /quip/config-local
        #  chown -R apache:apache /quip/config-local
        #fi
        #/quip/vendor/bin/drush -y config:export --destination /quip/config-local
        httpd -f /config/httpd.conf
	cd /quip/web
        /quip/vendor/bin/drush -y pm:enable css_editor
        /quip/vendor/bin/drush -y pm:uninstall restrict_by_ip
	/quip/vendor/bin/drush -y config-set system.theme admin bootstrap
	/quip/vendor/bin/drush -y config-set system.theme default bootstrap
	/quip/vendor/bin/drush config-delete block.block.bartik_branding
	/quip/vendor/bin/drush config-delete block.block.bartik_account_menu
	/quip/vendor/bin/drush config-delete block.block.bartik_breadcrumbs
	/quip/vendor/bin/drush config-delete block.block.bartik_content
	/quip/vendor/bin/drush config-delete block.block.bartik_footer
	/quip/vendor/bin/drush config-delete block.block.bartik_help
	/quip/vendor/bin/drush config-delete block.block.bartik_local_actions
	/quip/vendor/bin/drush config-delete block.block.bartik_local_tasks
	/quip/vendor/bin/drush config-delete block.block.bartik_main_menu
	/quip/vendor/bin/drush config-delete block.block.bartik_messages
	/quip/vendor/bin/drush config-delete block.block.bartik_page_title
	/quip/vendor/bin/drush config-delete block.block.bartik_powered
	/quip/vendor/bin/drush config-delete block.block.bartik_tools
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_account_menu
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_branding
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_breadcrumbs
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_content
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_help
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_local_actions
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_local_tasks
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_main_menu
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_messages
	/quip/vendor/bin/drush config-delete block.block.drupal8_w3css_theme_page_title
	/quip/vendor/bin/drush -y theme:uninstall drupal8_w3css_theme
	/quip/vendor/bin/drush -y theme:uninstall bartik
	/quip/vendor/bin/drush -y theme:uninstall seven
	/quip/vendor/bin/drush -y pm:uninstall ds_extras ds_switch_view_mode ds
        /quip/vendor/bin/drush config-delete field.storage.node.field_map_type
        mkdir /data/tmp2
	cp -f /quip/config-update/field.storage.node.field_map_type.yml /data/tmp2
	/quip/vendor/bin/drush -y config:import --partial --source /data/tmp2
	/quip/vendor/bin/drush -y config:import --partial --source /quip/config-update/
	/quip/vendor/bin/drush -y updatedb
	/quip/vendor/bin/drush -y cache-rebuild	
	/quip/vendor/bin/drush -y user:cancel archon

while true; do sleep 1000; done

