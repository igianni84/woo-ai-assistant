/**
 * StreamingService Tests
 * 
 * Comprehensive test suite for the StreamingService class that handles
 * Server-Sent Events (SSE) connections and real-time response streaming.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import { StreamingService, STREAMING_CONFIG, STREAM_EVENTS, CONNECTION_STATES } from './StreamingService';

// Mock EventSource
class MockEventSource {
  constructor(url) {
    this.url = url;
    this.readyState = 0;
    this.onopen = null;
    this.onmessage = null;
    this.onerror = null;
    this.addEventListener = jest.fn((event, handler) => {
      this[`on${event}`] = handler;
    });
    this.removeEventListener = jest.fn();
    this.close = jest.fn(() => {
      this.readyState = 2;
    });
    
    // Auto-trigger open after creation
    setTimeout(() => {
      this.readyState = 1;
      if (this.onopen) {
        this.onopen({ type: 'open' });
      }
    }, 10);
  }
}

// Mock fetch for browser capability detection
global.fetch = jest.fn();
global.EventSource = MockEventSource;
global.ReadableStream = class ReadableStream {};

describe('StreamingService', () => {
  let streamingService;
  let mockOnStateChange;
  let mockOnError;
  let mockLogger;

  beforeEach(() => {
    mockOnStateChange = jest.fn();
    mockOnError = jest.fn();
    mockLogger = {
      log: jest.fn()
    };

    streamingService = new StreamingService({
      baseUrl: 'http://localhost:8888',
      namespace: 'woo-ai-assistant/v1',
      nonce: 'test-nonce-123',
      onStateChange: mockOnStateChange,
      onError: mockOnError,
      logger: mockLogger
    });

    jest.clearAllMocks();
  });

  afterEach(() => {
    if (streamingService) {
      streamingService.disconnect();
    }
  });

  describe('Constructor and Initialization', () => {
    it('should initialize with correct configuration', () => {
      expect(streamingService.baseUrl).toBe('http://localhost:8888');
      expect(streamingService.namespace).toBe('woo-ai-assistant/v1');
      expect(streamingService.nonce).toBe('test-nonce-123');
      expect(streamingService.state).toBe(CONNECTION_STATES.DISCONNECTED);
    });

    it('should detect streaming support correctly', () => {
      expect(streamingService.supportsSSE).toBe(true);
      expect(streamingService.supportsStreaming).toBe(true);
    });

    it('should normalize base URL correctly', () => {
      const service = new StreamingService({
        baseUrl: 'http://localhost:8888/',
        onStateChange: jest.fn(),
        onError: jest.fn()
      });
      
      expect(service.baseUrl).toBe('http://localhost:8888');
    });

    it('should handle missing base URL', () => {
      // Mock window.location
      Object.defineProperty(window, 'location', {
        value: {
          origin: 'http://test.local'
        },
        writable: true
      });

      const service = new StreamingService({
        onStateChange: jest.fn(),
        onError: jest.fn()
      });
      
      expect(service.baseUrl).toBe('http://test.local');
    });
  });

  describe('URL Building', () => {
    it('should build correct streaming URL', () => {
      const url = streamingService.buildStreamingUrl('/stream');
      expect(url).toBe('http://localhost:8888/wp-json/woo-ai-assistant/v1/stream');
    });

    it('should handle different endpoints', () => {
      const chatUrl = streamingService.buildStreamingUrl('/chat');
      expect(chatUrl).toBe('http://localhost:8888/wp-json/woo-ai-assistant/v1/chat');
    });
  });

  describe('State Management', () => {
    it('should change state and notify listeners', () => {
      streamingService.changeState(CONNECTION_STATES.CONNECTING);
      
      expect(streamingService.state).toBe(CONNECTION_STATES.CONNECTING);
      expect(mockOnStateChange).toHaveBeenCalledWith(
        expect.objectContaining({
          state: CONNECTION_STATES.CONNECTING,
          previousState: CONNECTION_STATES.DISCONNECTED,
          timestamp: expect.any(String)
        })
      );
    });

    it('should include metadata in state changes', () => {
      const metadata = { attempt: 1 };
      streamingService.changeState(CONNECTION_STATES.RECONNECTING, metadata);
      
      expect(mockOnStateChange).toHaveBeenCalledWith(
        expect.objectContaining({
          state: CONNECTION_STATES.RECONNECTING,
          attempt: 1
        })
      );
    });
  });

  describe('Stream Session Management', () => {
    it('should start streaming session successfully', async () => {
      const message = 'Test message';
      const conversationId = 'conv-123';
      const userContext = { page: 'product' };
      const onChunk = jest.fn();
      const onComplete = jest.fn();

      const sessionId = await streamingService.startStream(
        message,
        conversationId,
        userContext,
        onChunk,
        onComplete
      );

      expect(sessionId).toMatch(/^stream_\d+_[a-z0-9]+$/);
      expect(streamingService.activeStreams.has(sessionId)).toBe(true);
      expect(streamingService.messageBuffer.has(sessionId)).toBe(true);
    });

    it('should reject streaming when not supported', async () => {
      streamingService.supportsStreaming = false;
      
      await expect(
        streamingService.startStream('test', 'conv-123', {}, jest.fn(), jest.fn())
      ).rejects.toThrow('Streaming not supported in this browser');
    });

    it('should handle EventSource creation failure', async () => {
      // Mock EventSource to throw
      global.EventSource = class {
        constructor() {
          throw new Error('EventSource not supported');
        }
      };

      await expect(
        streamingService.startStream('test', 'conv-123', {}, jest.fn(), jest.fn())
      ).rejects.toThrow('Failed to initialize streaming connection');

      // Restore mock
      global.EventSource = MockEventSource;
    });
  });

  describe('Message Processing', () => {
    let sessionId;
    let mockOnChunk;
    let mockOnComplete;

    beforeEach(async () => {
      mockOnChunk = jest.fn();
      mockOnComplete = jest.fn();
      
      sessionId = await streamingService.startStream(
        'test message',
        'conv-123',
        { page: 'test' },
        mockOnChunk,
        mockOnComplete
      );
    });

    it('should process chunk data correctly', () => {
      const chunkData = {
        chunk: 'Hello',
        index: 0,
        isComplete: false
      };

      streamingService.processChunk(sessionId, chunkData);

      const buffer = streamingService.messageBuffer.get(sessionId);
      expect(buffer.content).toBe('Hello');

      const session = streamingService.activeStreams.get(sessionId);
      expect(session.totalChunks).toBe(1);
      expect(session.totalBytes).toBe(5);
    });

    it('should handle metadata correctly', () => {
      const metadata = {
        confidence: 0.95,
        sources: ['kb-1', 'kb-2']
      };

      streamingService.processMetadata(sessionId, metadata);

      const buffer = streamingService.messageBuffer.get(sessionId);
      expect(buffer.metadata).toEqual(metadata);
    });

    it('should process completion correctly', () => {
      // Add some content first
      streamingService.processChunk(sessionId, {
        chunk: 'Complete message',
        index: 0,
        isComplete: false
      });

      const completeData = {
        finalStats: { tokens: 100 }
      };

      streamingService.processComplete(sessionId, completeData);

      expect(mockOnComplete).toHaveBeenCalledWith(
        expect.objectContaining({
          content: 'Complete message',
          statistics: expect.objectContaining({
            totalChunks: 1,
            totalBytes: 16,
            duration: expect.any(Number)
          }),
          finalStats: { tokens: 100 }
        })
      );

      // Session should be cleaned up
      expect(streamingService.activeStreams.has(sessionId)).toBe(false);
      expect(streamingService.messageBuffer.has(sessionId)).toBe(false);
    });

    it('should handle errors gracefully', () => {
      const errorData = {
        message: 'Stream processing failed',
        type: 'processing_error',
        code: 'STREAM_001'
      };

      streamingService.processError(sessionId, errorData);

      expect(mockOnError).toHaveBeenCalledWith(
        expect.objectContaining({
          message: 'Stream processing failed',
          type: 'processing_error',
          sessionId: sessionId
        })
      );
    });
  });

  describe('Chunk Debouncing', () => {
    let sessionId;
    let mockOnChunk;

    beforeEach(async () => {
      mockOnChunk = jest.fn();
      
      sessionId = await streamingService.startStream(
        'test message',
        'conv-123',
        {},
        mockOnChunk,
        jest.fn()
      );
    });

    it('should debounce rapid chunks', (done) => {
      // Send multiple rapid chunks
      streamingService.processChunk(sessionId, {
        chunk: 'Hello ',
        index: 0,
        isComplete: false
      });

      streamingService.processChunk(sessionId, {
        chunk: 'World',
        index: 1,
        isComplete: false
      });

      // Only the last chunk should be delivered after debounce
      setTimeout(() => {
        expect(mockOnChunk).toHaveBeenCalledTimes(1);
        expect(mockOnChunk).toHaveBeenLastCalledWith(
          expect.objectContaining({
            chunk: 'World',
            content: 'Hello World'
          })
        );
        done();
      }, STREAMING_CONFIG.chunk.debounceMs + 10);
    });
  });

  describe('Connection Management', () => {
    it('should handle connection timeout', () => {
      jest.useFakeTimers();
      
      streamingService.handleConnectionTimeout();
      
      expect(streamingService.state).toBe(CONNECTION_STATES.ERROR);
      expect(mockOnStateChange).toHaveBeenCalledWith(
        expect.objectContaining({
          state: CONNECTION_STATES.ERROR,
          error: 'Connection timeout'
        })
      );

      jest.useRealTimers();
    });

    it('should attempt reconnection on error', () => {
      jest.useFakeTimers();
      
      streamingService.reconnectAttempt = 0;
      streamingService.handleStreamError('session-123', new Error('Connection lost'));
      
      expect(streamingService.state).toBe(CONNECTION_STATES.RECONNECTING);
      expect(streamingService.reconnectAttempt).toBe(1);

      jest.useRealTimers();
    });

    it('should stop reconnection after max attempts', () => {
      streamingService.reconnectAttempt = STREAMING_CONFIG.reconnect.maxRetries;
      streamingService.handleStreamError('session-123', new Error('Connection lost'));
      
      expect(streamingService.state).toBe(CONNECTION_STATES.ERROR);
    });
  });

  describe('Progress Calculation', () => {
    let session;

    beforeEach(() => {
      session = {
        startTime: Date.now() - 5000, // 5 seconds ago
        totalChunks: 0,
        totalBytes: 0
      };
    });

    it('should calculate progress from chunk index', () => {
      const chunkData = {
        index: 2,
        totalChunks: 5
      };

      const progress = streamingService.calculateProgress(session, chunkData);
      expect(progress).toBe(0.6); // (2 + 1) / 5
    });

    it('should estimate progress from time when no total chunks', () => {
      const chunkData = { index: undefined };
      session.startTime = Date.now() - 2000; // 2 seconds ago

      const progress = streamingService.calculateProgress(session, chunkData);
      expect(progress).toBeLessThan(0.9);
      expect(progress).toBeGreaterThan(0);
    });

    it('should cap estimated progress at 90%', () => {
      const chunkData = { index: undefined };
      session.startTime = Date.now() - 20000; // 20 seconds ago

      const progress = streamingService.calculateProgress(session, chunkData);
      expect(progress).toBe(0.9);
    });
  });

  describe('Session Cleanup', () => {
    let sessionId;

    beforeEach(async () => {
      sessionId = await streamingService.startStream(
        'test',
        'conv-123',
        {},
        jest.fn(),
        jest.fn()
      );
    });

    it('should cleanup session correctly', () => {
      expect(streamingService.activeStreams.has(sessionId)).toBe(true);
      expect(streamingService.messageBuffer.has(sessionId)).toBe(true);

      streamingService.cleanup(sessionId);

      expect(streamingService.activeStreams.has(sessionId)).toBe(false);
      expect(streamingService.messageBuffer.has(sessionId)).toBe(false);
    });

    it('should close EventSource when no active streams', () => {
      const mockEventSource = streamingService.eventSource;
      streamingService.cleanup(sessionId);

      expect(mockEventSource.close).toHaveBeenCalled();
      expect(streamingService.eventSource).toBe(null);
      expect(streamingService.state).toBe(CONNECTION_STATES.DISCONNECTED);
    });

    it('should stop streaming session', () => {
      jest.spyOn(streamingService, 'cleanup');
      
      streamingService.stopStream(sessionId);
      
      expect(streamingService.cleanup).toHaveBeenCalledWith(sessionId);
    });
  });

  describe('Disconnect', () => {
    it('should disconnect all streams', async () => {
      const sessionId1 = await streamingService.startStream(
        'test1', 'conv-1', {}, jest.fn(), jest.fn()
      );
      const sessionId2 = await streamingService.startStream(
        'test2', 'conv-2', {}, jest.fn(), jest.fn()
      );

      expect(streamingService.activeStreams.size).toBe(2);

      streamingService.disconnect();

      expect(streamingService.activeStreams.size).toBe(0);
      expect(streamingService.messageBuffer.size).toBe(0);
      expect(streamingService.state).toBe(CONNECTION_STATES.DISCONNECTED);
    });

    it('should clear reconnection timer', () => {
      streamingService.reconnectTimer = setTimeout(() => {}, 1000);
      const timerId = streamingService.reconnectTimer;
      
      jest.spyOn(global, 'clearTimeout');
      
      streamingService.disconnect();
      
      expect(clearTimeout).toHaveBeenCalledWith(timerId);
      expect(streamingService.reconnectTimer).toBe(null);
    });
  });

  describe('Utility Methods', () => {
    it('should return current state', () => {
      streamingService.state = CONNECTION_STATES.CONNECTED;
      expect(streamingService.getState()).toBe(CONNECTION_STATES.CONNECTED);
    });

    it('should check streaming availability', () => {
      streamingService.supportsStreaming = true;
      streamingService.state = CONNECTION_STATES.CONNECTED;
      expect(streamingService.isStreamingAvailable()).toBe(true);

      streamingService.state = CONNECTION_STATES.ERROR;
      expect(streamingService.isStreamingAvailable()).toBe(false);
    });

    it('should return active stream count', async () => {
      expect(streamingService.getActiveStreamCount()).toBe(0);
      
      await streamingService.startStream(
        'test', 'conv-123', {}, jest.fn(), jest.fn()
      );
      
      expect(streamingService.getActiveStreamCount()).toBe(1);
    });

    it('should handle debug logging', () => {
      // Test development mode
      process.env.NODE_ENV = 'development';
      streamingService.debugLog('Test message', { data: 'test' });
      expect(mockLogger.log).toHaveBeenCalledWith(
        '[WooAI-Streaming] Test message',
        { data: 'test' }
      );

      // Test with debug flag
      process.env.NODE_ENV = 'production';
      window.wooAiAssistant = { debug: true };
      streamingService.debugLog('Debug message');
      expect(mockLogger.log).toHaveBeenCalledWith(
        '[WooAI-Streaming] Debug message',
        {}
      );

      // Cleanup
      delete window.wooAiAssistant;
    });
  });

  describe('Error Handling', () => {
    it('should handle EventSource errors gracefully', async () => {
      const sessionId = await streamingService.startStream(
        'test', 'conv-123', {}, jest.fn(), jest.fn()
      );

      const error = new Error('Network error');
      streamingService.handleStreamError(sessionId, error);

      expect(streamingService.state).toBe(CONNECTION_STATES.RECONNECTING);
      expect(streamingService.reconnectAttempt).toBe(1);
    });

    it('should handle invalid session IDs gracefully', () => {
      expect(() => {
        streamingService.processChunk('invalid-session', { chunk: 'test' });
      }).not.toThrow();

      expect(() => {
        streamingService.processComplete('invalid-session', {});
      }).not.toThrow();
    });

    it('should handle malformed message data', () => {
      const mockEvent = {
        data: 'invalid-json'
      };

      expect(() => {
        streamingService.handleStreamMessage('session-123', mockEvent);
      }).not.toThrow();
    });
  });

  describe('Browser Compatibility', () => {
    it('should detect lack of EventSource support', () => {
      const originalEventSource = global.EventSource;
      delete global.EventSource;

      const service = new StreamingService({
        onStateChange: jest.fn(),
        onError: jest.fn()
      });

      expect(service.supportsSSE).toBe(false);
      expect(service.supportsStreaming).toBe(false);

      // Restore
      global.EventSource = originalEventSource;
    });

    it('should detect lack of ReadableStream support', () => {
      const originalReadableStream = global.ReadableStream;
      delete global.ReadableStream;

      const service = new StreamingService({
        onStateChange: jest.fn(),
        onError: jest.fn()
      });

      expect(service.supportsStreaming).toBe(false);

      // Restore
      global.ReadableStream = originalReadableStream;
    });
  });

  describe('Constants Export', () => {
    it('should export correct configuration constants', () => {
      expect(STREAMING_CONFIG).toBeDefined();
      expect(STREAMING_CONFIG.reconnect.maxRetries).toBe(5);
      expect(STREAMING_CONFIG.timeouts.connection).toBe(10000);
    });

    it('should export correct event constants', () => {
      expect(STREAM_EVENTS).toBeDefined();
      expect(STREAM_EVENTS.CONNECT).toBe('connect');
      expect(STREAM_EVENTS.CHUNK).toBe('chunk');
    });

    it('should export correct connection state constants', () => {
      expect(CONNECTION_STATES).toBeDefined();
      expect(CONNECTION_STATES.CONNECTING).toBe('connecting');
      expect(CONNECTION_STATES.CONNECTED).toBe('connected');
    });
  });
});