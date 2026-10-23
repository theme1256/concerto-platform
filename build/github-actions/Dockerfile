FROM campsych/concerto-platform:test
MAINTAINER Przemyslaw Lis <przemek@concertoplatform.com>

RUN curl -sS https://getcomposer.org/installer -o composer-setup.php \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer --2 \
 && php -r "unlink('composer-setup.php');"

CMD if [ "$CONCERTO_COOKIES_SECURE" = "true" ]; \
    then export CONCERTO_COOKIES_SECURE_PHP=1; \
    else export CONCERTO_COOKIES_SECURE_PHP=0; \
    fi \
 && /wait-for-it.sh $DB_TEST_HOST:$DB_TEST_PORT -t 300 \
 && php bin/console concerto:setup --env=test \
 && BASE_DIR=$(bash /app/concerto/scripts/basedir.sh $CONCERTO_PLATFORM_URL) \
 && sed -e "s/{{nginx_port}}/$NGINX_PORT/g" -e "s|{{nginx_server_conf}}|$NGINX_SERVER_CONF|g" -e "s|{{base_dir}}|$BASE_DIR|g" /app/concerto/build/docker/nginx/concerto.conf.tpl > /etc/nginx/sites-available/concerto.conf \
 && service nginx start \
 && php bin/console concerto:forker:start --env=test \
 && /usr/local/bin/concerto-start-php-fpm \
 && vendor/bin/simple-phpunit
