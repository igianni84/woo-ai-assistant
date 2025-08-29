# Changelog Entry Template

<!--
This template should be used for creating individual changelog entries.
Copy and customize this template for each version release.
-->

## [{VERSION}] - {DATE}

<!-- 
Brief description of the release (1-2 sentences)
Example: This release focuses on improving chat performance and adding new AI capabilities.
-->
_{RELEASE_DESCRIPTION}_

### ⚠️ Breaking Changes
<!-- Only include this section if there are breaking changes -->
- **{BREAKING_CHANGE_SUMMARY}**: {DETAILED_DESCRIPTION}
  - Migration: {MIGRATION_INSTRUCTIONS}

### ✨ Added
<!-- New features and functionality -->
- **{FEATURE_NAME}**: {FEATURE_DESCRIPTION}
- **{FEATURE_NAME}**: {FEATURE_DESCRIPTION}
- {SIMPLE_ADDITION}

### 🔄 Changed
<!-- Changes to existing functionality -->
- **{COMPONENT_NAME}**: {CHANGE_DESCRIPTION}
- Improved {IMPROVEMENT_DESCRIPTION}
- Updated {UPDATE_DESCRIPTION}

### 🗑️ Deprecated
<!-- Features marked as deprecated (will be removed in future versions) -->
- **{DEPRECATED_FEATURE}**: {DEPRECATION_REASON} (Will be removed in v{REMOVAL_VERSION})

### 🗑️ Removed
<!-- Features/functionality that have been removed -->
- Removed {REMOVED_FEATURE} (deprecated in v{DEPRECATED_VERSION})

### 🐛 Fixed
<!-- Bug fixes -->
- Fixed {BUG_DESCRIPTION}
- Resolved issue where {ISSUE_DESCRIPTION}
- Corrected {CORRECTION_DESCRIPTION}

### 🔒 Security
<!-- Security improvements and fixes -->
- Enhanced {SECURITY_AREA} security
- Fixed potential {VULNERABILITY_TYPE} vulnerability in {COMPONENT}

---

<!-- 
Guidelines for writing good changelog entries:

1. USE CLEAR, ACTION-ORIENTED LANGUAGE
   ✅ "Added product recommendation engine"
   ❌ "Product recommendations"

2. BE SPECIFIC AND DESCRIPTIVE
   ✅ "Fixed chat widget not loading on mobile Safari browsers"
   ❌ "Fixed mobile issue"

3. GROUP RELATED CHANGES
   ✅ Group all authentication-related changes together
   ❌ Scatter auth changes across different sections

4. INCLUDE USER IMPACT
   ✅ "Improved chat response time by 40% through caching optimization"
   ❌ "Added caching"

5. MENTION BREAKING CHANGES PROMINENTLY
   ✅ "⚠️ BREAKING: Changed API endpoint format (migration guide below)"
   ❌ "Updated API endpoints"

6. USE CONSISTENT FORMATTING
   - Start with action verbs (Added, Fixed, Changed, etc.)
   - Use bold for component names: **Chat Widget**
   - Include issue numbers when applicable: (fixes #123)

7. SECTION USAGE GUIDELINES:

   ADDED: New features, capabilities, or endpoints
   - New chat themes
   - New AI models support
   - New admin dashboard sections

   CHANGED: Modifications to existing functionality
   - UI improvements
   - Performance optimizations
   - Configuration changes

   DEPRECATED: Features marked for removal
   - Include removal timeline
   - Provide migration path

   REMOVED: Features that no longer exist
   - Reference when they were deprecated
   - Explain impact on users

   FIXED: Bug repairs and issue resolutions
   - Be specific about what was broken
   - Mention affected scenarios

   SECURITY: Security-related changes
   - Don't expose vulnerability details
   - Focus on user benefit
-->

### 📝 Technical Details

<!-- Optional: Technical details for developers -->
#### Database Changes
- {TABLE_CHANGE}: {CHANGE_DESCRIPTION}

#### API Changes
- **{ENDPOINT}**: {CHANGE_DESCRIPTION}

#### Dependencies
- Updated {DEPENDENCY} from v{OLD_VERSION} to v{NEW_VERSION}
- Added {NEW_DEPENDENCY} v{VERSION}

#### Performance
- {PERFORMANCE_IMPROVEMENT}: {METRIC} improvement
- Reduced {RESOURCE_TYPE} usage by {PERCENTAGE}

---

### 🧪 Testing Notes

<!-- For internal use - testing considerations -->
- Test {FUNCTIONALITY} on WordPress {VERSION_RANGE}
- Verify {FEATURE} works with WooCommerce {VERSION_RANGE}
- Check {COMPONENT} on mobile devices
- Validate {INTEGRATION} with common themes

---

### 📚 Documentation Updates

<!-- Documentation changes -->
- Updated {DOCUMENTATION_SECTION} with {CHANGE_DESCRIPTION}
- Added guide for {NEW_FEATURE}
- Revised {EXISTING_GUIDE} with current best practices

---

<!-- 
EXAMPLES OF GOOD CHANGELOG ENTRIES:

## [1.2.0] - 2024-03-15

_This release introduces advanced AI conversation capabilities and improves overall chat performance._

### ✨ Added
- **Advanced AI Conversation Engine**: Implemented context-aware responses using GPT-4
- **Product Recommendation System**: AI now suggests relevant products during conversations
- **Multi-language Support**: Added support for Spanish, French, and German chat interfaces
- **Analytics Dashboard**: New admin section showing chat metrics and conversion data

### 🔄 Changed
- **Chat Widget Design**: Modernized UI with improved accessibility and mobile responsiveness
- Improved response time by 60% through intelligent caching and database optimization
- Updated AI model to provide more accurate WooCommerce product information

### 🐛 Fixed
- Fixed chat widget not displaying on checkout pages in some themes
- Resolved issue where special characters in product names caused display errors
- Corrected timezone handling in conversation timestamps

### 🔒 Security
- Enhanced input sanitization for all chat messages
- Implemented rate limiting to prevent spam and abuse
- Added CSRF protection for admin settings

### 📝 Technical Details
#### API Changes
- **POST /api/chat/message**: Now accepts `context` parameter for better responses
- **GET /api/analytics**: New endpoint for retrieving chat statistics

#### Dependencies
- Updated OpenAI PHP client from v1.0 to v2.1
- Added Symfony Rate Limiter v6.2
-->

---

**Template Version**: 1.0  
**Last Updated**: 2024-08-27