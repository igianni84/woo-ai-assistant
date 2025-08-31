/**
 * API Service Layer
 *
 * Provides REST API communication with the WordPress backend for the
 * Woo AI Assistant widget. Handles all HTTP requests, authentication,
 * error handling, and response processing.
 *
 * @package WooAiAssistant
 * @subpackage Services
 * @since 1.0.0
 * @author Claude Code Assistant
 */

/**
 * Default configuration for API requests
 */
const DEFAULT_CONFIG = {
  namespace: 'woo-ai-assistant/v1',
  timeout: 30000, // 30 seconds
  retryAttempts: 3,
  retryDelay: 1000, // 1 second base delay
  cacheTimeout: 300000 // 5 minutes for config cache
};

/**
 * HTTP status codes for response handling
 */
const HTTP_STATUS = {
  OK: 200,
  CREATED: 201,
  BAD_REQUEST: 400,
  UNAUTHORIZED: 401,
  FORBIDDEN: 403,
  NOT_FOUND: 404,
  TOO_MANY_REQUESTS: 429,
  INTERNAL_ERROR: 500,
  NOT_IMPLEMENTED: 501,
  SERVICE_UNAVAILABLE: 503
};

/**
 * Custom error class for API-related errors
 */
class ApiError extends Error {
  constructor(message, status = 500, response = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.response = response;
  }
}

/**
 * API Service class for communicating with WordPress REST API
 */
class ApiService {
  constructor() {
    this.config = { ...DEFAULT_CONFIG };
    this.baseUrl = null;
    this.nonce = null;
    this.configCache = null;
    this.configCacheTime = 0;
    this.requestInterceptors = [];
    this.responseInterceptors = [];

    // Bind methods to maintain context
    this.get = this.get.bind(this);
    this.post = this.post.bind(this);
    this.put = this.put.bind(this);
    this.delete = this.delete.bind(this);
  }

  /**
   * Initialize the API service with WordPress REST API details
   *
   * @param {Object} options - Configuration options
   * @param {string} options.restUrl - WordPress REST API base URL
   * @param {string} options.nonce - WordPress nonce for authentication
   * @param {Object} options.config - Additional configuration options
   * @returns {Promise<ApiService>} The initialized service instance
   */
  async initialize(options = {}) {
    try {
      // Set base configuration
      if (options.restUrl) {
        this.baseUrl = this.normalizeUrl(options.restUrl);
      } else if (typeof window !== 'undefined' && window.wpApiSettings) {
        this.baseUrl = this.normalizeUrl(window.wpApiSettings.root);
      } else {
        // Fallback: construct from current location
        const protocol = window.location.protocol;
        const host = window.location.host;
        this.baseUrl = `${protocol}//${host}/wp-json/`;
      }

      // Set nonce for authentication
      if (options.nonce) {
        this.nonce = options.nonce;
      } else if (typeof window !== 'undefined' && window.wpApiSettings) {
        this.nonce = window.wpApiSettings.nonce;
      }

      // Merge additional configuration
      if (options.config) {
        this.config = { ...this.config, ...options.config };
      }

      // Try to load initial configuration from backend
      try {
        await this.loadConfig();
      } catch (configError) {
        // Continue with defaults if config load fails
      }

      return this;
    } catch (error) {
      throw new ApiError('Failed to initialize API service', 500, error);
    }
  }

  /**
   * Normalize URL to ensure proper format
   *
   * @param {string} url - URL to normalize
   * @returns {string} Normalized URL
   */
  normalizeUrl(url) {
    if (!url) return '';

    // Ensure URL ends with slash
    if (!url.endsWith('/')) {
      url += '/';
    }

    return url;
  }

  /**
   * Build full API endpoint URL
   *
   * @param {string} endpoint - Endpoint path (without leading slash)
   * @returns {string} Full URL
   */
  buildUrl(endpoint) {
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
    return `${this.baseUrl}${this.config.namespace}/${cleanEndpoint}`;
  }

  /**
   * Load widget configuration from backend
   *
   * @param {Object} params - Optional parameters for config request
   * @returns {Promise<Object>} Configuration object
   */
  async loadConfig(params = {}) {
    try {
      // Check cache first
      const now = Date.now();
      if (this.configCache && (now - this.configCacheTime) < this.config.cacheTimeout) {
        return this.configCache;
      }

      const config = await this.get('/config', params);

      // Cache the configuration
      this.configCache = config;
      this.configCacheTime = now;

      // Update nonce if provided in config
      if (config.nonce) {
        this.nonce = config.nonce;
      }

      return config;
    } catch (error) {
      return {
        api_base_url: this.baseUrl + this.config.namespace,
        features: { chat_enabled: true },
        settings: {}
      };
    }
  }

