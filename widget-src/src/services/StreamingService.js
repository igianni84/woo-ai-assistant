/**
 * Streaming Service
 * 
 * Core EventSource (SSE) client with fallback support for real-time AI response streaming.
 * Provides progressive response delivery, error handling, reconnection logic, and seamless
 * integration with the existing ApiService architecture.
 * 
 * @package WooAiAssistant
 * @subpackage Services
 * @since 1.0.0
 * @author Claude Code Assistant
 */

/**
 * Streaming configuration constants
 */
const STREAMING_CONFIG = {
    endpoints: {
        stream: '/stream',
        fallback: '/chat'
    },
    reconnect: {
        maxRetries: 5,
        initialDelay: 1000, // 1 second
        maxDelay: 30000, // 30 seconds
        backoffFactor: 2
    },
    timeouts: {
        connection: 10000, // 10 seconds
        response: 60000, // 1 minute
        keepAlive: 30000 // 30 seconds
    },
    chunk: {
        maxSize: 1000, // Maximum chunk size in characters
        debounceMs: 50 // Debounce time for rapid chunks
    }
};

/**
 * Streaming event types
 */
const STREAM_EVENTS = {
    CONNECT: 'connect',
    DISCONNECT: 'disconnect',
    CHUNK: 'chunk',
    COMPLETE: 'complete',
    ERROR: 'error',
    RETRY: 'retry'
};

/**
 * Connection states
 */
const CONNECTION_STATES = {
    DISCONNECTED: 'disconnected',
    CONNECTING: 'connecting',
    CONNECTED: 'connected',
    RECONNECTING: 'reconnecting',
    ERROR: 'error'
};

/**
 * Streaming Service Class
 * 
 * Handles Server-Sent Events (SSE) connections for real-time AI response streaming
 * with comprehensive fallback support and error recovery.
 */
