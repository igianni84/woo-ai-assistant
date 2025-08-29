/**
 * Chat Widget Component
 * 
 * Enhanced complete chat interface component with modular components,
 * message handling, open/close functionality, responsive design, and theme support
 * for Task 4.2: Chat Components.
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useCallback, useEffect, useRef } from 'react';
import PropTypes from 'prop-types';
import Message from './Message';
import TypingIndicator from './TypingIndicator';
import MessageInput from './MessageInput';

const ChatWindow = ({
  isVisible,
  onToggleVisibility,
  onClose,
  conversation,
  isTyping,
  connectionStatus,
  onSendMessage,
  onClearConversation,
  onReconnect,
  config,
  userContext,
  pageContext,
  error,
  onDismissError,
  streamingMessage,
  streamingProgress,
  isStreamingSupported,
}) => {
  const [isMinimizing, setIsMinimizing] = useState(false);
  const [showWelcomeMessage, setShowWelcomeMessage] = useState(true);
  const [autoScroll, setAutoScroll] = useState(true);
  const messagesEndRef = useRef(null);
  const messagesContainerRef = useRef(null);

  // Enhanced scroll handling
  const scrollToBottom = useCallback(() => {
    if (autoScroll && messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'end',
        inline: 'nearest'
      });
    }
  }, [autoScroll]);

  // Check if user has scrolled up to disable auto-scroll
  const handleScroll = useCallback(() => {
    if (messagesContainerRef.current) {
      const { scrollTop, scrollHeight, clientHeight } = messagesContainerRef.current;
      const isNearBottom = scrollHeight - scrollTop - clientHeight < 50;
      setAutoScroll(isNearBottom);
    }
  }, []);

  useEffect(() => {
    if (isVisible) {
      scrollToBottom();
    }
  }, [isVisible, conversation, scrollToBottom]);

  // Handle message submission from MessageInput component
  const handleSendMessage = useCallback((message) => {
    onSendMessage(message);
    setShowWelcomeMessage(false);
    // Force scroll to bottom when user sends a message
    setAutoScroll(true);
    setTimeout(scrollToBottom, 100);
  }, [onSendMessage, scrollToBottom]);

  // Handle minimize with animation
  const handleMinimize = useCallback(() => {
    setIsMinimizing(true);
    setTimeout(() => {
      onToggleVisibility();
      setIsMinimizing(false);
    }, 200);
  }, [onToggleVisibility]);

  // Handle keyboard navigation for the chat window
  useEffect(() => {
    const handleKeyDown = (e) => {
      // Close on Escape key when chat is visible
      if (e.key === 'Escape' && isVisible) {
        handleMinimize();
      }
    };

    if (isVisible) {
      document.addEventListener('keydown', handleKeyDown);
    }

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isVisible, handleMinimize]);

  // Welcome message based on page context
  const getWelcomeMessage = () => {
    if (config.welcomeMessage) {
      return config.welcomeMessage;
    }
    
    switch (pageContext?.type) {
      case 'product':
        return "Hi! I can help you with questions about this product, shipping, or anything else.";
      case 'shop':
        return "Hi! I'm here to help you find the perfect products. What are you looking for?";
      case 'cart':
        return "Hi! Need help with your cart or have questions before checkout?";
      default:
        return "Hi! I'm your AI assistant. How can I help you today?";
    }
  };

  // Get connection status display
  const getStatusDisplay = () => {
    switch (connectionStatus) {
      case 'connected':
        return { text: 'Online', className: 'status-connected' };
      case 'connecting':
        return { text: 'Connecting...', className: 'status-connecting' };
      case 'disconnected':
        return { text: 'Disconnected', className: 'status-disconnected' };
      case 'error':
        return { text: 'Connection Error', className: 'status-error' };
      default:
        return { text: 'Unknown', className: 'status-unknown' };
    }
  };

  const status = getStatusDisplay();

  return (
    <div 
      className={`chat-widget ${isVisible ? 'visible' : 'minimized'} ${isMinimizing ? 'minimizing' : ''}`}
      role="dialog"
      aria-label="AI Assistant Chat"
      aria-expanded={isVisible}
      data-connection-status={connectionStatus}
    >
      {/* Minimized State - Chat Button */}
      {!isVisible && (
        <button
          className="chat-toggle-button"
          onClick={onToggleVisibility}
          aria-label="Open AI Assistant Chat"
          title="Chat with AI Assistant"
        >
          <svg 
            width="24" 
            height="24" 
            viewBox="0 0 24 24" 
            fill="none" 
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              d="M20 2H4C2.9 2 2 2.9 2 4V16C2 17.1 2.9 18 4 18H6L10 22L14 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z"
              fill="currentColor"
            />
          </svg>
          <span className="sr-only">Open Chat</span>
        </button>
      )}
      
      {/* Expanded State - Chat Window */}
      {isVisible && (
        <div className="chat-window">
          {/* Header */}
          <div className="chat-header">
            <div className="chat-title">
              <h3>AI Assistant</h3>
              <div className={`connection-status ${status.className}`}>
                <span className="status-indicator" aria-hidden="true"></span>
                <span className="status-text">{status.text}</span>
              </div>
            </div>
            
            <div className="chat-controls">
              <button
                className="control-button minimize-button"
                onClick={handleMinimize}
                aria-label="Minimize chat"
                title="Minimize"
              >
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M4 8H12" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                </svg>
              </button>
              
              <button
                className="control-button close-button"
                onClick={onClose}
                aria-label="Close chat"
                title="Close"
              >
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
                </svg>
              </button>
            </div>
          </div>
          
          {/* Error Display */}
          {error && (
            <div className="chat-error" role="alert" aria-live="polite">
              <div className="error-content">
                <span className="error-message">{error.message}</span>
                <button
                  className="error-dismiss"
                  onClick={onDismissError}
                  aria-label="Dismiss error"
                >
                  ×
                </button>
              </div>
              {connectionStatus === 'error' && onReconnect && (
                <button className="retry-button" onClick={onReconnect}>
                  Try Again
                </button>
              )}
            </div>
          )}
          
          {/* Messages Area */}
          <div 
            className="chat-messages" 
            ref={messagesContainerRef}
            onScroll={handleScroll}
            role="log" 
            aria-live="polite" 
            aria-label="Chat messages"
          >
            {/* Welcome Message */}
            {showWelcomeMessage && conversation.length === 0 && (
              <Message
                message={{
                  id: 'welcome',
                  content: getWelcomeMessage(),
                  type: 'bot',
                  timestamp: new Date().toISOString(),
                }}
                showTimestamp={false}
                showAvatar={true}
              />
            )}
            
            {/* Conversation Messages */}
            {conversation.map((message, index) => (
              <Message
                key={message.id || `msg-${index}`}
                message={message}
                showTimestamp={config?.showTimestamps !== false}
                showAvatar={message.type === 'bot'}
                isStreaming={message.id === streamingMessage}
                streamingProgress={message.id === streamingMessage ? streamingProgress : 0}
                enableStreamingAnimation={config?.enableStreamingAnimation !== false}
              />
            ))}
            
            {/* Typing Indicator */}
            {isTyping && (
              <TypingIndicator
                showAvatar={true}
                ariaLabel={config?.strings?.typing || 'AI assistant is typing'}
              />
            )}
            
            {/* Auto-scroll anchor */}
            <div ref={messagesEndRef} aria-hidden="true" />
          </div>
          
          {/* Input Area */}
          <MessageInput
            onSendMessage={handleSendMessage}
            isDisabled={connectionStatus === 'error' || connectionStatus === 'disconnected'}
            isTyping={isTyping}
            placeholder={config?.strings?.inputPlaceholder || 'Type your message...'}
            maxLength={config?.limits?.maxMessageLength || 1000}
            showCounterAt={config?.limits?.showCounterAt || 800}
            autoFocus={isVisible}
          />
          
          {/* Footer Actions */}
          {conversation.length > 0 && (
            <div className="chat-footer">
              <button
                className="clear-chat-button"
                onClick={onClearConversation}
                title="Clear conversation"
              >
                Clear Chat
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

ChatWindow.propTypes = {
  /**
   * Whether the chat widget is visible or minimized
   */
  isVisible: PropTypes.bool.isRequired,
  
  /**
   * Function to toggle widget visibility
   */
  onToggleVisibility: PropTypes.func.isRequired,
  
  /**
   * Function to close the widget completely
   */
  onClose: PropTypes.func.isRequired,
  
  /**
   * Array of conversation messages
   */
  conversation: PropTypes.arrayOf(PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    type: PropTypes.oneOf(['user', 'bot']).isRequired,
    content: PropTypes.string.isRequired,
    timestamp: PropTypes.string,
  })),
  
  /**
   * Whether the AI is currently typing
   */
  isTyping: PropTypes.bool,
  
  /**
   * Current connection status
   */
  connectionStatus: PropTypes.oneOf(['connected', 'connecting', 'disconnected', 'error']),
  
  /**
   * Function to send a message
   */
  onSendMessage: PropTypes.func.isRequired,
  
  /**
   * Function to clear the conversation
   */
  onClearConversation: PropTypes.func.isRequired,
  
  /**
   * Function to reconnect when there's an error
   */
  onReconnect: PropTypes.func,
  
  /**
   * Widget configuration object
   */
  config: PropTypes.shape({
    welcomeMessage: PropTypes.string,
    language: PropTypes.string,
    theme: PropTypes.oneOf(['light', 'dark', 'auto']),
    position: PropTypes.oneOf(['bottom-left', 'bottom-right', 'top-left', 'top-right']),
    showTimestamps: PropTypes.bool,
    strings: PropTypes.shape({
      inputPlaceholder: PropTypes.string,
      typing: PropTypes.string,
      clearChat: PropTypes.string,
      minimize: PropTypes.string,
      close: PropTypes.string,
    }),
    limits: PropTypes.shape({
      maxMessageLength: PropTypes.number,
      showCounterAt: PropTypes.number,
      maxConversationLength: PropTypes.number,
    }),
  }).isRequired,
  
  /**
   * Current user context
   */
  userContext: PropTypes.shape({
    id: PropTypes.number,
    email: PropTypes.string,
  }).isRequired,
  
  /**
   * Current page context
   */
  pageContext: PropTypes.shape({
    type: PropTypes.string,
    id: PropTypes.number,
    url: PropTypes.string,
  }).isRequired,
  
  /**
   * Current error state
   */
  error: PropTypes.shape({
    message: PropTypes.string.isRequired,
    critical: PropTypes.bool,
  }),
  
  /**
   * Function to dismiss errors
   */
  onDismissError: PropTypes.func.isRequired,
  
  /**
   * ID of message currently being streamed
   */
  streamingMessage: PropTypes.string,
  
  /**
   * Progress of current streaming (0-1)
   */
  streamingProgress: PropTypes.number,
  
  /**
   * Whether streaming is supported
   */
  isStreamingSupported: PropTypes.bool,
};

ChatWindow.defaultProps = {
  conversation: [],
  isTyping: false,
  connectionStatus: 'connecting',
  error: null,
  onReconnect: null,
  streamingMessage: null,
  streamingProgress: 0,
  isStreamingSupported: false,
};

export default ChatWindow;