  /**
   * Clear cached configuration
   */
  clearConfigCache() {
    this.configCache = null;
    this.configCacheTime = 0;
  }

  /**
   * Add request interceptor
   *
   * @param {Function} interceptor - Function to modify request before sending
   */
  addRequestInterceptor(interceptor) {
    if (typeof interceptor === 'function') {
      this.requestInterceptors.push(interceptor);
    }
  }

  /**
   * Add response interceptor
   *
   * @param {Function} interceptor - Function to process response after receiving
   */
  addResponseInterceptor(interceptor) {
    if (typeof interceptor === 'function') {
      this.responseInterceptors.push(interceptor);
    }
  }

  /**
   * Prepare request configuration
   *
   * @param {string} method - HTTP method
   * @param {Object} options - Request options
   * @returns {Object} Prepared request configuration
   */
  prepareRequest(method, options = {}) {
    const config = {
      method: method.toUpperCase(),
      headers: {
        ...(options.headers || {})
      },
      ...options
    };

    // Only set Content-Type for requests with body
    if (method !== 'GET' && options.data) {
      config.headers['Content-Type'] = 'application/json';
    }

    // Add nonce for authentication if available
    if (this.nonce) {
      config.headers['X-WP-Nonce'] = this.nonce;
    }

    // Add body for non-GET requests
    if (method !== 'GET' && options.data) {
      config.body = JSON.stringify(options.data);
    }

    // Apply request interceptors
    let finalConfig = config;
    for (const interceptor of this.requestInterceptors) {
      try {
        finalConfig = interceptor(finalConfig) || finalConfig;
      } catch (error) {
        // Silently ignore interceptor errors in production
      }
    }

    return finalConfig;
  }

  /**
   * Process response through interceptors and error handling
   *
   * @param {Response} response - Fetch response object
   * @param {string} url - Request URL for error context
   * @returns {Promise<Object>} Processed response data
   */
  async processResponse(response) {
    let processedResponse = response;

    // Apply response interceptors
    for (const interceptor of this.responseInterceptors) {
      try {
        processedResponse = (await interceptor(processedResponse)) || processedResponse;
      } catch (error) {
        // Silently ignore interceptor errors in production
      }
    }

    // Handle non-OK responses
    if (!processedResponse.ok) {
      let errorMessage = `Request failed: ${processedResponse.status} ${processedResponse.statusText}`;
      let errorData = null;

      try {
        errorData = await processedResponse.json();
        if (errorData.message) {
          errorMessage = errorData.message;
        }
      } catch (parseError) {
        // Response is not JSON, use default message
      }

      throw new ApiError(errorMessage, processedResponse.status, errorData);
    }

    // Parse response body
    try {
      const data = await processedResponse.json();
      return data;
    } catch (parseError) {
      throw new ApiError('Invalid JSON response', 500, parseError);
    }
  }

  /**
   * Execute HTTP request with retry logic and exponential backoff
   *
   * @param {string} url - Request URL
   * @param {Object} config - Request configuration
   * @returns {Promise<Object>} Response data
   */
  async executeRequest(url, config) {
    let lastError;

    for (let attempt = 1; attempt <= this.config.retryAttempts; attempt++) {
      let timeoutId;
      try {
        const controller = new AbortController();
        timeoutId = setTimeout(() => controller.abort(), this.config.timeout);

        const response = await fetch(url, {
          ...config,
          signal: controller.signal
        });

        clearTimeout(timeoutId);
        return await this.processResponse(response, url);

      } catch (error) {
        if (timeoutId) {
          clearTimeout(timeoutId);
        }
        lastError = error;

        // Don't retry on client errors (4xx) except rate limiting
        if (error.status >= 400 && error.status < 500 && error.status !== HTTP_STATUS.TOO_MANY_REQUESTS) {
          throw error;
        }

        // Don't retry on the last attempt
        if (attempt === this.config.retryAttempts) {
          throw error;
        }

        // Calculate exponential backoff delay
        const delay = this.config.retryDelay * Math.pow(2, attempt - 1);

        // Wait before retrying
        await new Promise(resolve => setTimeout(resolve, delay));
      }
    }

    throw lastError;
  }

