/**
 * useChat Hook
 * 
 * Custom React hook for managing chat functionality and state
 * with full integration to the new ApiService layer.
 * 
 * @package WooAiAssistant
 * @subpackage Hooks
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import { useState, useEffect, useCallback } from 'react';
import { useApi } from '../services/ApiService';

/**
 * Custom hook for managing chat functionality and state
 * 
 * @param {Object} options - Hook options
 * @param {string} options.apiEndpoint - API endpoint URL (deprecated, now uses ApiService)
 * @param {string} options.nonce - WordPress nonce (deprecated, now handled by ApiService)
 * @param {number} options.userId - User ID
 * @param {Object} options.pageContext - Current page context
 * @param {Function} options.onError - Error callback function
 * @returns {Object} Chat state and functions
 */
export const useChat = ({ 
  apiEndpoint, // Deprecated but kept for compatibility
  nonce, // Deprecated but kept for compatibility
  userId, 
  pageContext, 
  onError 
}) => {
  const [conversation, setConversation] = useState([]);
  const [isTyping, setIsTyping] = useState(false);
  const [connectionStatus, setConnectionStatus] = useState('connecting');
  const [conversationId, setConversationId] = useState(null);
  
  // Streaming state
  const [streamingMessage, setStreamingMessage] = useState(null);
  const [streamingProgress, setStreamingProgress] = useState(0);
  const [isStreamingSupported, setIsStreamingSupported] = useState(false);
  
  // Get the API service from context
  const apiService = useApi();

  // Initialize connection using ApiService
  useEffect(() => {
    const initializeChat = async () => {
      try {
        setConnectionStatus('connecting');
        
        // Test API connectivity
        const isConnected = await apiService.testConnection();
        
        if (isConnected) {
          setConnectionStatus('connected');
          
          // Generate a conversation ID
          setConversationId(`conv_${Date.now()}_${userId}`);
          
          // Check streaming support
          setIsStreamingSupported(apiService.isStreamingAvailable());
        } else {
          throw new Error('API connectivity test failed');
        }
        
      } catch (error) {
        setConnectionStatus('error');
        if (onError) {
          onError({
            message: 'Failed to connect to chat service',
            type: 'connection_error',
            critical: false,
          });
        }
      }
    };

    if (apiService) {
      initializeChat();
    }
  }, [apiService, userId, onError]);

  // Send message function using ApiService with streaming support
  const sendMessage = useCallback(async (messageText) => {
    if (!messageText.trim() || !conversationId || !apiService) {
      return;
    }

    const userMessage = {
      id: `msg_${Date.now()}`,
      content: messageText.trim(),
      type: 'user',
      timestamp: new Date().toISOString(),
    };

    // Add user message to conversation
    setConversation(prev => [...prev, userMessage]);
    setIsTyping(true);

    // Create bot message placeholder for streaming
    const botMessageId = `msg_${Date.now() + 1}`;
    const botMessage = {
      id: botMessageId,
      content: '',
      type: 'bot',
      timestamp: new Date().toISOString(),
      isStreaming: isStreamingSupported
    };

    // Add bot message placeholder
    setConversation(prev => [...prev, botMessage]);
    setStreamingMessage(botMessageId);
    setStreamingProgress(0);

    try {
      let response;
      
      if (isStreamingSupported) {
        // Use streaming message with real-time updates
        response = await apiService.sendStreamingMessage(
          messageText,
          conversationId,
          (chunkInfo) => {
            const { content, progress, isComplete, metadata } = chunkInfo;
            
            // Update the streaming message content
            setConversation(prev => prev.map(msg =>
              msg.id === botMessageId
                ? { ...msg, content, metadata, isStreaming: !isComplete }
                : msg
            ));
            
            // Update progress
            setStreamingProgress(progress || 0);
            
            // Clear streaming state when complete
            if (isComplete) {
              setStreamingMessage(null);
              setStreamingProgress(0);
            }
          },
          pageContext
        );
      } else {
        // Use regular message sending
        response = await apiService.sendMessage(
          messageText, 
          conversationId, 
          pageContext
        );
        
        // Update the bot message with response (non-streaming)
        setConversation(prev => prev.map(msg =>
          msg.id === botMessageId
            ? { 
                ...msg, 
                content: response.response || 'Sorry, I could not process your request.',
                metadata: response.metadata,
                isStreaming: false
              }
            : msg
        ));
      }
      
      // Update conversation ID if provided by server
      if (response.conversationId && response.conversationId !== conversationId) {
        setConversationId(response.conversationId);
      }
      
      // Create AI response message
      const aiResponse = {
        id: `msg_${Date.now() + 1}`,
        content: response.response,
        type: 'bot',
        timestamp: response.timestamp || new Date().toISOString(),
        confidence: response.confidence,
        sources: response.sources,
        metadata: response.metadata
      };

      setConversation(prev => [...prev, aiResponse]);
      setIsTyping(false);

    } catch (error) {
      setIsTyping(false);
      setStreamingMessage(null);
      setStreamingProgress(0);
      
      // Handle streaming error - update message with error
      if (streamingMessage) {
        setConversation(prev => prev.map(msg =>
          msg.id === streamingMessage
            ? { 
                ...msg, 
                content: 'Sorry, there was an error processing your request.',
                isStreaming: false,
                metadata: { error: true }
              }
            : msg
        ));
      }
      
      // Error is already handled by ApiService onError callback
      // But we also update connection status if it's a critical error
      if (error.type === 'auth_error' || error.type === 'server_error') {
        setConnectionStatus('error');
      }
    }
  }, [conversationId, apiService, pageContext, isStreamingSupported, streamingMessage]);

  // Execute action function using ApiService
  const executeAction = useCallback(async (actionType, actionData) => {
    if (!conversationId || !apiService) {
      throw new Error('Chat not initialized');
    }

    try {
      const response = await apiService.executeAction(
        actionType,
        actionData,
        conversationId
      );
      
      return response;

    } catch (error) {
      throw error;
    }
  }, [conversationId, apiService]);

  // Submit rating function using ApiService
  const submitRating = useCallback(async (rating, feedback = '') => {
    if (!conversationId || !apiService) {
      throw new Error('Chat not initialized');
    }

    try {
      const response = await apiService.submitRating(
        conversationId,
        rating,
        feedback
      );
      
      return response;

    } catch (error) {
      throw error;
    }
  }, [conversationId, apiService]);

  // Clear conversation function
  const clearConversation = useCallback(() => {
    setConversation([]);
    setConversationId(`conv_${Date.now()}_${userId}`);
  }, [userId]);

  // Reconnect function using ApiService
  const reconnect = useCallback(async () => {
    if (!apiService) {
      return;
    }

    setConnectionStatus('connecting');
    
    try {
      // Test connection using ApiService
      const isConnected = await apiService.testConnection();
      
      if (isConnected) {
        setConnectionStatus('connected');
      } else {
        throw new Error('Reconnection failed');
      }
    } catch (error) {
      setConnectionStatus('error');
      if (onError) {
        onError({
          message: 'Failed to reconnect',
          type: 'reconnection_error',
          critical: false,
        });
      }
    }
  }, [apiService, onError]);

  // Cancel all requests when unmounting
  useEffect(() => {
    return () => {
      if (apiService) {
        apiService.cancelAllRequests();
      }
    };
  }, [apiService]);

  return {
    conversation,
    isTyping,
    connectionStatus,
    conversationId,
    sendMessage,
    executeAction,
    submitRating,
    clearConversation,
    reconnect,
    // Streaming-related state
    streamingMessage,
    streamingProgress,
    isStreamingSupported,
  };
};