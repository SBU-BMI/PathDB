if [ ! -d /data/tmp ]; then
	mkdir -p /data/tmp
	chmod a=rwx,o+t /data/tmp
fi
if [ ! -d /data/pathdb/mysql ]; then
	rm -rf /data/pathdb/quip
	cp -rp /quip /data/pathdb
	chown -R apache /data/pathdb/quip
# PathDB not initialized.  Create default MySQL database and make PathDB changes
        mysql_install_db --user=mysql --ldata=/data/pathdb/mysql
        /usr/bin/mysqld_safe --datadir='/data/pathdb/mysql' &
        sleep 10
        mysql -u root -e "create database QuIP"
        cd /data/pathdb/quip
        /data/pathdb/quip/vendor/bin/drush -y si standard --db-url=mysql://root:@localhost/QuIP
        /data/pathdb/quip/vendor/bin/drush -y upwd admin bluecheese2018
        /data/pathdb/quip/vendor/bin/drush -y pm:enable rest serialization
        /data/pathdb/quip/vendor/bin/drush -y cset system.site uuid 533fc7cc-82dd-46b2-8d63-160785138977
        /data/pathdb/quip/vendor/bin/drush -y ev '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'
        /data/pathdb/quip/vendor/bin/drush -y config:import --source /quip/pathdbconfig/
        /data/pathdb/quip/vendor/bin/drush -y php-eval 'node_access_rebuild();'
	/data/pathdb/quip/vendor/bin/drush -y pm:uninstall toolbar
        /data/pathdb/quip/vendor/bin/drush -y pm:uninstall hide_revision_field
        /data/pathdb/quip/vendor/bin/drush -y cache-rebuild
	chown -R apache /data/pathdb/files
	rm -f /var/run/httpd/httpd.pid
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

	# create REST API System User
	/data/pathdb/quip/vendor/bin/drush user:create --password bluecheese2018 archon
	/data/pathdb/quip/vendor/bin/drush user:role:add administrator archon
	/data/pathdb/quip/vendor/bin/drush user:role:add administrator admin

	# create private and public security taxonomy items
        curl --user admin:bluecheese2018 -k -X POST http://localhost/taxonomy/term?_format=json -H "Content-Type: application/json" -d '{"vid": [{"target_id": "collections","target_type": "taxonomy_vocabulary"}],"name": [{"value": "Public"}]}'

	# create PathDB menu items
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Images"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/listofimages","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Search"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/imagesearch","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Maps"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/maps","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Collections"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/collections","title": "","options": []}],"weight": [{"value": 3}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Upload an Image"}],"description": [],"menu_name": [{"value": "main"}],"link": [{"uri": "internal:/node/add/wsi","title": "","options": []}],"weight": [{"value": 1}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "About"}],"description": [],"menu_name": [{"value": "account"}],"link": [{"uri": "https://sbu-bmi.github.io/quip_distro/","title": "","options": []}],"weight": [{"value": 0}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Feedback"}],"description": [],"menu_name": [{"value": "account"}],"link": [{"uri": "https://docs.google.com/forms/d/e/1FAIpQLSdSt7xU4-j2m-JpFRb5nNkOEuBz212i1--svMHyuHKErNkFvA/viewform","title": "","options": []}],"weight": [{"value": 1}],"expanded": [{"value": true}]}'

	# create PathDB admin menu
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Access Control"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/admin/config/people/tac_lite","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Add Collection"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/admin/structure/taxonomy/manage/collections/add","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "List Collections"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/admin/structure/taxonomy/manage/collections/overview","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "User Administration"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/admin/people","title": "","options": []}],"weight": [{"value": 4}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Bulk Upload Images"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/node/add/bulk_csv_upload","title": "","options": []}],"weight": [{"value": 5}],"expanded": [{"value": true}]}'
        curl --user admin:bluecheese2018 -k -X POST http://localhost/entity/menu_link_content?_format=json -H "Content-Type: application/json" -d '{"bundle": [{"value": "menu_link_content"}],"enabled": [{"value": true}],"title": [{"value": "Bulk Upload Template"}],"description": [],"menu_name": [{"value": "quip-admin"}],"link": [{"uri": "internal:/sup/TemplateBulkUpload.csv","title": "","options": []}],"weight": [{"value": 6}],"expanded": [{"value": true}]}'

	# create core content
	curl --user admin:bluecheese2018 -k -X POST http://localhost/node?_format=json -H "Content-Type: application/json" --data-binary "@/quip/content/node1"
else
        /usr/bin/mysqld_safe --datadir='/data/pathdb/mysql' &
        httpd -f /config/httpd.conf
fi

while true; do sleep 1000; done

