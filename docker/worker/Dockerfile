# worker/Dockerfile
FROM php:8.3-cli

# Set working directory
WORKDIR /var/www

# Install cron
RUN apt-get update && apt-get install -y cron

# Update repositories and install necessary packages
RUN apt-get update && apt-get install -y \
    supervisor \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    zip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    locales \
    libicu-dev \
    libonig-dev \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql zip exif pcntl

# Install redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Install intl extension
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl

# Install Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Copy crontab file
COPY crontab /etc/cron.d/cron-jobs

# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/cron-jobs

# Apply cron job
RUN crontab /etc/cron.d/cron-jobs

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
