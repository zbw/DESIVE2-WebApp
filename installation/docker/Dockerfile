FROM php:8-apache

# Install mysql extension
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql 

# Install zip extension
RUN apt-get update && apt-get install -y libzip-dev zip && docker-php-ext-install zip

# Modify Document_root to use the public sub-dir
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Change upload_max_filesize to 100M
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/upload.ini
# Change post_max_size to 100M
RUN echo "post_max_size = 100M" > /usr/local/etc/php/conf.d/post.ini
# Change memory_limit to 512M
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory.ini
# Change max_execution_time to 300
RUN echo "max_execution_time = 300" > /usr/local/etc/php/conf.d/execution.ini

# Change mailserver to use mail as server with port 25
RUN apt-get update && apt-get install msmtp -y
RUN echo "sendmail_path = /usr/bin/msmtp -t" > /usr/local/etc/php/conf.d/mail.ini
# Create file msmtprc in /etc/msmtprc
COPY ./msmtp/msmtprc /etc/msmtprc
# https://stackoverflow.com/a/59890576
RUN chown www-data:www-data /etc/msmtprc
RUN chmod 600 /etc/msmtprc
RUN touch /var/log/msmtp.log
RUN chown www-data:www-data /var/log/msmtp.log
RUN chmod 640 /var/log/msmtp.log

# Change timezone to Europe/Berlin
RUN ln -fs /usr/share/zoneinfo/Europe/Berlin /etc/localtime
RUN DEBIAN_FRONTEND=noninteractive apt-get install -y tzdata
RUN dpkg-reconfigure --frontend noninteractive tzdata
# Change in php.ini by using conf.d
RUN echo "date.timezone = Europe/Berlin" > /usr/local/etc/php/conf.d/timezone.ini

# Install cron package
RUN apt-get update && apt-get install -y cron

# Echo cron job to file
RUN echo "* * * * * /usr/local/bin/php /var/www/html/private/resources/cron/push_notifications.php >> /dev/null 2>&1" > /etc/cron.d/crontab

# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/crontab

# Apply cron job
RUN crontab /etc/cron.d/crontab

# Add cron to entrypoint
RUN sed -i 's/^exec /service cron start\n\nexec /' /usr/local/bin/apache2-foreground