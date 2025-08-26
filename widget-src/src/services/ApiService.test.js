/**
 * API Service Tests
 * 
 * Comprehensive test suite for the ApiService layer covering all functionality,
 * error handling, retry logic, and integration scenarios.
 * 
 * @package WooAiAssistant
 * @subpackage Services
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

import ApiService, { ApiProvider, useApi, ERROR_TYPES } from './ApiService';

// Mock fetch globally
global.fetch = jest.fn();

describe('ApiService', () => {
  const mockConfig = {
    baseUrl: 'https://example.com',
    nonce: 'test-nonce',
    userId: 123,
    pageContext: { type: 'product', id: 456 },
    onError: jest.fn()
  };

  beforeEach(() => {
    jest.clearAllMocks();
    fetch.mockClear();
    // Mock console.log to avoid noise during tests
    jest.spyOn(console, 'log').mockImplementation(() => {});
  });

  afterEach(() => {
    console.log.mockRestore();
  });

  describe('Constructor and Initialization', () => {
    it('should initialize with correct configuration', () => {
      const service = new ApiService(mockConfig);
      
      expect(service.baseUrl).toBe('https://example.com');
      expect(service.nonce).toBe('test-nonce');
      expect(service.userId).toBe(123);
      expect(service.pageContext).toEqual({ type: 'product', id: 456 });
    });

    it('should normalize base URL correctly', () => {
      const serviceWithSlash = new ApiService({
        ...mockConfig,
        baseUrl: 'https://example.com/'
      });
      
      expect(serviceWithSlash.baseUrl).toBe('https://example.com');
    });

    it('should handle missing base URL', () => {
      const serviceWithoutUrl = new ApiService({
        ...mockConfig,
        baseUrl: null
      });
      
      expect(serviceWithoutUrl.baseUrl).toBe(window.location.origin);
    });
  });

  describe('URL Building and Headers', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should build API URLs correctly', () => {
      const url = service.buildApiUrl('/chat');
      expect(url).toBe('https://example.com/wp-json/woo-ai-assistant/v1/chat');
    });

    it('should create proper headers', () => {
      const headers = service.createHeaders({ 'Custom-Header': 'test' });
      
      expect(headers).toEqual({
        'Content-Type': 'application/json',
        'X-WP-Nonce': 'test-nonce',
        'X-Requested-With': 'XMLHttpRequest',
        'Custom-Header': 'test'
      });
    });
  });

  describe('Circuit Breaker', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should initially have closed circuit breaker', () => {
      expect(service.isCircuitBreakerOpen()).toBe(false);
    });

    it('should open circuit breaker after failures', () => {
      // Record multiple failures
      for (let i = 0; i < 5; i++) {
        service.recordCircuitBreakerFailure();
      }
      
      expect(service.isCircuitBreakerOpen()).toBe(true);
    });

    it('should reset circuit breaker on success', () => {
      // First, open the circuit breaker
      for (let i = 0; i < 5; i++) {
        service.recordCircuitBreakerFailure();
      }
      
      // Then reset it
      service.resetCircuitBreaker();
      
      expect(service.isCircuitBreakerOpen()).toBe(false);
    });
  });

  describe('Rate Limiting', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should allow requests under limit', () => {
      expect(service.checkRateLimit()).toBe(true);
    });

    it('should deny requests over limit', () => {
      // Fill the rate limit
      for (let i = 0; i < 60; i++) {
        service.checkRateLimit();
      }
      
      // Next request should be denied
      expect(service.checkRateLimit()).toBe(false);
    });
  });

  describe('Error Handling', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should create API errors correctly', () => {
      const error = service.createApiError(
        ERROR_TYPES.VALIDATION,
        'Test error',
        400,
        { field: 'test' }
      );
      
      expect(error.isApiError).toBe(true);
      expect(error.type).toBe(ERROR_TYPES.VALIDATION);
      expect(error.message).toBe('Test error');
      expect(error.status).toBe(400);
      expect(error.details.field).toBe('test');
    });

    it('should determine retry eligibility correctly', () => {
      const networkError = service.createApiError(ERROR_TYPES.NETWORK, 'Network error');
      const authError = service.createApiError(ERROR_TYPES.AUTHENTICATION, 'Auth error');
      const rateLimitError = service.createApiError(ERROR_TYPES.RATE_LIMIT, 'Rate limit');
      
      expect(service.shouldRetry(networkError)).toBe(true);
      expect(service.shouldRetry(authError)).toBe(false);
      expect(service.shouldRetry(rateLimitError)).toBe(false);
    });
  });

  describe('Message Sending', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should send message successfully', async () => {
      const mockResponse = {
        success: true,
        data: {
          conversation_id: 'conv_123',
          response: 'Test response',
          timestamp: '2024-01-01T00:00:00.000Z',
          confidence: 0.8,
          sources: []
        }
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse)
      });

      const result = await service.sendMessage('Hello', 'conv_123');

      expect(result).toEqual({
        conversationId: 'conv_123',
        response: 'Test response',
        timestamp: '2024-01-01T00:00:00.000Z',
        confidence: 0.8,
        sources: [],
        metadata: {}
      });

      expect(fetch).toHaveBeenCalledWith(
        'https://example.com/wp-json/woo-ai-assistant/v1/chat',
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            'X-WP-Nonce': 'test-nonce'
          }),
          body: expect.any(String)
        })
      );

      // Verify the request body structure separately
      const requestBody = JSON.parse(fetch.mock.calls[0][1].body);
      expect(requestBody).toEqual({
        message: 'Hello',
        conversation_id: 'conv_123',
        user_context: expect.objectContaining({
          type: 'product',
          id: 456,
          timestamp: expect.stringMatching(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/)
        }),
        nonce: 'test-nonce'
      });
    });

    it('should reject empty messages', async () => {
      await expect(service.sendMessage('')).rejects.toThrow(
        'Message is required and must be a non-empty string'
      );
    });

    it('should handle server errors', async () => {
      fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: () => Promise.resolve({
          message: 'Server error',
          code: 'server_error'
        })
      });

      try {
        await service.sendMessage('Hello');
        fail('Expected error was not thrown');
      } catch (error) {
        expect(error.message).toBe('Server error');
        expect(mockConfig.onError).toHaveBeenCalled();
      }
    }, 10000);

    it('should handle network errors', async () => {
      fetch.mockRejectedValueOnce(new TypeError('Failed to fetch'));

      try {
        await service.sendMessage('Hello');
        fail('Expected error was not thrown');
      } catch (error) {
        expect(error.message).toBe('Network error. Please check your connection.');
        expect(error.type).toBe('NETWORK');
      }
    }, 10000);
  });

  describe('Streaming Messages', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should simulate streaming when not supported by backend', async () => {
      const mockResponse = {
        success: true,
        data: {
          conversation_id: 'conv_123',
          response: 'This is a test response with multiple words',
          timestamp: '2024-01-01T00:00:00.000Z'
        }
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse)
      });

      const chunks = [];
      const onChunk = jest.fn((chunk) => chunks.push(chunk));

      const result = await service.sendStreamingMessage(
        'Hello', 
        'conv_123', 
        onChunk
      );

      expect(result.response).toBe('This is a test response with multiple words');
      expect(onChunk).toHaveBeenCalled();
      expect(chunks.length).toBeGreaterThan(0);
      expect(chunks[chunks.length - 1].isComplete).toBe(true);
    });
  });

  describe('Action Execution', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should execute actions successfully', async () => {
      const mockResponse = {
        success: true,
        data: { success: true, product_added: true }
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse)
      });

      const result = await service.executeAction(
        'add_to_cart',
        { product_id: 123, quantity: 1 },
        'conv_123'
      );

      expect(result).toEqual({ success: true, product_added: true });
    });

    it('should validate action parameters', async () => {
      await expect(service.executeAction()).rejects.toThrow(
        'Action type, data, and conversation ID are required'
      );
    });
  });

  describe('Rating Submission', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should submit ratings successfully', async () => {
      const mockResponse = {
        success: true,
        data: { rating_id: 456, submitted: true }
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve(mockResponse)
      });

      const result = await service.submitRating('conv_123', 5, 'Great service!');

      expect(result).toEqual({ rating_id: 456, submitted: true });
    });

    it('should validate rating parameters', async () => {
      await expect(service.submitRating()).rejects.toThrow(
        'Valid conversation ID and rating (1-5) are required'
      );

      await expect(service.submitRating('conv_123', 0)).rejects.toThrow(
        'Valid conversation ID and rating (1-5) are required'
      );

      await expect(service.submitRating('conv_123', 6)).rejects.toThrow(
        'Valid conversation ID and rating (1-5) are required'
      );
    });
  });

  describe('Configuration', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should get configuration from backend', async () => {
      const mockConfig = {
        theme: 'dark',
        language: 'en',
        welcomeMessage: 'Hello!',
        features: { streaming: true }
      };

      fetch.mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ data: mockConfig })
      });

      const result = await service.getConfig();

      expect(result).toEqual(mockConfig);
    });

    it('should return default config on error', async () => {
      fetch.mockRejectedValueOnce(new Error('Network error'));

      const result = await service.getConfig();

      expect(result).toEqual({
        theme: 'light',
        language: 'en',  
        welcomeMessage: 'Hi! How can I help you today?',
        features: {
          streaming: false,
          actions: true,
          ratings: true
        }
      });
    }, 10000);
  });

  describe('Connection Testing', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should return true for successful connection test', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ data: {} })
      });

      const result = await service.testConnection();
      expect(result).toBe(true);
    });

    it('should return false for failed connection test', async () => {
      fetch.mockRejectedValueOnce(new Error('Network error'));

      const result = await service.testConnection();
      expect(result).toBe(false);
    }, 10000);
  });

  describe('Request Cancellation', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should cancel all active requests', () => {
      service.activeRequests.add('request-1');
      service.activeRequests.add('request-2');
      service.requestQueue.push(Date.now());
      service.retryAttempts.set('test', 1);

      service.cancelAllRequests();

      expect(service.activeRequests.size).toBe(0);
      expect(service.requestQueue.length).toBe(0);
      expect(service.retryAttempts.size).toBe(0);
    });
  });

  describe('Retry Logic', () => {
    let service;

    beforeEach(() => {
      service = new ApiService(mockConfig);
    });

    it('should calculate retry delay with backoff', () => {
      const delay0 = service.calculateRetryDelay(0);
      const delay1 = service.calculateRetryDelay(1);
      const delay2 = service.calculateRetryDelay(2);

      expect(delay0).toBeGreaterThanOrEqual(1000);
      expect(delay0).toBeLessThan(2000);
      expect(delay1).toBeGreaterThanOrEqual(2000);
      expect(delay1).toBeLessThan(3000);
      expect(delay2).toBeGreaterThanOrEqual(4000);
      expect(delay2).toBeLessThan(5000);
    });

    it('should respect maximum retry delay', () => {
      const delay = service.calculateRetryDelay(10); // Very high attempt
      expect(delay).toBeLessThanOrEqual(11000); // maxDelay + jitter
    });
  });
});

describe('ApiProvider and useApi', () => {
  const mockConfig = {
    baseUrl: 'https://example.com',
    nonce: 'test-nonce',
    userId: 123,
    pageContext: { type: 'product', id: 456 },
    onError: jest.fn()
  };

  it('should provide API service through context', () => {
    const TestComponent = () => {
      const api = useApi();
      return <div data-testid="api-service">{api ? 'API Available' : 'No API'}</div>;
    };

    render(
      <ApiProvider {...mockConfig}>
        <TestComponent />
      </ApiProvider>
    );

    expect(screen.getByTestId('api-service')).toHaveTextContent('API Available');
  });

  it('should throw error when useApi used outside provider', () => {
    const TestComponent = () => {
      const api = useApi();
      return <div>{api ? 'API Available' : 'No API'}</div>;
    };

    // Suppress console.error for this test
    const originalError = console.error;
    console.error = jest.fn();

    expect(() => render(<TestComponent />)).toThrow(
      'useApi must be used within an ApiProvider'
    );

    console.error = originalError;
  });

  it('should memoize API service instance', () => {
    let apiInstance1, apiInstance2;

    const TestComponent = () => {
      const api = useApi();
      if (!apiInstance1) {
        apiInstance1 = api;
      } else {
        apiInstance2 = api;
      }
      return <div>Test</div>;
    };

    const { rerender } = render(
      <ApiProvider {...mockConfig}>
        <TestComponent />
      </ApiProvider>
    );

    rerender(
      <ApiProvider {...mockConfig}>
        <TestComponent />
      </ApiProvider>
    );

    expect(apiInstance1).toBe(apiInstance2);
  });
});

describe('Integration Tests', () => {
  let service;
  const mockConfig = {
    baseUrl: 'https://example.com',
    nonce: 'test-nonce',
    userId: 123,
    pageContext: { type: 'product', id: 456 },
    onError: jest.fn()
  };

  beforeEach(() => {
    service = new ApiService(mockConfig);
    jest.clearAllMocks();
    fetch.mockClear();
  });

  it('should handle complete chat flow', async () => {
    // Mock successful message send
    fetch.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: {
          conversation_id: 'conv_123',
          response: 'Hello! How can I help you?',
          timestamp: '2024-01-01T00:00:00.000Z'
        }
      })
    });

    const result = await service.sendMessage('Hello', 'conv_123');

    expect(result.conversationId).toBe('conv_123');
    expect(result.response).toBe('Hello! How can I help you?');
  });

  it('should handle error recovery with retry', async () => {
    // First call fails
    fetch
      .mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: () => Promise.resolve({ message: 'Server error' })
      })
      // Second call succeeds
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({
          success: true,
          data: { conversation_id: 'conv_123', response: 'Success!' }
        })
      });

    const result = await service.sendMessage('Hello');
    expect(result.response).toBe('Success!');
  });

  it('should respect circuit breaker in integration flow', async () => {
    // Force circuit breaker to open
    for (let i = 0; i < 5; i++) {
      service.recordCircuitBreakerFailure();
    }

    await expect(service.sendMessage('Hello')).rejects.toThrow(
      'Service temporarily unavailable'
    );

    // No fetch should be called due to circuit breaker
    expect(fetch).not.toHaveBeenCalled();
  });
});

describe('Naming Conventions', () => {
  it('should follow camelCase for method names', () => {
    const service = new ApiService({
      baseUrl: 'https://example.com',
      nonce: 'test',
      userId: 1,
      onError: () => {}
    });

    // Check that all public methods follow camelCase
    const methods = [
      'normalizeBaseUrl',
      'buildApiUrl', 
      'createHeaders',
      'isCircuitBreakerOpen',
      'recordCircuitBreakerFailure',
      'resetCircuitBreaker',
      'checkRateLimit',
      'calculateRetryDelay',
      'makeRequest',
      'handleErrorResponse',
      'shouldRetry',
      'createApiError',
      'sendMessage',
      'sendStreamingMessage',
      'executeAction',
      'submitRating',
      'getConfig',
      'testConnection',
      'cancelAllRequests',
      'debugLog'
    ];

    methods.forEach(methodName => {
      expect(typeof service[methodName]).toBe('function');
      // Check camelCase pattern: starts with lowercase, may have uppercase letters
      expect(methodName).toMatch(/^[a-z][a-zA-Z0-9]*$/);
    });
  });

  it('should follow PascalCase for component names', () => {
    expect(ApiProvider.name).toBe('ApiProvider');
    expect(ApiService.name).toBe('ApiService');
  });

  it('should follow UPPER_SNAKE_CASE for constants', () => {
    const constantNames = Object.keys(ERROR_TYPES);
    
    constantNames.forEach(constantName => {
      expect(constantName).toMatch(/^[A-Z][A-Z_0-9]*$/);
    });
  });
});