/**
 * Message Input Component
 * 
 * Text input component for chat messages with validation,
 * character counting, and keyboard shortcuts
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useCallback, useRef, useEffect } from 'react';
import PropTypes from 'prop-types';

/**
 * MessageInput component for typing and sending chat messages
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {Function} props.onSendMessage - Function called when message is sent
 * @param {boolean} [props.isDisabled=false] - Whether input is disabled
 * @param {boolean} [props.isTyping=false] - Whether AI is currently typing
 * @param {string} [props.placeholder='Type your message...'] - Input placeholder text
 * @param {number} [props.maxLength=1000] - Maximum message length
 * @param {number} [props.showCounterAt=800] - Character count to start showing counter
 * @param {boolean} [props.autoFocus=false] - Whether to auto-focus input
 * @param {Function} [props.onFocus] - Function called when input is focused
 * @param {Function} [props.onBlur] - Function called when input loses focus
 * 
 * @returns {JSX.Element} MessageInput component
 * 
 * @example
 * <MessageInput
 *   onSendMessage={(message) => console.log(message)}
 *   isDisabled={false}
 *   isTyping={false}
 *   maxLength={1000}
 *   autoFocus={true}
 * />
 */
const MessageInput = ({ 
  onSendMessage,
  isDisabled = false,
  isTyping = false,
  placeholder = 'Type your message...',
  maxLength = 1000,
  showCounterAt = 800,
  autoFocus = false,
  onFocus,
  onBlur 
}) => {
  const [inputMessage, setInputMessage] = useState('');
  const [isFocused, setIsFocused] = useState(false);
  const inputRef = useRef(null);

  // Auto-focus when enabled
  useEffect(() => {
    if (autoFocus && inputRef.current) {
      setTimeout(() => inputRef.current?.focus(), 100);
    }
  }, [autoFocus]);

  /**
   * Handles input value changes with validation
   * @param {Event} e - Input change event
   */
  const handleInputChange = useCallback((e) => {
    const value = e.target.value;
    
    // Prevent input beyond maxLength
    if (value.length <= maxLength) {
      setInputMessage(value);
    }
  }, [maxLength]);

  /**
   * Handles form submission
   * @param {Event} e - Form submit event
   */
  const handleSubmit = useCallback((e) => {
    e.preventDefault();
    const trimmedMessage = inputMessage.trim();
    
    // Validate message
    if (!trimmedMessage || isTyping || isDisabled) {
      return;
    }
    
    // Send message and clear input
    onSendMessage(trimmedMessage);
    setInputMessage('');
    
    // Refocus input after sending
    setTimeout(() => inputRef.current?.focus(), 50);
  }, [inputMessage, isTyping, isDisabled, onSendMessage]);

  /**
   * Handles keyboard shortcuts
   * @param {KeyboardEvent} e - Keyboard event
   */
  const handleKeyDown = useCallback((e) => {
    // Send on Enter (without Shift)
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  }, [handleSubmit]);

  /**
   * Handles input focus
   * @param {Event} e - Focus event
   */
  const handleFocus = useCallback((e) => {
    setIsFocused(true);
    if (onFocus) {
      onFocus(e);
    }
  }, [onFocus]);

  /**
   * Handles input blur
   * @param {Event} e - Blur event
   */
  const handleBlur = useCallback((e) => {
    setIsFocused(false);
    if (onBlur) {
      onBlur(e);
    }
  }, [onBlur]);

  // Calculate if send button should be enabled
  const isSendEnabled = inputMessage.trim() && !isTyping && !isDisabled;
  
  // Show character counter
  const showCounter = inputMessage.length >= showCounterAt;
  
  // Calculate remaining characters
  const remainingChars = maxLength - inputMessage.length;
  
  // Determine counter color based on remaining characters
  const getCounterClass = () => {
    if (remainingChars < 50) return 'counter-critical';
    if (remainingChars < 100) return 'counter-warning';
    return 'counter-normal';
  };

  return (
    <form 
      className="chat-input-form" 
      onSubmit={handleSubmit}
      role="form"
      aria-label="Message input form"
    >
      <div className={`input-container ${isFocused ? 'focused' : ''}`}>
        <textarea
          ref={inputRef}
          value={inputMessage}
          onChange={handleInputChange}
          onKeyDown={handleKeyDown}
          onFocus={handleFocus}
          onBlur={handleBlur}
          placeholder={placeholder}
          aria-label="Type your message"
          aria-describedby={showCounter ? 'char-counter' : undefined}
          className="message-input"
          rows="1"
          disabled={isDisabled}
          maxLength={maxLength}
          style={{
            minHeight: '20px',
            maxHeight: '120px',
            resize: 'none',
            overflow: 'auto',
          }}
        />
        
        <button
          type="submit"
          className={`send-button ${isSendEnabled ? 'enabled' : 'disabled'}`}
          disabled={!isSendEnabled}
          aria-label={`Send message${isTyping ? ' (AI is typing)' : ''}`}
          title={
            isTyping ? 'Please wait for AI response' :
            !inputMessage.trim() ? 'Enter a message to send' :
            'Send message (Enter)'
          }
        >
          <svg 
            width="20" 
            height="20" 
            viewBox="0 0 24 24" 
            fill="none" 
            aria-hidden="true"
          >
            <path 
              d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" 
              stroke="currentColor" 
              strokeWidth="2" 
              strokeLinecap="round" 
              strokeLinejoin="round"
            />
          </svg>
          <span className="sr-only">Send</span>
        </button>
      </div>
      
      {/* Character Counter */}
      {showCounter && (
        <div 
          id="char-counter"
          className={`character-counter ${getCounterClass()}`}
          role="status"
          aria-live="polite"
        >
          {inputMessage.length}/{maxLength}
          {remainingChars < 50 && (
            <span className="sr-only">
              {remainingChars} characters remaining
            </span>
          )}
        </div>
      )}
      
      {/* Input Helper Text */}
      {isFocused && !isDisabled && (
        <div className="input-helper" role="status">
          <span className="keyboard-hint">
            Press <kbd>Enter</kbd> to send, <kbd>Shift+Enter</kbd> for new line
          </span>
        </div>
      )}
    </form>
  );
};

MessageInput.propTypes = {
  /**
   * Function called when a message is submitted
   */
  onSendMessage: PropTypes.func.isRequired,
  
  /**
   * Whether the input is disabled
   */
  isDisabled: PropTypes.bool,
  
  /**
   * Whether AI is currently typing (prevents sending)
   */
  isTyping: PropTypes.bool,
  
  /**
   * Placeholder text for the input
   */
  placeholder: PropTypes.string,
  
  /**
   * Maximum length of message
   */
  maxLength: PropTypes.number,
  
  /**
   * Character count threshold to show counter
   */
  showCounterAt: PropTypes.number,
  
  /**
   * Whether to auto-focus the input
   */
  autoFocus: PropTypes.bool,
  
  /**
   * Function called when input is focused
   */
  onFocus: PropTypes.func,
  
  /**
   * Function called when input loses focus
   */
  onBlur: PropTypes.func,
};

MessageInput.defaultProps = {
  isDisabled: false,
  isTyping: false,
  placeholder: 'Type your message...',
  maxLength: 1000,
  showCounterAt: 800,
  autoFocus: false,
  onFocus: null,
  onBlur: null,
};

export default MessageInput;