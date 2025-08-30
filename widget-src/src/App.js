/**
 * Main App Component
 *
 * Root component for the chat widget that handles the overall
 * widget state and renders the appropriate UI components.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 */

import { useState, useCallback } from 'react';

/**
 * Main App Component
 */
const App = () => {
  const [isOpen, setIsOpen] = useState(false);

  const handleToggle = useCallback(() => {
    setIsOpen(prev => !prev);
  }, []);

  const handleClose = useCallback(() => {
    setIsOpen(false);
  }, []);

  return (
    <div className='woo-ai-assistant-app'>
      {/* Widget Toggle Button */}
      <button
        className={`woo-ai-assistant-toggle ${isOpen ? 'active' : ''}`}
        onClick={handleToggle}
        aria-label={isOpen ? 'Close chat' : 'Open chat'}
        type='button'
      >
        {isOpen ? (
          <CloseIcon />
        ) : (
          <ChatIcon />
        )}
      </button>

      {/* Chat Window */}
      {isOpen && (
        <div
          className={`woo-ai-assistant-chat-window ${isOpen ? 'visible' : ''}`}
          role='dialog'
          aria-label='AI Assistant Chat'
          aria-modal='true'
        >
          <div className='woo-ai-assistant-chat-header'>
            <h2 className='woo-ai-assistant-chat-title'>
              AI Assistant
            </h2>
            <button
              className='woo-ai-assistant-chat-close'
              onClick={handleClose}
              aria-label='Close chat'
              type='button'
            >
              <CloseIcon />
            </button>
          </div>

          <div className='woo-ai-assistant-chat-content'>
            <div className='woo-ai-assistant-messages'>
              <div className='woo-ai-assistant-message assistant'>
                <div className='woo-ai-assistant-message-content'>
                  Hi! I&apos;m your AI shopping assistant. How can I help you today?
                </div>
              </div>
            </div>

            <div className='woo-ai-assistant-input-area'>
              <textarea
                className='woo-ai-assistant-input'
                placeholder='Type your message...'
                rows={1}
                aria-label='Message input'
              />
              <button
                className='woo-ai-assistant-send'
                type='button'
                aria-label='Send message'
              >
                <SendIcon />
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

/**
 * Chat Icon Component
 */
const ChatIcon = () => (
  <svg width='28' height='28' viewBox='0 0 28 28' fill='none' aria-hidden='true'>
    <path
      d='M21 6H7C5.9 6 5 6.9 5 8V16C5 17.1 5.9 18 7 18H8V22L12 18H21C22.1 18 23 17.1 23 16V8C23 6.9 22.1 6 21 6Z'
      fill='currentColor'
    />
    <rect x='8' y='10' width='8' height='1.5' rx='0.75' fill='white' opacity='0.8' />
    <rect x='8' y='12.5' width='10' height='1.5' rx='0.75' fill='white' opacity='0.8' />
  </svg>
);

/**
 * Close Icon Component
 */
const CloseIcon = () => (
  <svg width='24' height='24' viewBox='0 0 24 24' fill='none' aria-hidden='true'>
    <path
      d='M18 6L6 18M6 6L18 18'
      stroke='currentColor'
      strokeWidth='2'
      strokeLinecap='round'
      strokeLinejoin='round'
    />
  </svg>
);

/**
 * Send Icon Component
 */
const SendIcon = () => (
  <svg width='20' height='20' viewBox='0 0 20 20' fill='none' aria-hidden='true'>
    <path
      d='M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11'
      stroke='currentColor'
      strokeWidth='2'
      strokeLinecap='round'
      strokeLinejoin='round'
    />
  </svg>
);

export default App;
