FROM silintl/php8:8.3

ARG GITHUB_REF_NAME
ENV GITHUB_REF_NAME=$GITHUB_REF_NAME

RUN apt-get update && \
    apt-get --no-install-recommends install -y ssl-cert && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    make-ssl-cert generate-default-snakeoil && \
    a2enmod ssl

WORKDIR /data

# Install/cleanup composer dependencies
COPY application/composer.json /data/
COPY application/composer.lock /data/
RUN composer install --prefer-dist --no-interaction --no-dev --optimize-autoloader --no-progress

# It is expected that /data is = application/ in project folder
COPY application/ /data/

# Fix folder permissions
RUN chown -R www-data:www-data \
    console/runtime/ \
    frontend/runtime/ \
    frontend/web/assets/

COPY dockerbuild/vhost.conf /etc/apache2/sites-enabled/

# ErrorLog inside a VirtualHost block is ineffective for unknown reasons
RUN sed -i -E 's@ErrorLog .*@ErrorLog /proc/self/fd/2@i' /etc/apache2/apache2.conf

RUN touch /etc/default/locale

EXPOSE 80
CMD ["/data/run.sh"]
