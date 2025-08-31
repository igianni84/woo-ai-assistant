/**
 * Simplified API Service Tests
 * 
 * Basic tests for the ApiService class to verify core functionality
 */

import apiServiceSingleton, { ApiServiceClass, ApiError, HTTP_STATUS } from '../../services/ApiService';

// Mock fetch globally
const mockFetch = jest.fn();
global.fetch = mockFetch;

// Mock AbortController
global.AbortController = jest.fn(() => ({
  signal: {},
  abort: jest.fn()
}));

describe('ApiService Basic Tests', () => {
  let apiService;

  beforeEach(() => {
    // Create fresh instance
    apiService = new ApiServiceClass();
    
    // Reset mocks
    jest.clearAllMocks();
    mockFetch.mockReset();
    
    // Mock console to reduce noise
    jest.spyOn(console, 'log').mockImplementation(() => {});
    jest.spyOn(console, 'warn').mockImplementation(() => {});
    jest.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe('Initialization', () => {
    it('should initialize with basic configuration', async () => {
      // Mock successful config load
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: jest.fn().mockResolvedValue({
          api_base_url: 'http://localhost/wp-json/woo-ai-assistant/v1',
          nonce: 'test-nonce',
          features: { chat_enabled: true }
        })
      });

      await apiService.initialize({
        restUrl: 'http://localhost/wp-json/',
        nonce: 'initial-nonce'
      });

      expect(apiService.baseUrl).toBe('http://localhost/wp-json/');
      expect(apiService.isInitialized()).toBe(true);
    });

    it('should handle config load failure gracefully', async () => {
      // Mock config load failure
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      await apiService.initialize({
        restUrl: 'http://localhost/wp-json/',
        nonce: 'test-nonce'
      });

      // Should still initialize with defaults
      expect(apiService.baseUrl).toBe('http://localhost/wp-json/');
      expect(apiService.isInitialized()).toBe(true);
    });
  });

  describe('URL Handling', () => {
    it('should normalize URLs correctly', () => {
      expect(apiService.normalizeUrl('http://localhost/wp-json')).toBe('http://localhost/wp-json/');
      expect(apiService.normalizeUrl('http://localhost/wp-json/')).toBe('http://localhost/wp-json/');
      expect(apiService.normalizeUrl('')).toBe('');
    });

    it('should build endpoint URLs correctly', () => {
      apiService.baseUrl = 'http://localhost/wp-json/';
      
      expect(apiService.buildUrl('config')).toBe('http://localhost/wp-json/woo-ai-assistant/v1/config');
      expect(apiService.buildUrl('/config')).toBe('http://localhost/wp-json/woo-ai-assistant/v1/config');
    });
  });

  describe('Request Preparation', () => {
    beforeEach(() => {
      apiService.nonce = 'test-nonce';
    });

    it('should prepare GET requests without Content-Type', () => {
      const config = apiService.prepareRequest('GET');

      expect(config.method).toBe('GET');
      expect(config.headers['Content-Type']).toBeUndefined();
      expect(config.headers['X-WP-Nonce']).toBe('test-nonce');
      expect(config.body).toBeUndefined();
    });

    it('should prepare POST requests with Content-Type and body', () => {
      const data = { message: 'Hello' };
      const config = apiService.prepareRequest('POST', { data });

      expect(config.method).toBe('POST');
      expect(config.headers['Content-Type']).toBe('application/json');
      expect(config.headers['X-WP-Nonce']).toBe('test-nonce');
      expect(config.body).toBe(JSON.stringify(data));
    });
  });

  describe('Response Processing', () => {
    it('should process successful responses', async () => {
      const responseData = { success: true, data: 'test' };
      const mockResponse = {
        ok: true,
        json: jest.fn().mockResolvedValue(responseData)
      };

      const result = await apiService.processResponse(mockResponse, 'http://test.com');

      expect(result).toEqual(responseData);
    });

    it('should handle error responses', async () => {
      const errorData = { message: 'Test error' };
      const mockResponse = {
        ok: false,
        status: 400,
        statusText: 'Bad Request',
        json: jest.fn().mockResolvedValue(errorData)
      };

      await expect(
        apiService.processResponse(mockResponse, 'http://test.com')
      ).rejects.toThrow('Test error');
    });
  });

  describe('Basic HTTP Methods', () => {
    beforeEach(() => {
      apiService.baseUrl = 'http://localhost/wp-json/';
    });

    it('should make GET requests', async () => {
      const responseData = { success: true };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: jest.fn().mockResolvedValue(responseData)
      });

      const result = await apiService.get('/config');

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/woo-ai-assistant/v1/config',
        expect.objectContaining({
          method: 'GET'
        })
      );
      expect(result).toEqual(responseData);
    });

    it('should make POST requests', async () => {
      const requestData = { message: 'Hello' };
      const responseData = { success: true };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: jest.fn().mockResolvedValue(responseData)
      });

      const result = await apiService.post('/chat/message', requestData);

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/woo-ai-assistant/v1/chat/message',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(requestData)
        })
      );
      expect(result).toEqual(responseData);
    });
  });

  describe('Configuration Management', () => {
    beforeEach(() => {
      apiService.baseUrl = 'http://localhost/wp-json/';
    });

    it('should cache configuration', async () => {
      const configData = { features: { chat_enabled: true } };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: jest.fn().mockResolvedValue(configData)
      });

      const config1 = await apiService.loadConfig();
      mockFetch.mockReset(); // Clear mock calls
      const config2 = await apiService.loadConfig();

      expect(config1).toEqual(configData);
      expect(config2).toEqual(configData);
      expect(mockFetch).not.toHaveBeenCalled(); // Second call used cache
    });

    it('should clear cache', () => {
      apiService.configCache = { test: 'data' };
      apiService.configCacheTime = Date.now();

      apiService.clearConfigCache();

      expect(apiService.configCache).toBeNull();
      expect(apiService.configCacheTime).toBe(0);
    });
  });

  describe('Utility Methods', () => {
    it('should check initialization status', () => {
      expect(apiService.isInitialized()).toBe(false);

      apiService.baseUrl = 'http://localhost/wp-json/';
      apiService.config.namespace = 'woo-ai-assistant/v1';

      expect(apiService.isInitialized()).toBe(true);
    });

    it('should provide error messages', () => {
      expect(apiService.getErrorMessage(new ApiError('Test', 401)))
        .toBe('Authentication required. Please refresh the page and try again.');
      
      expect(apiService.getErrorMessage(new ApiError('Test', 404)))
        .toBe('The requested resource was not found.');
      
      expect(apiService.getErrorMessage(new Error('General error')))
        .toBe('General error');
    });
  });
});

