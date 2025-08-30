#!/bin/bash
set -euo pipefail

# WordPress and WooCommerce Auto-Setup Script
# This script runs after WordPress is initialized but before it's fully ready

echo "🚀 Starting Woo AI Assistant Docker initialization..."

# Wait for WordPress to be ready
echo "⏳ Waiting for WordPress to be ready..."
until wp core is-installed --allow-root 2>/dev/null; do
    echo "   WordPress not ready yet, waiting 5 seconds..."
    sleep 5
done

echo "✅ WordPress is ready!"

# Check if WooCommerce is already installed
if ! wp plugin is-installed woocommerce --allow-root 2>/dev/null; then
    echo "📦 Installing WooCommerce..."
    wp plugin install woocommerce --activate --allow-root
    
    # Set up WooCommerce store
    echo "🏪 Setting up WooCommerce store..."
    wp wc --user=admin tool run install_pages --allow-root 2>/dev/null || echo "   Store pages setup completed (or already exists)"
    
    # Set basic WooCommerce settings
    wp option update woocommerce_store_address "123 Developer Street" --allow-root
    wp option update woocommerce_store_city "Dev City" --allow-root
    wp option update woocommerce_default_country "IT" --allow-root
    wp option update woocommerce_store_postcode "00100" --allow-root
    wp option update woocommerce_currency "EUR" --allow-root
    wp option update woocommerce_product_type "both" --allow-root
    wp option update woocommerce_allow_tracking "no" --allow-root
    
    echo "✅ WooCommerce installed and configured!"
else
    echo "✅ WooCommerce already installed"
fi

# Check if our plugin is already activated
if ! wp plugin is-active woo-ai-assistant --allow-root 2>/dev/null; then
    echo "🤖 Activating Woo AI Assistant plugin..."
    wp plugin activate woo-ai-assistant --allow-root || echo "   Plugin activation will be done when plugin files are ready"
else
    echo "✅ Woo AI Assistant plugin already active"
fi

# Install development plugins
DEV_PLUGINS=("query-monitor" "debug-bar" "wp-crontrol")
for plugin in "${DEV_PLUGINS[@]}"; do
    if ! wp plugin is-installed "$plugin" --allow-root 2>/dev/null; then
        echo "🔧 Installing development plugin: $plugin"
        wp plugin install "$plugin" --activate --allow-root
    fi
done

# Create sample WooCommerce products for testing
if [ "$(wp post list --post_type=product --format=count --allow-root)" -eq "0" ]; then
    echo "🛍️  Creating sample products for testing..."
    
    # Create sample products
    wp wc product create \
        --user=admin \
        --name="Test Product 1" \
        --type=simple \
        --regular_price=19.99 \
        --description="This is a test product for the AI assistant to learn about." \
        --short_description="A simple test product." \
        --status=publish \
        --allow-root
        
    wp wc product create \
        --user=admin \
        --name="Premium Product 2" \
        --type=simple \
        --regular_price=99.99 \
        --description="This is a premium product with advanced features that customers love." \
        --short_description="Premium quality product." \
        --status=publish \
        --allow-root
        
    wp wc product create \
        --user=admin \
        --name="Digital Download" \
        --type=simple \
        --regular_price=29.99 \
        --virtual=true \
        --downloadable=true \
        --description="A digital product that can be downloaded instantly." \
        --short_description="Instant digital download." \
        --status=publish \
        --allow-root
        
    echo "✅ Sample products created!"
else
    echo "✅ Sample products already exist"
fi

# Set up development-friendly permalinks
wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --allow-root

# Enable WordPress debugging (in case it's not already set)
wp config set WP_DEBUG true --raw --allow-root
wp config set WP_DEBUG_LOG true --raw --allow-root
wp config set WP_DEBUG_DISPLAY false --raw --allow-root
wp config set SCRIPT_DEBUG true --raw --allow-root

# Plugin-specific debug settings
wp config set WOO_AI_ASSISTANT_DEBUG true --raw --allow-root
wp config set WOO_AI_DEVELOPMENT_MODE true --raw --allow-root

# Configure mail to use Mailhog
wp config set WPMS_ON true --raw --allow-root || true
wp config set WPMS_SMTP_HOST mailhog --allow-root || true
wp config set WPMS_SMTP_PORT 1025 --raw --allow-root || true
wp config set WPMS_SMTP_AUTH false --raw --allow-root || true

# Create an admin user if it doesn't exist
if ! wp user get admin --allow-root 2>/dev/null; then
    echo "👤 Creating admin user..."
    wp user create admin admin@example.com \
        --role=administrator \
        --user_pass=password \
        --display_name="Admin User" \
        --allow-root
    echo "✅ Admin user created (admin/password)"
else
    echo "✅ Admin user already exists"
fi

# Display useful information
echo ""
echo "🎉 Woo AI Assistant Docker environment is ready!"
echo ""
echo "📍 Access URLs:"
echo "   WordPress:    http://localhost:8080"
echo "   Admin:        http://localhost:8080/wp-admin (admin/password)"
echo "   phpMyAdmin:   http://localhost:8081"
echo "   Mailhog:      http://localhost:8025"
echo ""
echo "📊 Database Info:"
echo "   Host: mysql (internal) / localhost:3306 (external)"
echo "   Name: woo_ai_dev"
echo "   User: wordpress"
echo "   Pass: wordpress"
echo ""
echo "🔧 Useful Commands:"
echo "   View logs:        docker-compose logs -f wordpress"
echo "   Run WP-CLI:       docker-compose exec wordpress wp --allow-root [command]"
echo "   Run Composer:     docker-compose exec wordpress composer [command]"
echo "   Run tests:        docker-compose --profile testing run test-runner composer test"
echo ""

# Set proper permissions for plugin directory
chown -R www-data:www-data /var/www/html/wp-content/plugins/woo-ai-assistant || true
chmod -R 755 /var/www/html/wp-content/plugins/woo-ai-assistant || true

echo "✅ Docker initialization complete!"