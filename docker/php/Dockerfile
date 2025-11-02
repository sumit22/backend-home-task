FROM ghcr.io/debricked/docker-image-php-xdebug:latest

# Install PCOV extension for code coverage
RUN pecl install pcov \
    && docker-php-ext-enable pcov

# Configure PCOV
RUN echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/pcov.ini \
    && echo "pcov.directory=/var/www/html" >> /usr/local/etc/php/conf.d/pcov.ini

WORKDIR /var/www/html

# Copy and make startup script executable
COPY docker/php/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh
