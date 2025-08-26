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

  // Send message function using ApiService
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

    try {
      // Use ApiService to send message
      const response = await apiService.sendMessage(
        messageText, 
        conversationId, 
        pageContext
      );
      
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
      
      // Error is already handled by ApiService onError callback
      // But we also update connection status if it's a critical error
      if (error.type === 'auth_error' || error.type === 'server_error') {
        setConnectionStatus('error');
      }
    }
  }, [conversationId, apiService, pageContext]);

  // Send streaming message function using ApiService
  const sendStreamingMessage = useCallback(async (messageText, onChunk) => {
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

    try {
      // Use ApiService streaming
      const response = await apiService.sendStreamingMessage(
        messageText, 
        conversationId, 
        onChunk,
        pageContext
      );
      
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

      return response;

    } catch (error) {
      setIsTyping(false);
      
      // Error is already handled by ApiService onError callback
      if (error.type === 'auth_error' || error.type === 'server_error') {
        setConnectionStatus('error');
      }
      
      throw error;
    }
  }, [conversationId, apiService, pageContext]);

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
    sendStreamingMessage,
    executeAction,
    submitRating,
    clearConversation,
    reconnect,
  };
};