describe('ApiError Class', () => {
  it('should create error instances correctly', () => {
    const error = new ApiError('Test error', 400, { field: 'required' });

    expect(error.name).toBe('ApiError');
    expect(error.message).toBe('Test error');
    expect(error.status).toBe(400);
    expect(error.response).toEqual({ field: 'required' });
    expect(error instanceof Error).toBe(true);
  });

  it('should use default values', () => {
    const error = new ApiError('Test error');

    expect(error.status).toBe(500);
    expect(error.response).toBeNull();
  });
});

describe('HTTP_STATUS Constants', () => {
  it('should have correct status codes', () => {
    expect(HTTP_STATUS.OK).toBe(200);
    expect(HTTP_STATUS.CREATED).toBe(201);
    expect(HTTP_STATUS.BAD_REQUEST).toBe(400);
    expect(HTTP_STATUS.UNAUTHORIZED).toBe(401);
    expect(HTTP_STATUS.FORBIDDEN).toBe(403);
    expect(HTTP_STATUS.NOT_FOUND).toBe(404);
    expect(HTTP_STATUS.TOO_MANY_REQUESTS).toBe(429);
    expect(HTTP_STATUS.INTERNAL_ERROR).toBe(500);
    expect(HTTP_STATUS.NOT_IMPLEMENTED).toBe(501);
    expect(HTTP_STATUS.SERVICE_UNAVAILABLE).toBe(503);
  });
});