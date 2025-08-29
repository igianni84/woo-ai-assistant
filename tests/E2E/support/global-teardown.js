/**
 * Playwright Global Teardown
 * 
 * Cleans up the WordPress environment after E2E testing, including removing
 * test data and resetting configurations.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Global teardown function executed after all tests
 * 
 * @param {Object} config - Playwright configuration
 */
async function globalTeardown(config) {
  console.log('🧹 Cleaning up E2E test environment...');
  
  const browser = await chromium.launch();
  const context = await browser.newContext();
  
  // Load authentication state if available
  const authStatePath = path.join(__dirname, '../../storage-state.json');
  if (fs.existsSync(authStatePath)) {
    const storageState = JSON.parse(fs.readFileSync(authStatePath, 'utf8'));
    await context.addCookies(storageState.cookies);
  }
  
  const page = await context.newPage();
  
  try {
    const baseURL = config.projects[0].use.baseURL;
    
    // Only clean up if CLEANUP_AFTER_TESTS is set
    if (process.env.CLEANUP_AFTER_TESTS === 'true') {
      console.log('🗑️ Cleaning up test data...');
      
      // Clean up test products
      await cleanupTestProducts(page, baseURL);
      
      // Clean up test users
      await cleanupTestUsers(page, baseURL);
      
      // Clean up test data from plugin
      await cleanupPluginTestData(page, baseURL);
    } else {
      console.log('ℹ️ Skipping cleanup (CLEANUP_AFTER_TESTS not set)');
    }
    
    // Clean up temporary files
    await cleanupTempFiles();
    
    console.log('✅ E2E test environment cleanup completed');
    
  } catch (error) {
    console.error('❌ Error during E2E test cleanup:', error);
    // Don't throw error in cleanup to avoid masking test failures
  } finally {
    await browser.close();
  }
}

/**
 * Clean up test products created during testing
 * 
 * @param {Object} page - Playwright page object
 * @param {string} baseURL - WordPress base URL
 */
async function cleanupTestProducts(page, baseURL) {
  console.log('📦 Cleaning up test products...');
  
  await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`);
  
  const testProducts = ['Test Product 1', 'Test Product 2'];
  
  for (const productName of testProducts) {
    try {
      // Find the product in the list
      const productRow = page.locator(`tr:has-text("${productName}")`);
      
      if (await productRow.count() > 0) {
        // Hover over the row to show action links
        await productRow.hover();
        
        // Click the Trash/Delete link
        const deleteLink = productRow.locator('span.trash a');
        if (await deleteLink.count() > 0) {
          await deleteLink.click();
          console.log(`✅ Deleted test product: ${productName}`);
        }
      }
    } catch (error) {
      console.warn(`⚠️ Could not delete test product ${productName}:`, error.message);
    }
  }
  
  // Empty trash if needed
  try {
    if (await page.locator('text="Empty Trash"').count() > 0) {
      await page.click('text="Empty Trash"');
      await page.waitForSelector('.notice', { timeout: 5000 });
    }
  } catch (error) {
    console.warn('⚠️ Could not empty product trash:', error.message);
  }
}

/**
 * Clean up test users created during testing
 * 
 * @param {Object} page - Playwright page object
 * @param {string} baseURL - WordPress base URL
 */
async function cleanupTestUsers(page, baseURL) {
  console.log('👥 Cleaning up test users...');
  
  await page.goto(`${baseURL}/wp-admin/users.php`);
  
  const testUsers = ['testcustomer'];
  
  for (const username of testUsers) {
    try {
      // Find the user in the list
      const userRow = page.locator(`tr:has-text("${username}")`);
      
      if (await userRow.count() > 0) {
        // Hover over the row to show action links
        await userRow.hover();
        
        // Click the Delete link
        const deleteLink = userRow.locator('span.delete a');
        if (await deleteLink.count() > 0) {
          await deleteLink.click();
          
          // Confirm deletion if confirmation page appears
          if (await page.locator('#submit').count() > 0) {
            await page.click('#submit');
          }
          
          console.log(`✅ Deleted test user: ${username}`);
        }
      }
    } catch (error) {
      console.warn(`⚠️ Could not delete test user ${username}:`, error.message);
    }
  }
}

/**
 * Clean up plugin-specific test data
 * 
 * @param {Object} page - Playwright page object
 * @param {string} baseURL - WordPress base URL
 */
async function cleanupPluginTestData(page, baseURL) {
  console.log('🔌 Cleaning up plugin test data...');
  
  try {
    // Navigate to plugin admin page
    await page.goto(`${baseURL}/wp-admin/admin.php?page=woo-ai-assistant`);
    
    // Check if there's a cleanup or reset option in the admin
    if (await page.locator('text="Reset Data"').count() > 0) {
      await page.click('text="Reset Data"');
      
      // Confirm if needed
      if (await page.locator('button:has-text("Confirm")').count() > 0) {
        await page.click('button:has-text("Confirm")');
      }
      
      console.log('✅ Plugin test data cleaned up');
    }
    
  } catch (error) {
    console.warn('⚠️ Could not clean up plugin test data:', error.message);
  }
}

/**
 * Clean up temporary files and storage
 */
async function cleanupTempFiles() {
  console.log('🗃️ Cleaning up temporary files...');
  
  try {
    // Remove authentication state file
    const authStatePath = path.join(__dirname, '../../storage-state.json');
    if (fs.existsSync(authStatePath)) {
      fs.unlinkSync(authStatePath);
      console.log('✅ Removed authentication state file');
    }
    
    // Clean up any other temporary files
    const tempDir = path.join(__dirname, '../../temp');
    if (fs.existsSync(tempDir)) {
      fs.rmSync(tempDir, { recursive: true, force: true });
      console.log('✅ Removed temporary directory');
    }
    
  } catch (error) {
    console.warn('⚠️ Could not clean up temporary files:', error.message);
  }
}

module.exports = globalTeardown;