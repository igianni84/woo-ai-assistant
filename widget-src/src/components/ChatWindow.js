/**
 * Chat Window Component
 *
 * Main chat interface component that handles user conversations
 * with the AI assistant. Features full message display, typing indicators,
 * input handling, and responsive design.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';
import PropTypes from 'prop-types';
import Message from './Message';
import TypingIndicator from './TypingIndicator';

/**
 * Chat Window Component
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {boolean} props.isVisible - Whether the chat window is visible
 * @param {Array} props.messages - Array of conversation messages
 * @param {boolean} props.isTyping - Whether the bot is typing
 * @param {boolean} props.isConnected - Whether connected to chat service
 * @param {string} props.conversationId - Current conversation ID
 * @param {Object} props.userContext - Current user context
 * @param {Object} props.wooCommerceData - WooCommerce data context
 * @param {Object} props.config - Widget configuration
 * @param {Object} props.error - Current error state
 * @param {Function} props.onClose - Close handler
 * @param {Function} props.onMinimize - Minimize handler
 * @param {Function} props.onSendMessage - Send message handler
 * @param {Function} props.onClearMessages - Clear messages handler
 * @returns {JSX.Element} Chat window component
 */
const ChatWindow = ({
  isVisible,
  messages,
  isTyping,
  isConnected,
  conversationId,
  userContext,
  wooCommerceData,
  config,
  error,
  onClose,
  onMinimize,
  onSendMessage,
  onClearMessages
}) => {
  // Local state
  const [inputValue, setInputValue] = useState('');
  const [isInputFocused, setIsInputFocused] = useState(false);
  
  // Refs
  const messagesEndRef = useRef(null);
  const inputRef = useRef(null);
  const messagesContainerRef = useRef(null);

  // Auto-scroll to bottom when new messages arrive
  useEffect(() => {
    if (messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ 
        behavior: 'smooth',
        block: 'end'
      });
    }
  }, [messages, isTyping]);

  // Focus input when window opens
  useEffect(() => {
    if (isVisible && inputRef.current && isConnected) {
      const timer = setTimeout(() => {
        inputRef.current.focus();
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [isVisible, isConnected]);

  // Handle input changes with auto-resize
  const handleInputChange = useCallback((e) => {
    const value = e.target.value;
    setInputValue(value);
    
    // Auto-resize textarea
    const textarea = e.target;
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 120)}px`;
  }, []);

  // Handle message sending
  const handleSendMessage = useCallback((e) => {
    e.preventDefault();
    
    if (!inputValue.trim() || !isConnected || isTyping) {
      return;
    }

    onSendMessage(inputValue.trim());
    setInputValue('');
    
    // Reset textarea height
    if (inputRef.current) {
      inputRef.current.style.height = 'auto';
    }
  }, [inputValue, isConnected, isTyping, onSendMessage]);

  // Handle key press (Enter to send, Shift+Enter for new line)
  const handleKeyPress = useCallback((e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendMessage(e);
    }
  }, [handleSendMessage]);

  // Connection status indicator
  const getConnectionStatus = () => {
    if (!isConnected) {
      return { text: 'Connecting...', className: 'connecting' };
    }
    if (error) {
      return { text: 'Connection error', className: 'error' };
    }
    return { text: 'Connected', className: 'connected' };
  };

  const connectionStatus = getConnectionStatus();

  return (
    <div
      className={`woo-ai-assistant-chat-window ${
        isVisible ? 'visible' : ''
      } ${!isConnected ? 'disconnected' : ''} ${error ? 'has-error' : ''}`}
      role="dialog"
      aria-label="AI Assistant Chat"
      aria-modal="true"
      aria-describedby="woo-ai-chat-description"
    >
      {/* Header */}
      <div className="woo-ai-assistant-chat-header">
        <div className="woo-ai-assistant-chat-title-area">
          <h2 className="woo-ai-assistant-chat-title">
            AI Shopping Assistant
          </h2>
          <div className="woo-ai-assistant-connection-status">
            <span 
              className={`woo-ai-assistant-status-indicator ${connectionStatus.className}`}
              title={connectionStatus.text}
            />
            <span className="woo-ai-assistant-status-text">
              {connectionStatus.text}
            </span>
          </div>
        </div>
        
        <div className="woo-ai-assistant-chat-controls">
          <button
            className="woo-ai-assistant-chat-minimize"
            onClick={onMinimize}
            aria-label="Minimize chat"
            type="button"
            title="Minimize"
          >
            <MinimizeIcon />
          </button>
          <button
            className="woo-ai-assistant-chat-close"
            onClick={onClose}
            aria-label="Close chat"
            type="button"
            title="Close"
          >
            <CloseIcon />
          </button>
        </div>
      </div>

      {/* Messages Area */}
      <div className="woo-ai-assistant-chat-content">
        <div 
          className="woo-ai-assistant-messages"
          ref={messagesContainerRef}
          role="log"
          aria-label="Chat messages"
          id="woo-ai-chat-description"
        >
          {messages.length === 0 && !isTyping && (
            <div className="woo-ai-assistant-empty-state">
              <div className="woo-ai-assistant-empty-state-content">
                <div className="woo-ai-assistant-bot-avatar">
                  <BotIcon />
                </div>
                <p>Hi! I'm your AI shopping assistant.</p>
                <p>Ask me about products, orders, or anything else!</p>
              </div>
            </div>
          )}
          
          {messages.map((message, index) => (
            <Message
              key={message.id}
              message={message}
              isLatest={index === messages.length - 1}
              userContext={userContext}
              wooCommerceData={wooCommerceData}
              config={config}
              onActionSuccess={(actionType, result) => {
                // Handle successful actions (show success message, update UI)
                // Could emit success notification or update cart display
                if (window.wooAiAssistant?.trackEvent) {
                  window.wooAiAssistant.trackEvent('action_success', {
                    type: actionType,
                    success: true
                  });
                }
              }}
              onActionError={(actionType, error) => {
                // Handle action errors (show error message)
                // Could emit error notification
                if (window.wooAiAssistant?.trackEvent) {
                  window.wooAiAssistant.trackEvent('action_error', {
                    type: actionType,
                    error: error
                  });
                }
              }}
            />
          ))}
          
          {isTyping && <TypingIndicator />}
          
          {error && (
            <div className="woo-ai-assistant-error-message">
              <div className="woo-ai-assistant-message-content error">
                <ErrorIcon />
                <div>
                  <strong>Connection Error</strong>
                  <p>{error.message || 'Unable to connect to chat service'}</p>
                  <button 
                    className="woo-ai-assistant-retry-button"
                    onClick={() => window.location.reload()}
                  >
                    Retry
                  </button>
                </div>
              </div>
            </div>
          )}
          
          <div ref={messagesEndRef} />
        </div>

        {/* Input Area */}
        <form 
          className="woo-ai-assistant-input-area"
          onSubmit={handleSendMessage}
        >
          <div className="woo-ai-assistant-input-container">
            <textarea
              ref={inputRef}
              className={`woo-ai-assistant-input ${
                isInputFocused ? 'focused' : ''
              }`}
              placeholder="Type your message... (Press Enter to send)"
              value={inputValue}
              onChange={handleInputChange}
              onKeyPress={handleKeyPress}
              onFocus={() => setIsInputFocused(true)}
              onBlur={() => setIsInputFocused(false)}
              rows={1}
              maxLength={2000}
              aria-label="Message input"
              disabled={!isConnected || !!error}
              style={{ resize: 'none' }}
            />
            <div className="woo-ai-assistant-input-actions">
              {messages.length > 0 && (
                <button
                  className="woo-ai-assistant-clear-button"
                  onClick={onClearMessages}
                  aria-label="Clear conversation"
                  type="button"
                  title="Clear conversation"
                >
                  <ClearIcon />
                </button>
              )}
              <button
                className={`woo-ai-assistant-send ${
                  inputValue.trim() && isConnected && !isTyping ? 'active' : ''
                }`}
                type="submit"
                aria-label="Send message"
                disabled={!inputValue.trim() || !isConnected || isTyping}
                title="Send message"
              >
                {isTyping ? <LoadingIcon /> : <SendIcon />}
              </button>
            </div>
          </div>
          
          <div className="woo-ai-assistant-input-footer">
            <span className="woo-ai-assistant-character-count">
              {inputValue.length}/2000
            </span>
          </div>
        </form>
      </div>
    </div>
  );
};

// Icon Components

/**
 * Minimize Icon Component
 */
const MinimizeIcon = () => (
  <svg 
    width="16" 
    height="16" 
    viewBox="0 0 16 16" 
    fill="none" 
    aria-hidden="true"
  >
    <path
      d="M4 8h8"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
    />
  </svg>
);

/**
 * Close Icon Component
 */
const CloseIcon = () => (
  <svg 
    width="16" 
    height="16" 
    viewBox="0 0 16 16" 
    fill="none" 
    aria-hidden="true"
  >
    <path
      d="M12 4L4 12M4 4L12 12"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

/**
 * Send Icon Component
 */
const SendIcon = () => (
  <svg 
    width="16" 
    height="16" 
    viewBox="0 0 16 16" 
    fill="none" 
    aria-hidden="true"
  >
    <path
      d="M15 1L7 9M15 1L10 15L7 9M15 1L1 6L7 9"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

/**
 * Bot Icon Component
 */
const BotIcon = () => (
  <svg 
    width="24" 
    height="24" 
    viewBox="0 0 24 24" 
    fill="none" 
    aria-hidden="true"
  >
    <path
      d="M12 2C13.1046 2 14 2.89543 14 4V5H16C18.2091 5 20 6.79086 20 9V16C20 18.2091 18.2091 20 16 20H8C5.79086 20 4 18.2091 4 16V9C4 6.79086 5.79086 5 8 5H10V4C10 2.89543 10.8954 2 12 2Z"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <circle cx="9" cy="12" r="1" fill="currentColor"/>
    <circle cx="15" cy="12" r="1" fill="currentColor"/>
    <path
      d="M9 16H15"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
    />
  </svg>
);

/**
 * Error Icon Component
 */
const ErrorIcon = () => (
  <svg 
    width="16" 
    height="16" 
    viewBox="0 0 16 16" 
    fill="none" 
    aria-hidden="true"
  >
    <circle cx="8" cy="8" r="7" stroke="currentColor" strokeWidth="2"/>
    <path d="M8 4v4" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
    <circle cx="8" cy="12" r="1" fill="currentColor"/>
  </svg>
);

/**
 * Clear Icon Component
 */
const ClearIcon = () => (
  <svg 
    width="16" 
    height="16" 
    viewBox="0 0 16 16" 
    fill="none" 
    aria-hidden="true"
  >
    <path
      d="M3 6h10l-1 8H4l-1-8zM5 6V4a1 1 0 011-1h4a1 1 0 011 1v2M7 9v3M9 9v3"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

/**
 * Loading Icon Component
 */
const LoadingIcon = () => (
  <svg 
    width="16" 
    height="16" 
    viewBox="0 0 16 16" 
    fill="none" 
    aria-hidden="true"
    className="woo-ai-assistant-loading"
  >
    <path
      d="M8 1.5v3M8 11.5v3M3.75 3.75l2.12 2.12M10.13 10.13l2.12 2.12M1.5 8h3M11.5 8h3M3.75 12.25l2.12-2.12M10.13 5.87l2.12-2.12"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

// PropTypes
ChatWindow.propTypes = {
  isVisible: PropTypes.bool.isRequired,
  messages: PropTypes.arrayOf(PropTypes.shape({
    id: PropTypes.string.isRequired,
    type: PropTypes.oneOf(['user', 'assistant', 'system', 'error']).isRequired,
    content: PropTypes.string.isRequired,
    timestamp: PropTypes.string.isRequired,
    metadata: PropTypes.object
  })).isRequired,
  isTyping: PropTypes.bool.isRequired,
  isConnected: PropTypes.bool.isRequired,
  conversationId: PropTypes.string,
  userContext: PropTypes.object.isRequired,
  wooCommerceData: PropTypes.object.isRequired,
  config: PropTypes.object.isRequired,
  error: PropTypes.shape({
    type: PropTypes.string,
    message: PropTypes.string,
    details: PropTypes.any
  }),
  onClose: PropTypes.func.isRequired,
  onMinimize: PropTypes.func.isRequired,
  onSendMessage: PropTypes.func.isRequired,
  onClearMessages: PropTypes.func.isRequired
};

export default ChatWindow;