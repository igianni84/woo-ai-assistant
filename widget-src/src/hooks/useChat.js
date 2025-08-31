/**
 * useChat Hook
 *
 * Custom hook for managing chat state, messages, and API communication.
 * Provides a clean interface for chat functionality.
 *
 * @package WooAiAssistant
 * @subpackage Hooks
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import { useState, useCallback, useEffect, useRef, useMemo } from 'react';

/**
 * Chat hook for managing conversation state
 *
 * @param {Object} config - Configuration object
 * @param {Object} config.userContext - User context data
 * @param {Object} config.wooCommerceData - WooCommerce data
 * @param {Object} config.config - Widget configuration
 * @returns {Object} Chat state and methods
 */
export const useChat = ({ userContext = {}, wooCommerceData = {}, config = {} }) => {
  // State management
  const [messages, setMessages] = useState([]);
  const [isTyping, setIsTyping] = useState(false);
  const [isConnected, setIsConnected] = useState(false);
  const [conversationId, setConversationId] = useState(null);
  const [error, setError] = useState(null);
  const [isLoading, setIsLoading] = useState(false);

  // Refs for managing async operations
  const abortControllerRef = useRef(null);
  const retryCountRef = useRef(0);
  const lastMessageIdRef = useRef(0);

  // Configuration with defaults
  const chatConfig = useMemo(() => ({
    apiUrl: config.apiUrl || '/wp-json/woo-ai-assistant/v1',
    nonce: config.nonce || '',
    maxRetries: config.maxRetries || 3,
    retryDelay: config.retryDelay || 1000,
    typingDelay: config.typingDelay || 500,
    ...config
  }), [config]);

  // Initialize connection on mount
  useEffect(() => {
    initializeConnection();

    return () => {
      // Cleanup on unmount
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, []);

  /**
   * Initialize connection to chat service
   */
  const initializeConnection = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      // Simulate connection initialization
      // In Task 4.3, this will make actual API calls
      await new Promise(resolve => setTimeout(resolve, 500));

      setIsConnected(true);
      setConversationId(generateConversationId());

      // Add welcome message if no messages exist
      if (messages.length === 0) {
        addMessage({
          id: generateMessageId(),
          type: 'assistant',
          content: getWelcomeMessage(userContext, wooCommerceData),
          timestamp: new Date().toISOString()
        });
      }

    } catch (err) {
      if (process.env.NODE_ENV === 'development') {
        console.error('Connection failed:', err);
      }
      setError({
        type: 'connection_failed',
        message: 'Failed to connect to chat service',
        details: err
      });
      setIsConnected(false);
    } finally {
      setIsLoading(false);
    }
  }, [messages.length, userContext, wooCommerceData]);

  /**
   * Send a message
   */
  const sendMessage = useCallback(async (content, options = {}) => {
    if (!content?.trim() || !isConnected) {
      return;
    }

    const userMessage = {
      id: generateMessageId(),
      type: 'user',
      content: content.trim(),
      timestamp: new Date().toISOString(),
      ...options
    };

    // Add user message immediately
    addMessage(userMessage);
    setIsTyping(true);
    setError(null);

    try {
      // Cancel previous request if still pending
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }

      // Create new abort controller
      abortControllerRef.current = new AbortController();

      // Simulate API call delay (will be real API in Task 4.3)
      await new Promise(resolve => setTimeout(resolve, chatConfig.typingDelay));

      // Generate mock response (will be real AI response in Task 4.3)
      const assistantMessage = {
        id: generateMessageId(),
        type: 'assistant',
        content: generateMockResponse(content, userContext, wooCommerceData),
        timestamp: new Date().toISOString(),
        conversationId
      };

      addMessage(assistantMessage);
      retryCountRef.current = 0; // Reset retry count on success

    } catch (err) {
      if (err.name !== 'AbortError') {
        if (process.env.NODE_ENV === 'development') {
          console.error('Send message failed:', err);
        }

        const errorMessage = {
          id: generateMessageId(),
          type: 'error',
          content: 'Sorry, I encountered an error. Please try again.',
          timestamp: new Date().toISOString(),
          error: err
        };

        addMessage(errorMessage);
        setError({
          type: 'send_failed',
          message: 'Failed to send message',
          details: err
        });
      }
    } finally {
      setIsTyping(false);
    }
  }, [isConnected, conversationId, userContext, wooCommerceData, chatConfig.typingDelay]);

  /**
   * Add message to conversation
   */
  const addMessage = useCallback((message) => {
    setMessages(prev => [...prev, message]);
  }, []);

  /**
   * Clear all messages
   */
  const clearMessages = useCallback(() => {
    setMessages([]);
    setError(null);
  }, []);

  /**
   * Retry last failed operation
   */
  const retry = useCallback(() => {
    if (retryCountRef.current < chatConfig.maxRetries) {
      retryCountRef.current++;
      setError(null);

      if (!isConnected) {
        initializeConnection();
      }
    }
  }, [chatConfig.maxRetries, isConnected, initializeConnection]);

  return {
    // State
    messages,
    isTyping,
    isConnected,
    conversationId,
    error,
    isLoading,

    // Methods
    sendMessage,
    clearMessages,
    retry,

    // Computed
    messageCount: messages.length,
    canSend: isConnected && !isLoading && !error,
    hasUnreadMessages: messages.some(msg => msg.unread === true)
  };
};

/**
 * Generate unique conversation ID
 */
const generateConversationId = () => {
  return `conv_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
};

/**
 * Generate unique message ID
 */
const generateMessageId = () => {
  return `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
};

/**
 * Generate welcome message based on context
 */
const getWelcomeMessage = (userContext, wooCommerceData) => {
  const userName = userContext?.userName || '';
  const hasCart = wooCommerceData?.cartItems?.length > 0;
  const currentProduct = wooCommerceData?.currentProduct;

  if (currentProduct) {
    return `Hi${userName ? ` ${userName}` : ''}! I can help you with questions about ${currentProduct.name} or anything else you need.`;
  }

  if (hasCart) {
    return `Hi${userName ? ` ${userName}` : ''}! I see you have items in your cart. How can I help you complete your purchase?`;
  }

  return `Hi${userName ? ` ${userName}` : ''}! I'm your AI shopping assistant. How can I help you today?`;
};

/**
 * Generate mock response (placeholder for real AI in Task 4.3)
 */
const generateMockResponse = (userMessage, userContext, wooCommerceData) => {
  const message = userMessage.toLowerCase();

  if (message.includes('product') || message.includes('item')) {
    return "I'd be happy to help you find the right product! Could you tell me more about what you're looking for?";
  }

  if (message.includes('cart') || message.includes('checkout')) {
    return 'I can help you with your shopping cart. Would you like me to review your items or assist with checkout?';
  }

  if (message.includes('price') || message.includes('cost')) {
    return 'I can help you find information about pricing and any available discounts. What specific product are you interested in?';
  }

  if (message.includes('shipping') || message.includes('delivery')) {
    return 'I can provide information about shipping options and delivery times. What would you like to know?';
  }

  return "Thanks for your message! I'm here to help with any questions about products, orders, or shopping. What would you like to know?";
};

export default useChat;
