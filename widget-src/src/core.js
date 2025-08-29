/**
 * Core Widget Entry Point
 * 
 * Minimal core functionality for initial widget load.
 * Includes only essential components to keep bundle size <30KB.
 * 
 * @package WooAiAssistant
 * @subpackage Widget Core
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import ReactDOM from 'react-dom';
import ErrorBoundary from './components/ErrorBoundary';
import ApiService from './services/ApiService';

// Core styles - critical for first paint
import './styles/core.css';

/**
 * Minimal widget trigger component
 * Only renders the chat trigger button for initial load
 */
const WidgetTrigger = React.memo(() => {
  const [isVisible, setIsVisible] = React.useState(false);
  
  const handleTriggerClick = React.useCallback(async () => {
    // Lazy load the full chat interface when needed
    if (!isVisible) {
      try {
        // Dynamic import for code splitting
        const { default: ChatWindow } = await import(
          /* webpackChunkName: "chat-bundle" */ './components/ChatWindow'
        );
        
        setIsVisible(true);
        
        // Mount the chat window
        const chatContainer = document.getElementById('woo-ai-chat-container');
        if (chatContainer) {
          ReactDOM.render(
            <ErrorBoundary>
              <ChatWindow 
                onClose={() => setIsVisible(false)}
                isVisible={isVisible}
              />
            </ErrorBoundary>,
            chatContainer
          );
        }
      } catch (error) {
        console.error('Failed to load chat interface:', error);
      }
    } else {
      setIsVisible(false);
    }
  }, [isVisible]);

  return (
    <button
      className="woo-ai-trigger"
      onClick={handleTriggerClick}
      aria-label="Open AI Assistant"
      type="button"
    >
      <svg
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
          fill="currentColor"
        />
      </svg>
    </button>
  );
});

WidgetTrigger.displayName = 'WidgetTrigger';

/**
 * Core widget initialization
 * Lightweight initialization that loads quickly
 */
class WidgetCore {
  constructor() {
    this.initialized = false;
    this.apiService = new ApiService();
  }

  /**
   * Initialize the core widget
   * @param {HTMLElement} container - Widget container element
   * @param {Object} config - Widget configuration
   */
  init(container, config = {}) {
    if (this.initialized) {
      return;
    }

    try {
      // Set up API service with configuration
      this.apiService.configure({
        baseUrl: config.apiUrl || window.wooAiAssistant?.apiUrl,
        nonce: config.nonce || window.wooAiAssistant?.nonce,
      });

      // Create chat container for lazy loading
      const chatContainer = document.createElement('div');
      chatContainer.id = 'woo-ai-chat-container';
      chatContainer.style.display = 'none';
      document.body.appendChild(chatContainer);

      // Render the trigger button
      ReactDOM.render(
        <ErrorBoundary>
          <WidgetTrigger />
        </ErrorBoundary>,
        container
      );

      this.initialized = true;
      
      // Preload chat bundle if idle
      this.preloadChatBundle();
      
    } catch (error) {
      console.error('Widget core initialization failed:', error);
    }
  }

  /**
   * Preload chat bundle during idle time for better performance
   */
  preloadChatBundle() {
    // Use requestIdleCallback or setTimeout as fallback
    const schedulePreload = (callback) => {
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(callback, { timeout: 2000 });
      } else {
        setTimeout(callback, 1000);
      }
    };

    schedulePreload(() => {
      // Prefetch chat bundle
      import(
        /* webpackChunkName: "chat-bundle" */
        /* webpackPrefetch: true */
        './components/ChatWindow'
      ).catch(() => {
        // Silent fail for prefetch
      });
    });
  }

  /**
   * Destroy the widget instance
   */
  destroy() {
    if (this.initialized) {
      const chatContainer = document.getElementById('woo-ai-chat-container');
      if (chatContainer) {
        ReactDOM.unmountComponentAtNode(chatContainer);
        chatContainer.remove();
      }
      this.initialized = false;
    }
  }
}

// Export for global access
export default WidgetCore;

// Auto-initialize if window.wooAiAssistant exists
if (typeof window !== 'undefined' && window.wooAiAssistant) {
  const widget = new WidgetCore();
  
  // Make widget available globally
  window.wooAiAssistant.core = widget;
  
  // Auto-initialize if container exists
  const container = document.getElementById('woo-ai-assistant-widget');
  if (container) {
    widget.init(container, window.wooAiAssistant);
  }
}