  /**
   * GET request
   *
   * @param {string} endpoint - API endpoint
   * @param {Object} params - Query parameters
   * @param {Object} options - Additional options
   * @returns {Promise<Object>} Response data
   */
  async get(endpoint, params = {}, options = {}) {
    const url = new URL(this.buildUrl(endpoint));

    // Add query parameters
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined) {
        url.searchParams.append(key, params[key]);
      }
    });

    const config = this.prepareRequest('GET', options);
    return await this.executeRequest(url.toString(), config);
  }

  /**
   * POST request
   *
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request data
   * @param {Object} options - Additional options
   * @returns {Promise<Object>} Response data
   */
  async post(endpoint, data = {}, options = {}) {
    const url = this.buildUrl(endpoint);
    const config = this.prepareRequest('POST', { ...options, data });
    return await this.executeRequest(url, config);
  }

  /**
   * PUT request
   *
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request data
   * @param {Object} options - Additional options
   * @returns {Promise<Object>} Response data
   */
  async put(endpoint, data = {}, options = {}) {
    const url = this.buildUrl(endpoint);
    const config = this.prepareRequest('PUT', { ...options, data });
    return await this.executeRequest(url, config);
  }

  /**
   * DELETE request
   *
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Additional options
   * @returns {Promise<Object>} Response data
   */
  async delete(endpoint, options = {}) {
    const url = this.buildUrl(endpoint);
    const config = this.prepareRequest('DELETE', options);
    return await this.executeRequest(url, config);
  }

  // ===========================
  // CHAT API METHODS
  // ===========================

  /**
   * Send a chat message and receive AI response
   *
   * @param {Object} messageData - Message data
   * @param {string} messageData.message - User message content
   * @param {string} messageData.conversationId - Optional conversation ID
   * @param {Object} messageData.context - Chat context (page, product, etc.)
   * @returns {Promise<Object>} AI response data
   */
  async sendMessage(messageData) {
    try {
      const data = {
        message: messageData.message,
        conversation_id: messageData.conversationId || null,
        context: messageData.context || {}
      };

      return await this.post('/chat/message', data);
    } catch (error) {
      throw new ApiError('Failed to send message', error.status || 500, error);
    }
  }

  /**
   * Start a new conversation
   *
   * @param {Object} contextData - Initial context
   * @param {Object} contextData.context - Chat context
   * @param {number} contextData.userId - Optional user ID
   * @returns {Promise<Object>} New conversation data
   */
  async startConversation(contextData = {}) {
    try {
      const data = {
        context: contextData.context || {},
        user_id: contextData.userId || null
      };

      return await this.post('/chat/conversation', data);
    } catch (error) {
      throw new ApiError('Failed to start conversation', error.status || 500, error);
    }
  }

  /**
   * Get conversation history
   *
   * @param {string} conversationId - Conversation ID
   * @returns {Promise<Object>} Conversation data
   */
  async getConversation(conversationId) {
    try {
      return await this.get(`/chat/conversation/${conversationId}`);
    } catch (error) {
      throw new ApiError('Failed to get conversation', error.status || 500, error);
    }
  }

  // ===========================
  // ACTION API METHODS
  // ===========================

  /**
   * Add product to cart
   *
   * @param {Object} productData - Product data
   * @param {number} productData.productId - Product ID
   * @param {number} productData.quantity - Quantity to add
   * @param {number} productData.variationId - Optional variation ID
   * @param {Object} productData.variation - Optional variation attributes
   * @returns {Promise<Object>} Cart update response
   */
  async addToCart(productData) {
    try {
      const data = {
        product_id: productData.productId,
        quantity: productData.quantity || 1,
        variation_id: productData.variationId || null,
        variation: productData.variation || {}
      };

      return await this.post('/actions/add-to-cart', data);
    } catch (error) {
      throw new ApiError('Failed to add product to cart', error.status || 500, error);
    }
  }

  /**
   * Apply coupon code
   *
   * @param {string} couponCode - Coupon code to apply
   * @returns {Promise<Object>} Coupon application response
   */
  async applyCoupon(couponCode) {
    try {
      const data = {
        coupon_code: couponCode
      };

      return await this.post('/actions/apply-coupon', data);
    } catch (error) {
      throw new ApiError('Failed to apply coupon', error.status || 500, error);
    }
  }

  /**
   * Update cart
   *
   * @param {Object} cartData - Cart update data
   * @returns {Promise<Object>} Cart update response
   */
  async updateCart(cartData) {
    try {
      return await this.post('/actions/update-cart', cartData);
    } catch (error) {
      throw new ApiError('Failed to update cart', error.status || 500, error);
    }
  }

  // ===========================
  // RATING API METHODS
  // ===========================

  /**
   * Rate a conversation
   *
   * @param {Object} ratingData - Rating data
   * @param {string} ratingData.conversationId - Conversation ID
   * @param {number} ratingData.rating - Rating value (1-5)
   * @param {string} ratingData.feedback - Optional feedback text
   * @returns {Promise<Object>} Rating submission response
   */
  async rateConversation(ratingData) {
    try {
      const data = {
        conversation_id: ratingData.conversationId,
        rating: ratingData.rating,
        feedback: ratingData.feedback || ''
      };

      return await this.post('/rating/conversation', data);
    } catch (error) {
      throw new ApiError('Failed to rate conversation', error.status || 500, error);
    }
  }

  /**
   * Submit feedback
   *
   * @param {Object} feedbackData - Feedback data
   * @param {string} feedbackData.type - Feedback type
   * @param {string} feedbackData.message - Feedback message
   * @param {Object} feedbackData.context - Optional context data
   * @returns {Promise<Object>} Feedback submission response
   */
  async submitFeedback(feedbackData) {
    try {
      const data = {
        feedback_type: feedbackData.type,
        message: feedbackData.message,
        context: feedbackData.context || {}
      };

      return await this.post('/rating/feedback', data);
    } catch (error) {
      throw new ApiError('Failed to submit feedback', error.status || 500, error);
    }
  }

  // ===========================
  // STREAMING SUPPORT METHODS
  // ===========================

  /**
   * Create streaming response handler for real-time chat
   *
   * @param {string} endpoint - Streaming endpoint
   * @param {Object} data - Request data
   * @param {Function} onMessage - Callback for each message chunk
   * @param {Function} onError - Error callback
   * @param {Function} onComplete - Completion callback
   * @returns {Promise<void>} Streaming promise
   */
  async createStreamingRequest(endpoint, data, onMessage, onError, onComplete) {
    try {
      const url = this.buildUrl(endpoint);
      const config = this.prepareRequest('POST', {
        data,
        headers: {
          'Accept': 'text/event-stream',
          'Cache-Control': 'no-cache'
        }
      });

      const response = await fetch(url, config);

      if (!response.ok) {
        const errorData = await this.processResponse(response, url);
        throw new ApiError('Streaming request failed', response.status, errorData);
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();

      try {
        // eslint-disable-next-line no-constant-condition
        while (true) {
          const { done, value } = await reader.read();

          if (done) {
            if (onComplete) onComplete();
            break;
          }

          const chunk = decoder.decode(value, { stream: true });
          const lines = chunk.split('\n').filter(line => line.trim());

          for (const line of lines) {
            if (line.startsWith('data: ')) {
              try {
                const jsonData = JSON.parse(line.slice(6));
                if (onMessage) onMessage(jsonData);
              } catch (parseError) {
                // Silently ignore parsing errors for streaming data
              }
            }
          }
        }
      } finally {
        reader.releaseLock();
      }

    } catch (error) {
      if (onError) onError(error);
      throw error;
    }
  }

  // ===========================
  // UTILITY METHODS
  // ===========================

  /**
   * Check if API service is properly initialized
   *
   * @returns {boolean} True if initialized
   */
  isInitialized() {
    return !!(this.baseUrl && this.config.namespace);
  }

  /**
   * Get current configuration
   *
   * @returns {Object} Current configuration object
   */
  getConfig() {
    return { ...this.config };
  }

  /**
   * Update service configuration
   *
   * @param {Object} newConfig - New configuration options
   */
  updateConfig(newConfig) {
    this.config = { ...this.config, ...newConfig };

    // Clear config cache to force reload
    this.clearConfigCache();
  }

  /**
   * Get health check data
   *
   * @returns {Promise<Object>} Health check response
   */
  async getHealth() {
    try {
      return await this.get('/health');
    } catch (error) {
      throw new ApiError('Health check failed', error.status || 500, error);
    }
  }

  /**
   * Handle common error scenarios with user-friendly messages
   *
   * @param {Error} error - Error to handle
   * @returns {string} User-friendly error message
   */
  getErrorMessage(error) {
    if (error instanceof ApiError) {
      switch (error.status) {
        case HTTP_STATUS.UNAUTHORIZED:
          return 'Authentication required. Please refresh the page and try again.';
        case HTTP_STATUS.FORBIDDEN:
          return 'You don\'t have permission to perform this action.';
        case HTTP_STATUS.NOT_FOUND:
          return 'The requested resource was not found.';
        case HTTP_STATUS.TOO_MANY_REQUESTS:
          return 'Too many requests. Please wait a moment and try again.';
        case HTTP_STATUS.SERVICE_UNAVAILABLE:
          return 'Service is temporarily unavailable. Please try again later.';
        case HTTP_STATUS.NOT_IMPLEMENTED:
          return 'This feature is not yet available.';
        default:
          return error.message || 'An unexpected error occurred.';
      }
    }

    if (error.name === 'AbortError') {
      return 'Request timed out. Please check your connection and try again.';
    }

    if (error.name === 'NetworkError' || error.code === 'NETWORK_ERROR') {
      return 'Network error. Please check your internet connection.';
    }

    return error.message || 'An unexpected error occurred.';
  }
}

// Export singleton instance and class
const apiService = new ApiService();

export default apiService;
export { ApiService as ApiServiceClass, ApiError, HTTP_STATUS };

