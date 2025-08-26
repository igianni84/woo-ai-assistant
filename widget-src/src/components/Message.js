/**
 * Message Component
 * 
 * Individual chat message component for user and bot messages
 * with timestamp display and proper accessibility support
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import PropTypes from 'prop-types';

/**
 * Message component that displays individual chat messages
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {Object} props.message - Message object containing content and metadata
 * @param {string} props.message.id - Unique message identifier
 * @param {string} props.message.content - Message text content
 * @param {'user'|'bot'} props.message.type - Message type (user or bot)
 * @param {string} [props.message.timestamp] - ISO timestamp string
 * @param {boolean} [props.showTimestamp=true] - Whether to show timestamp
 * @param {boolean} [props.showAvatar=true] - Whether to show avatar for bot messages
 * 
 * @returns {JSX.Element} Message component
 * 
 * @example
 * <Message
 *   message={{
 *     id: 'msg_123',
 *     content: 'Hello, how can I help?',
 *     type: 'bot',
 *     timestamp: '2023-08-26T10:30:00Z'
 *   }}
 *   showTimestamp={true}
 *   showAvatar={true}
 * />
 */
const Message = ({ 
  message, 
  showTimestamp = true, 
  showAvatar = true 
}) => {
  const { id, content, type, timestamp } = message;
  
  /**
   * Formats timestamp for display
   * @returns {string} Formatted time string
   */
  const formatTime = () => {
    if (!timestamp) return '';
    
    try {
      return new Date(timestamp).toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit' 
      });
    } catch (error) {
      console.warn('Invalid timestamp format:', timestamp);
      return '';
    }
  };

  /**
   * Renders bot avatar SVG
   * @returns {JSX.Element} Avatar SVG element
   */
  const renderBotAvatar = () => (
    <div className="message-avatar" aria-hidden="true">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" fill="currentColor"/>
        <path 
          d="M8 14s1.5 2 4 2 4-2 4-2" 
          stroke="white" 
          strokeWidth="2" 
          strokeLinecap="round"
        />
        <path 
          d="M9 9h.01M15 9h.01" 
          stroke="white" 
          strokeWidth="2" 
          strokeLinecap="round"
        />
      </svg>
    </div>
  );

  return (
    <div 
      className={`message ${type === 'user' ? 'user-message' : 'bot-message'}`}
      data-message-id={id}
      role={type === 'bot' ? 'log' : undefined}
      aria-label={type === 'user' ? 'Your message' : 'Assistant message'}
    >
      {/* Bot Avatar */}
      {type === 'bot' && showAvatar && renderBotAvatar()}
      
      {/* Message Content */}
      <div className="message-content">
        <div className="message-text">
          {content}
        </div>
        
        {/* Timestamp */}
        {showTimestamp && timestamp && (
          <time 
            className="message-timestamp" 
            dateTime={timestamp}
            title={new Date(timestamp).toLocaleString()}
          >
            {formatTime()}
          </time>
        )}
      </div>
    </div>
  );
};

Message.propTypes = {
  /**
   * Message object containing all message data
   */
  message: PropTypes.shape({
    /**
     * Unique identifier for the message
     */
    id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    
    /**
     * The text content of the message
     */
    content: PropTypes.string.isRequired,
    
    /**
     * Type of message - either user or bot
     */
    type: PropTypes.oneOf(['user', 'bot']).isRequired,
    
    /**
     * ISO timestamp string for when message was sent
     */
    timestamp: PropTypes.string,
  }).isRequired,
  
  /**
   * Whether to display the message timestamp
   */
  showTimestamp: PropTypes.bool,
  
  /**
   * Whether to show avatar for bot messages
   */
  showAvatar: PropTypes.bool,
};

Message.defaultProps = {
  showTimestamp: true,
  showAvatar: true,
};

export default Message;