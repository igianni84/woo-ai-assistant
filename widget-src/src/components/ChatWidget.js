/**
 * Chat Widget Component
 * 
 * Main chat interface component that handles the chat window display
 * and user interactions.
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import PropTypes from 'prop-types';

const ChatWidget = ({
  isVisible,
  onToggleVisibility,
  onClose,
  conversation,
  isTyping,
  connectionStatus,
  onSendMessage,
  config,
  userContext,
  pageContext,
  error,
  onDismissError,
}) => {
  // Placeholder implementation - will be expanded in Task 4.2
  return (
    <div 
      className={`chat-widget ${isVisible ? 'visible' : 'minimized'}`}
      role="dialog"
      aria-label="AI Assistant Chat"
      aria-expanded={isVisible}
    >
      {/* Minimized State - Chat Button */}
      {!isVisible && (
        <button
          className="chat-toggle-button"
          onClick={onToggleVisibility}
          aria-label="Open AI Assistant Chat"
          title="Chat with AI Assistant"
        >
          💬
        </button>
      )}
      
      {/* Expanded State - Chat Window */}
      {isVisible && (
        <div className="chat-window">
          <div className="chat-header">
            <h3>AI Assistant</h3>
            <div className="chat-controls">
              <button
                className="minimize-button"
                onClick={onToggleVisibility}
                aria-label="Minimize chat"
                title="Minimize"
              >
                −
              </button>
              <button
                className="close-button"
                onClick={onClose}
                aria-label="Close chat"
                title="Close"
              >
                ×
              </button>
            </div>
          </div>
          
          <div className="chat-content">
            <p>Chat widget is ready! (Implementation in progress)</p>
            <p>Connection: {connectionStatus}</p>
            <p>User ID: {userContext?.id}</p>
            <p>Page: {pageContext?.type}</p>
            {error && (
              <div className="error-message">
                Error: {error.message}
                <button onClick={onDismissError}>Dismiss</button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

ChatWidget.propTypes = {
  isVisible: PropTypes.bool.isRequired,
  onToggleVisibility: PropTypes.func.isRequired,
  onClose: PropTypes.func.isRequired,
  conversation: PropTypes.array,
  isTyping: PropTypes.bool,
  connectionStatus: PropTypes.oneOf(['connected', 'connecting', 'disconnected', 'error']),
  onSendMessage: PropTypes.func.isRequired,
  config: PropTypes.object.isRequired,
  userContext: PropTypes.object.isRequired,
  pageContext: PropTypes.object.isRequired,
  error: PropTypes.object,
  onDismissError: PropTypes.func.isRequired,
};

ChatWidget.defaultProps = {
  conversation: [],
  isTyping: false,
  connectionStatus: 'connecting',
  error: null,
};

export default ChatWidget;