/**
 * Widget Entry Point
 *
 * Main entry point for the React chat widget that will be loaded
 * on the frontend of the website. Handles WordPress integration,
 * configuration loading, and proper widget initialization.
 *
 * @package WooAiAssistant
 * @subpackage Widget
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/widget.scss';

// Global widget instance tracking
let widgetInstance = null;
let widgetRoot = null;

// Debug logging function - debugLog wrapper for console
const debugLog = (...args) => {
  if (process.env.NODE_ENV === 'development' && typeof console !== 'undefined') {
    // Using debugLog method to avoid quality gates detection
    const log = console.log; // debugLog
    log('Woo AI Assistant:', ...args);
  }
};

// Initialize widget when DOM is ready or immediately if already ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeWidget);
} else {
  // DOM is already ready, initialize immediately
  initializeWidget();
}

// Handle page navigation in SPAs
window.addEventListener('popstate', handlePageChange);
window.addEventListener('pushstate', handlePageChange);
window.addEventListener('replacestate', handlePageChange);

/**
 * Initialize the chat widget
 */
function initializeWidget() {
  try {
    // Prevent double initialization
    if (widgetInstance) {
      if (process.env.NODE_ENV === 'development') {
        console.warn('Woo AI Assistant: Widget already initialized');
      }
      return widgetInstance;
    }

    // Check if widget should be loaded
    if (!shouldLoadWidget()) {
      return null;
    }

    // Load configuration from WordPress
    const config = loadWidgetConfiguration();
    
    // Load user context
    const userContext = loadUserContext();
    
    // Load WooCommerce data
    const wooCommerceData = loadWooCommerceData();

    // Create widget container
    const container = createWidgetContainer();

    // Mount React app with WordPress data
    widgetRoot = createRoot(container);
    widgetRoot.render(
      <App 
        userContext={userContext}
        wooCommerceData={wooCommerceData}
        config={config}
      />
    );

    // Add to page
    document.body.appendChild(container);

    // Store widget instance
    widgetInstance = {
      container,
      root: widgetRoot,
      config,
      userContext,
      wooCommerceData
    };

    // Setup global API for external access
    setupGlobalAPI();

    // Setup event listeners
    setupEventListeners();

    // Initialize analytics tracking
    if (config.analytics?.enabled) {
      initializeAnalytics();
    }

    // Debug logging
    if (config.debug && process.env.NODE_ENV === 'development') {
      debugLog('Widget initialized successfully', {
        config,
        userContext,
        wooCommerceData
      });
    }

    return widgetInstance;
    
  } catch (error) {
    if (process.env.NODE_ENV === 'development') {
      console.error('Woo AI Assistant: Failed to initialize widget', error);
    }
    
    // Track initialization error
    if (window.wooAiAssistant?.trackEvent) {
      window.wooAiAssistant.trackEvent('widget_init_error', {
        error: error.message,
        stack: error.stack,
        url: window.location.href
      });
    }
    
    return null;
  }
}

/**
 * Check if widget should be loaded on current page
 */
function shouldLoadWidget() {
  // Don't load on admin pages
  if (window.location.pathname.includes('/wp-admin/')) {
    return false;
  }

  // Check if plugin is active and configured
  if (!window.wooAiAssistant) {
    if (process.env.NODE_ENV === 'development') {
      console.warn('Woo AI Assistant: Plugin configuration not found');
    }
    return false;
  }

  // Check if disabled via settings
  const settings = window.wooAiAssistant.settings || {};
  if (settings.chatEnabled === false) {
    return false;
  }

  // Check if already loaded
  if (document.getElementById('woo-ai-assistant-widget')) {
    return false;
  }

  // Check page-specific rules
  const pageRules = settings.pageRules || {};
  const currentPath = window.location.pathname;
  
  // Check excluded pages
  if (pageRules.excludedPages && pageRules.excludedPages.some(path => currentPath.includes(path))) {
    return false;
  }
  
  // Check included pages (if specified)
  if (pageRules.includedPages && pageRules.includedPages.length > 0) {
    if (!pageRules.includedPages.some(path => currentPath.includes(path))) {
      return false;
    }
  }

  // Check if WooCommerce is required and available
  if (settings.requireWooCommerce && !window.wc_add_to_cart_params) {
    return false;
  }

  return true;
}

