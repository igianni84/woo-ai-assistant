/**
 * Admin Interface E2E Tests
 * 
 * Tests for the WordPress admin interface functionality of the
 * Woo AI Assistant plugin.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

const { test, expect } = require('../support/fixtures');

test.describe('Admin Interface', () => {
  test.beforeEach(async ({ authenticatedPage }) => {
    // Navigate to plugin dashboard
    await authenticatedPage.goto('/wp-admin/admin.php?page=woo-ai-assistant');
    await authenticatedPage.waitForLoadState('networkidle');
  });

  test('should display plugin dashboard', async ({ authenticatedPage }) => {
    // Check page title
    await expect(authenticatedPage).toHaveTitle(/AI Assistant/);
    
    // Check main heading
    await expect(authenticatedPage.locator('h1')).toContainText('Woo AI Assistant');
    
    // Check dashboard sections exist
    await expect(authenticatedPage.locator('.woo-ai-dashboard')).toBeVisible();
    await expect(authenticatedPage.locator('.dashboard-stats')).toBeVisible();
    await expect(authenticatedPage.locator('.recent-conversations')).toBeVisible();
  });

  test('should navigate to settings page', async ({ authenticatedPage }) => {
    // Click settings menu item
    await authenticatedPage.click('text=Settings');
    await authenticatedPage.waitForLoadState('networkidle');
    
    // Verify we're on settings page
    await expect(authenticatedPage).toHaveURL(/page=woo-ai-assistant-settings/);
    await expect(authenticatedPage.locator('h1')).toContainText('Settings');
    
    // Check settings form exists
    await expect(authenticatedPage.locator('form[action="options.php"]')).toBeVisible();
    
    // Check key settings fields
    await expect(authenticatedPage.locator('#woo_ai_enabled')).toBeVisible();
    await expect(authenticatedPage.locator('#woo_ai_api_key')).toBeVisible();
    await expect(authenticatedPage.locator('#woo_ai_welcome_message')).toBeVisible();
  });

  test('should update plugin settings', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/admin.php?page=woo-ai-assistant-settings');
    
    // Update welcome message
    const newMessage = 'Test welcome message from E2E test';
    await authenticatedPage.fill('#woo_ai_welcome_message', newMessage);
    
    // Enable auto-indexing
    await authenticatedPage.check('#woo_ai_auto_index');
    
    // Save settings
    await authenticatedPage.click('input[type="submit"]');
    await authenticatedPage.waitForSelector('.notice-success');
    
    // Verify success message
    await expect(authenticatedPage.locator('.notice-success')).toContainText('Settings saved');
    
    // Verify settings were saved
    await expect(authenticatedPage.locator('#woo_ai_welcome_message')).toHaveValue(newMessage);
    await expect(authenticatedPage.locator('#woo_ai_auto_index')).toBeChecked();
  });

  test('should display conversations log', async ({ authenticatedPage }) => {
    await authenticatedPage.click('text=Conversations');
    await authenticatedPage.waitForLoadState('networkidle');
    
    // Check page structure
    await expect(authenticatedPage).toHaveURL(/page=woo-ai-assistant-conversations/);
    await expect(authenticatedPage.locator('h1')).toContainText('Conversations');
    
    // Check conversations table or empty state
    const conversationsTable = authenticatedPage.locator('.wp-list-table');
    const emptyState = authenticatedPage.locator('.no-conversations');
    
    // Should have either conversations table or empty state
    await expect(conversationsTable.or(emptyState)).toBeVisible();
    
    // If conversations exist, check table structure
    if (await conversationsTable.count() > 0) {
      await expect(conversationsTable.locator('th')).toContainText(['User', 'Messages', 'Date']);
    }
  });

  test('should show knowledge base status', async ({ authenticatedPage }) => {
    // Check knowledge base section exists
    const kbSection = authenticatedPage.locator('.knowledge-base-status');
    if (await kbSection.count() > 0) {
      await expect(kbSection).toBeVisible();
      
      // Check for status indicators
      await expect(kbSection.locator('.status-indicator')).toBeVisible();
      
      // Check for indexing controls
      const indexButton = kbSection.locator('button:has-text("Index Now")');
      if (await indexButton.count() > 0) {
        await expect(indexButton).toBeVisible();
      }
    }
  });

  test('should handle knowledge base indexing', async ({ authenticatedPage }) => {
    const indexButton = authenticatedPage.locator('button:has-text("Index Now")');
    
    if (await indexButton.count() > 0) {
      // Click index button
      await indexButton.click();
      
      // Should show loading state
      await expect(authenticatedPage.locator('.indexing-progress')).toBeVisible();
      
      // Wait for completion (with timeout)
      await authenticatedPage.waitForSelector('.indexing-complete, .indexing-error', {
        timeout: 30000
      });
      
      // Should show completion message
      const completionMessage = authenticatedPage.locator('.indexing-complete, .indexing-error');
      await expect(completionMessage).toBeVisible();
    }
  });

  test('should display plugin help documentation', async ({ authenticatedPage }) => {
    // Look for help tab or documentation link
    const helpTab = authenticatedPage.locator('a:has-text("Help")');
    const docsLink = authenticatedPage.locator('a:has-text("Documentation")');
    
    if (await helpTab.count() > 0) {
      await helpTab.click();
      await expect(authenticatedPage.locator('.help-content')).toBeVisible();
    } else if (await docsLink.count() > 0) {
      // Check that documentation link exists
      await expect(docsLink).toBeVisible();
      await expect(docsLink).toHaveAttribute('href', /docs|documentation/);
    }
  });

  test('should handle plugin deactivation warning', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/wp-admin/plugins.php');
    
    const pluginRow = authenticatedPage.locator('tr[data-plugin="woo-ai-assistant/woo-ai-assistant.php"]');
    
    // Hover to show deactivate link
    await pluginRow.hover();
    
    // Click deactivate (but handle any confirmation)
    const deactivateLink = pluginRow.locator('.deactivate a');
    await deactivateLink.click();
    
    // Check for deactivation confirmation or immediate deactivation
    const confirmDialog = authenticatedPage.locator('.deactivation-feedback');
    if (await confirmDialog.count() > 0) {
      // If there's a feedback form, skip it
      const skipButton = confirmDialog.locator('button:has-text("Skip")');
      if (await skipButton.count() > 0) {
        await skipButton.click();
      }
    }
    
    // Should show success notice
    await expect(authenticatedPage.locator('.notice-success')).toBeVisible();
    
    // Plugin should show activate link
    await expect(pluginRow.locator('.activate')).toBeVisible();
    
    // Reactivate for other tests
    await pluginRow.locator('.activate a').click();
    await authenticatedPage.waitForSelector('.notice-success');
  });

  test('should validate admin permissions', async ({ authenticatedPage, page }) => {
    // Test with non-admin user
    const customerPage = await page.context().newPage();
    
    // Create and login as customer
    await customerPage.goto('/wp-login.php');
    await customerPage.fill('#user_login', 'testcustomer');
    await customerPage.fill('#user_pass', 'testpass123');
    await customerPage.click('#wp-submit');
    await customerPage.waitForLoadState('networkidle');
    
    // Try to access admin page
    await customerPage.goto('/wp-admin/admin.php?page=woo-ai-assistant');
    
    // Should be redirected or show permission error
    const currentUrl = customerPage.url();
    const hasPermissionError = await customerPage.locator('text=You do not have sufficient permissions').count() > 0;
    const isRedirected = !currentUrl.includes('page=woo-ai-assistant');
    
    expect(hasPermissionError || isRedirected).toBeTruthy();
    
    await customerPage.close();
  });

  test('should handle AJAX requests correctly', async ({ authenticatedPage }) => {
    // Monitor network requests
    const ajaxRequests = [];
    authenticatedPage.on('request', request => {
      if (request.url().includes('admin-ajax.php')) {
        ajaxRequests.push(request);
      }
    });
    
    // Trigger an AJAX action (if available)
    const ajaxButton = authenticatedPage.locator('button[data-action]');
    if (await ajaxButton.count() > 0) {
      await ajaxButton.first().click();
      
      // Wait for AJAX request
      await authenticatedPage.waitForTimeout(2000);
      
      // Should have made AJAX request
      expect(ajaxRequests.length).toBeGreaterThan(0);
      
      // Check for response feedback
      const feedbackElement = authenticatedPage.locator('.ajax-feedback, .notice');
      await expect(feedbackElement).toBeVisible();
    }
  });
});