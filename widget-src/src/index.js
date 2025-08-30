/**
 * Widget Entry Point
 *
 * Main entry point for the React chat widget that will be loaded
 * on the frontend of the website.
 *
 * @package WooAiAssistant
 * @subpackage Widget
 * @since 1.0.0
 */

import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/widget.scss';

// Initialize widget when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  initializeWidget();
});

/**
 * Initialize the chat widget
 */
function initializeWidget() {
  // Check if widget should be loaded
  if (!shouldLoadWidget()) {
    return;
  }

  // Create widget container
  const container = createWidgetContainer();

  // Mount React app
  const root = createRoot(container);
  root.render(<App />);

  // Add to page
  document.body.appendChild(container);

  // Widget initialization complete
}

/**
 * Check if widget should be loaded on current page
 */
function shouldLoadWidget() {
  // Don't load on admin pages
  if (window.location.pathname.includes('/wp-admin/')) {
    return false;
  }

  // Check if disabled via settings
  if (window.wooAiAssistant?.settings?.chatEnabled === false) {
    return false;
  }

  // Check if already loaded
  if (document.getElementById('woo-ai-assistant-widget')) {
    return false;
  }

  return true;
}

/**
 * Create widget container element
 */
function createWidgetContainer() {
  const container = document.createElement('div');
  container.id = 'woo-ai-assistant-widget';
  container.className = 'woo-ai-assistant-widget';

  return container;
}

// Export for testing
export { initializeWidget, shouldLoadWidget, createWidgetContainer };
