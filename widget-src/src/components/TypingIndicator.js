/**
 * Typing Indicator Component
 *
 * Shows an animated typing indicator when the AI assistant is processing
 * a response. Features three animated dots with a smooth pulse animation
 * and proper accessibility attributes.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useEffect, useState } from 'react';
import PropTypes from 'prop-types';

/**
 * TypingIndicator Component
 *
 * @component
 * @param {Object} props - Component properties
 * @param {string} props.assistantName - Name of the AI assistant
 * @param {Object} props.config - Widget configuration
 * @returns {JSX.Element} Typing indicator component
 */
const TypingIndicator = ({
  assistantName = 'AI Assistant',
  config = {}
}) => {
  // Handle empty string case
  const displayName = assistantName || 'AI Assistant';
  const [isVisible, setIsVisible] = useState(false);

  // Fade-in effect
  useEffect(() => {
    const timer = setTimeout(() => {
      setIsVisible(true);
    }, 100);
    return () => clearTimeout(timer);
  }, []);

  // Get random typing messages for variety
  const getTypingMessage = () => {
    const messages = [
      'is typing...',
      'is thinking...',
      'is processing...',
      'is analyzing...'
    ];
    return messages[Math.floor(Math.random() * messages.length)];
  };

  const [typingMessage] = useState(getTypingMessage());

  return (
    <div
      className={`woo-ai-assistant-typing-indicator ${
        isVisible ? 'woo-ai-assistant-typing-indicator--visible' : ''
      }`}
      role="status"
      aria-live="polite"
      aria-label={`${displayName} is typing`}
    >
      {/* Avatar */}
      <div className="woo-ai-assistant-typing-avatar">
        <BotIcon />
      </div>

      {/* Typing content */}
      <div className="woo-ai-assistant-typing-content">
        <div className="woo-ai-assistant-typing-header">
          <span className="woo-ai-assistant-typing-sender">
            {displayName}
          </span>
          <span className="woo-ai-assistant-typing-status">
            {typingMessage}
          </span>
        </div>

        <div className="woo-ai-assistant-typing-animation">
          <div className="woo-ai-assistant-typing-dots">
            <span className="woo-ai-assistant-typing-dot"></span>
            <span className="woo-ai-assistant-typing-dot"></span>
            <span className="woo-ai-assistant-typing-dot"></span>
          </div>
        </div>
      </div>
    </div>
  );
};

/**
 * Alternative minimal typing indicator (just dots)
 */
export const MinimalTypingIndicator = () => {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      setIsVisible(true);
    }, 100);
    return () => clearTimeout(timer);
  }, []);

  return (
    <div
      className={`woo-ai-assistant-typing-minimal ${
        isVisible ? 'woo-ai-assistant-typing-minimal--visible' : ''
      }`}
      role="status"
      aria-live="polite"
      aria-label="AI is typing"
    >
      <div className="woo-ai-assistant-typing-dots-minimal">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
  );
};

/**
 * Bot Icon Component
 */
const BotIcon = () => (
  <svg
    width="18"
    height="18"
    viewBox="0 0 18 18"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M9 1.5C9.82843 1.5 10.5 2.17157 10.5 3V3.5H12C13.6569 3.5 15 4.84315 15 6.5V12C15 13.6569 13.6569 15 12 15H6C4.34315 15 3 13.6569 3 12V6.5C3 4.84315 4.34315 3.5 6 3.5H7.5V3C7.5 2.17157 8.17157 1.5 9 1.5Z"
      stroke="currentColor"
      strokeWidth="1.2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <circle cx="6.75" cy="8.5" r="0.75" fill="currentColor"/>
    <circle cx="11.25" cy="8.5" r="0.75" fill="currentColor"/>
    <path
      d="M6.75 11.5H11.25"
      stroke="currentColor"
      strokeWidth="1.2"
      strokeLinecap="round"
    />
  </svg>
);

// PropTypes
TypingIndicator.propTypes = {
  assistantName: PropTypes.string,
  config: PropTypes.object
};

MinimalTypingIndicator.propTypes = {};

export default TypingIndicator;
