/**
 * Woo AI Assistant Main App Component
 * 
 * Root component for the React chat widget that manages overall state
 * and renders the chat interface.
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useEffect, useCallback } from 'react';
import PropTypes from 'prop-types';

import ChatWidget from './components/ChatWidget';
import ErrorBoundary from './components/ErrorBoundary';
import { ApiProvider } from './services/ApiService';
import { useChat } from './hooks/useChat';
import { useKeyboardNavigation } from './hooks/useKeyboardNavigation';

const App = ({ widgetId, config }) => {
  const [isVisible, setIsVisible] = useState(!config.minimized);
  const [isLoaded, setIsLoaded] = useState(false);
  const [error, setError] = useState(null);

  // Initialize chat functionality
  const {
    conversation,
    isTyping,
    connectionStatus,
    sendMessage,
    clearConversation,
    reconnect,
  } = useChat({
    apiEndpoint: config.apiEndpoint,
    nonce: config.nonce,
    userId: config.userId,
    pageContext: {
      type: config.pageType,
      id: config.pageId,
      url: config.pageUrl,
    },
    onError: setError,
  });

  // Setup keyboard navigation for accessibility
  useKeyboardNavigation({
    isVisible,
    onToggleVisibility: () => setIsVisible(!isVisible),
    onEscape: () => setIsVisible(false),
  });

  // Handle widget visibility toggle
  const handleToggleVisibility = useCallback(() => {
    setIsVisible(prevVisible => !prevVisible);
    
    // Clear any errors when opening the widget
    if (!isVisible && error) {
      setError(null);
    }
  }, [isVisible, error]);

  // Handle widget close
  const handleClose = useCallback(() => {
    setIsVisible(false);
  }, []);

  // Auto-open widget if configured
  useEffect(() => {
    if (config.autoOpen && !isVisible) {
      // Delay auto-open to avoid being intrusive
      const timer = setTimeout(() => {
        setIsVisible(true);
      }, 2000);
      
      return () => clearTimeout(timer);
    }
  }, [config.autoOpen, isVisible]);

  // Mark widget as loaded
  useEffect(() => {
    setIsLoaded(true);
    
    // Add loaded attribute to container
    const container = document.getElementById(widgetId);
    if (container) {
      container.setAttribute('data-widget-loaded', 'true');
    }

    // Cleanup on unmount
    return () => {
      if (container) {
        container.removeAttribute('data-widget-loaded');
      }
    };
  }, [widgetId]);

  // Log widget lifecycle in debug mode
  useEffect(() => {
    if (config.isDebug) {
      console.log('Woo AI Assistant Widget App mounted:', {
        widgetId,
        config,
        isVisible,
        connectionStatus,
      });
    }
  }, [widgetId, config, isVisible, connectionStatus]);

  // Handle critical errors
  if (error && error.critical) {
    return (
      <div 
        className="woo-ai-widget-error"
        role="alert"
        aria-live="polite"
      >
        <div className="error-content">
          <h4>Chat Unavailable</h4>
          <p>
            The chat service is temporarily unavailable. 
            Please try again later or contact support.
          </p>
          {config.isDebug && (
            <details>
              <summary>Error Details</summary>
              <pre>{error.message}</pre>
            </details>
          )}
        </div>
      </div>
    );
  }

  return (
    <ErrorBoundary
      onError={(errorInfo) => {
        console.error('Woo AI Assistant Widget Error:', errorInfo);
        if (config.isDebug) {
          setError({
            message: errorInfo.error.message,
            stack: errorInfo.errorInfo.componentStack,
            critical: false,
          });
        }
      }}
    >
      <ApiProvider
        baseUrl={config.apiEndpoint}
        nonce={config.nonce}
        debug={config.isDebug}
      >
        <div 
          className={`woo-ai-assistant-app ${config.theme}`}
          data-widget-id={widgetId}
          data-position={config.position}
          data-visible={isVisible}
          data-loaded={isLoaded}
          style={{
            // CSS custom properties for theming
            '--widget-primary-color': config.primaryColor || '#007cba',
            '--widget-secondary-color': config.secondaryColor || '#f0f0f1',
            '--widget-text-color': config.textColor || '#1e1e1e',
            '--widget-border-radius': config.borderRadius || '8px',
          }}
        >
          <ChatWidget
            isVisible={isVisible}
            onToggleVisibility={handleToggleVisibility}
            onClose={handleClose}
            
            // Chat functionality
            conversation={conversation}
            isTyping={isTyping}
            connectionStatus={connectionStatus}
            onSendMessage={sendMessage}
            onClearConversation={clearConversation}
            onReconnect={reconnect}
            
            // Configuration
            config={{
              welcomeMessage: config.welcomeMessage,
              language: config.language,
              theme: config.theme,
              position: config.position,
              strings: window.wooAiAssistant.strings || {},
              limits: window.wooAiAssistant.limits || {},
            }}
            
            // User and page context
            userContext={{
              id: config.userId,
              email: config.userEmail,
            }}
            pageContext={{
              type: config.pageType,
              id: config.pageId,
              url: config.pageUrl,
            }}
            
            // Error handling
            error={error}
            onDismissError={() => setError(null)}
          />
        </div>
      </ApiProvider>
    </ErrorBoundary>
  );
};

App.propTypes = {
  /**
   * Unique identifier for this widget instance
   */
  widgetId: PropTypes.string.isRequired,
  
  /**
   * Widget configuration object
   */
  config: PropTypes.shape({
    // Positioning
    position: PropTypes.oneOf(['bottom-left', 'bottom-right', 'top-left', 'top-right']),
    
    // Behavior
    autoOpen: PropTypes.bool,
    minimized: PropTypes.bool,
    
    // User context
    userId: PropTypes.number,
    userEmail: PropTypes.string,
    
    // Page context
    pageType: PropTypes.string,
    pageId: PropTypes.number,
    pageUrl: PropTypes.string,
    
    // Customization
    welcomeMessage: PropTypes.string,
    theme: PropTypes.oneOf(['light', 'dark', 'auto']),
    language: PropTypes.string,
    primaryColor: PropTypes.string,
    secondaryColor: PropTypes.string,
    textColor: PropTypes.string,
    borderRadius: PropTypes.string,
    
    // API
    apiEndpoint: PropTypes.string.isRequired,
    nonce: PropTypes.string.isRequired,
    
    // Debug
    isDebug: PropTypes.bool,
    pluginVersion: PropTypes.string,
  }).isRequired,
};

App.defaultProps = {
  config: {
    position: 'bottom-right',
    autoOpen: false,
    minimized: true,
    theme: 'light',
    language: 'en',
    isDebug: false,
  },
};

export default App;