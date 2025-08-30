# WordPress with PHP 8.2 and WooCommerce pre-installed
FROM wordpress:php8.2-apache

# Install additional PHP extensions needed for the plugin
RUN docker-php-ext-install pdo_mysql mysqli exif

# Install additional tools
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    vim \
    nano \
    curl \
    wget \
    less \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Configure PHP for development
RUN { \
    echo 'memory_limit = 512M'; \
    echo 'max_execution_time = 300'; \
    echo 'upload_max_filesize = 256M'; \
    echo 'post_max_size = 256M'; \
    echo 'max_input_vars = 3000'; \
    echo 'display_errors = On'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/log/php_errors.log'; \
    echo 'date.timezone = Europe/Rome'; \
} > /usr/local/etc/php/conf.d/development.ini

# Configure Apache
RUN a2enmod rewrite headers expires deflate

# Create log directory
RUN mkdir -p /var/log/wordpress && chown www-data:www-data /var/log/wordpress

# Copy initialization script
COPY docker/wordpress/init.sh /docker-entrypoint-init.d/init.sh
RUN chmod +x /docker-entrypoint-init.d/init.sh

# Set working directory
WORKDIR /var/www/html

# Expose port
EXPOSE 80

# Use the original WordPress entrypoint with our initialization
CMD ["apache2-foreground"]