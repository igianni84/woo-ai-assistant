/**
 * Chat Toggle Button Component
 *
 * Floating action button that toggles the chat window visibility.
 * Displays different states based on connection status, errors, and messages.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useMemo } from 'react';
import PropTypes from 'prop-types';

/**
 * Chat Toggle Button Component
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {boolean} props.isOpen - Whether chat window is open
 * @param {boolean} props.isMinimized - Whether chat was minimized
 * @param {boolean} props.isConnected - Connection status
 * @param {boolean} props.hasError - Whether there's an active error
 * @param {number} props.messageCount - Number of messages in conversation
 * @param {Function} props.onToggle - Toggle handler function
 * @returns {JSX.Element} Toggle button component
 */
const ChatToggleButton = ({
  isOpen,
  isMinimized,
  isConnected,
  hasError,
  messageCount,
  onToggle
}) => {
  // Calculate button state and styling
  const buttonState = useMemo(() => {
    const classes = ['woo-ai-assistant-toggle'];
    const attributes = {};
    let ariaLabel = 'Open AI Assistant Chat';
    let statusText = '';

    // Open/closed state
    if (isOpen) {
      classes.push('woo-ai-assistant-toggle--open');
      ariaLabel = 'Close AI Assistant Chat';
    } else {
      classes.push('woo-ai-assistant-toggle--closed');
    }

    // Connection state
    if (!isConnected) {
      classes.push('woo-ai-assistant-toggle--disconnected');
      statusText = 'Disconnected';
      attributes['aria-describedby'] = 'woo-ai-assistant-status';
    } else {
      classes.push('woo-ai-assistant-toggle--connected');
    }

    // Error state
    if (hasError) {
      classes.push('woo-ai-assistant-toggle--error');
      statusText = 'Error - Click to retry';
      attributes['aria-describedby'] = 'woo-ai-assistant-status';
    }

    // Minimized state
    if (isMinimized && !isOpen) {
      classes.push('woo-ai-assistant-toggle--minimized');
      ariaLabel = 'Restore AI Assistant Chat';
    }

    // Message count indicator
    if (messageCount > 0 && !isOpen) {
      classes.push('woo-ai-assistant-toggle--has-messages');
    }

    return {
      classes: classes.join(' '),
      attributes,
      ariaLabel,
      statusText
    };
  }, [isOpen, isMinimized, isConnected, hasError, messageCount]);

  // Choose appropriate icon
  const IconComponent = useMemo(() => {
    if (hasError) return ErrorIcon;
    if (!isConnected) return DisconnectedIcon;
    if (isOpen) return CloseIcon;
    return ChatIcon;
  }, [hasError, isConnected, isOpen]);

  return (
    <>
      <button
        type="button"
        className={buttonState.classes}
        onClick={onToggle}
        aria-label={buttonState.ariaLabel}
        {...buttonState.attributes}
      >
        {/* Main icon */}
        <span className="woo-ai-assistant-toggle-icon">
          <IconComponent />
        </span>

        {/* Connection status indicator */}
        <span 
          className="woo-ai-assistant-toggle-status"
          aria-hidden="true"
        >
          <StatusDot 
            isConnected={isConnected} 
            hasError={hasError} 
          />
        </span>

        {/* Unread message indicator */}
        {messageCount > 0 && !isOpen && (
          <span 
            className="woo-ai-assistant-toggle-badge"
            aria-label={`${messageCount} unread messages`}
          >
            {messageCount > 9 ? '9+' : messageCount}
          </span>
        )}

        {/* Pulsing animation for new messages */}
        {messageCount > 0 && !isOpen && (
          <span 
            className="woo-ai-assistant-toggle-pulse" 
            aria-hidden="true" 
          />
        )}
      </button>

      {/* Screen reader status text */}
      {buttonState.statusText && (
        <span 
          id="woo-ai-assistant-status" 
          className="sr-only"
        >
          {buttonState.statusText}
        </span>
      )}
    </>
  );
};

/**
 * Status Dot Component
 * 
 * @param {Object} props
 * @param {boolean} props.isConnected
 * @param {boolean} props.hasError
 */
const StatusDot = ({ isConnected, hasError }) => {
  let className = 'woo-ai-assistant-status-dot';
  
  if (hasError) {
    className += ' woo-ai-assistant-status-dot--error';
  } else if (isConnected) {
    className += ' woo-ai-assistant-status-dot--connected';
  } else {
    className += ' woo-ai-assistant-status-dot--disconnected';
  }
  
  return <span className={className} />;
};

/**
 * Chat Icon Component
 */
const ChatIcon = () => (
  <svg 
    width="28" 
    height="28" 
    viewBox="0 0 28 28" 
    fill="none" 
    aria-hidden="true"
  >
    <path
      d="M21 6H7C5.9 6 5 6.9 5 8V16C5 17.1 5.9 18 7 18H8V22L12 18H21C22.1 18 23 17.1 23 16V8C23 6.9 22.1 6 21 6Z"
      fill="currentColor"
    />
    <rect 
      x="8" 
      y="10" 
      width="8" 
      height="1.5" 
      rx="0.75" 
      fill="white" 
      opacity="0.8" 
    />
    <rect 
      x="8" 
      y="12.5" 
      width="10" 
      height="1.5" 
      rx="0.75" 
      fill="white" 
      opacity="0.8" 
    />
  </svg>
);

/**
 * Close Icon Component
 */
const CloseIcon = () => (
  <svg 
    width="24" 
    height="24" 
    viewBox="0 0 24 24" 
    fill="none" 
    aria-hidden="true"
  >
    <path
      d="M18 6L6 18M6 6L18 18"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

/**
 * Error Icon Component
 */
const ErrorIcon = () => (
  <svg 
    width="24" 
    height="24" 
    viewBox="0 0 24 24" 
    fill="none" 
    aria-hidden="true"
  >
    <circle 
      cx="12" 
      cy="12" 
      r="10" 
      stroke="currentColor" 
      strokeWidth="2"
    />
    <line 
      x1="12" 
      y1="8" 
      x2="12" 
      y2="12" 
      stroke="currentColor" 
      strokeWidth="2"
    />
    <circle 
      cx="12" 
      cy="16" 
      r="1" 
      fill="currentColor"
    />
  </svg>
);

/**
 * Disconnected Icon Component
 */
const DisconnectedIcon = () => (
  <svg 
    width="24" 
    height="24" 
    viewBox="0 0 24 24" 
    fill="none" 
    aria-hidden="true"
  >
    <path
      d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <line 
      x1="1" 
      y1="1" 
      x2="23" 
      y2="23" 
      stroke="currentColor" 
      strokeWidth="2"
      strokeLinecap="round"
    />
  </svg>
);

// PropTypes
ChatToggleButton.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  isMinimized: PropTypes.bool.isRequired,
  isConnected: PropTypes.bool.isRequired,
  hasError: PropTypes.bool.isRequired,
  messageCount: PropTypes.number.isRequired,
  onToggle: PropTypes.func.isRequired
};

StatusDot.propTypes = {
  isConnected: PropTypes.bool.isRequired,
  hasError: PropTypes.bool.isRequired
};

export default ChatToggleButton;