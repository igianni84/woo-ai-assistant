/**
 * Chat Widget E2E Tests
 * 
 * Tests for the chat widget functionality, including initialization,
 * user interactions, and AI responses.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

const { test, expect } = require('../support/fixtures');

test.describe('Chat Widget Functionality', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to shop page where widget should be active
    await page.goto('/shop');
    await page.waitForLoadState('networkidle');
  });

  test('should initialize chat widget on shop page', async ({ page }) => {
    // Widget container should be present
    await expect(page.locator('[data-testid="woo-ai-chat-widget"]')).toBeVisible();
    
    // Chat trigger button should be visible
    await expect(page.locator('[data-testid="chat-trigger-button"]')).toBeVisible();
    
    // Chat window should be hidden initially
    await expect(page.locator('[data-testid="chat-window"]')).not.toBeVisible();
  });

  test('should open and close chat window', async ({ chatWidget }) => {
    // Initially closed
    await expect(chatWidget.window).not.toBeVisible();
    
    // Open chat
    await chatWidget.open();
    await expect(chatWidget.window).toBeVisible();
    
    // Should show welcome message
    await expect(chatWidget.messages.first()).toContainText(/Hello.*help/i);
    
    // Close chat
    await chatWidget.close();
    await expect(chatWidget.window).not.toBeVisible();
  });

  test('should display chat interface elements', async ({ chatWidget }) => {
    await chatWidget.open();
    
    // Chat header
    await expect(chatWidget.window.locator('[data-testid="chat-header"]')).toBeVisible();
    await expect(chatWidget.window.locator('[data-testid="chat-title"]')).toContainText(/AI Assistant/i);
    
    // Message area
    await expect(chatWidget.window.locator('[data-testid="chat-messages"]')).toBeVisible();
    
    // Input area
    await expect(chatWidget.input).toBeVisible();
    await expect(chatWidget.sendButton).toBeVisible();
    
    // Close button
    await expect(chatWidget.window.locator('[data-testid="chat-close-button"]')).toBeVisible();
  });

  test('should send and receive messages', async ({ chatWidget }) => {
    await chatWidget.open();
    
    const testMessage = 'Hello, I need help with products';
    
    // Send message
    await chatWidget.sendMessage(testMessage);
    
    // Verify user message appears
    await expect(chatWidget.messages.last()).toContainText(testMessage);
    await expect(chatWidget.messages.last()).toHaveAttribute('data-sender', 'user');
    
    // Wait for AI response
    await chatWidget.waitForResponse();
    
    // Verify AI response appears
    const aiMessage = chatWidget.messages.last();
    await expect(aiMessage).toHaveAttribute('data-sender', 'ai');
    await expect(aiMessage).not.toBeEmpty();
  });

  test('should handle product queries', async ({ chatWidget }) => {
    await chatWidget.open();
    
    // Ask about products
    await chatWidget.sendMessage('What products do you have?');
    await chatWidget.waitForResponse();
    
    // Should receive response about products
    const response = await chatWidget.messages.last().textContent();
    expect(response.toLowerCase()).toContain('product');
  });

  test('should show typing indicator', async ({ chatWidget, page }) => {
    await chatWidget.open();
    
    // Send a message
    await chatWidget.input.fill('Tell me about your store');
    await chatWidget.sendButton.click();
    
    // Typing indicator should appear briefly
    await expect(page.locator('[data-testid="typing-indicator"]')).toBeVisible({ timeout: 2000 });
    
    // Then disappear when response arrives
    await expect(page.locator('[data-testid="typing-indicator"]')).not.toBeVisible({ timeout: 10000 });
  });

  test('should handle empty messages', async ({ chatWidget }) => {
    await chatWidget.open();
    
    // Try to send empty message
    await chatWidget.input.clear();
    await chatWidget.sendButton.click();
    
    // Should not send empty message
    const messageCount = await chatWidget.messages.count();
    expect(messageCount).toBe(1); // Only welcome message
    
    // Input should still be focused
    await expect(chatWidget.input).toBeFocused();
  });

  test('should handle long messages', async ({ chatWidget }) => {
    await chatWidget.open();
    
    const longMessage = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '.repeat(10);
    
    await chatWidget.sendMessage(longMessage);
    
    // Message should be sent and displayed
    await expect(chatWidget.messages.last()).toContainText('Lorem ipsum');
    
    // Should still get a response
    await chatWidget.waitForResponse();
    await expect(chatWidget.messages.last()).toHaveAttribute('data-sender', 'ai');
  });

  test('should persist conversation during session', async ({ chatWidget }) => {
    await chatWidget.open();
    
    // Send first message
    await chatWidget.sendMessage('My first message');
    await chatWidget.waitForResponse();
    
    const firstCount = await chatWidget.messages.count();
    
    // Close and reopen chat
    await chatWidget.close();
    await chatWidget.open();
    
    // Messages should still be there
    const reopenCount = await chatWidget.messages.count();
    expect(reopenCount).toBe(firstCount);
    
    // Send another message
    await chatWidget.sendMessage('My second message');
    await chatWidget.waitForResponse();
    
    // Should have all messages
    const finalCount = await chatWidget.messages.count();
    expect(finalCount).toBeGreaterThan(firstCount);
  });

  test('should handle network errors gracefully', async ({ chatWidget, page }) => {
    await chatWidget.open();
    
    // Simulate network failure
    await page.route('**/wp-json/woo-ai-assistant/**', route => route.abort());
    
    await chatWidget.sendMessage('This should fail');
    
    // Should show error message
    await expect(page.locator('[data-testid="error-message"]')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('[data-testid="error-message"]')).toContainText(/error|failed/i);
    
    // Should allow retry
    await expect(page.locator('[data-testid="retry-button"]')).toBeVisible();
  });

  test('should be responsive on mobile', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Widget should still be visible
    await expect(page.locator('[data-testid="woo-ai-chat-widget"]')).toBeVisible();
    
    // Trigger button should be appropriately sized
    const triggerButton = page.locator('[data-testid="chat-trigger-button"]');
    await expect(triggerButton).toBeVisible();
    
    // Open chat
    await triggerButton.click();
    
    // Chat window should fill mobile screen appropriately
    const chatWindow = page.locator('[data-testid="chat-window"]');
    await expect(chatWindow).toBeVisible();
    
    // Should have mobile-appropriate styling
    await expect(chatWindow).toHaveClass(/mobile|responsive/);
  });

  test('should support keyboard navigation', async ({ chatWidget, page }) => {
    await chatWidget.open();
    
    // Tab navigation
    await page.keyboard.press('Tab');
    await expect(chatWidget.input).toBeFocused();
    
    // Send message with Enter
    await chatWidget.input.fill('Testing keyboard input');
    await page.keyboard.press('Enter');
    
    // Message should be sent
    await expect(chatWidget.messages.last()).toContainText('Testing keyboard input');
    
    // Escape should close chat
    await page.keyboard.press('Escape');
    await expect(chatWidget.window).not.toBeVisible();
  });

  test('should handle special characters and emojis', async ({ chatWidget }) => {
    await chatWidget.open();
    
    const specialMessage = 'Testing special chars: <>&"\'🎉🤖💬';
    
    await chatWidget.sendMessage(specialMessage);
    
    // Should display correctly without XSS issues
    await expect(chatWidget.messages.last()).toContainText('Testing special chars');
    await expect(chatWidget.messages.last()).toContainText('🎉🤖💬');
    
    // Should get response
    await chatWidget.waitForResponse();
    await expect(chatWidget.messages.last()).toHaveAttribute('data-sender', 'ai');
  });

  test('should load on different page types', async ({ page }) => {
    // Test on different WooCommerce pages
    const pagesToTest = [
      '/shop',
      '/cart', 
      '/checkout',
      '/my-account'
    ];
    
    for (const pagePath of pagesToTest) {
      await page.goto(pagePath);
      await page.waitForLoadState('networkidle');
      
      // Widget should be present
      await expect(page.locator('[data-testid="woo-ai-chat-widget"]')).toBeVisible();
    }
  });
});