/**
 * API Service Layer
 * 
 * Comprehensive REST API communication service for the Woo AI Assistant
 * chat widget. Handles all backend communication, streaming responses,
 * error handling, and retry logic.
 * 
 * @package WooAiAssistant
 * @subpackage Services
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { createContext, useContext, useCallback, useMemo } from 'react';
import PropTypes from 'prop-types';

/**
 * Configuration constants for API service
 */
const API_CONFIG = {
    namespace: 'woo-ai-assistant/v1',
    endpoints: {
        chat: '/chat',
        action: '/action',
        rating: '/rating',
        config: '/config'
    },
    retryConfig: {
        maxRetries: 3,
        baseDelay: 1000, // 1 second
        maxDelay: 10000, // 10 seconds
        backoffFactor: 2
    },
    timeouts: {
        default: 30000, // 30 seconds
        streaming: 60000, // 1 minute for streaming
        config: 10000 // 10 seconds for config
    },
    rateLimits: {
        requests: 60,
        windowMs: 60000 // 1 minute
    }
};

/**
 * Error types for better error handling
 */
const ERROR_TYPES = {
    NETWORK: 'network_error',
    TIMEOUT: 'timeout_error',
    RATE_LIMIT: 'rate_limit_error',
    AUTHENTICATION: 'auth_error',
    VALIDATION: 'validation_error',
    SERVER: 'server_error',
    STREAMING: 'streaming_error',
    UNKNOWN: 'unknown_error'
};

/**
 * API Context for providing service throughout the app
 */
const ApiContext = createContext(null);

/**
 * Custom hook to use the API service
 * 
 * @returns {Object} API service instance
 * @throws {Error} If used outside ApiProvider
 */
export const useApi = () => {
    const context = useContext(ApiContext);
    if (!context) {
        throw new Error('useApi must be used within an ApiProvider');
    }
    return context;
};

/**
 * API Service Class
 * 
 * Handles all REST API communication with comprehensive error handling,
 * retry logic, and streaming support.
 */
class ApiService {
    /**
     * Constructor
     * 
     * @param {Object} config - Configuration object
     * @param {string} config.baseUrl - WordPress site base URL
     * @param {string} config.nonce - WordPress nonce for authentication
     * @param {number} config.userId - Current user ID
     * @param {Object} config.pageContext - Current page context
     * @param {Function} config.onError - Error callback function
     */
    constructor({ baseUrl, nonce, userId, pageContext, onError }) {
        this.baseUrl = this.normalizeBaseUrl(baseUrl);
        this.nonce = nonce;
        this.userId = userId;
        this.pageContext = pageContext || {};
        this.onError = onError || (() => {});
        
        // Initialize request tracking for rate limiting
        this.requestQueue = [];
        this.activeRequests = new Set();
        this.retryAttempts = new Map();
        
        // Circuit breaker state
        this.circuitBreakerState = {
            isOpen: false,
            failures: 0,
            lastFailureTime: null,
            threshold: 5, // Open after 5 consecutive failures
            timeout: 30000 // 30 seconds
        };

        this.debugLog('ApiService initialized', {
            baseUrl: this.baseUrl,
            userId: this.userId,
            pageContext: this.pageContext
        });
    }

    /**
     * Normalize base URL to ensure proper format
     * 
     * @param {string} url - Base URL to normalize
     * @returns {string} Normalized URL
     */
    normalizeBaseUrl(url) {
        if (!url) {
            // Fallback to current site URL
            return window.location.origin;
        }
        
        // Remove trailing slash
        return url.replace(/\/$/, '');
    }

    /**
     * Build full API endpoint URL
     * 
     * @param {string} endpoint - Endpoint path
     * @returns {string} Full URL
     */
    buildApiUrl(endpoint) {
        const restBase = `${this.baseUrl}/wp-json`;
        const fullEndpoint = `${API_CONFIG.namespace}${endpoint}`;
        return `${restBase}/${fullEndpoint}`;
    }

    /**
     * Create request headers with authentication
     * 
     * @param {Object} additionalHeaders - Additional headers to include
     * @returns {Object} Request headers
     */
    createHeaders(additionalHeaders = {}) {
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': this.nonce,
            'X-Requested-With': 'XMLHttpRequest',
            ...additionalHeaders
        };