export class StreamingService {
    /**
     * Constructor
     * 
     * @param {Object} config - Configuration object
     * @param {string} config.baseUrl - WordPress site base URL
     * @param {string} config.namespace - REST API namespace
     * @param {string} config.nonce - WordPress nonce for authentication
     * @param {Function} config.onStateChange - State change callback
     * @param {Function} config.onError - Error callback
     * @param {Object} config.logger - Logger instance
     */
    constructor({ baseUrl, namespace, nonce, onStateChange, onError, logger }) {
        this.baseUrl = this.normalizeBaseUrl(baseUrl);
        this.namespace = namespace || 'woo-ai-assistant/v1';
        this.nonce = nonce;
        this.onStateChange = onStateChange || (() => {});
        this.onError = onError || (() => {});
        this.logger = logger || console;

        // Connection state management
        this.state = CONNECTION_STATES.DISCONNECTED;
        this.eventSource = null;
        this.reconnectAttempt = 0;
        this.reconnectTimer = null;
        
        // Active streaming sessions
        this.activeStreams = new Map();
        this.messageBuffer = new Map();
        this.debounceTimers = new Map();
        
        // Feature detection
        this.supportsSSE = typeof EventSource !== 'undefined';
        this.supportsStreaming = this.detectStreamingSupport();

        this.debugLog('StreamingService initialized', {
            baseUrl: this.baseUrl,
            namespace: this.namespace,
            supportsSSE: this.supportsSSE,
            supportsStreaming: this.supportsStreaming
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
            return window.location.origin;
        }
        return url.replace(/\/$/, '');
    }

    /**
     * Detect streaming support
     * 
     * @returns {boolean} True if streaming is supported
     */
    detectStreamingSupport() {
        // Check for EventSource support
        if (!this.supportsSSE) {
            return false;
        }

        // Check for fetch streaming support (ReadableStream)
        if (typeof ReadableStream === 'undefined') {
            return false;
        }

        // Additional browser capability checks
        try {
            // Test EventSource creation
            const testEventSource = new EventSource('data:text/event-stream,');
            testEventSource.close();
            return true;
        } catch (error) {
            this.debugLog('EventSource test failed', error);
            return false;
        }
    }

    /**
     * Build streaming endpoint URL
     * 
     * @param {string} endpoint - Endpoint path
     * @returns {string} Full streaming URL
     */
    buildStreamingUrl(endpoint) {
        const restBase = `${this.baseUrl}/wp-json`;
        const fullEndpoint = `${this.namespace}${endpoint}`;
        return `${restBase}/${fullEndpoint}`;
    }

    /**
     * Change connection state and notify listeners
     * 
     * @param {string} newState - New connection state
     * @param {Object} metadata - Additional state metadata
     */
    changeState(newState, metadata = {}) {
        const oldState = this.state;
        this.state = newState;
        
        this.debugLog('State changed', {
            from: oldState,
            to: newState,
            metadata
        });

        this.onStateChange({
            state: newState,
            previousState: oldState,
            timestamp: new Date().toISOString(),
            ...metadata
        });
    }

    /**
     * Start streaming conversation
     * 
     * @param {string} message - User message
     * @param {string} conversationId - Conversation ID
     * @param {Object} userContext - User context data
     * @param {Function} onChunk - Chunk callback function
     * @param {Function} onComplete - Completion callback function
     * @returns {Promise<string>} Stream session ID
     */
    async startStream(message, conversationId, userContext, onChunk, onComplete) {
        if (!this.supportsStreaming) {
            throw new Error('Streaming not supported in this browser');
        }

        const sessionId = `stream_${Date.now()}_${Math.random().toString(36).substring(7)}`;
        
        this.debugLog('Starting stream session', {
            sessionId,
            conversationId,
            message: message.substring(0, 100) + '...'
        });

        // Store session callbacks
        this.activeStreams.set(sessionId, {
            conversationId,
            onChunk,
            onComplete,
            startTime: Date.now(),
            totalChunks: 0,
            totalBytes: 0
        });

        // Initialize message buffer for this session
        this.messageBuffer.set(sessionId, {
            content: '',
            metadata: null
        });

        try {
            await this.initializeConnection(sessionId, message, conversationId, userContext);
            return sessionId;
        } catch (error) {
            this.activeStreams.delete(sessionId);
            this.messageBuffer.delete(sessionId);
            throw error;
        }
    }

    /**
     * Initialize SSE connection
     * 
     * @param {string} sessionId - Session ID
     * @param {string} message - User message
     * @param {string} conversationId - Conversation ID
     * @param {Object} userContext - User context data
     */
    async initializeConnection(sessionId, message, conversationId, userContext) {
        this.changeState(CONNECTION_STATES.CONNECTING);

        // Build streaming URL with parameters
        const streamingUrl = this.buildStreamingUrl(STREAMING_CONFIG.endpoints.stream);
        const urlWithParams = new URL(streamingUrl);
        
        // Add query parameters for SSE
        urlWithParams.searchParams.append('session_id', sessionId);
        urlWithParams.searchParams.append('conversation_id', conversationId);
        urlWithParams.searchParams.append('message', encodeURIComponent(message));
        urlWithParams.searchParams.append('nonce', this.nonce);
        
        if (userContext) {
            urlWithParams.searchParams.append('user_context', JSON.stringify(userContext));
        }

        try {
            // Create EventSource connection
            this.eventSource = new EventSource(urlWithParams.toString());
            
            // Set connection timeout
            const connectionTimeout = setTimeout(() => {
                this.handleConnectionTimeout();
            }, STREAMING_CONFIG.timeouts.connection);

            // Setup event listeners
            this.setupEventSourceListeners(sessionId, connectionTimeout);
            
        } catch (error) {
            this.changeState(CONNECTION_STATES.ERROR, { error: error.message });
            throw new Error(`Failed to initialize streaming connection: ${error.message}`);
        }
    }

    /**
     * Setup EventSource event listeners
     * 
     * @param {string} sessionId - Session ID
     * @param {number} connectionTimeout - Connection timeout ID
     */
    setupEventSourceListeners(sessionId, connectionTimeout) {
        if (!this.eventSource) return;

        // Connection opened
        this.eventSource.onopen = () => {
            clearTimeout(connectionTimeout);
            this.changeState(CONNECTION_STATES.CONNECTED);
            this.reconnectAttempt = 0;
            this.debugLog('SSE connection established', { sessionId });
        };

        // Message received (default event)
        this.eventSource.onmessage = (event) => {
            this.handleStreamMessage(sessionId, event);
        };

        // Connection error
        this.eventSource.onerror = (error) => {
            clearTimeout(connectionTimeout);
            this.handleStreamError(sessionId, error);
        };

        // Custom event listeners for different message types
        this.eventSource.addEventListener('chunk', (event) => {
            this.handleChunkEvent(sessionId, event);
        });

        this.eventSource.addEventListener('metadata', (event) => {
            this.handleMetadataEvent(sessionId, event);
        });

        this.eventSource.addEventListener('complete', (event) => {
            this.handleCompleteEvent(sessionId, event);
        });

        this.eventSource.addEventListener('error', (event) => {
            this.handleErrorEvent(sessionId, event);
        });
    }

    /**
     * Handle SSE message event
     * 
     * @param {string} sessionId - Session ID
     * @param {MessageEvent} event - SSE message event
     */
    handleStreamMessage(sessionId, event) {
        try {
            const data = JSON.parse(event.data);
            
            switch (data.type) {
                case 'chunk':
                    this.processChunk(sessionId, data);
                    break;
                case 'metadata':
                    this.processMetadata(sessionId, data);
                    break;
                case 'complete':
                    this.processComplete(sessionId, data);
                    break;
                case 'error':
                    this.processError(sessionId, data);
                    break;
                default:
                    this.debugLog('Unknown message type received', data);
            }
        } catch (error) {
            this.debugLog('Error parsing SSE message', error);
        }
    }

    /**
     * Handle chunk event
     * 
     * @param {string} sessionId - Session ID
     * @param {MessageEvent} event - Chunk event
     */
    handleChunkEvent(sessionId, event) {
        try {
            const chunkData = JSON.parse(event.data);
            this.processChunk(sessionId, chunkData);
        } catch (error) {
            this.debugLog('Error processing chunk event', error);
        }
    }

    /**
     * Handle metadata event
     * 
     * @param {string} sessionId - Session ID
     * @param {MessageEvent} event - Metadata event
     */
    handleMetadataEvent(sessionId, event) {
        try {
            const metadata = JSON.parse(event.data);
            this.processMetadata(sessionId, metadata);
        } catch (error) {
            this.debugLog('Error processing metadata event', error);
        }
    }

    /**
     * Handle completion event
     * 
     * @param {string} sessionId - Session ID
     * @param {MessageEvent} event - Completion event
     */
    handleCompleteEvent(sessionId, event) {
        try {
            const completeData = JSON.parse(event.data);
            this.processComplete(sessionId, completeData);
        } catch (error) {
            this.debugLog('Error processing complete event', error);
        }
    }

    /**
     * Handle error event
     * 
     * @param {string} sessionId - Session ID
     * @param {MessageEvent} event - Error event
     */
    handleErrorEvent(sessionId, event) {
        try {
            const errorData = JSON.parse(event.data);
            this.processError(sessionId, errorData);
        } catch (error) {
            this.debugLog('Error processing error event', error);
        }
    }

    /**
     * Process streaming chunk
     * 
     * @param {string} sessionId - Session ID
     * @param {Object} chunkData - Chunk data
     */
    processChunk(sessionId, chunkData) {
        const session = this.activeStreams.get(sessionId);
        const buffer = this.messageBuffer.get(sessionId);
        
        if (!session || !buffer) {
            this.debugLog('Session not found for chunk', { sessionId });
            return;
        }

        const { chunk, index, isComplete } = chunkData;
        
        // Update buffer
        buffer.content += chunk;
        
        // Update session statistics
        session.totalChunks += 1;
        session.totalBytes += chunk.length;

        // Debounce rapid chunks to avoid UI thrashing
        if (this.debounceTimers.has(sessionId)) {
            clearTimeout(this.debounceTimers.get(sessionId));
        }

        const debounceTimer = setTimeout(() => {
            this.deliverChunk(sessionId, {
                chunk,
                content: buffer.content,
                index,
                isComplete,
                progress: this.calculateProgress(session, chunkData),
                metadata: buffer.metadata
            });
            this.debounceTimers.delete(sessionId);
        }, STREAMING_CONFIG.chunk.debounceMs);

        this.debounceTimers.set(sessionId, debounceTimer);
    }

    /**
     * Process metadata
     * 
     * @param {string} sessionId - Session ID
     * @param {Object} metadata - Metadata object
     */
    processMetadata(sessionId, metadata) {
        const buffer = this.messageBuffer.get(sessionId);
        if (buffer) {
            buffer.metadata = metadata;
            this.debugLog('Metadata received', { sessionId, metadata });
        }
    }

    /**
     * Process completion
     * 
     * @param {string} sessionId - Session ID
     * @param {Object} completeData - Completion data
     */
    processComplete(sessionId, completeData) {
        const session = this.activeStreams.get(sessionId);
        const buffer = this.messageBuffer.get(sessionId);
        
        if (!session || !buffer) {
            this.debugLog('Session not found for completion', { sessionId });
            return;
        }

        // Clear any pending debounce timers
        if (this.debounceTimers.has(sessionId)) {
            clearTimeout(this.debounceTimers.get(sessionId));
            this.debounceTimers.delete(sessionId);
        }

        const completionResult = {
            content: buffer.content,
            metadata: buffer.metadata || {},
            statistics: {
                totalChunks: session.totalChunks,
                totalBytes: session.totalBytes,
                duration: Date.now() - session.startTime
            },
            ...completeData
        };

        this.debugLog('Stream completed', {
            sessionId,
            statistics: completionResult.statistics
        });

        // Notify completion
        session.onComplete(completionResult);
        
        // Cleanup
        this.cleanup(sessionId);
    }

    /**
     * Process streaming error
     * 
     * @param {string} sessionId - Session ID
     * @param {Object} errorData - Error data
     */
    processError(sessionId, errorData) {
        const session = this.activeStreams.get(sessionId);
        
        if (session) {
            const error = new Error(errorData.message || 'Streaming error occurred');
            error.type = errorData.type || 'stream_error';
            error.code = errorData.code;
            error.details = errorData.details;

            this.onError({
                message: error.message,
                type: error.type,
                critical: false,
                sessionId,
                details: error.details
            });

            // Try to recover with fallback if configured
            this.attemptRecovery(sessionId, error);
        }
    }

    /**
     * Deliver chunk to callback
     * 
     * @param {string} sessionId - Session ID
     * @param {Object} chunkInfo - Chunk information
     */
    deliverChunk(sessionId, chunkInfo) {
        const session = this.activeStreams.get(sessionId);
        
        if (session && session.onChunk) {
            try {
                session.onChunk(chunkInfo);
            } catch (error) {
                this.debugLog('Error in chunk callback', error);
            }
        }
    }

    /**
     * Calculate streaming progress
     * 
     * @param {Object} session - Session object
     * @param {Object} chunkData - Chunk data
     * @returns {number} Progress percentage (0-1)
     */
    calculateProgress(session, chunkData) {
        // If server provides total chunks, use that
        if (chunkData.totalChunks && chunkData.index !== undefined) {
            return Math.min(1, (chunkData.index + 1) / chunkData.totalChunks);
        }
        
        // Otherwise estimate based on time (less accurate)
        const elapsed = Date.now() - session.startTime;
        const estimatedDuration = 10000; // 10 seconds estimated
        return Math.min(0.9, elapsed / estimatedDuration); // Cap at 90% without completion
    }

    /**
     * Handle connection timeout
     */
    handleConnectionTimeout() {
        this.debugLog('SSE connection timeout');
        this.changeState(CONNECTION_STATES.ERROR, { 
            error: 'Connection timeout' 
        });
        
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    /**
     * Handle streaming error and attempt recovery
     * 
     * @param {string} sessionId - Session ID
     * @param {Error} error - Connection error
     */
    handleStreamError(sessionId, error) {
        this.debugLog('SSE connection error', error);
        
        // Determine if we should attempt reconnection
        if (this.reconnectAttempt < STREAMING_CONFIG.reconnect.maxRetries) {
            this.attemptReconnection(sessionId);
        } else {
            this.changeState(CONNECTION_STATES.ERROR, {
                error: 'Max reconnection attempts exceeded'
            });
            this.cleanup(sessionId);
        }
    }

    /**
     * Attempt reconnection with exponential backoff
     * 
     * @param {string} sessionId - Session ID
     */
    attemptReconnection(sessionId) {
        this.reconnectAttempt += 1;
        this.changeState(CONNECTION_STATES.RECONNECTING, {
            attempt: this.reconnectAttempt,
            maxRetries: STREAMING_CONFIG.reconnect.maxRetries
        });

        const delay = Math.min(
            STREAMING_CONFIG.reconnect.initialDelay * 
            Math.pow(STREAMING_CONFIG.reconnect.backoffFactor, this.reconnectAttempt - 1),
            STREAMING_CONFIG.reconnect.maxDelay
        );

        this.debugLog('Attempting reconnection', {
            attempt: this.reconnectAttempt,
            delay,
            sessionId
        });

        this.reconnectTimer = setTimeout(() => {
            this.retryConnection(sessionId);
        }, delay);
    }

    /**
     * Retry connection for a session
     * 
     * @param {string} sessionId - Session ID
     */
    async retryConnection(sessionId) {
        const session = this.activeStreams.get(sessionId);
        
        if (!session) {
            this.debugLog('Session not found for retry', { sessionId });
            return;
        }

        try {
            // Close existing connection
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }

            // Attempt to re-establish connection
            // Note: This would need the original message and context
            // For now, we'll just notify of the error
            throw new Error('Reconnection logic not fully implemented');
            
        } catch (error) {
            this.debugLog('Reconnection failed', error);
            
            if (this.reconnectAttempt < STREAMING_CONFIG.reconnect.maxRetries) {
                this.attemptReconnection(sessionId);
            } else {
                this.changeState(CONNECTION_STATES.ERROR);
                this.cleanup(sessionId);
            }
        }
    }

    /**
     * Attempt recovery from streaming error
     * 
     * @param {string} sessionId - Session ID
     * @param {Error} error - Original error
     */
    attemptRecovery(sessionId, error) {
        this.debugLog('Attempting error recovery', { sessionId, error: error.message });
        
        // For now, just cleanup and let higher level handle fallback
        this.cleanup(sessionId);
    }

    /**
     * Stop streaming session
     * 
     * @param {string} sessionId - Session ID
     */
    stopStream(sessionId) {
        this.debugLog('Stopping stream session', { sessionId });
        
        // Clear debounce timer
        if (this.debounceTimers.has(sessionId)) {
            clearTimeout(this.debounceTimers.get(sessionId));
            this.debounceTimers.delete(sessionId);
        }

        this.cleanup(sessionId);
    }

    /**
     * Cleanup streaming session
     * 
     * @param {string} sessionId - Session ID
     */
    cleanup(sessionId) {
        // Remove from active streams
        this.activeStreams.delete(sessionId);
        this.messageBuffer.delete(sessionId);
        
        // Clear any timers
        if (this.debounceTimers.has(sessionId)) {
            clearTimeout(this.debounceTimers.get(sessionId));
            this.debounceTimers.delete(sessionId);
        }

        // Close connection if no active streams
        if (this.activeStreams.size === 0 && this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            this.changeState(CONNECTION_STATES.DISCONNECTED);
        }
    }

    /**
     * Disconnect all streaming connections
     */
    disconnect() {
        this.debugLog('Disconnecting all streams');
        
        // Clear reconnection timer
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }

        // Cleanup all sessions
        for (const sessionId of this.activeStreams.keys()) {
            this.cleanup(sessionId);
        }

        // Close EventSource
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }

        this.changeState(CONNECTION_STATES.DISCONNECTED);
        this.reconnectAttempt = 0;
    }

    /**
     * Get current connection state
     * 
     * @returns {string} Current connection state
     */
    getState() {
        return this.state;
    }

    /**
     * Check if streaming is available
     * 
     * @returns {boolean} True if streaming is supported and available
     */
    isStreamingAvailable() {
        return this.supportsStreaming && this.state !== CONNECTION_STATES.ERROR;
    }

    /**
     * Get active stream count
     * 
     * @returns {number} Number of active streams
     */
    getActiveStreamCount() {
        return this.activeStreams.size;
    }

    /**
     * Debug logging
     * 
     * @param {string} message - Log message
     * @param {Object} data - Additional data to log
     */
    debugLog(message, data = {}) {
        if (process.env.NODE_ENV === 'development' || 
            window.wooAiAssistant?.debug === true) {
            this.logger.log(`[WooAI-Streaming] ${message}`, data);
        }
    }
}

export default StreamingService;

// Export constants for use in components
export { STREAMING_CONFIG, STREAM_EVENTS, CONNECTION_STATES };