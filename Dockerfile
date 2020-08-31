FROM centos:7
MAINTAINER Erich Bremer "erich.bremer@stonybrook.edu"
#
# QuIP - PathDB Docker Container
#
### update OS
RUN yum update -y && yum clean all
RUN yum -y install wget which zip unzip java-1.8.0-openjdk bind-utils epel-release
RUN rpm -Uvh http://mirror.bebout.net/remi/enterprise/remi-release-7.rpm
RUN yum-config-manager --enable remi-php73
RUN yum -y install httpd openssl mod_ssl mod_php php-opcache php-xml php-mcrypt \
					php-gd php-devel php-mysql php-intl php-mbstring \
					php-uploadprogress php-pecl-zip php-ldap
RUN yum -y install mariadb-server mariadb-client git
RUN sed -i 's/;date.timezone =/date.timezone = America\/New_York/g' /etc/php.ini
RUN sed -i 's/;always_populate_raw_post_data = -1/always_populate_raw_post_data = -1/g' /etc/php.ini

# download Drupal management tools
WORKDIR /build
RUN wget https://getcomposer.org/installer
RUN php installer
RUN rm -f installer
RUN mv composer.phar /usr/local/bin/composer
COPY pathdbmysql.cnf pathdbmysql.cnf
COPY w3-theme-custom.css w3-theme-custom.css

# create initial Drupal environment
WORKDIR /
COPY quip/ quip/
COPY modules/quip/ /quip/web/modules/quip/
COPY images/ /quip/web/images/
COPY settings.php /build
COPY mysql.tgz /build

# adjust location of Drupal-supporting MySQL database files
RUN sed -i 's/datadir=\/var\/lib\/mysql/datadir=\/data\/pathdb\/mysql/g' /etc/my.cnf
# increase php file upload sizes and posts
RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 30G/g' /etc/php.ini
RUN sed -i 's/post_max_size = 8M/post_max_size = 30G/g' /etc/php.ini
RUN sed -i 's/;upload_tmp_dir =/upload_tmp_dir = "\/data\/tmp"/g' /etc/php.ini
RUN sed -i 's/sys_temp_dir =/sys_temp_dir = "\/data\/tmp"/g' /etc/php.ini

# set up Drupal private file area
RUN mkdir -p /data/pathdb/files
RUN chgrp -R 0 /data/pathdb/files
RUN chmod -R 775 /data/pathdb/files

# create self-signed digital keys for JWT
WORKDIR /etc/httpd/conf
RUN openssl req -subj '/CN=www.mydom.com/O=My Company Name LTD./C=US' -x509 -nodes -newkey rsa:2048 -keyout quip.key -out quip.crt

# copy over Docker initialization scripts
EXPOSE 80 8080
COPY run.sh /root/run.sh
COPY mysql.tgz /build
RUN mkdir /quip/config
RUN mkdir /quip/config-update
COPY config/* /quip/config/
COPY config/* /quip/config-update/
# remove local exceptions to updates
RUN rm /quip/config-update/tac_lite.settings.yml
COPY content/* /quip/content/

# download caMicroscope
WORKDIR /quip/web
ARG viewer="v3.7.7"
RUN if [ -z ${viewer} ]; then git clone https://github.com/camicroscope/caMicroscope.git --branch=v3.5.10; else git clone https://github.com/camicroscope/caMicroscope.git --branch=$viewer; fi
ARG featureMap
RUN if [ -z ${featureMap} ]; then git clone https://github.com/SBU-BMI/FeatureMap --branch=2.0.3; else git clone https://github.com/SBU-BMI/FeatureMap --branch=$featureMap; fi
RUN rm /etc/httpd/conf.d/ssl.conf
RUN chmod 755 /root/run.sh
RUN yum update -y && yum clean all

# To run container as non-root user
# 	Copy some of the config files during build
#   Change group and mod of folders
RUN 	mkdir /config
COPY 	config_quip/ /config/
RUN 	cp /config/httpd.conf.template /config/httpd.conf
RUN 	cp /config/pathdb_routes.json /config/routes.json

RUN 	mkdir /keys
COPY 	jwt_keys_quip/ /keys/

RUN 	mkdir -p /quip/web/sites/default
COPY 	config_quip/pathdb/ /quip/web/sites/default/

RUN		mkdir -p /data/tmp && \
    	chmod a=rwx,o+t /data/tmp

RUN		mkdir -p /data/pathdb && \
		mkdir -p /data/pathdb/config/sync && \
		mkdir -p /data/pathdb/files && \
		mkdir -p /data/pathdb/files/wsi && \
		mkdir -p /data/pathdb/logs && \
		touch /data/pathdb/logs/error_log && \
		touch /data/pathdb/logs/access_log 

RUN 	chgrp -R 0 /root/* && \
    	chmod -R g=rwx,o+t /root/*

RUN 	chgrp -R 0 /keys/* && \
    	chmod -R g=rwx,o+t /keys/* 

RUN 	chgrp -R 0 /data/* && \
    	chmod -R g=rwx,o+t /data/* 

RUN 	chgrp -R 0 /config/* && \
    	chmod -R g=rwx,o+t /config/* 

RUN 	chgrp -R 0 /run/* && \
    	chmod -R g=rwx,o+t /run/* 

RUN 	chgrp -R 0 /build/* && \
    	chmod -R g=rwx,o+t /build/* 

RUN 	chgrp -R 0 /quip/* && \
    	chmod -R g=rwx,o+t /quip/* 

RUN 	chgrp -R 0 /var/* && \
    	chmod -R g=rwx,o+t /var/* 

USER 1001

CMD ["sh", "/root/run.sh"]