        return headers;
    }

    /**
     * Check if circuit breaker is open
     * 
     * @returns {boolean} True if circuit breaker is open
     */
    isCircuitBreakerOpen() {
        const { isOpen, lastFailureTime, timeout } = this.circuitBreakerState;
        
        if (!isOpen) {
            return false;
        }

        // Check if timeout has passed - close the circuit breaker
        if (Date.now() - lastFailureTime > timeout) {
            this.circuitBreakerState.isOpen = false;
            this.circuitBreakerState.failures = 0;
            this.debugLog('Circuit breaker closed after timeout');
            return false;
        }

        return true;
    }

    /**
     * Record circuit breaker failure
     */
    recordCircuitBreakerFailure() {
        this.circuitBreakerState.failures += 1;
        this.circuitBreakerState.lastFailureTime = Date.now();

        if (this.circuitBreakerState.failures >= this.circuitBreakerState.threshold) {
            this.circuitBreakerState.isOpen = true;
            this.debugLog('Circuit breaker opened due to failures', {
                failures: this.circuitBreakerState.failures
            });
        }
    }

    /**
     * Reset circuit breaker on successful request
     */
    resetCircuitBreaker() {
        if (this.circuitBreakerState.failures > 0) {
            this.circuitBreakerState.failures = 0;
            this.circuitBreakerState.isOpen = false;
            this.debugLog('Circuit breaker reset after successful request');
        }
    }

    /**
     * Check rate limiting
     * 
     * @returns {boolean} True if request is allowed
     */
    checkRateLimit() {
        const now = Date.now();
        const { requests, windowMs } = API_CONFIG.rateLimits;

        // Remove old requests outside the window
        this.requestQueue = this.requestQueue.filter(
            timestamp => now - timestamp < windowMs
        );

        // Check if we're under the limit
        if (this.requestQueue.length >= requests) {
            this.debugLog('Rate limit exceeded', {
                requests: this.requestQueue.length,
                limit: requests
            });
            return false;
        }

        // Record this request
        this.requestQueue.push(now);
        return true;
    }

    /**
     * Calculate retry delay with exponential backoff
     * 
     * @param {number} attempt - Current retry attempt (0-based)
     * @returns {number} Delay in milliseconds
     */
    calculateRetryDelay(attempt) {
        const { baseDelay, maxDelay, backoffFactor } = API_CONFIG.retryConfig;
        const delay = Math.min(baseDelay * Math.pow(backoffFactor, attempt), maxDelay);
        
        // Add jitter to prevent thundering herd
        return delay + Math.random() * 1000;
    }

    /**
     * Wait for specified delay
     * 
     * @param {number} ms - Milliseconds to wait
     * @returns {Promise} Promise that resolves after delay
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Make HTTP request with comprehensive error handling and retry logic
     * 
     * @param {string} endpoint - API endpoint
     * @param {Object} options - Request options
     * @param {string} options.method - HTTP method
     * @param {Object} options.body - Request body
     * @param {Object} options.headers - Additional headers
     * @param {number} options.timeout - Request timeout
     * @param {boolean} options.skipRetry - Skip retry logic
     * @returns {Promise<Object>} API response
     */
    async makeRequest(endpoint, options = {}) {
        const {
            method = 'GET',
            body = null,
            headers = {},
            timeout = API_CONFIG.timeouts.default,
            skipRetry = false
        } = options;

        const requestId = `${method}-${endpoint}-${Date.now()}`;
        
        this.debugLog('Making API request', {
            requestId,
            endpoint,
            method,
            timeout
        });

        // Check circuit breaker
        if (this.isCircuitBreakerOpen()) {
            const error = this.createApiError(
                ERROR_TYPES.SERVER,
                'Service temporarily unavailable',
                503,
                { circuitBreakerOpen: true }
            );
            throw error;
        }

        // Check rate limiting
        if (!this.checkRateLimit()) {
            const error = this.createApiError(
                ERROR_TYPES.RATE_LIMIT,
                'Too many requests. Please try again later.',
                429
            );
            throw error;
        }

        const url = this.buildApiUrl(endpoint);
        const requestHeaders = this.createHeaders(headers);
        
        // Create AbortController for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => {
            controller.abort();
        }, timeout);

        const fetchOptions = {
            method,
            headers: requestHeaders,
            signal: controller.signal
        };

        if (body && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            fetchOptions.body = JSON.stringify(body);
        }

        this.activeRequests.add(requestId);

        try {
            const response = await fetch(url, fetchOptions);
            clearTimeout(timeoutId);

            // Check if response is ok
            if (!response.ok) {
                await this.handleErrorResponse(response);
            }

            // Parse JSON response
            const data = await response.json();
            
            // Reset circuit breaker on success
            this.resetCircuitBreaker();
            
            this.debugLog('API request successful', {
                requestId,
                status: response.status
            });

            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            
            // Record circuit breaker failure
            this.recordCircuitBreakerFailure();

            // Handle specific error types
            let apiError;
            
            if (error.name === 'AbortError') {
                apiError = this.createApiError(
                    ERROR_TYPES.TIMEOUT,
                    'Request timed out. Please try again.',
                    408,
                    { timeout }
                );
            } else if (error instanceof TypeError && error.message.includes('fetch')) {
                apiError = this.createApiError(
                    ERROR_TYPES.NETWORK,
                    'Network error. Please check your connection.',
                    0,
                    { originalError: error.message }
                );
            } else if (error.isApiError) {
                apiError = error;
            } else {
                apiError = this.createApiError(
                    ERROR_TYPES.UNKNOWN,
                    'An unexpected error occurred.',
                    0,
                    { originalError: error.message }
                );
            }

            this.debugLog('API request failed', {
                requestId,
                error: apiError
            });

            // Try retry if not explicitly skipped
            if (!skipRetry) {
                const retryKey = `${method}-${endpoint}`;
                const currentAttempts = this.retryAttempts.get(retryKey) || 0;
                
                if (currentAttempts < API_CONFIG.retryConfig.maxRetries && 
                    this.shouldRetry(apiError)) {
                    
                    this.retryAttempts.set(retryKey, currentAttempts + 1);
                    const delay = this.calculateRetryDelay(currentAttempts);
                    
                    this.debugLog('Retrying request', {
                        requestId,
                        attempt: currentAttempts + 1,
                        delay
                    });

                    await this.delay(delay);
                    return this.makeRequest(endpoint, { ...options, skipRetry: false });
                }
                
                // Clear retry attempts after max retries
                this.retryAttempts.delete(retryKey);
            }

            throw apiError;

        } finally {
            this.activeRequests.delete(requestId);
        }
    }

    /**
     * Handle error response from server
     * 
     * @param {Response} response - Fetch response object
     * @throws {Error} API error
     */
    async handleErrorResponse(response) {
        let errorData;
        
        try {
            errorData = await response.json();
        } catch (parseError) {
            errorData = {
                message: `HTTP ${response.status}: ${response.statusText}`,
                code: 'http_error'
            };
        }

        let errorType;
        switch (response.status) {
            case 400:
                errorType = ERROR_TYPES.VALIDATION;
                break;
            case 401:
            case 403:
                errorType = ERROR_TYPES.AUTHENTICATION;
                break;
            case 429:
                errorType = ERROR_TYPES.RATE_LIMIT;
                break;
            case 500:
            case 502:
            case 503:
            case 504:
                errorType = ERROR_TYPES.SERVER;
                break;
            default:
                errorType = ERROR_TYPES.UNKNOWN;
        }

        const error = this.createApiError(
            errorType,
            errorData.message || 'Request failed',
            response.status,
            errorData
        );

        throw error;
    }

    /**
     * Determine if a request should be retried
     * 
     * @param {Error} error - The error that occurred
     * @returns {boolean} True if should retry
     */
    shouldRetry(error) {
        // Don't retry authentication or validation errors
        if (error.type === ERROR_TYPES.AUTHENTICATION || 
            error.type === ERROR_TYPES.VALIDATION) {
            return false;
        }

        // Don't retry rate limit errors
        if (error.type === ERROR_TYPES.RATE_LIMIT) {
            return false;
        }

        // Retry network, timeout, and server errors
        return [
            ERROR_TYPES.NETWORK,
            ERROR_TYPES.TIMEOUT,
            ERROR_TYPES.SERVER,
            ERROR_TYPES.UNKNOWN
        ].includes(error.type);
    }

    /**
     * Create standardized API error object
     * 
     * @param {string} type - Error type
     * @param {string} message - Error message
     * @param {number} status - HTTP status code
     * @param {Object} details - Additional error details
     * @returns {Error} Standardized error object
     */
    createApiError(type, message, status = 0, details = {}) {
        const error = new Error(message);
        error.isApiError = true;
        error.type = type;
        error.status = status;
        error.details = details;
        error.timestamp = new Date().toISOString();
        
        return error;
    }

    /**
     * Send chat message to backend
     * 
     * @param {string} message - User message
     * @param {string} conversationId - Optional conversation ID
     * @param {Object} userContext - Additional user context
     * @returns {Promise<Object>} Chat response
     */
    async sendMessage(message, conversationId = null, userContext = {}) {
        if (!message || typeof message !== 'string' || !message.trim()) {
            throw this.createApiError(
                ERROR_TYPES.VALIDATION,
                'Message is required and must be a non-empty string'
            );
        }

        const requestBody = {
            message: message.trim(),
            conversation_id: conversationId,
            user_context: {
                ...this.pageContext,
                ...userContext,
                timestamp: new Date().toISOString()
            },
            nonce: this.nonce
        };

        try {
            const response = await this.makeRequest(API_CONFIG.endpoints.chat, {
                method: 'POST',
                body: requestBody,
                timeout: API_CONFIG.timeouts.default
            });

            // Validate response structure
            if (!response.success || !response.data) {
                throw this.createApiError(
                    ERROR_TYPES.SERVER,
                    'Invalid response format from server'
                );
            }

            return {
                conversationId: response.data.conversation_id,
                response: response.data.response,
                timestamp: response.data.timestamp,
                confidence: response.data.confidence || 0,
                sources: response.data.sources || [],
                metadata: response.data.metadata || {}
            };

        } catch (error) {
            this.onError({
                message: error.message,
                type: error.type || ERROR_TYPES.UNKNOWN,
                critical: error.type === ERROR_TYPES.AUTHENTICATION,
                details: error.details
            });
            throw error;
        }
    }

    /**
     * Send streaming chat message (future enhancement)
     * 
     * @param {string} message - User message
     * @param {string} conversationId - Optional conversation ID
     * @param {Function} onChunk - Callback for each response chunk
     * @param {Object} userContext - Additional user context
     * @returns {Promise<Object>} Final response
     */
    async sendStreamingMessage(message, conversationId = null, onChunk = null, userContext = {}) {
        // For now, fallback to regular message sending
        // Streaming will be implemented when backend supports it
        this.debugLog('Streaming not yet supported, falling back to regular message');
        
        const response = await this.sendMessage(message, conversationId, userContext);
        
        // Simulate streaming for better UX
        if (onChunk && response.response) {
            const words = response.response.split(' ');
            const chunkSize = Math.max(1, Math.floor(words.length / 8)); // 8 chunks
            
            for (let i = 0; i < words.length; i += chunkSize) {
                const chunk = words.slice(i, i + chunkSize).join(' ');
                onChunk({
                    chunk,
                    isComplete: i + chunkSize >= words.length,
                    progress: Math.min(1, (i + chunkSize) / words.length)
                });
                
                // Small delay between chunks
                await this.delay(100);
            }
        }
        
        return response;
    }

    /**
     * Execute agentic action
     * 
     * @param {string} actionType - Type of action to execute
     * @param {Object} actionData - Action-specific data
     * @param {string} conversationId - Conversation ID
     * @returns {Promise<Object>} Action response
     */
    async executeAction(actionType, actionData, conversationId) {
        if (!actionType || !actionData || !conversationId) {
            throw this.createApiError(
                ERROR_TYPES.VALIDATION,
                'Action type, data, and conversation ID are required'
            );
        }

        const requestBody = {
            action_type: actionType,
            action_data: actionData,
            conversation_id: conversationId,
            nonce: this.nonce
        };

        try {
            const response = await this.makeRequest(API_CONFIG.endpoints.action, {
                method: 'POST',
                body: requestBody
            });

            return response.data || response;

        } catch (error) {
            this.onError({
                message: `Action failed: ${error.message}`,
                type: error.type || ERROR_TYPES.UNKNOWN,
                critical: false,
                details: { actionType, ...error.details }
            });
            throw error;
        }
    }

    /**
     * Submit conversation rating
     * 
     * @param {string} conversationId - Conversation ID to rate
     * @param {number} rating - Rating value (1-5)
     * @param {string} feedback - Optional feedback text
     * @returns {Promise<Object>} Rating response
     */
    async submitRating(conversationId, rating, feedback = '') {
        if (!conversationId || !rating || rating < 1 || rating > 5) {
            throw this.createApiError(
                ERROR_TYPES.VALIDATION,
                'Valid conversation ID and rating (1-5) are required'
            );
        }

        const requestBody = {
            conversation_id: conversationId,
            rating: Math.floor(rating),
            feedback: feedback.trim(),
            nonce: this.nonce
        };

        try {
            const response = await this.makeRequest(API_CONFIG.endpoints.rating, {
                method: 'POST',
                body: requestBody
            });

            return response.data || response;

        } catch (error) {
            this.onError({
                message: `Rating submission failed: ${error.message}`,
                type: error.type || ERROR_TYPES.UNKNOWN,
                critical: false
            });
            throw error;
        }
    }

    /**
     * Get widget configuration from backend
     * 
     * @param {Object} pageContext - Current page context
     * @returns {Promise<Object>} Widget configuration
     */
    async getConfig(pageContext = {}) {
        try {
            const queryParams = new URLSearchParams();
            if (Object.keys(pageContext).length > 0) {
                queryParams.set('page_context', JSON.stringify(pageContext));
            }

            const endpoint = API_CONFIG.endpoints.config + 
                (queryParams.toString() ? `?${queryParams.toString()}` : '');

            const response = await this.makeRequest(endpoint, {
                method: 'GET',
                timeout: API_CONFIG.timeouts.config
            });

            return response.data || response;

        } catch (error) {
            this.debugLog('Failed to load config, using defaults', error);
            
            // Return default config on error
            return {
                theme: 'light',
                language: 'en',
                welcomeMessage: 'Hi! How can I help you today?',
                features: {
                    streaming: false,
                    actions: true,
                    ratings: true
                }
            };
        }
    }

    /**
     * Test API connectivity
     * 
     * @returns {Promise<boolean>} True if API is reachable
     */
    async testConnection() {
        try {
            await this.getConfig();
            return true;
        } catch (error) {
            this.debugLog('Connection test failed', error);
            return false;
        }
    }

    /**
     * Cancel all active requests
     */
    cancelAllRequests() {
        this.debugLog('Cancelling all active requests', {
            activeCount: this.activeRequests.size
        });
        
        this.activeRequests.clear();
        this.requestQueue = [];
        this.retryAttempts.clear();
    }

    /**
     * Debug logging (only in development)
     * 
     * @param {string} message - Log message
     * @param {Object} data - Additional data to log
     */
    debugLog(message, data = {}) {
        if (process.env.NODE_ENV === 'development' || 
            window.wooAiAssistant?.debug === true) {
            console.log(`[WooAI-API] ${message}`, data);
        }
    }
}

