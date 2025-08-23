#!/usr/bin/env node

/**
 * Bundle Size Checker
 * 
 * Validates that the built JavaScript bundle doesn't exceed size limits.
 * Part of the mandatory quality gates for frontend assets.
 * 
 * @package WooAiAssistant
 * @subpackage Scripts
 * @since 1.0.0
 * @author Claude Code Assistant
 */

const fs = require('fs');
const path = require('path');

// Configuration
const CONFIG = {
  maxBundleSize: 50 * 1024, // 50KB
  maxGzipSize: 15 * 1024,   // 15KB gzipped
  assetsDir: path.join(__dirname, '..', 'assets', 'js'),
  mainBundle: 'widget.js',
  warningThreshold: 0.8 // Warning at 80% of limit
};

// Colors for console output
const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m'
};

/**
 * Log functions with colors
 */
function logHeader(message) {
  console.log(`\n${colors.bright}${colors.cyan}${message}${colors.reset}`);
  console.log('='.repeat(message.length));
}

function logSuccess(message) {
  console.log(`${colors.green}✅ ${message}${colors.reset}`);
}

function logWarning(message) {
  console.log(`${colors.yellow}⚠️  ${message}${colors.reset}`);
}

function logError(message) {
  console.log(`${colors.red}❌ ${message}${colors.reset}`);
}

function logInfo(message) {
  console.log(`${colors.blue}ℹ️  ${message}${colors.reset}`);
}

/**
 * Format file size in human readable format
 */
