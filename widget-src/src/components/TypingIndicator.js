/**
 * Typing Indicator Component
 * 
 * Animated typing indicator to show when the AI assistant is processing
 * and about to respond with proper accessibility support
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import PropTypes from 'prop-types';

/**
 * TypingIndicator component showing animated dots when AI is typing
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {boolean} [props.showAvatar=true] - Whether to show the bot avatar
 * @param {string} [props.ariaLabel='AI assistant is typing'] - Accessibility label
 * @param {string} [props.className=''] - Additional CSS class names
 * 
 * @returns {JSX.Element} TypingIndicator component
 * 
 * @example
 * <TypingIndicator 
 *   showAvatar={true}
 *   ariaLabel="Assistant is thinking..."
 * />
 */
const TypingIndicator = ({ 
  showAvatar = true, 
  ariaLabel = 'AI assistant is typing',
  className = '' 
}) => {
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
      className={`message bot-message typing-indicator ${className}`}
      role="status"
      aria-live="polite"
      aria-label={ariaLabel}
    >
      {/* Bot Avatar */}
      {showAvatar && renderBotAvatar()}
      
      {/* Typing Content */}
      <div className="message-content">
        <div className="typing-dots" aria-hidden="true">
          <span className="dot dot-1"></span>
          <span className="dot dot-2"></span>
          <span className="dot dot-3"></span>
        </div>
        <span className="sr-only">{ariaLabel}</span>
      </div>
    </div>
  );
};

TypingIndicator.propTypes = {
  /**
   * Whether to display the bot avatar
   */
  showAvatar: PropTypes.bool,
  
  /**
   * Accessibility label for screen readers
   */
  ariaLabel: PropTypes.string,
  
  /**
   * Additional CSS class names
   */
  className: PropTypes.string,
};

TypingIndicator.defaultProps = {
  showAvatar: true,
  ariaLabel: 'AI assistant is typing',
  className: '',
};

export default TypingIndicator;