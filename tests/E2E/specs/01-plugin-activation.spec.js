/**
 * Plugin Activation E2E Tests
 * 
 * Tests for plugin activation, deactivation, and basic functionality
 * verification in WordPress admin.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

const { test, expect } = require('../support/fixtures');

test.describe('Plugin Activation', () => {
  test.beforeEach(async ({ authenticatedPage }) => {
    // Ensure we're starting from the admin dashboard
    await authenticatedPage.goto('/wp-admin');
    await expect(authenticatedPage).toHaveTitle(/Dashboard/);
  });

  test('should display plugin in plugins list', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/plugins.php');
    
    // Check that our plugin is listed
    await expect(authenticatedPage.locator('tr[data-plugin="woo-ai-assistant/woo-ai-assistant.php"]')).toBeVisible();
    
    // Check plugin details
    const pluginRow = authenticatedPage.locator('tr[data-plugin="woo-ai-assistant/woo-ai-assistant.php"]');
    await expect(pluginRow.locator('.plugin-title strong')).toContainText('Woo AI Assistant');
    await expect(pluginRow.locator('.plugin-description')).toContainText('AI-powered chatbot');
  });

  test('should show plugin is active', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/plugins.php');
    
    const pluginRow = authenticatedPage.locator('tr[data-plugin="woo-ai-assistant/woo-ai-assistant.php"]');
    
    // Plugin should be active (deactivate link should be visible)
    await expect(pluginRow.locator('.deactivate')).toBeVisible();
    
    // Should not show activate link
    await expect(pluginRow.locator('.activate')).not.toBeVisible();
  });

  test('should add admin menu item', async ({ authenticatedPage }) => {
    // Check that admin menu item is added
    await expect(authenticatedPage.locator('#adminmenu .menu-icon-generic:has-text("AI Assistant")')).toBeVisible();
    
    // Check submenu items
    const menuItem = authenticatedPage.locator('#adminmenu .menu-icon-generic:has-text("AI Assistant")');
    await menuItem.hover();
    
    await expect(authenticatedPage.locator('text=Dashboard')).toBeVisible();
    await expect(authenticatedPage.locator('text=Settings')).toBeVisible();
    await expect(authenticatedPage.locator('text=Conversations')).toBeVisible();
  });

  test('should create database tables on activation', async ({ authenticatedPage }) => {
    // Navigate to plugin dashboard to trigger any initialization
    await authenticatedPage.click('#adminmenu .menu-icon-generic:has-text("AI Assistant")');
    await authenticatedPage.waitForLoadState('networkidle');
    
    // Check that the dashboard loads successfully (implies tables exist)
    await expect(authenticatedPage).toHaveURL(/page=woo-ai-assistant/);
    await expect(authenticatedPage.locator('.woo-ai-dashboard')).toBeVisible();
  });

  test('should show settings page', async ({ authenticatedPage }) => {
    // Navigate to settings
    await authenticatedPage.click('#adminmenu .menu-icon-generic:has-text("AI Assistant")');
    await authenticatedPage.click('text=Settings');
    
    await expect(authenticatedPage).toHaveURL(/page=woo-ai-assistant-settings/);
    await expect(authenticatedPage.locator('h1')).toContainText('AI Assistant Settings');
    
    // Check for key settings sections
    await expect(authenticatedPage.locator('text=API Configuration')).toBeVisible();
    await expect(authenticatedPage.locator('text=Knowledge Base Settings')).toBeVisible();
    await expect(authenticatedPage.locator('text=Chat Behavior')).toBeVisible();
  });

  test('should handle deactivation gracefully', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/plugins.php');
    
    const pluginRow = authenticatedPage.locator('tr[data-plugin="woo-ai-assistant/woo-ai-assistant.php"]');
    
    // Deactivate plugin
    await pluginRow.locator('.deactivate a').click();
    await authenticatedPage.waitForLoadState('networkidle');
    
    // Should show activation notice
    await expect(authenticatedPage.locator('.notice-success')).toBeVisible();
    
    // Plugin should now show activate link
    await expect(pluginRow.locator('.activate')).toBeVisible();
    await expect(pluginRow.locator('.deactivate')).not.toBeVisible();
    
    // Admin menu should be removed
    await authenticatedPage.goto('/wp-admin');
    await expect(authenticatedPage.locator('#adminmenu .menu-icon-generic:has-text("AI Assistant")')).not.toBeVisible();
    
    // Re-activate for other tests
    await authenticatedPage.goto('/wp-admin/plugins.php');
    await pluginRow.locator('.activate a').click();
    await authenticatedPage.waitForLoadState('networkidle');
    
    // Verify re-activation
    await expect(authenticatedPage.locator('.notice-success')).toBeVisible();
    await expect(pluginRow.locator('.deactivate')).toBeVisible();
  });

  test('should check WordPress and WooCommerce compatibility', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/plugins.php');
    
    const pluginRow = authenticatedPage.locator('tr[data-plugin="woo-ai-assistant/woo-ai-assistant.php"]');
    
    // Should not show compatibility warnings
    await expect(pluginRow.locator('.plugin-version-author-uri')).not.toContainText('incompatible');
    await expect(pluginRow.locator('.plugin-version-author-uri')).not.toContainText('requires');
    
    // Check plugin works with current WooCommerce version
    await authenticatedPage.goto('/wp-admin/admin.php?page=woo-ai-assistant');
    
    // Should not show WooCommerce missing warnings
    await expect(authenticatedPage.locator('.notice-error:has-text("WooCommerce")')).not.toBeVisible();
  });

  test('should initialize default settings', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/admin.php?page=woo-ai-assistant-settings');
    
    // Check that default settings are set
    const enabledCheckbox = authenticatedPage.locator('#woo_ai_enabled');
    await expect(enabledCheckbox).toBeVisible();
    
    // Check default welcome message
    const welcomeMessage = authenticatedPage.locator('#woo_ai_welcome_message');
    await expect(welcomeMessage).toHaveValue(/Hello.*help/i);
    
    // Check default auto-index setting
    const autoIndex = authenticatedPage.locator('#woo_ai_auto_index');
    await expect(autoIndex).toBeChecked();
  });

  test('should handle plugin updates', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/plugins.php');
    
    const pluginRow = authenticatedPage.locator('tr[data-plugin="woo-ai-assistant/woo-ai-assistant.php"]');
    
    // Check current version is displayed
    await expect(pluginRow.locator('.plugin-version-author-uri')).toContainText('Version 1.0');
    
    // Should not show update available (since we're testing current version)
    await expect(pluginRow.locator('.plugin-update-tr')).not.toBeVisible();
  });

  test('should pass activation health checks', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/admin.php?page=woo-ai-assistant');
    
    // Look for any error notices on the dashboard
    await expect(authenticatedPage.locator('.notice-error')).not.toBeVisible();
    
    // Check for health status indicators
    await expect(authenticatedPage.locator('.health-check, .status-indicator')).toBeVisible();
    
    // Verify knowledge base initialization
    const kbStatus = authenticatedPage.locator('[data-testid="kb-status"]');
    if (await kbStatus.count() > 0) {
      await expect(kbStatus).not.toContainText('error');
    }
  });
});