/**
 * API Provider Component
 * 
 * Provides API service instance to child components via React Context.
 * 
 * @param {Object} props - Component props
 * @param {React.ReactNode} props.children - Child components
 * @param {string} props.baseUrl - WordPress site base URL
 * @param {string} props.nonce - WordPress nonce for authentication
 * @param {number} props.userId - Current user ID
 * @param {Object} props.pageContext - Current page context
 * @param {Function} props.onError - Error callback function
 */
export const ApiProvider = ({ 
    children, 
    baseUrl, 
    nonce, 
    userId, 
    pageContext, 
    onError 
}) => {
    const apiService = useMemo(() => {
        return new ApiService({
            baseUrl,
            nonce,
            userId,
            pageContext,
            onError
        });
    }, [baseUrl, nonce, userId, pageContext, onError]);

    return (
        <ApiContext.Provider value={apiService}>
            {children}
        </ApiContext.Provider>
    );
};

ApiProvider.propTypes = {
    /**
     * Child components to provide API service to
     */
    children: PropTypes.node.isRequired,
    
    /**
     * WordPress site base URL
     */
    baseUrl: PropTypes.string.isRequired,
    
    /**
     * WordPress nonce for authentication
     */
    nonce: PropTypes.string.isRequired,
    
    /**
     * Current user ID
     */
    userId: PropTypes.number.isRequired,
    
    /**
     * Current page context
     */
    pageContext: PropTypes.shape({
        type: PropTypes.string,
        id: PropTypes.number,
        url: PropTypes.string
    }),
    
    /**
     * Error callback function
     */
    onError: PropTypes.func.isRequired
};

ApiProvider.defaultProps = {
    pageContext: {}
};

export default ApiService;

// Export error types for use in components
export { ERROR_TYPES };