/**
 * Product Action Service
 *
 * Service layer for handling product-related actions like adding to cart,
 * applying coupons, and managing product interactions. Provides a clean
 * interface between components and the API service.
 *
 * @package WooAiAssistant
 * @subpackage Services
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import apiService from './ApiService';

/**
 * Product Action Service class
 */
class ProductActionService {
  constructor() {
    // Event handlers for notifications
    this.eventHandlers = {
      cartUpdated: [],
      couponApplied: [],
      error: []
    };
  }

  /**
   * Add event listener for service events
   *
   * @param {string} event - Event name
   * @param {Function} handler - Event handler function
   */
  on(event, handler) {
    if (this.eventHandlers[event] && typeof handler === 'function') {
      this.eventHandlers[event].push(handler);
    }
  }

  /**
   * Remove event listener
   *
   * @param {string} event - Event name
   * @param {Function} handler - Event handler function to remove
   */
  off(event, handler) {
    if (this.eventHandlers[event]) {
      const index = this.eventHandlers[event].indexOf(handler);
      if (index > -1) {
        this.eventHandlers[event].splice(index, 1);
      }
    }
  }

  /**
   * Emit event to registered handlers
   *
   * @param {string} event - Event name
   * @param {any} data - Event data
   */
  emit(event, data) {
    if (this.eventHandlers[event]) {
      this.eventHandlers[event].forEach(handler => {
        try {
          handler(data);
        } catch (error) {
          // Silently ignore event handler errors
        }
      });
    }
  }

  /**
   * Add product to cart
   *
   * @param {Object} productData - Product data
   * @param {number} productData.productId - Product ID
   * @param {number} productData.quantity - Quantity to add
   * @param {number} productData.variationId - Optional variation ID
   * @param {Object} productData.variation - Optional variation attributes
   * @returns {Promise<Object>} Result with success status and data
   */
  async addToCart(productData) {
    try {
      // Validate input
      if (!productData.productId) {
        throw new Error('Product ID is required');
      }

      const quantity = parseInt(productData.quantity) || 1;
      if (quantity < 1 || quantity > 99) {
        throw new Error('Invalid quantity. Must be between 1 and 99.');
      }

      // Call API
      const response = await apiService.addToCart({
        productId: productData.productId,
        quantity: quantity,
        variationId: productData.variationId,
        variation: productData.variation
      });

      // Emit success event
      this.emit('cartUpdated', {
        action: 'add',
        productId: productData.productId,
        quantity: quantity,
        response: response
      });

      return {
        success: true,
        data: response,
        message: response.message || `Added ${quantity} item(s) to cart`
      };

    } catch (error) {
      const errorMessage = apiService.getErrorMessage(error);
      
      this.emit('error', {
        action: 'addToCart',
        error: errorMessage,
        productData
      });

      return {
        success: false,
        error: errorMessage,
        data: null
      };
    }
  }

  /**
   * Apply coupon code
   *
   * @param {string} couponCode - Coupon code to apply
   * @returns {Promise<Object>} Result with success status and data
   */
  async applyCoupon(couponCode) {
    try {
      // Validate input
      if (!couponCode || typeof couponCode !== 'string') {
        throw new Error('Valid coupon code is required');
      }

      const trimmedCode = couponCode.trim().toUpperCase();
      if (trimmedCode.length === 0) {
        throw new Error('Coupon code cannot be empty');
      }

      // Call API
      const response = await apiService.applyCoupon(trimmedCode);

      // Emit success event
      this.emit('couponApplied', {
        couponCode: trimmedCode,
        response: response
      });

      return {
        success: true,
        data: response,
        message: response.message || `Coupon "${trimmedCode}" applied successfully`
      };

    } catch (error) {
      const errorMessage = apiService.getErrorMessage(error);
      
      this.emit('error', {
        action: 'applyCoupon',
        error: errorMessage,
        couponCode
      });

      return {
        success: false,
        error: errorMessage,
        data: null
      };
    }
  }

  /**
   * Remove coupon code
   *
   * @param {string} couponCode - Coupon code to remove
   * @returns {Promise<Object>} Result with success status and data
   */
  async removeCoupon(couponCode) {
    try {
      // Call API (assuming we have this endpoint)
      const response = await apiService.post('/actions/remove-coupon', {
        coupon_code: couponCode
      });

      return {
        success: true,
        data: response,
        message: response.message || `Coupon "${couponCode}" removed successfully`
      };

    } catch (error) {
      const errorMessage = apiService.getErrorMessage(error);
      
      this.emit('error', {
        action: 'removeCoupon',
        error: errorMessage,
        couponCode
      });

      return {
        success: false,
        error: errorMessage,
        data: null
      };
    }
  }

