FROM centos/s2i-core-centos7

# Description
# This image provides an PHP environment for running PHP 7.3 applications.

ENV PHP_VERSION=7.3 \
    PHP_VER_SHORT=73 \
    NAME=php \
    PATH=$PATH

ENV SUMMARY="Platform for building and running PHP $PHP_VERSION applications" \
    DESCRIPTION="PHP $PHP_VERSION available as container is a base platform for \
building and running various PHP $PHP_VERSION applications and frameworks. \
PHP is an HTML-embedded scripting language. PHP attempts to make it easy for developers \
to write dynamically generated web pages. PHP also offers built-in database integration \
for several commercial and non-commercial database management systems, so writing \
a database-enabled webpage with PHP is fairly simple. The most common use of PHP coding \
is probably as a replacement for CGI scripts."

LABEL summary="${SUMMARY}" \
      description="${DESCRIPTION}" \
      io.k8s.description="${DESCRIPTION}" \
      io.k8s.display-name="PHP ${PHP_VERSION}" \
      io.openshift.tags="builder,${NAME},${NAME}${PHP_VER_SHORT},rh-${NAME}${PHP_VER_SHORT}" \
      io.openshift.s2i.scripts-url="image:///usr/libexec/s2i" \
      io.s2i.scripts-url="image:///usr/libexec/s2i" \
      name="gamedev.by/i2s_centos7_php73 \
      version="latest"

RUN INSTALL_PKGS="epel-release \
                  https://rpms.remirepo.net/enterprise/remi-release-7.rpm \
                  centos-release-scl \
                  deltarpm" && \
    rpm --import http://mirror.centos.org/centos/RPM-GPG-KEY-CentOS-7 && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    yum-config-manager --enable remi-php73 && \
    yum -y --setopt=tsflags=nodocs update --nogpgcheck && \
    $STI_SCRIPTS_PATH/yum-cleanup

RUN INSTALL_PKGS="nss_wrapper htop gcc-c++ make git" && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    $STI_SCRIPTS_PATH/yum-cleanup

RUN DUMB_VERSION="1.2.2" && \
    curl -fsSL https://github.com/Yelp/dumb-init/releases/download/v${DUMB_VERSION}/dumb-init_${DUMB_VERSION}_amd64 > /usr/local/bin/dumb-init && \
    chmod +x /usr/local/bin/dumb-init && \
    dumb-init -V

RUN ICU_VERSION="63.1" && \
    mkdir /tmp/icu && \
    cd /tmp/icu && \
    ICU_VERSION_S=$(echo $ICU_VERSION | sed 's/\./-/g') && \
    git -c advice.detachedHead=false clone --quiet -b "release-$ICU_VERSION_S" --single-branch --depth 1 https://github.com/unicode-org/icu.git . && \
    ./icu4c/source/runConfigureICU Linux/gcc --prefix=/opt/icu5c && \
    make --quiet -j2 && make --quiet -j2 check && make install && \
    rm -rf /tmp/icu

RUN INSTALL_PKGS="php php-devel php-opcache php-curl php-gd php-mbstring php-xml php-zip php-sqlite3 php-mysql php-soap" && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    $STI_SCRIPTS_PATH/yum-cleanup

RUN PHP_VERSION=$(php -version | grep -P '(?<=PHP )([0-9]+\.[0-9]+\.[0-9]+)' -o) && \
    mkdir /tmp/php && \
    cd /tmp/php && \
    git -c advice.detachedHead=false clone --quiet -b "php-$PHP_SHORT_VERSION" --single-branch --depth 1 https://github.com/php/php-src.git . && \
    cd ./ext/intl && \
    export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:/opt/icu5c/lib && \
    export TEST_PHP_ARGS="-q" && \
    phpize && \
    ./configure --enable-intl --with-icu-dir=/opt/icu5c && \
    make && make test && make install && \
    echo "extension=intl.so" > /etc/php.d/20-intl.ini && \
    rm -rf /tmp/php

RUN php -i
RUN ls /usr/lib64/php/modules/
RUN cat ${PHP_SYSCONF_PATH}/php.ini
