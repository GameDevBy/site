FROM centos:7

# This image provides an PHP environment for running PHP 7.3 applications.
# Base on:
#    https://github.com/sclorg/s2i-base-container (2f92944a0dc4e52e0c44913cd85e32b5c0c772bd 22 ‎Jan 2019)
#    https://github.com/sclorg/s2i-php-container (60378a6fa5420b33d5d7ce72e894fcce54c8c5df 5 Oct 2018)

# Variable for debug|release build
ARG BUILD_TIME_ENABLE_DEV=false
ENV ENABLE_DEV=$BUILD_TIME_ENABLE_DEV

# Images description
LABEL name="gamedev.by/i2s_centos7_php73" \
      version="latest"

# Set base env variable
ENV \
    # Path to be used in other layers to place s2i scripts into
    STI_SCRIPTS_PATH=/usr/libexec/s2i \
    APP_ROOT=/opt/app-root \
    # The $HOME is not set by default, but some applications needs this variable
    HOME=/opt/app-root/src \
    PATH=/opt/app-root/src/bin:/opt/app-root/bin:$PATH

# Directory with the sources is set as the working directory so all STI scripts can execute relative to this path.
WORKDIR $HOME

# Copy script for yum cleanup
COPY ./s2i/bin/yum-cleanup $STI_SCRIPTS_PATH/yum-cleanup

# When bash is started non-interactively, to run a shell script, for example it
# looks for this variable and source the content of this file. This will enable
# the SCL for all scripts without need to do 'scl enable'.
ENV \
    BASH_ENV=$APP_ROOT/etc/scl_enable \
    ENV=$APP_ROOT/etc/scl_enable \
    PROMPT_COMMAND=". $APP_ROOT/etc/scl_enable"

# Install base repository
RUN mkdir -p $HOME/.pki/nssdb && \
    chown -R 1001:0 $HOME/.pki && \
    INSTALL_PKGS="epel-release \
                  https://rpms.remirepo.net/enterprise/remi-release-7.rpm \
                  centos-release-scl \
                  deltarpm" && \
    rpm --import http://mirror.centos.org/centos/RPM-GPG-KEY-CentOS-7 && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    yum-config-manager --enable remi-php73 && \
    yum -y --setopt=tsflags=nodocs update --nogpgcheck && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Install additional packages
RUN INSTALL_PKGS="nss_wrapper gcc-c++ make git" && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Install dumb
#RUN DUMB_VERSION="1.2.2" && \
#    curl -fsSL https://github.com/Yelp/dumb-init/releases/download/v$DUMB_VERSION/dumb-init_${DUMB_VERSION}_amd64 > /usr/local/bin/dumb-init && \
#    chmod +x /usr/local/bin/dumb-init && \
#    dumb-init -V

# Build icu
#RUN ICU_VERSION="63.1" && \
#    mkdir /tmp/icu && \
#    cd /tmp/icu && \
#    ICU_VERSION_S=$(echo $ICU_VERSION | sed 's/\./-/g') && \
#    git -c advice.detachedHead=false clone --quiet -b "release-$ICU_VERSION_S" --single-branch --depth 1 https://github.com/unicode-org/icu.git . && \
#    ./icu4c/source/runConfigureICU Linux/gcc --prefix=/opt/icu5c && \
#    make -j2 && make -j2 check && make install && \
#    rm -rf /tmp/icu

# Install php
RUN INSTALL_PKGS="php php-devel php-opcache php-curl php-gd php-mbstring php-xml php-zip php-sqlite3 php-mysql php-soap" && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    php -version && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Set php env variable
ENV \
    APP_DATA=$APP_ROOT/src \
    PHP_DEFAULT_INCLUDE_PATH=/usr/share/pear:/usr/share/php \
    PHP_SYSCONF_PATH=/etc \
    PHP_INI_SCAN_DIR=$PHP_SYSCONF_PATH/php.d \
    SYMFONY_ENV=prod \
    APP_ENV=prod

# Build php intl extension
# RUN PHP_VERSION=$(php -version | grep -P '(?<=PHP )([0-9]+\.[0-9]+\.[0-9]+)' -o) && \
#     mkdir /tmp/php && \
#     cd /tmp/php && \
#     git -c advice.detachedHead=false clone --quiet -b "php-$PHP_VERSION" --single-branch --depth 1 https://github.com/php/php-src.git . && \
#     cd ./ext/intl && \
#     export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:/opt/icu5c/lib && \
#     export TEST_PHP_ARGS="-q" && \
#     phpize && \
#     ./configure --enable-intl --with-icu-dir=/opt/icu5c && \
#     make && make test && make install && \
#     echo "extension=intl.so" > $PHP_INI_SCAN_DIR/20-intl.ini && \
#     rm -rf /tmp/php

# Install php-fmp and nginx
RUN echo -e "[nginx]\nname=nginx repo\nbaseurl=http://nginx.org/packages/mainline/centos/$releasever/$basearch/\ngpgcheck=0\nenabled=1" > /etc/yum.repos.d/nginx.repo && \
    cat /etc/yum.repos.d/nginx.repo && \
    rpm --import https://nginx.org/keys/nginx_signing.key && \
    INSTALL_PKGS="php-fpm nginx" && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    nginx -V && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Set web env
ENV PHP_FPM_DATA_PATH=$APP_DATA/web

# Debug info
# RUN ls /usr/lib64/php/modules/
# RUN php -i
# RUN cat $PHP_SYSCONF_PATH/php.ini
# RUN cat $PHP_INI_SCAN_DIR/10-opcache.ini
RUN cat $PHP_SYSCONF_PATH/nginx/mime.types

RUN ls -la $PHP_SYSCONF_PATH/nginx/
#TODO RUN cat $PHP_SYSCONF_PATH/nginx/fastcgi-502.include
RUN cat $PHP_SYSCONF_PATH/nginx/nginx.conf
RUN cat $PHP_SYSCONF_PATH/nginx/fastcgi_params

RUN ls -la $PHP_SYSCONF_PATH/nginx/conf.d/
RUN cat $PHP_SYSCONF_PATH/nginx/conf.d/sever.conf
RUN cat $PHP_SYSCONF_PATH/php-fpm.conf

RUN ls -la $PHP_SYSCONF_PATH/php-fpm.d/
RUN cat $PHP_SYSCONF_PATH/php-fpm.d/www.conf

# Remove unnecessary packeges
#RUN yum remove -y --setopt=tsflags=nodocs php-devel deltarpm gcc-c++ make --nogpgcheck && \
#    yum -y clean all --enablerepo='*' && \
#    rm -rf /var/cache/yum && \
#    rm $STI_SCRIPTS_PATH/yum-cleanup

# Add default user
#RUN useradd -u 1001 -r -g 0 -d $HOME -s /sbin/nologin -c "Default Application User" default && \
#    chown -R 1001:0 $APP_ROOT

#EXPOSE 8080

# Copy the S2I scripts from the specific language image to $STI_SCRIPTS_PATH
#COPY ./s2i/bin/ $STI_SCRIPTS_PATH

# Copy extra files to the image.
#COPY ./root/ /

#RUN /usr/libexec/container-setup && rpm-file-permissions

#USER 1001
