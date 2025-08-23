/**
 * Woo AI Assistant React Widget Entry Point
 * 
 * Main entry point for the React chat widget that integrates with WordPress
 * 
 * @package WooAiAssistant
 * @subpackage Widget
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

import App from './App';
import './styles/index.css';

// WordPress integration - wait for DOM and WordPress to be ready
const initializeWidget = () => {
  // Check if WordPress and required globals are available
  if (typeof window.wooAiAssistant === 'undefined') {
    console.error('Woo AI Assistant: WordPress integration not found');
    return;
  }

  // Find all widget containers on the page
  const widgetContainers = document.querySelectorAll('[data-woo-ai-widget]');
  
  if (widgetContainers.length === 0) {
    // Create a default container if none exists (for manual initialization)
    const container = document.createElement('div');
    container.id = 'woo-ai-assistant-widget';
    container.setAttribute('data-woo-ai-widget', 'true');
    document.body.appendChild(container);
    widgetContainers = [container];
  }

  // Initialize widget in each container
  widgetContainers.forEach((container, index) => {
    const widgetId = `woo-ai-widget-${index}`;
    container.id = widgetId;

    // Get configuration from data attributes
    const config = {
      // Widget positioning
      position: container.getAttribute('data-position') || 'bottom-right',
      
      // Widget behavior
      autoOpen: container.getAttribute('data-auto-open') === 'true',
      minimized: container.getAttribute('data-minimized') !== 'false',
      
      // User context from WordPress
      userId: window.wooAiAssistant.currentUser?.id || 0,
      userEmail: window.wooAiAssistant.currentUser?.email || '',
      
      // Page context
      pageType: window.wooAiAssistant.currentPage?.type || 'unknown',
      pageId: window.wooAiAssistant.currentPage?.id || 0,
      pageUrl: window.wooAiAssistant.currentPage?.url || window.location.href,
      
      // Custom settings from shortcode or widget
      welcomeMessage: container.getAttribute('data-welcome-message') || '',
      theme: container.getAttribute('data-theme') || 'light',
      language: container.getAttribute('data-language') || 'en',
      
      // API configuration
      apiEndpoint: window.wooAiAssistant.apiUrl,
      nonce: window.wooAiAssistant.nonce,
      
      // Plugin information
      pluginVersion: window.wooAiAssistant.version,
      isDebug: window.wooAiAssistant.debug || false,
    };

    try {
      // Create React root and render the app
      const root = createRoot(container);
      root.render(
        <React.StrictMode>
          <App 
            widgetId={widgetId}
            config={config}
          />
        </React.StrictMode>
      );

      // Store root reference for potential cleanup
      container._reactRoot = root;

      // Log successful initialization in debug mode
      if (config.isDebug) {
        console.log('Woo AI Assistant Widget initialized:', {
          widgetId,
          config,
          container,
        });
      }

    } catch (error) {
      console.error('Woo AI Assistant Widget initialization failed:', error);
      
      // Fallback error display
      container.innerHTML = `
        <div style="
          background: #f8d7da; 
          color: #721c24; 
          padding: 12px; 
          border-radius: 4px; 
          border: 1px solid #f5c6cb;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          font-size: 14px;
        ">
          <strong>Woo AI Assistant Error:</strong> Failed to initialize chat widget.
          ${config.isDebug ? `<br><small>${error.message}</small>` : ''}
        </div>
      `;
    }
  });
};

// Multiple initialization strategies to ensure widget loads properly

// 1. If DOM is already ready
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  // Small delay to ensure all WordPress scripts have loaded
  setTimeout(initializeWidget, 100);
}

// 2. Wait for DOM ready
document.addEventListener('DOMContentLoaded', initializeWidget);

// 3. Wait for full page load (fallback)
window.addEventListener('load', initializeWidget);

// 4. WordPress-specific ready event (if available)
if (typeof jQuery !== 'undefined') {
  jQuery(document).ready(initializeWidget);
}

// 5. Manual initialization method for programmatic use
window.WooAiAssistant = {
  // Initialize widget manually
  init: initializeWidget,
  
  // Reinitialize widget (useful for SPA navigation)
  reinit: () => {
    // Clean up existing widgets first
    document.querySelectorAll('[data-woo-ai-widget]').forEach(container => {
      if (container._reactRoot) {
        container._reactRoot.unmount();
        container._reactRoot = null;
      }
    });
    
    // Reinitialize
    initializeWidget();
  },
  
  // Check if widget is loaded
  isLoaded: () => {
    return document.querySelectorAll('[data-woo-ai-widget] [data-widget-loaded="true"]').length > 0;
  },
  
  // Get widget instances
  getInstances: () => {
    return Array.from(document.querySelectorAll('[data-woo-ai-widget]'))
      .filter(container => container._reactRoot)
      .map(container => ({
        id: container.id,
        container,
        root: container._reactRoot,
      }));
  },
};

// Export for potential module usage
export default window.WooAiAssistant;