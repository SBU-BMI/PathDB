FROM centos:7
MAINTAINER Erich Bremer "erich.bremer@stonybrook.edu"
#
# QuIP - PathDB Docker Container
#
### update OS
RUN yum -y update
RUN yum -y install wget which zip unzip telnet
RUN yum -y install epel-release
RUN rpm -Uvh https://mirror.webtatic.com/yum/el7/webtatic-release.rpm
RUN yum -y install httpd
RUN yum -y install java-1.8.0-openjdk
RUN yum -y install mod_php72w php72w-opcache
RUN yum -y install php72w-xml php72w-mcrypt php72w-gd php72w-devel php72w-mysql php72w-intl php72w-mbstring
RUN yum -y install mariadb-server mariadb-client
RUN yum -y install php-pecl-zip
RUN yum -y install openssl mod_ssl
RUN yum -y install git
RUN sed -i 's/;date.timezone =/date.timezone = America\/New_York/g' /etc/php.ini
RUN sed -i 's/;always_populate_raw_post_data = -1/always_populate_raw_post_data = -1/g' /etc/php.ini
RUN yum -y install initscripts

# download Drupal management tools
WORKDIR /build
RUN wget https://getcomposer.org/installer
RUN php installer
RUN rm -f installer
RUN mv composer.phar /usr/local/bin/composer

# create initial Drupal environment
RUN composer create-project drupal-composer/drupal-project:8.x-dev quip --stability dev --no-interaction
RUN mv quip /quip
WORKDIR /quip

# copy Drupal QuIP module over
WORKDIR /quip/web/modules
RUN mkdir quip
COPY quip/ quip/

WORKDIR /quip/web
COPY images/ images/

# download and install extra Drupal modules
WORKDIR /quip
RUN composer require drupal/restui
RUN composer require drupal/search_api
RUN composer require drupal/token
RUN composer require drupal/typed_data
RUN composer require drupal/jwt
RUN composer require drupal/d8w3css
RUN composer require drupal/hide_revision_field
RUN composer require drupal/field_group
RUN composer require drupal/tac_lite
RUN composer require drupal/field_permissions
RUN composer require drupal/views_taxonomy_term_name_depth
RUN composer require drupal/ds
RUN composer require drupal/taxonomy_unique
# set permissions correctly for apache demon access
RUN chown -R apache ../quip
RUN chgrp -R apache ../quip
# adjust location of Drupal-supporting MySQL database files
RUN sed -i 's/datadir=\/var\/lib\/mysql/datadir=\/data\/pathdb\/mysql/g' /etc/my.cnf
# increase php file upload sizes and posts
RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 1G/g' /etc/php.ini
RUN sed -i 's/post_max_size = 8M/post_max_size = 1G/g' /etc/php.ini
# set up Drupal private file area
RUN mkdir -p /data/pathdb/files
RUN chown -R apache /data/pathdb/files
RUN echo "\$settings['file_private_path'] = '/data/pathdb/files';" >> web/sites/default/settings.php

# create self-signed digital keys for JWT
WORKDIR /etc/httpd/conf
RUN openssl req -subj '/CN=www.mydom.com/O=My Company Name LTD./C=US' -x509 -nodes -newkey rsa:2048 -keyout quip.key -out quip.crt

# copy over Docker initialization scripts
EXPOSE 80
COPY run.sh /root/run.sh
COPY httpd.conf /etc/httpd/conf
RUN mkdir /quip/pathdbconfig
COPY config/* /quip/pathdbconfig/
RUN mkdir /quip/content
COPY content/* /quip/content/
RUN chmod 755 /root/run.sh
CMD ["sh", "/root/run.sh"]