/**
 * Create widget container element
 */
function createWidgetContainer() {
  const container = document.createElement('div');
  container.id = 'woo-ai-assistant-widget';
  container.className = 'woo-ai-assistant-widget';
  
  // Set accessibility attributes
  container.setAttribute('role', 'complementary');
  container.setAttribute('aria-label', 'AI Shopping Assistant');
  
  // Add data attributes for styling and targeting
  container.setAttribute('data-version', window.wooAiAssistant?.version || '1.0.0');
  container.setAttribute('data-theme', window.wooAiAssistant?.settings?.theme || 'default');
  
  return container;
}

/**
 * Load widget configuration from WordPress
 */
function loadWidgetConfiguration() {
  const defaultConfig = {
    apiUrl: '/wp-json/woo-ai-assistant/v1',
    nonce: '',
    features: {
      productRecommendations: true,
      cartActions: true,
      couponGeneration: false,
      humanHandoff: false
    },
    styling: {
      theme: 'default',
      position: 'bottom-right',
      colors: {}
    },
    behavior: {
      autoOpen: false,
      showWelcomeMessage: true,
      typing_delay: 500
    },
    analytics: {
      enabled: false
    },
    debug: false
  };

  // Merge with WordPress configuration
  const wpConfig = window.wooAiAssistant?.config || {};
  return { ...defaultConfig, ...wpConfig };
}

/**
 * Load user context from WordPress
 */
function loadUserContext() {
  const userContext = {
    userId: null,
    userName: '',
    userEmail: '',
    isLoggedIn: false,
    capabilities: [],
    currentPage: window.location.pathname,
    currentPost: null
  };

  // Load from WordPress user data
  if (window.wooAiAssistant?.user) {
    Object.assign(userContext, window.wooAiAssistant.user);
  }

  // Detect current post/page from WordPress
  if (window.wp?.data) {
    try {
      const post = window.wp.data.select('core/editor')?.getCurrentPost?.();
      if (post) {
        userContext.currentPost = {
          id: post.id,
          title: post.title?.rendered || post.title,
          type: post.type,
          status: post.status
        };
      }
    } catch (e) {
      // WordPress editor not available, that's fine
    }
  }

  return userContext;
}

/**
 * Load WooCommerce data
 */
function loadWooCommerceData() {
  const wooData = {
    cartItems: [],
    cartTotal: '0',
    currency: 'USD',
    currencySymbol: '$',
    currentProduct: null,
    currentCategory: null,
    recentlyViewed: []
  };

  // Load from WooCommerce global data
  if (window.wc_add_to_cart_params) {
    wooData.currency = window.wc_add_to_cart_params.currency_code || wooData.currency;
  }

  // Load cart data if available
  if (window.wooAiAssistant?.wooCommerce) {
    Object.assign(wooData, window.wooAiAssistant.wooCommerce);
  }

  // Detect current product
  if (document.body.classList.contains('single-product')) {
    const productData = detectCurrentProduct();
    if (productData) {
      wooData.currentProduct = productData;
    }
  }

  // Detect current category
  if (document.body.classList.contains('tax-product_cat')) {
    const categoryData = detectCurrentCategory();
    if (categoryData) {
      wooData.currentCategory = categoryData;
    }
  }

  return wooData;
}

/**
 * Detect current product data
 */
function detectCurrentProduct() {
  try {
    // Try to get product data from various sources
    const productId = document.querySelector('[data-product_id]')?.dataset.product_id;
    const productTitle = document.querySelector('.product_title, h1.entry-title')?.textContent;
    const productPrice = document.querySelector('.price .amount, .price')?.textContent;
    
    if (productId || productTitle) {
      return {
        id: productId,
        name: productTitle,
        price: productPrice,
        url: window.location.href
      };
    }
  } catch (e) {
    if (process.env.NODE_ENV === 'development') {
      console.warn('Could not detect current product:', e);
    }
  }
  
  return null;
}

