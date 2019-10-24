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
COPY pathdbmysql.cnf pathdbmysql.cnf
COPY w3-theme-custom.css w3-theme-custom.css

# create initial Drupal environment
WORKDIR /
COPY quip/ quip/
COPY modules/quip/ /quip/web/modules/quip/
COPY images/ /quip/web/images/
COPY settings.php /build
# set permissions correctly for apache demon access
RUN chown -R apache:apache /quip
# adjust location of Drupal-supporting MySQL database files
RUN sed -i 's/datadir=\/var\/lib\/mysql/datadir=\/data\/pathdb\/mysql/g' /etc/my.cnf
# increase php file upload sizes and posts
RUN sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 30G/g' /etc/php.ini
RUN sed -i 's/post_max_size = 8M/post_max_size = 30G/g' /etc/php.ini
RUN sed -i 's/;upload_tmp_dir =/upload_tmp_dir = "\/data\/tmp"/g' /etc/php.ini
RUN sed -i 's/sys_temp_dir =/sys_temp_dir = "\/data\/tmp"/g' /etc/php.ini
# set up Drupal private file area
RUN mkdir -p /data/pathdb/files
RUN chown -R apache:apache /data/pathdb/files
RUN chmod -R 775 /data/pathdb/files

# create self-signed digital keys for JWT
WORKDIR /etc/httpd/conf
RUN openssl req -subj '/CN=www.mydom.com/O=My Company Name LTD./C=US' -x509 -nodes -newkey rsa:2048 -keyout quip.key -out quip.crt

# copy over Docker initialization scripts
EXPOSE 80
COPY run.sh /root/run.sh
COPY mysql.tgz /build
RUN mkdir /quip/pathdbconfig
COPY config/* /quip/pathdbconfig/
COPY content/* /quip/content/
RUN mkdir /quip/web/sup
COPY sup/* /quip/web/sup/
# download caMicroscope
WORKDIR /quip/web
RUN git clone https://github.com/camicroscope/caMicroscope.git --branch=v3.5.6
RUN git clone https://github.com/SBU-BMI/FeatureMap --branch=2.0.3
RUN rm /etc/httpd/conf.d/ssl.conf
RUN chmod 755 /root/run.sh
RUN yum update -y && yum clean all
CMD ["sh", "/root/run.sh"]
