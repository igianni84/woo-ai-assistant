# Woo AI Assistant - Development Configuration System

This document explains how to use the development configuration system for the Woo AI Assistant plugin.

## Quick Setup

1. **Copy the template file:**
   ```bash
   cp .env.development .env.development.local
   ```

2. **Edit the configuration:**
   ```bash
   # Open .env.development.local and add your actual API keys
   nano .env.development.local
   ```

3. **The plugin will automatically detect development mode and use your configuration.**

## How It Works

The development configuration system automatically:

- **Detects Development Environment:** Automatically detects localhost, MAMP, XAMPP, etc.
- **Bypasses License Validation:** Any license key is accepted as valid in development mode
- **Loads Development API Keys:** Uses API keys from `.env.development` file
- **Enables Advanced Features:** Grants Unlimited plan features for testing
- **Provides Development Logging:** Enhanced debug logging when enabled

## Configuration Hierarchy

The system checks for configuration in this order:

1. **Development Environment Variables** (highest priority in dev mode)
2. **Regular Environment Variables**
3. **WordPress Admin Settings**
4. **Legacy Options** (for backward compatibility)

## Environment Variables

### Core Development Settings

```env
# Enable development mode
WOO_AI_DEVELOPMENT_MODE=true

# Enable debug logging
WOO_AI_ASSISTANT_DEBUG=true

# Development license key (any value accepted)
WOO_AI_DEVELOPMENT_LICENSE_KEY=dev-license-12345
```

### API Keys

```env
# OpenRouter for AI chat
OPENROUTER_API_KEY=your-openrouter-key

# OpenAI for embeddings
OPENAI_API_KEY=your-openai-key

# Pinecone for vector database
PINECONE_API_KEY=your-pinecone-key
PINECONE_ENVIRONMENT=development
PINECONE_INDEX_NAME=woo-ai-assistant-dev

# Google/Gemini (alternative)
GOOGLE_API_KEY=your-google-key
```

### Development Features

```env
# Use dummy data instead of real API calls
WOO_AI_USE_DUMMY_DATA=true

# Mock API calls for testing
WOO_AI_MOCK_API_CALLS=false

# Enhanced debug logging
WOO_AI_ENHANCED_DEBUG=true

# Development server URL
WOO_AI_DEVELOPMENT_SERVER_URL=http://localhost:3000
```

### Performance Settings

```env
# Reduced timeouts for development
WOO_AI_DEV_API_TIMEOUT=10

# Shorter cache TTL
WOO_AI_DEV_CACHE_TTL=60

# Limit items processed
WOO_AI_DEV_MAX_ITEMS=10
```

## License Bypass

In development mode, the plugin automatically:

- Accepts any license key as valid
- Grants Unlimited plan features
- Skips server validation
- Allows unlimited conversations and indexing

## Admin Interface

When development mode is active, you'll see:

- **Development Notice:** Orange notice in admin indicating dev mode is active
- **All Features Unlocked:** Access to all Unlimited plan features
- **Debug Information:** Additional logging and status information
- **License Flexibility:** Any license key is accepted

## File Structure

```
woo-ai-assistant/
├── .env.development          # Template file (committed to repo)
├── .env.development.local    # Your personal config (gitignored)
├── src/Common/
│   ├── DevelopmentConfig.php # Development configuration manager
│   └── ApiConfiguration.php  # Updated to use dev config
└── src/Api/
    └── LicenseManager.php    # Updated to bypass license in dev mode
```

## Security Notes

- **Never commit real API keys** - use `.env.development.local` for actual keys
- **Development mode is for local use only** - not for production
- **API keys are loaded only in development mode** - safe for production
- **License bypass only works in development** - production validation unchanged

## Testing Scenarios

### Test License Bypass
```php
// In development mode, any license key is accepted
$licenseManager = LicenseManager::getInstance();
$result = $licenseManager->setLicenseKey('any-key-here');
// Returns: valid=true, plan=unlimited
```

### Test API Configuration
```php
// Development API keys take priority
$apiConfig = ApiConfiguration::getInstance();
$openaiKey = $apiConfig->getApiKey('openai');
// Returns key from .env.development if in dev mode
```

### Test Feature Access
```php
// All features unlocked in development
$licenseManager = LicenseManager::getInstance();
$canUseFeature = $licenseManager->isFeatureEnabled('advanced_ai');
// Returns true in development mode
```

## Debugging

### Check Development Status
```php
$devConfig = DevelopmentConfig::getInstance();
$isDev = $devConfig->isDevelopmentMode();
$config = $devConfig->exportConfigForDebug();
```

### View Configuration
The development configuration can be exported safely (without sensitive data) for debugging purposes.

## Common Issues

### Development Mode Not Detected
- Ensure you're running on localhost, MAMP, XAMPP, or similar
- Set `WOO_AI_DEVELOPMENT_MODE=true` explicitly in your environment file
- Check that WP_DEBUG is enabled in wp-config.php

### API Keys Not Loading
- Verify the `.env.development` file exists and is readable
- Check file permissions
- Ensure variable names match exactly (case-sensitive)
- Review error logs for parsing issues

### License Still Requiring Validation
- Confirm development mode is active (check admin notice)
- Clear any cached license data
- Verify ApiConfiguration is detecting development mode

## Production Safety

The development configuration system is designed to be safe for production:

- Development features are completely disabled in production
- Real license validation occurs in production
- No performance impact when development mode is disabled
- Environment detection prevents accidental dev mode activation

---

**Ready to develop!** The system automatically detects your local environment and provides a seamless development experience while maintaining production security.