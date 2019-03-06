FROM centos:7

# This image provides an PHP environment for running PHP 7.3 applications.
# Base on:
#    https://github.com/sclorg/s2i-base-container (3c2b4d2e896a3fdea29f19580af1fe3d50c3db84 - 13 Feb 2019)
#    https://github.com/sclorg/s2i-php-container (eebcb2ced3539fd2c541fc8b0ede2b892070f899 - 06 ‎March 2019)

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

# Config shell for run commands
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

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
    INSTALL_PKGS=(epel-release \
                  https://rpms.remirepo.net/enterprise/remi-release-7.rpm \
                  centos-release-scl \
                  deltarpm) && \
    rpm --import http://mirror.centos.org/centos/RPM-GPG-KEY-CentOS-7 && \
    yum install -y --setopt=tsflags=nodocs "${INSTALL_PKGS[@]}" && \
    yum-config-manager --enable remi-php73 && \
    yum -y --setopt=tsflags=nodocs update && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Install additional packages
RUN INSTALL_PKGS=(yum-utils nss_wrapper gcc-c++ make git) && \
    yum install -y --setopt=tsflags=nodocs "${INSTALL_PKGS[@]}" && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Install dumb
RUN DUMB_VERSION="1.2.2" && \
    curl -fsSL https://github.com/Yelp/dumb-init/releases/download/v$DUMB_VERSION/dumb-init_${DUMB_VERSION}_amd64 > /usr/local/bin/dumb-init && \
    chmod +x /usr/local/bin/dumb-init && \
    dumb-init -V