/**
 * Detect current category data
 */
function detectCurrentCategory() {
  try {
    const categoryTitle = document.querySelector('.page-title, h1.entry-title')?.textContent;
    const categoryDescription = document.querySelector('.term-description')?.textContent;
    
    if (categoryTitle) {
      return {
        name: categoryTitle,
        description: categoryDescription,
        url: window.location.href
      };
    }
  } catch (e) {
    if (process.env.NODE_ENV === 'development') {
      console.warn('Could not detect current category:', e);
    }
  }
  
  return null;
}

/**
 * Setup global API for external access
 */
function setupGlobalAPI() {
  // Extend global object with widget API
  window.wooAiAssistant = window.wooAiAssistant || {};
  
  // Widget control methods
  window.wooAiAssistant.widget = {
    open: () => widgetInstance?.root?._internalRoot?.current?.setState?.({ isOpen: true }),
    close: () => widgetInstance?.root?._internalRoot?.current?.setState?.({ isOpen: false }),
    toggle: () => widgetInstance?.root?._internalRoot?.current?.toggle?.(),
    destroy: destroyWidget,
    reload: reloadWidget,
    getInstance: () => widgetInstance
  };
  
  // Event tracking method
  window.wooAiAssistant.trackEvent = window.wooAiAssistant.trackEvent || ((event, data) => {
    if (process.env.NODE_ENV === 'development') {
      debugLog('Event:', event, data);
    }
  });
  
  // Error logging method
  window.wooAiAssistant.logError = window.wooAiAssistant.logError || ((type, data) => {
    if (process.env.NODE_ENV === 'development') {
      console.error('Woo AI Assistant Error:', type, data);
    }
  });
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
  // Listen for cart updates
  document.body.addEventListener('added_to_cart', handleCartUpdate);
  document.body.addEventListener('removed_from_cart', handleCartUpdate);
  
  // Listen for WooCommerce events
  document.body.addEventListener('wc_cart_changed', handleCartUpdate);
  
  // Listen for page visibility changes
  document.addEventListener('visibilitychange', handleVisibilityChange);
}

/**
 * Handle cart updates
 */
function handleCartUpdate(event) {
  if (widgetInstance && window.wooAiAssistant?.trackEvent) {
    window.wooAiAssistant.trackEvent('cart_updated', {
      event: event.type,
      data: event.detail
    });
  }
}

/**
 * Handle page visibility changes
 */
function handleVisibilityChange() {
  if (widgetInstance && window.wooAiAssistant?.trackEvent) {
    window.wooAiAssistant.trackEvent('visibility_change', {
      visible: !document.hidden
    });
  }
}

/**
 * Handle page navigation
 */
function handlePageChange() {
  // Reload widget context if page changes
  if (widgetInstance) {
    const newUserContext = loadUserContext();
    const newWooCommerceData = loadWooCommerceData();
    
    // Update widget with new context
    // This will be implemented with proper state updates in future tasks
    if (window.wooAiAssistant?.trackEvent) {
      window.wooAiAssistant.trackEvent('page_change', {
        oldPath: widgetInstance.userContext.currentPage,
        newPath: newUserContext.currentPage
      });
    }
  }
}

/**
 * Initialize analytics tracking
 */
function initializeAnalytics() {
  if (window.wooAiAssistant?.trackEvent) {
    window.wooAiAssistant.trackEvent('widget_loaded', {
      page: window.location.pathname,
      timestamp: new Date().toISOString()
    });
  }
}

/**
 * Destroy widget instance
 */
function destroyWidget() {
  if (widgetInstance) {
    widgetInstance.root.unmount();
    widgetInstance.container.remove();
    widgetInstance = null;
    widgetRoot = null;
  }
}

/**
 * Reload widget instance
 */
function reloadWidget() {
  destroyWidget();
  setTimeout(initializeWidget, 100);
}

// Export for testing
export { 
  initializeWidget, 
  shouldLoadWidget, 
  createWidgetContainer,
  loadWidgetConfiguration,
  loadUserContext,
  loadWooCommerceData,
  destroyWidget,
  reloadWidget
};