  /**
   * Update cart item quantity
   *
   * @param {string} cartItemKey - Cart item key
   * @param {number} quantity - New quantity (0 to remove)
   * @returns {Promise<Object>} Result with success status and data
   */
  async updateCartItem(cartItemKey, quantity) {
    try {
      const response = await apiService.updateCart({
        cart_item_key: cartItemKey,
        quantity: Math.max(0, parseInt(quantity) || 0)
      });

      this.emit('cartUpdated', {
        action: 'update',
        cartItemKey,
        quantity,
        response
      });

      return {
        success: true,
        data: response,
        message: response.message || 'Cart updated successfully'
      };

    } catch (error) {
      const errorMessage = apiService.getErrorMessage(error);
      
      this.emit('error', {
        action: 'updateCartItem',
        error: errorMessage,
        cartItemKey,
        quantity
      });

      return {
        success: false,
        error: errorMessage,
        data: null
      };
    }
  }

  /**
   * Get current cart contents
   *
   * @returns {Promise<Object>} Cart data
   */
  async getCart() {
    try {
      const response = await apiService.get('/cart');
      
      return {
        success: true,
        data: response,
        message: 'Cart retrieved successfully'
      };

    } catch (error) {
      const errorMessage = apiService.getErrorMessage(error);
      
      this.emit('error', {
        action: 'getCart',
        error: errorMessage
      });

      return {
        success: false,
        error: errorMessage,
        data: null
      };
    }
  }

  /**
   * Get available coupons for current user/cart
   *
   * @returns {Promise<Object>} Available coupons
   */
  async getAvailableCoupons() {
    try {
      const response = await apiService.get('/coupons/available');
      
      return {
        success: true,
        data: response,
        message: 'Coupons retrieved successfully'
      };

    } catch (error) {
      const errorMessage = apiService.getErrorMessage(error);
      
      return {
        success: false,
        error: errorMessage,
        data: []
      };
    }
  }

  /**
   * Generate personalized coupon based on conversation context
   *
   * @param {Object} context - Conversation context
   * @returns {Promise<Object>} Generated coupon data
   */
  async generatePersonalizedCoupon(context = {}) {
    try {
      const response = await apiService.post('/actions/generate-coupon', {
        context: context,
        conversation_id: context.conversationId
      });
      
      this.emit('couponGenerated', {
        context,
        response
      });

      return {
        success: true,
        data: response,
        message: response.message || 'Personalized coupon generated successfully'
      };

    } catch (error) {
      const errorMessage = apiService.getErrorMessage(error);
      
      this.emit('error', {
        action: 'generatePersonalizedCoupon',
        error: errorMessage,
        context
      });

      return {
        success: false,
        error: errorMessage,
        data: null
      };
    }
  }

  /**
   * Track product interaction for analytics
   *
   * @param {Object} interactionData - Interaction data
   * @param {string} interactionData.type - Interaction type
   * @param {number} interactionData.productId - Product ID
   * @param {Object} interactionData.context - Additional context
   */
  trackProductInteraction(interactionData) {
    try {
      // Track analytics (non-blocking)
      if (window.wooAiAssistant?.trackEvent) {
        window.wooAiAssistant.trackEvent('product_interaction', interactionData);
      }

      // Send to backend for analytics (non-blocking)
      apiService.post('/analytics/product-interaction', interactionData)
        .catch(error => {
          // Silently ignore analytics errors
        });

    } catch (error) {
      // Silently ignore tracking errors
    }
  }

  /**
   * Validate product availability before actions
   *
   * @param {number} productId - Product ID
   * @param {number} quantity - Desired quantity
   * @returns {Promise<Object>} Validation result
   */
  async validateProductAvailability(productId, quantity = 1) {
    try {
      const response = await apiService.get(`/products/${productId}/availability`, {
        quantity: quantity
      });
      
      return {
        success: true,
        available: response.available,
        stock: response.stock,
        message: response.message
      };

    } catch (error) {
      return {
        success: false,
        available: false,
        stock: 0,
        message: 'Unable to check product availability'
      };
    }
  }

  /**
   * Get product recommendations based on current context
   *
   * @param {Object} context - Current context (cart, viewed products, etc.)
   * @returns {Promise<Object>} Product recommendations
   */
  async getProductRecommendations(context = {}) {
    try {
      const response = await apiService.post('/products/recommendations', {
        context: context,
        limit: context.limit || 5
      });
      
      return {
        success: true,
        data: response.products || [],
        message: 'Recommendations retrieved successfully'
      };

    } catch (error) {
      return {
        success: false,
        data: [],
        message: 'Unable to get product recommendations'
      };
    }
  }
}

// Export singleton instance
const productActionService = new ProductActionService();

export default productActionService;
export { ProductActionService };