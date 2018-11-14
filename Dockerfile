FROM centos:7
MAINTAINER Erich Bremer "erich.bremer@stonybrook.edu"

### turn off selinux
####RUN sed -i 's/SELINUX=enforcing/SELINUX=disabled/g' /etc/selinux/config
### turn off ipv6
####RUN sed -i 's/GRUB_CMDLINE_LINUX="crashkernel=auto rhgb quiet"/GRUB_CMDLINE_LINUX="ipv6.disable=1 crashkernel=auto rhgb quiet"/g' /etc/default/grub
### update OS
RUN yum -y update
RUN yum -y install wget which zip unzip
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
# Define working directory.
WORKDIR /data
RUN wget https://getcomposer.org/installer
RUN php installer
RUN rm -f installer
RUN mv composer.phar /usr/local/bin/composer

RUN composer create-project drupal-composer/drupal-project:8.x-dev quip --stability dev --no-interaction
WORKDIR /data/quip/web/modules
RUN git clone https://github.com/ebremer/PathDB.git
RUN mv PathDB quip
WORKDIR /data/quip
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

RUN chown -R apache ../quip
RUN chgrp -R apache ../quip
RUN sed -i 's/datadir=\/var\/lib\/mysql/datadir=\/data\/mysql/g' /etc/my.cnf
RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/g' /etc/php.ini
RUN sed -i 's/post_max_size = 8M/post_max_size = 512M/g' /etc/php.ini
#RUN sed -i 's/socket=\/var\/lib\/mysql\/mysql.sock/socket=\/data\/mysql\/mysql.sock/g' /etc/my.cnf
RUN mkdir /data/dfiles
RUN chown -R apache /data/dfiles
RUN echo "\$settings['file_private_path'] = '/data/dfiles';" >> web/sites/default/settings.php
WORKDIR /etc/httpd/conf
RUN openssl req -subj '/CN=www.mydom.com/O=My Company Name LTD./C=US' -x509 -nodes -newkey rsa:2048 -keyout quip.key -out quip.crt
WORKDIR /data/quip
EXPOSE 80
COPY run.sh /root/run.sh
COPY init.sh /root/init.sh
COPY httpd.conf /etc/httpd/conf
RUN mkdir /data/quip/pathdbconfig
COPY config/* /data/quip/pathdbconfig/
RUN chmod 755 /root/run.sh
RUN chmod 755 /root/init.sh

CMD ["sh", "/root/run.sh"]
