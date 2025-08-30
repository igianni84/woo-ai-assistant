# Testing environment for PHPUnit and Jest
FROM wordpress:cli-php8.2

# Install additional dependencies for testing
RUN apk add --no-cache \
    git \
    curl \
    bash \
    nodejs \
    npm \
    mysql-client \
    zip \
    unzip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHPUnit globally
RUN composer global require phpunit/phpunit ^10.0

# Install Node.js testing tools
RUN npm install -g jest jest-cli

# Set working directory
WORKDIR /app

# Create test directories
RUN mkdir -p /app/tests/unit /app/tests/integration /app/coverage

# Set PHP configuration for testing
RUN echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/test.ini
RUN echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/test.ini

# Add Composer to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

# Create test database setup script
RUN echo '#!/bin/bash\n\
set -e\n\
echo "Setting up test environment..."\n\
\n\
# Wait for MySQL\n\
until mysql -h"$WORDPRESS_DB_HOST" -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; do\n\
  echo "Waiting for MySQL..."\n\
  sleep 2\n\
done\n\
\n\
# Create test database\n\
mysql -h"$WORDPRESS_DB_HOST" -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS woo_ai_test;"\n\
\n\
echo "Test environment ready!"\n\
' > /usr/local/bin/setup-test-env.sh && chmod +x /usr/local/bin/setup-test-env.sh

# Health check for test environment
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD mysql -h"$WORDPRESS_DB_HOST" -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" -e "SELECT 1" || exit 1

# Default command
CMD ["/usr/local/bin/setup-test-env.sh"]