# Build icu
RUN ICU_VERSION="63.1" && \
    mkdir /tmp/icu && \
    pushd /tmp/icu || exit 1 && \
    ICU_VERSION_S=${ICU_VERSION//\./-} && \
    git -c advice.detachedHead=false clone --quiet -b "release-$ICU_VERSION_S" --single-branch --depth 1 https://github.com/unicode-org/icu.git . && \
    pushd ./icu4c/source/ || exit 1 && \
    ./runConfigureICU Linux --prefix=/opt/icu5c && \
    popd || exit 1 && \
    make -j2 && make -j2 check && make install && \
    rm -rf /tmp/icu && \
    popd || exit 1

# Install php
# Enable extension from https://www.drupal.org/docs/8/system-requirements/php-requirements
RUN INSTALL_PKGS=(php php-common php-devel php-gd php-json php-mbstring php-opcache php-pdo php-xml php-mysqlnd) && \
    yum install -y --setopt=tsflags=nodocs "${INSTALL_PKGS[@]}" && \
    rm -rf /etc/php.d/ && \
    mkdir -p /etc/php.d/ && \
    echo "extension=dom" > /etc/php.d/20-dom.ini && \
    echo "extension=gd" > /etc/php.d/20-gd.ini && \
    echo "extension=json" > /etc/php.d/20-json.ini && \
    echo "extension=pdo" > /etc/php.d/20-pdo.ini && \
    echo "extension=tokenizer" > /etc/php.d/20-tokenizer.ini && \
    echo "extension=xml" > /etc/php.d/20-xml.ini && \
    echo "extension=curl" > /etc/php.d/20-curl.ini && \
    echo "extension=mbstring" > /etc/php.d/20-mbstring.ini && \
    echo "extension=mysqlnd" > /etc/php.d/30-mysqlnd.ini && \
    echo "extension=phar" > /etc/php.d/40-phar.ini && \
    php -version && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Set php env variable
ENV \
    APP_DATA=$APP_ROOT/src \
    PHP_DEFAULT_INCLUDE_PATH=/usr/share/pear:/usr/share/php \
    PHP_SYSCONF_PATH=/etc \
    SYMFONY_ENV=prod \
    APP_ENV=prod

# Set php ini scan dir
ENV PHP_INI_SCAN_DIR=$PHP_SYSCONF_PATH/php.d

# Build php intl extension
RUN PHP_VERSION=$(php -version | grep -P '(?<=PHP )([0-9]+\.[0-9]+\.[0-9]+)' -o) && \
    mkdir /tmp/php && \
    pushd /tmp/php || exit 1 && \
    git -c advice.detachedHead=false clone --quiet -b "php-$PHP_VERSION" --single-branch --depth 1 https://github.com/php/php-src.git . && \
    pushd ./ext/intl || exit 1 && \
    export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:/opt/icu5c/lib && \
    export TEST_PHP_ARGS="-q" && \
    phpize && \
    ./configure --enable-intl --with-icu-dir=/opt/icu5c && \
    make && make test && make install && \
    echo "extension=intl" > $PHP_INI_SCAN_DIR/20-intl.ini && \
    popd || exit 1 && \
    rm -rf /tmp/php && \
    popd || exit 1 && \
    php -r 'function_exists("intl_error_name") || (print("Failed to enable php-intl\n") && exit(1));'

# Install php-fmp and nginx
# https://nginx.org/ru/linux_packages.html#RHEL-CentOS
#
# (From here https://www.nginx.com/blog/nginx-1-6-1-7-released/)
# Note that stable does not mean more reliable or more bug-free.
# In fact, the mainline is generally regarded as more reliable because we port all bug fixes to it,
# and not just critical fixes as for the stable branch.
# On the other hand, changes in the stable branch are very unlikely to affect third-party modules.
# We don’t make the same commitment concerning the mainline,
# where new features can affect the operation of third-party modules.
# Which Version Should I Use?
# We recommend that in general you deploy the NGINX mainline branch at all times.
# The main reason to use the stable branch is that you are concerned about possible impacts of new features,
# such as incompatibility with third-party modules or the inadvertent introduction of bugs in new features.
RUN echo -e "[nginx]\nname=nginx mainline repo\nbaseurl=http://nginx.org/packages/mainline/centos/7/x86_64/\ngpgcheck=1\nenabled=1\ngpgkey=https://nginx.org/keys/nginx_signing.key" > /etc/yum.repos.d/nginx.repo && \
    cat /etc/yum.repos.d/nginx.repo && \
    rpm --import https://nginx.org/keys/nginx_signing.key && \
    INSTALL_PKGS=(php-fpm nginx) && \
    yum install -y --setopt=tsflags=nodocs "${INSTALL_PKGS[@]}" && \
    nginx -V && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Set web env
ENV PHP_FPM_DATA_PATH=$APP_DATA/web

# Install composer
RUN mkdir /tmp/composer && \
    curl -o /tmp/composer/composer-setup.php https://getcomposer.org/installer && \
    curl -o /tmp/composer/composer-setup.sig https://composer.github.io/installer.sig && \
    php -r "if (hash('SHA384', file_get_contents('/tmp/composer/composer-setup.php')) !== trim(file_get_contents('/tmp/composer/composer-setup.sig'))) { unlink('/tmp/composer/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" && \
    php /tmp/composer/composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    composer diagnose --no-interaction --ansi --verbose && \
    rm -rf /tmp/composer

# Copy script for install development environment
COPY ./s2i/bin/debug-specific-config $STI_SCRIPTS_PATH/debug-specific-config

# Run script for install development environment
RUN $STI_SCRIPTS_PATH/debug-specific-config && \
    $STI_SCRIPTS_PATH/yum-cleanup

# Debug info
RUN ls -la /usr/lib64/php/modules/ && \
    ls -la /etc/php.d/ && \
    php -i

# View override files for debug purpose
# RUN cat $PHP_SYSCONF_PATH/php.ini
# RUN cat $PHP_INI_SCAN_DIR/10-opcache.ini
# RUN cat $PHP_SYSCONF_PATH/nginx/mime.types
# RUN cat $PHP_SYSCONF_PATH/nginx/nginx.conf
# RUN cat $PHP_SYSCONF_PATH/nginx/fastcgi_params
# RUN cat $PHP_SYSCONF_PATH/nginx/conf.d/default.conf
# RUN cat $PHP_SYSCONF_PATH/php-fpm.conf
# RUN cat $PHP_SYSCONF_PATH/php-fpm.d/www.conf

# Remove unnecessary packeges
#RUN REMOVE_PKGS="php-devel deltarpm gcc-c++ make" && \
#    yum remove -y --setopt=tsflags=nodocs $REMOVE_PKGS && \
#    yum -y clean all --enablerepo='*' && \
#    rm -rf /var/cache/yum && \
#    rm $STI_SCRIPTS_PATH/yum-cleanup && \
#    rm $STI_SCRIPTS_PATH/debug-specific-config && \
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
