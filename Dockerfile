FROM centos:7
MAINTAINER Erich Bremer "erich.bremer@stonybrook.edu"
#
# QuIP - PathDB Docker Container
#
### update OS
RUN yum -y install wget which zip unzip java-1.8.0-openjdk bind-utils epel-release
RUN rpm -Uvh http://mirror.bebout.net/remi/enterprise/remi-release-7.rpm
RUN yum-config-manager --enable remi-php73
RUN yum -y install httpd openssl mod_ssl mod_php php-opcache php-xml php-mcrypt php-gd php-devel php-mysql php-intl php-mbstring php-uploadprogress php-pecl-zip php-ldap
RUN yum -y install mariadb-server mariadb-client git
RUN sed -i 's/;date.timezone =/date.timezone = America\/New_York/g' /etc/php.ini
RUN sed -i 's/;always_populate_raw_post_data = -1/always_populate_raw_post_data = -1/g' /etc/php.ini

# download Drupal management tools
WORKDIR /build
RUN wget https://getcomposer.org/installer
RUN php installer
RUN rm -f installer
RUN mv composer.phar /usr/local/bin/composer

# create initial Drupal environment
RUN composer create-project drupal-composer/drupal-project:8.x-dev quip --stability dev --no-interaction
RUN mv quip /quip

# copy Drupal QuIP module over
WORKDIR /quip/web/modules
RUN mkdir quip
COPY quip/ quip/

WORKDIR /quip/web
COPY images/ images/

# download and install extra Drupal modules
WORKDIR /quip
RUN composer require drupal/restui &&\
    composer require drupal/search_api &&\
    composer require drupal/token &&\
    composer require drupal/typed_data &&\
    composer require drupal/jwt &&\
    composer require drupal/d8w3css &&\
    composer require drupal/hide_revision_field &&\
    composer require 'drupal/field_group:^3.0' &&\
    composer require drupal/tac_lite &&\
    composer require drupal/field_permissions &&\
    composer require drupal/views_taxonomy_term_name_depth &&\
    composer require drupal/ds &&\
    composer require drupal/taxonomy_unique &&\
    composer require drupal/prepopulate &&\
    composer require drupal/auto_entitylabel &&\
    composer require drupal/easy_breadcrumb &&\
    composer require drupal/csv_serialization &&\
    composer require drupal/views_data_export &&\
    composer require drupal/facets &&\
    composer require drupal/redirect_after_login &&\
    composer require drupal/views_base_url &&\
    composer require 'drupal/restrict_by_ip:4.x-dev' &&\
    composer require drupal/ldap

# set permissions correctly for apache demon access
RUN chown -R apache ../quip
RUN chgrp -R apache ../quip
# adjust location of Drupal-supporting MySQL database files
RUN sed -i 's/datadir=\/var\/lib\/mysql/datadir=\/data\/pathdb\/mysql/g' /etc/my.cnf
# increase php file upload sizes and posts
RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 30G/g' /etc/php.ini
RUN sed -i 's/post_max_size = 8M/post_max_size = 30G/g' /etc/php.ini
RUN sed -i 's/;upload_tmp_dir =/upload_tmp_dir = "\/data\/tmp"/g' /etc/php.ini
RUN sed -i 's/sys_temp_dir =/sys_temp_dir = "\/data\/tmp"/g' /etc/php.ini
# set up Drupal private file area
RUN mkdir -p /data/pathdb/files
RUN chown -R apache /data/pathdb/files
RUN chgrp -R apache /data/pathdb/files
RUN chmod -R 770 /data/pathdb/files
RUN echo "\
\$config_directories['sync'] = '/data/pathdb/config/sync';
\$settings['file_private_path'] = '/data/pathdb/files';\
\$databases['default']['default'] = array (\
  'database' => 'QuIP',\
  'username' => 'root',\
  'password' => '',\
  'prefix' => '',\
  'host' => 'localhost',\
  'port' => '',\
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',\
  'driver' => 'mysql',\
);\
\$settings['hash_salt'] = '`uuidgen`';\
" >> web/sites/default/settings.php

# create self-signed digital keys for JWT
WORKDIR /etc/httpd/conf
RUN openssl req -subj '/CN=www.mydom.com/O=My Company Name LTD./C=US' -x509 -nodes -newkey rsa:2048 -keyout quip.key -out quip.crt

# copy over Docker initialization scripts
EXPOSE 80
COPY run.sh /root/run.sh
COPY mysql.tgz /build
RUN mkdir /quip/pathdbconfig
COPY config/* /quip/pathdbconfig/
RUN mkdir /quip/content
COPY content/* /quip/content/
RUN mkdir /quip/web/sup
COPY sup/* /quip/web/sup/
# download caMicroscope
WORKDIR /quip/web
RUN git clone https://github.com/camicroscope/caMicroscope.git --branch=v3.4.2
RUN git clone https://github.com/SBU-BMI/FeatureMap --branch=2.0
RUN rm /etc/httpd/conf.d/ssl.conf
RUN chmod 755 /root/run.sh
RUN yum update -y && yum clean all
CMD ["sh", "/root/run.sh"]
