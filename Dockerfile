FROM rockylinux:9
#MAINTAINER Erich Bremer "erich.bremer@stonybrook.edu"
#
# QuIP - PathDB Docker Container
#
### update OS
RUN dnf -y update && dnf clean all
RUN dnf -y install wget which zip unzip bind-utils
RUN dnf install -y dnf-plugins-core
RUN dnf config-manager --set-enabled crb -y
RUN dnf install -y epel-release https://rpms.remirepo.net/enterprise/remi-release-9.rpm
RUN dnf module reset php -y && dnf module enable php:remi-8.4 -y
RUN dnf install -y php php-cli php-common php-fpm php-mysqlnd php-pecl-uploadprogress \
    php-opcache php-xml php-gd php-intl php-mbstring php-pecl-zip php-ldap \
    php-devel httpd telnet openssl mod_ssl procps-ng sudo
COPY mariadb.repo /etc/yum.repos.d/mariadb.repo
RUN dnf install -y MariaDB-server MariaDB-client git
# download Drupal management tools
WORKDIR /build
RUN wget https://getcomposer.org/installer && php installer && rm -f installer && mv composer.phar /usr/local/bin/composer
COPY pathdbmysql.cnf pathdbmysql.cnf
COPY w3-theme-custom.css w3-theme-custom.css
# create initial Drupal environment
WORKDIR /
COPY quip/ quip/
COPY modules/quip/ /quip/web/modules/quip/
COPY images/ /quip/web/images/
COPY settings.php /build
COPY mysql.tgz /build
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
RUN openssl genrsa 2048 > quip.key
# copy over Docker initialization scripts
EXPOSE 80
COPY run.sh /root/run.sh
COPY savepathdb /root/savepathdb
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
ARG viewer
RUN if [ -z ${viewer} ]; then git clone https://github.com/camicroscope/caMicroscope.git --branch=v3.9.1; else git clone https://github.com/camicroscope/caMicroscope.git --branch=$viewer; fi
ARG featureMap
RUN if [ -z ${featureMap} ]; then git clone https://github.com/SBU-BMI/FeatureMap --branch=2.0.3; else git clone https://github.com/SBU-BMI/FeatureMap --branch=$featureMap; fi
RUN rm /etc/httpd/conf.d/ssl.conf
RUN chmod 755 /root/run.sh
RUN dnf update -y && dnf clean all
CMD ["sh", "/root/run.sh"]

