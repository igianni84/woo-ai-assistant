/**
 * Main App Component
 *
 * Root component for the chat widget that handles the overall
 * widget state and renders the appropriate UI components.
 * Implements proper state management, error boundaries, and accessibility.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useCallback, useEffect, useMemo } from 'react';
import PropTypes from 'prop-types';
import WidgetErrorBoundary from './components/WidgetErrorBoundary';
import ChatToggleButton from './components/ChatToggleButton';
import ChatWindow from './components/ChatWindow';
import { useChat } from './hooks/useChat';

/**
 * Main App Component
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {Object} props.userContext - User context data from WordPress
 * @param {Object} props.wooCommerceData - WooCommerce specific data
 * @param {Object} props.config - Widget configuration
 * @returns {JSX.Element} Main widget component
 */
const App = ({ userContext = {}, wooCommerceData = {}, config = {} }) => {
  // Widget visibility state
  const [isOpen, setIsOpen] = useState(false);
  const [isMinimized, setIsMinimized] = useState(false);
  
  // Chat hook for conversation management
  const {
    messages,
    isTyping,
    isConnected,
    conversationId,
    sendMessage,
    clearMessages,
    error: chatError
  } = useChat({
    userContext,
    wooCommerceData,
    config
  });

  // Widget toggle handlers
  const handleToggle = useCallback(() => {
    setIsOpen(prev => {
      const newState = !prev;
      
      // Track widget interactions for analytics
      if (window.wooAiAssistant?.trackEvent) {
        window.wooAiAssistant.trackEvent('widget_toggle', {
          action: newState ? 'open' : 'close',
          page: window.location.pathname,
          conversationId
        });
      }
      
      return newState;
    });
  }, [conversationId]);

  const handleClose = useCallback(() => {
    setIsOpen(false);
    setIsMinimized(false);
  }, []);

  const handleMinimize = useCallback(() => {
    setIsMinimized(true);
    setIsOpen(false);
  }, []);

  // Handle escape key to close widget
  useEffect(() => {
    const handleEscape = (event) => {
      if (event.key === 'Escape' && isOpen) {
        handleClose();
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleEscape);
      // Prevent body scroll when chat is open
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    return () => {
      document.removeEventListener('keydown', handleEscape);
      document.body.style.overflow = '';
    };
  }, [isOpen, handleClose]);

  // Calculate widget state for styling
  const widgetState = useMemo(() => ({
    isOpen,
    isMinimized,
    isConnected,
    hasError: !!chatError,
    messageCount: messages.length
  }), [isOpen, isMinimized, isConnected, chatError, messages.length]);

  return (
    <WidgetErrorBoundary>
      <div 
        className={`woo-ai-assistant-app ${
          Object.entries(widgetState)
            .filter(([, value]) => value)
            .map(([key]) => `woo-ai-assistant-app--${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`)
            .join(' ')
        }`}
        data-widget-state={JSON.stringify(widgetState)}
      >
        {/* Toggle Button */}
        <ChatToggleButton
          isOpen={isOpen}
          isMinimized={isMinimized}
          isConnected={isConnected}
          hasError={!!chatError}
          messageCount={messages.length}
          onToggle={handleToggle}
        />

        {/* Chat Window */}
        {isOpen && (
          <ChatWindow
            isVisible={isOpen}
            messages={messages}
            isTyping={isTyping}
            isConnected={isConnected}
            conversationId={conversationId}
            userContext={userContext}
            wooCommerceData={wooCommerceData}
            config={config}
            error={chatError}
            onClose={handleClose}
            onMinimize={handleMinimize}
            onSendMessage={sendMessage}
            onClearMessages={clearMessages}
          />
        )}

        {/* Accessibility announcements */}
        <div 
          id="woo-ai-assistant-announcements" 
          className="sr-only" 
          aria-live="polite" 
          aria-atomic="true"
        />
      </div>
    </WidgetErrorBoundary>
  );
};

// Component PropTypes
App.propTypes = {
  userContext: PropTypes.shape({
    userId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    userName: PropTypes.string,
    userEmail: PropTypes.string,
    isLoggedIn: PropTypes.bool,
    capabilities: PropTypes.array,
    currentPage: PropTypes.string,
    currentPost: PropTypes.object
  }),
  wooCommerceData: PropTypes.shape({
    cartItems: PropTypes.array,
    cartTotal: PropTypes.string,
    currency: PropTypes.string,
    currencySymbol: PropTypes.string,
    currentProduct: PropTypes.object,
    currentCategory: PropTypes.object,
    recentlyViewed: PropTypes.array
  }),
  config: PropTypes.shape({
    apiUrl: PropTypes.string,
    nonce: PropTypes.string,
    features: PropTypes.object,
    styling: PropTypes.object,
    behavior: PropTypes.object
  })
};

// Default props
App.defaultProps = {
  userContext: {},
  wooCommerceData: {},
  config: {}
};

export default App;

// Export for testing
export { App }; // Named export for testing
