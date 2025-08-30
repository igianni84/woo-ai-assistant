/**
 * Jest setup file for React component testing
 * 
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import '@testing-library/jest-dom';

// Mock WordPress global objects
global.wp = {
  i18n: {
    __: (text) => text,
    _x: (text) => text,
    _n: (single, plural, number) => number === 1 ? single : plural,
    sprintf: (format, ...args) => {
      let i = 0;
      return format.replace(/%[sd%]/g, () => args[i++] || '');
    }
  },
  apiFetch: jest.fn(),
  hooks: {
    addAction: jest.fn(),
    addFilter: jest.fn(),
    doAction: jest.fn(),
    applyFilters: jest.fn()
  }
};

// Mock wooAiAssistant global object
global.wooAiAssistant = {
  ajaxUrl: 'http://localhost/wp-admin/admin-ajax.php',
  nonce: 'test-nonce',
  apiUrl: 'http://localhost/wp-json/woo-ai-assistant/v1',
  restNonce: 'test-rest-nonce',
  settings: {
    chatEnabled: true,
    maxMessages: 100,
    theme: 'light'
  },
  debug: true
};

// Mock ResizeObserver
global.ResizeObserver = jest.fn().mockImplementation(() => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn()
}));

// Mock IntersectionObserver
global.IntersectionObserver = jest.fn().mockImplementation(() => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn()
}));

// Mock matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(),
    removeListener: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn()
  }))
});

// Mock CSS.supports for styling tests
global.CSS = {
  supports: jest.fn(() => false)
};

// Increase timeout for async tests
jest.setTimeout(10000);

// Clean up after each test
afterEach(() => {
  jest.clearAllMocks();
});