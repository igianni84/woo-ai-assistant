/**
 * Chat Window Component
 *
 * Main chat interface component - placeholder implementation for Task 4.1.
 * This will be fully implemented in Task 4.2.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import PropTypes from 'prop-types';

/**
 * Chat Window Component (Placeholder)
 * 
 * @component
 * @param {Object} props - Component properties
 * @returns {JSX.Element} Chat window placeholder
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
  return (
    <div
      className={`woo-ai-assistant-chat-window ${isVisible ? 'visible' : ''}`}
      role="dialog"
      aria-label="AI Assistant Chat"
      aria-modal="true"
    >
      <div className="woo-ai-assistant-chat-header">
        <h2 className="woo-ai-assistant-chat-title">
          AI Assistant
        </h2>
        <div className="woo-ai-assistant-chat-controls">
          <button
            className="woo-ai-assistant-chat-minimize"
            onClick={onMinimize}
            aria-label="Minimize chat"
            type="button"
          >
            <MinimizeIcon />
          </button>
          <button
            className="woo-ai-assistant-chat-close"
            onClick={onClose}
            aria-label="Close chat"
            type="button"
          >
            <CloseIcon />
          </button>
        </div>
      </div>

      <div className="woo-ai-assistant-chat-content">
        <div className="woo-ai-assistant-messages">
          {/* Welcome message */}
          <div className="woo-ai-assistant-message assistant">
            <div className="woo-ai-assistant-message-content">
              Hi! I'm your AI shopping assistant. How can I help you today?
            </div>
          </div>
          
          {/* Error state */}
          {error && (
            <div className="woo-ai-assistant-message error">
              <div className="woo-ai-assistant-message-content">
                Sorry, I'm having trouble connecting. Please try again.
              </div>
            </div>
          )}
          
          {/* Connection status */}
          {!isConnected && (
            <div className="woo-ai-assistant-message system">
              <div className="woo-ai-assistant-message-content">
                Connecting...
              </div>
            </div>
          )}
        </div>

        <div className="woo-ai-assistant-input-area">
          <textarea
            className="woo-ai-assistant-input"
            placeholder="Type your message..."
            rows={1}
            aria-label="Message input"
            disabled={!isConnected || !!error}
          />
          <button
            className="woo-ai-assistant-send"
            type="button"
            aria-label="Send message"
            disabled={!isConnected || !!error}
          >
            <SendIcon />
          </button>
        </div>
      </div>
    </div>
  );
};

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

// PropTypes
ChatWindow.propTypes = {
  isVisible: PropTypes.bool.isRequired,
  messages: PropTypes.array.isRequired,
  isTyping: PropTypes.bool.isRequired,
  isConnected: PropTypes.bool.isRequired,
  conversationId: PropTypes.string,
  userContext: PropTypes.object.isRequired,
  wooCommerceData: PropTypes.object.isRequired,
  config: PropTypes.object.isRequired,
  error: PropTypes.object,
  onClose: PropTypes.func.isRequired,
  onMinimize: PropTypes.func.isRequired,
  onSendMessage: PropTypes.func.isRequired,
  onClearMessages: PropTypes.func.isRequired
};

export default ChatWindow;