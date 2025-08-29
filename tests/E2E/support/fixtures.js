/**
 * Playwright Test Fixtures
 * 
 * Custom fixtures for Woo AI Assistant E2E testing, providing reusable
 * setup and teardown functionality for tests.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

const { test as base, expect } = require('@playwright/test');
const path = require('path');

/**
 * Custom test fixture with WordPress and WooCommerce setup
 */
const test = base.extend({
  /**
   * Authenticated page fixture with admin login
   */
  authenticatedPage: async ({ browser }, use) => {
    const context = await browser.newContext({
      storageState: path.join(__dirname, '../../storage-state.json')
    });
    const page = await context.newPage();
    await use(page);
    await context.close();
  },

  /**
   * Customer page fixture with customer login
   */
  customerPage: async ({ browser }, use) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Login as test customer
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'testcustomer');
    await page.fill('#user_pass', 'testpass123');
    await page.click('#wp-submit');
    await page.waitForLoadState('networkidle');
    
    await use(page);
    await context.close();
  },

  /**
   * Fresh product page fixture
   */
  productPage: async ({ authenticatedPage }, use) => {
    // Create a fresh test product
    await authenticatedPage.goto('/wp-admin/post-new.php?post_type=product');
    
    const productName = `E2E Test Product ${Date.now()}`;
    await authenticatedPage.fill('#title', productName);
    await authenticatedPage.fill('#content', 'Test product for E2E testing');
    await authenticatedPage.fill('#_regular_price', '19.99');
    
    // Publish product
    await authenticatedPage.click('#publish');
    await authenticatedPage.waitForSelector('.notice-success');
    
    // Get the product URL
    const productUrl = await authenticatedPage.url();
    const productId = productUrl.match(/post=(\d+)/)?.[1];
    
    await use({
      page: authenticatedPage,
      productName,
      productId,
      productUrl: `/shop/product/${productName.toLowerCase().replace(/\s+/g, '-')}`
    });
    
    // Cleanup: Delete the product
    if (productId) {
      await authenticatedPage.goto(`/wp-admin/post.php?post=${productId}&action=delete`);
    }
  },

  /**
   * Chat widget fixture with initialization
   */
  chatWidget: async ({ page }, use) => {
    // Navigate to a page where the chat widget should be available
    await page.goto('/shop');
    
    // Wait for the chat widget to initialize
    await page.waitForSelector('[data-testid="woo-ai-chat-widget"]', { timeout: 10000 });
    
    const widget = {
      container: page.locator('[data-testid="woo-ai-chat-widget"]'),
      trigger: page.locator('[data-testid="chat-trigger-button"]'),
      window: page.locator('[data-testid="chat-window"]'),
      input: page.locator('[data-testid="chat-message-input"]'),
      sendButton: page.locator('[data-testid="chat-send-button"]'),
      messages: page.locator('[data-testid="chat-message"]'),
      
      /**
       * Open the chat window
       */
      async open() {
        if (await this.trigger.isVisible()) {
          await this.trigger.click();
        }
        await page.waitForSelector('[data-testid="chat-window"]');
      },
      
      /**
       * Send a message in the chat
       */
      async sendMessage(message) {
        await this.input.fill(message);
        await this.sendButton.click();
        // Wait for the message to appear
        await page.waitForSelector(`[data-testid="chat-message"]:has-text("${message}")`);
      },
      
      /**
       * Wait for AI response
       */
      async waitForResponse(timeout = 10000) {
        // Wait for typing indicator to appear and then disappear
        await page.waitForSelector('[data-testid="typing-indicator"]', { timeout: 2000 }).catch(() => {});
        await page.waitForSelector('[data-testid="typing-indicator"]', { state: 'hidden', timeout }).catch(() => {});
        
        // Wait for a new AI message
        const messageCount = await this.messages.count();
        await expect(this.messages).toHaveCount(messageCount + 1, { timeout });
      },
      
      /**
       * Close the chat window
       */
      async close() {
        const closeButton = page.locator('[data-testid="chat-close-button"]');
        if (await closeButton.isVisible()) {
          await closeButton.click();
        }
        await page.waitForSelector('[data-testid="chat-window"]', { state: 'hidden' });
      }
    };
    
    await use(widget);
  },

  /**
   * WooCommerce store fixture with test data
   */
  woocommerceStore: async ({ page }, use) => {
    // Navigate to shop page
    await page.goto('/shop');
    
    const store = {
      page,
      
      /**
       * Add a product to cart
       */
      async addToCart(productName) {
        await page.click(`text=${productName}`);
        await page.waitForLoadState('networkidle');
        await page.click('.single_add_to_cart_button');
        await page.waitForSelector('.woocommerce-message', { timeout: 5000 });
      },
      
      /**
       * View cart
       */
      async viewCart() {
        await page.goto('/cart');
        await page.waitForLoadState('networkidle');
      },
      
      /**
       * Proceed to checkout
       */
      async proceedToCheckout() {
        await page.goto('/checkout');
        await page.waitForLoadState('networkidle');
      },
      
      /**
       * Get cart count
       */
      async getCartCount() {
        const cartCount = await page.locator('.cart-count, .cart-contents-count').textContent();
        return parseInt(cartCount || '0');
      }
    };
    
    await use(store);
  }
});

/**
 * Custom expect matchers for WordPress/WooCommerce
 */
expect.extend({
  /**
   * Check if element has WordPress admin bar
   */
  async toHaveWordPressAdminBar(page) {
    const adminBar = await page.locator('#wpadminbar').count();
    return {
      message: () => `Expected page to have WordPress admin bar`,
      pass: adminBar > 0
    };
  },

  /**
   * Check if page is a WooCommerce shop page
   */
  async toBeWooCommerceShop(page) {
    const shopClass = await page.locator('body.woocommerce-shop').count();
    return {
      message: () => `Expected page to be a WooCommerce shop page`,
      pass: shopClass > 0
    };
  },

  /**
   * Check if chat widget is initialized
   */
  async toHaveChatWidget(page) {
    const widget = await page.locator('[data-testid="woo-ai-chat-widget"]').count();
    return {
      message: () => `Expected page to have Woo AI chat widget`,
      pass: widget > 0
    };
  }
});

module.exports = { test, expect };