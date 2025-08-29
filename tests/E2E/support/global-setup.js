/**
 * Playwright Global Setup
 * 
 * Sets up the WordPress environment for E2E testing, including plugin activation,
 * test data seeding, and user authentication.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

/**
 * Global setup function executed before all tests
 * 
 * @param {Object} config - Playwright configuration
 */
async function globalSetup(config) {
  console.log('🚀 Setting up E2E test environment...');
  
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();
  
  try {
    const baseURL = config.projects[0].use.baseURL;
    
    // 1. Check WordPress is accessible
    await page.goto(`${baseURL}/wp-admin`);
    await page.waitForSelector('#loginform, .wp-admin', { timeout: 10000 });
    
    // 2. Login as admin if not already logged in
    if (await page.locator('#loginform').isVisible()) {
      await page.fill('#user_login', process.env.WP_ADMIN_USER || 'admin');
      await page.fill('#user_pass', process.env.WP_ADMIN_PASS || 'password');
      await page.click('#wp-submit');
      await page.waitForSelector('.wp-admin', { timeout: 10000 });
    }
    
    // 3. Activate WooCommerce if not already active
    await activatePlugin(page, 'WooCommerce', 'woocommerce/woocommerce.php');
    
    // 4. Activate our plugin
    await activatePlugin(page, 'Woo AI Assistant', 'woo-ai-assistant/woo-ai-assistant.php');
    
    // 5. Set up WooCommerce basic settings
    await setupWooCommerce(page);
    
    // 6. Create test products
    await createTestProducts(page);
    
    // 7. Create test users
    await createTestUsers(page);
    
    // 8. Save authentication state for tests
    await context.storageState({ path: path.join(__dirname, '../../storage-state.json') });
    
    console.log('✅ E2E test environment setup completed');
    
  } catch (error) {
    console.error('❌ Error setting up E2E test environment:', error);
    throw error;
  } finally {
    await browser.close();
  }
}

/**
 * Activate a WordPress plugin
 * 
 * @param {Object} page - Playwright page object
 * @param {string} pluginName - Human readable plugin name
 * @param {string} pluginSlug - Plugin file path
 */
async function activatePlugin(page, pluginName, pluginSlug) {
  console.log(`🔌 Activating ${pluginName}...`);
  
  await page.goto(`${page.url().split('/wp-admin')[0]}/wp-admin/plugins.php`);
  
  // Check if plugin is already active
  const isActive = await page.locator(`[data-plugin="${pluginSlug}"] .deactivate`).count() > 0;
  
  if (!isActive) {
    // Try to activate the plugin
    const activateLink = page.locator(`[data-plugin="${pluginSlug}"] .activate a`);
    if (await activateLink.count() > 0) {
      await activateLink.click();
      await page.waitForSelector('.notice-success, .notice-error', { timeout: 5000 });
      console.log(`✅ ${pluginName} activated`);
    } else {
      console.warn(`⚠️ ${pluginName} not found or already active`);
    }
  } else {
    console.log(`✅ ${pluginName} already active`);
  }
}

/**
 * Set up basic WooCommerce configuration
 * 
 * @param {Object} page - Playwright page object
 */
async function setupWooCommerce(page) {
  console.log('🛒 Setting up WooCommerce...');
  
  // Skip WooCommerce setup wizard if it appears
  await page.goto(`${page.url().split('/wp-admin')[0]}/wp-admin/admin.php?page=wc-admin`);
  
  // Wait a bit for any redirects
  await page.waitForTimeout(2000);
  
  // If setup wizard appears, skip it
  if (await page.locator('text=Skip setup store details').count() > 0) {
    await page.click('text=Skip setup store details');
  }
  
  // Set basic store settings
  await page.goto(`${page.url().split('/wp-admin')[0]}/wp-admin/admin.php?page=wc-settings`);
  
  // Enable guest checkout
  const guestCheckout = page.locator('#woocommerce_enable_guest_checkout');
  if (await guestCheckout.count() > 0 && !await guestCheckout.isChecked()) {
    await guestCheckout.check();
  }
  
  // Save settings if save button exists
  if (await page.locator('.woocommerce-save-button').count() > 0) {
    await page.click('.woocommerce-save-button');
    await page.waitForSelector('.notice-success', { timeout: 5000 });
  }
  
  console.log('✅ WooCommerce setup completed');
}

/**
 * Create test products for E2E testing
 * 
 * @param {Object} page - Playwright page object
 */
async function createTestProducts(page) {
  console.log('📦 Creating test products...');
  
  const testProducts = [
    {
      name: 'Test Product 1',
      price: '29.99',
      description: 'This is a test product for E2E testing.'
    },
    {
      name: 'Test Product 2',
      price: '49.99', 
      description: 'Another test product with different price.'
    }
  ];
  
  for (const product of testProducts) {
    // Check if product already exists
    await page.goto(`${page.url().split('/wp-admin')[0]}/wp-admin/edit.php?post_type=product`);
    
    const existingProduct = await page.locator(`text=${product.name}`).count();
    if (existingProduct > 0) {
      console.log(`✅ ${product.name} already exists`);
      continue;
    }
    
    // Create new product
    await page.goto(`${page.url().split('/wp-admin')[0]}/wp-admin/post-new.php?post_type=product`);
    
    await page.fill('#title', product.name);
    await page.fill('#content', product.description);
    await page.fill('#_regular_price', product.price);
    
    // Publish product
    await page.click('#publish');
    await page.waitForSelector('.notice-success', { timeout: 5000 });
    
    console.log(`✅ Created ${product.name}`);
  }
}

/**
 * Create test users for E2E testing
 * 
 * @param {Object} page - Playwright page object
 */
async function createTestUsers(page) {
  console.log('👥 Creating test users...');
  
  const testUsers = [
    {
      username: 'testcustomer',
      email: 'test@customer.com',
      password: 'testpass123',
      role: 'customer'
    }
  ];
  
  for (const user of testUsers) {
    await page.goto(`${page.url().split('/wp-admin')[0]}/wp-admin/users.php`);
    
    // Check if user already exists
    const existingUser = await page.locator(`text=${user.username}`).count();
    if (existingUser > 0) {
      console.log(`✅ User ${user.username} already exists`);
      continue;
    }
    
    // Create new user
    await page.goto(`${page.url().split('/wp-admin')[0]}/wp-admin/user-new.php`);
    
    await page.fill('#user_login', user.username);
    await page.fill('#email', user.email);
    await page.fill('#pass1', user.password);
    await page.fill('#pass2', user.password);
    await page.selectOption('#role', user.role);
    
    await page.click('#createusersub');
    await page.waitForSelector('.notice-success', { timeout: 5000 });
    
    console.log(`✅ Created user ${user.username}`);
  }
}

module.exports = globalSetup;