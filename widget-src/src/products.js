/**
 * Products Bundle Entry Point
 * 
 * Lazy-loaded product functionality bundle.
 * Contains product cards, quick actions, and e-commerce features.
 * 
 * @package WooAiAssistant
 * @subpackage Widget Products
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Product-specific components (lazy-loaded)
export { default as ProductCard } from './components/ProductCard';
export { default as QuickAction } from './components/QuickAction';

// Product-specific styles (loaded only when needed)
import('./styles/products.css');

/**
 * Product bundle initialization
 * Sets up product-specific functionality when loaded
 */
export const initializeProductsBundle = () => {
  // Register product-specific event listeners
  document.addEventListener('woo-ai-assistant:product-recommended', (event) => {
    // Track product recommendation metrics
    if (window.gtag) {
      window.gtag('event', 'product_recommended', {
        event_category: 'woo_ai_assistant',
        event_label: 'product_interaction',
        value: event.detail?.productId || 0
      });
    }
  });

  document.addEventListener('woo-ai-assistant:quick-action-used', (event) => {
    // Track quick action usage
    if (window.gtag) {
      window.gtag('event', 'quick_action_used', {
        event_category: 'woo_ai_assistant',
        event_label: event.detail?.actionType || 'unknown',
        value: 1
      });
    }
  });

  document.addEventListener('woo-ai-assistant:add-to-cart', (event) => {
    // Enhanced e-commerce tracking
    if (window.gtag && event.detail?.product) {
      const product = event.detail.product;
      window.gtag('event', 'add_to_cart', {
        currency: 'USD',
        value: product.price || 0,
        items: [{
          item_id: product.id,
          item_name: product.name,
          category: product.category || 'Unknown',
          quantity: 1,
          price: product.price || 0
        }]
      });
    }

    // WooCommerce integration
    if (window.wc_add_to_cart_params) {
      // Trigger WooCommerce add to cart event
      jQuery(document.body).trigger('added_to_cart', [
        event.detail?.fragments || {},
        event.detail?.cartHash || '',
        jQuery(`[data-product_id="${event.detail?.productId}"]`)
      ]);
    }
  });

  // Set up product-specific performance monitoring
  if (window.wooAiAssistant?.performanceMonitoring) {
    window.wooAiAssistant.performanceMonitoring.startBenchmark('products_bundle_loaded');
  }
};

/**
 * Product utilities for enhanced functionality
 */
export const ProductUtils = {
  /**
   * Format product price with currency
   * @param {number} price - Product price
   * @param {string} currency - Currency code
   * @returns {string} Formatted price
   */
  formatPrice(price, currency = 'USD') {
    try {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2
      }).format(price);
    } catch (error) {
      return `$${price?.toFixed(2) || '0.00'}`;
    }
  },

  /**
   * Generate product URL with tracking parameters
   * @param {Object} product - Product object
   * @returns {string} Enhanced product URL
   */
  getProductUrl(product) {
    if (!product?.url) return '#';
    
    const url = new URL(product.url, window.location.origin);
    url.searchParams.set('utm_source', 'ai_assistant');
    url.searchParams.set('utm_medium', 'chat');
    url.searchParams.set('utm_campaign', 'product_recommendation');
    
    return url.toString();
  },

  /**
   * Check if product is in stock
   * @param {Object} product - Product object
   * @returns {boolean} Stock availability
   */
  isInStock(product) {
    return product?.stock_status === 'instock' || 
           (product?.stock_quantity && product.stock_quantity > 0);
  },

  /**
   * Get product availability text
   * @param {Object} product - Product object
   * @returns {string} Availability text
   */
  getAvailabilityText(product) {
    if (this.isInStock(product)) {
      return product.stock_quantity 
        ? `${product.stock_quantity} in stock`
        : 'In stock';
    }
    return 'Out of stock';
  }
};

/**
 * WooCommerce integration helpers
 */
export const WooCommerceIntegration = {
  /**
   * Add product to cart via AJAX
   * @param {Object} product - Product to add
   * @param {number} quantity - Quantity to add
   * @returns {Promise} Add to cart promise
   */
  async addToCart(product, quantity = 1) {
    if (!product?.id) {
      throw new Error('Invalid product');
    }

    const formData = new FormData();
    formData.append('action', 'woocommerce_add_to_cart');
    formData.append('product_id', product.id);
    formData.append('quantity', quantity);

    if (window.wc_add_to_cart_params?.ajax_url) {
      try {
        const response = await fetch(window.wc_add_to_cart_params.ajax_url, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });

        const result = await response.json();
        
        if (result.error) {
          throw new Error(result.error);
        }

        // Trigger custom event
        document.dispatchEvent(new CustomEvent('woo-ai-assistant:add-to-cart', {
          detail: {
            product,
            quantity,
            fragments: result.fragments,
            cartHash: result.cart_hash
          }
        }));

        return result;
      } catch (error) {
        console.error('Add to cart failed:', error);
        throw error;
      }
    }

    throw new Error('WooCommerce not available');
  },

  /**
   * Get cart contents via AJAX
   * @returns {Promise} Cart contents promise
   */
  async getCartContents() {
    if (!window.wc_add_to_cart_params?.ajax_url) {
      return { items: [], total: 0 };
    }

    try {
      const formData = new FormData();
      formData.append('action', 'woocommerce_get_cart_contents');

      const response = await fetch(window.wc_add_to_cart_params.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      return await response.json();
    } catch (error) {
      console.error('Get cart contents failed:', error);
      return { items: [], total: 0 };
    }
  }
};

// Auto-initialize when bundle is loaded
if (typeof window !== 'undefined') {
  initializeProductsBundle();
}