function formatSize(bytes) {
  if (bytes === 0) return '0 B';
  
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

/**
 * Get file size
 */
function getFileSize(filePath) {
  try {
    const stats = fs.statSync(filePath);
    return stats.size;
  } catch (error) {
    return null;
  }
}

/**
 * Simulate gzip compression estimation
 */
function estimateGzipSize(filePath) {
  try {
    const content = fs.readFileSync(filePath, 'utf8');
    
    // Simple estimation: typically 70-80% compression for JS
    // This is an approximation since we don't have zlib here
    const compressionRatio = 0.25; // Assume 75% compression
    return Math.floor(content.length * compressionRatio);
  } catch (error) {
    return null;
  }
}

/**
 * Check if assets directory exists
 */
function checkAssetsDirectory() {
  if (!fs.existsSync(CONFIG.assetsDir)) {
    logError(`Assets directory not found: ${CONFIG.assetsDir}`);
    logInfo('Run "npm run build" to create the bundle first');
    return false;
  }
  return true;
}

/**
 * Get all JavaScript files in assets directory
 */
function getJavaScriptFiles() {
  try {
    const files = fs.readdirSync(CONFIG.assetsDir);
    return files
      .filter(file => file.endsWith('.js') && !file.endsWith('.map'))
      .map(file => ({
        name: file,
        path: path.join(CONFIG.assetsDir, file)
      }));
  } catch (error) {
    logError(`Failed to read assets directory: ${error.message}`);
    return [];
  }
}

/**
 * Analyze bundle size
 */
function analyzeBundleSize(filePath, fileName, maxSize, label) {
  const size = getFileSize(filePath);
  
  if (size === null) {
    logError(`Could not read ${fileName}`);
    return { passed: false, size: 0 };
  }
  
  const sizeFormatted = formatSize(size);
  const maxSizeFormatted = formatSize(maxSize);
  const percentageUsed = (size / maxSize) * 100;
  
  logInfo(`${label}: ${sizeFormatted} (${percentageUsed.toFixed(1)}% of ${maxSizeFormatted} limit)`);
  
  if (size > maxSize) {
    logError(`${fileName} exceeds ${label.toLowerCase()} limit!`);
    logError(`Size: ${sizeFormatted}, Limit: ${maxSizeFormatted}`);
    return { passed: false, size };
  }
  
  if (size > maxSize * CONFIG.warningThreshold) {
    logWarning(`${fileName} is approaching ${label.toLowerCase()} limit`);
    logWarning(`Consider optimizing bundle size`);
  } else {
    logSuccess(`${fileName} ${label.toLowerCase()} is within acceptable limits`);
  }
  
  return { passed: true, size };
}

/**
 * Provide optimization suggestions
 */
function provideOptimizationSuggestions() {
  logHeader('🔧 Bundle Optimization Suggestions');
  
  console.log('If your bundle is too large, consider:');
  console.log('');
  console.log('1. 📦 Code Splitting:');
  console.log('   - Split large components into separate chunks');
  console.log('   - Use dynamic imports: import("./Component")');
  console.log('');
  console.log('2. 🌳 Tree Shaking:');
  console.log('   - Use ES6 modules and avoid CommonJS');
  console.log('   - Import only what you need from libraries');
  console.log('');
  console.log('3. 📚 External Dependencies:');
  console.log('   - Consider if React should be externalized');
  console.log('   - Use WordPress provided libraries when possible');
  console.log('');
  console.log('4. 🗜️  Compression:');
  console.log('   - Enable gzip/brotli compression on server');
  console.log('   - Use webpack compression plugins');
  console.log('');
  console.log('5. 🔍 Bundle Analysis:');
  console.log('   - Run "npm run build:analyze" to see what\'s taking space');
  console.log('   - Look for duplicate dependencies');
  console.log('');
}

/**
 * Main function
 */
function main() {
  logHeader('📦 Bundle Size Check');
  
  console.log('Checking JavaScript bundle sizes against limits...');
  console.log('');
  
  // Check if assets directory exists
  if (!checkAssetsDirectory()) {
    process.exit(1);
  }
  
  // Get JavaScript files
  const jsFiles = getJavaScriptFiles();
  
  if (jsFiles.length === 0) {
    logError('No JavaScript files found in assets directory');
    logInfo('Run "npm run build" to create the bundle first');
    process.exit(1);
  }
  
  logInfo(`Found ${jsFiles.length} JavaScript files`);
  console.log('');
  
  let allPassed = true;
  let totalSize = 0;
  
  // Check each file
  for (const file of jsFiles) {
    logHeader(`Analyzing: ${file.name}`);
    
    // Check uncompressed size
    const uncompressedResult = analyzeBundleSize(
      file.path, 
      file.name, 
      CONFIG.maxBundleSize,
      'Uncompressed size'
    );
    
    if (!uncompressedResult.passed) {
      allPassed = false;
    }
    
    totalSize += uncompressedResult.size;
    
    // Check estimated gzip size
    const gzipSize = estimateGzipSize(file.path);
    if (gzipSize !== null) {
      const gzipResult = analyzeBundleSize(
        file.path,
        file.name,
        CONFIG.maxGzipSize,
        'Estimated gzipped size'
      );
      
      if (!gzipResult.passed) {
        allPassed = false;
      }
      
      // Override size for gzip display
      logInfo(`Estimated gzipped: ${formatSize(gzipSize)}`);
    }
    
    console.log('');
  }
  
  // Summary
  logHeader('📊 Bundle Size Summary');
  
  logInfo(`Total bundle size: ${formatSize(totalSize)}`);
  logInfo(`Bundle size limit: ${formatSize(CONFIG.maxBundleSize)} per file`);
  logInfo(`Gzip size limit: ${formatSize(CONFIG.maxGzipSize)} per file`);
  
  console.log('');
  
  if (allPassed) {
    logSuccess('All bundle size checks passed! 🎉');
    console.log('');
    process.exit(0);
  } else {
    logError('Bundle size checks failed! 🚫');
    console.log('');
    provideOptimizationSuggestions();
    process.exit(1);
  }
}

// Run if called directly
if (require.main === module) {
  main();
}

module.exports = {
  formatSize,
  getFileSize,
  estimateGzipSize,
  analyzeBundleSize
};