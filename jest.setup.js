/**
 * Jest Setup Configuration for Woo AI Assistant React Widget
 * 
 * Global test environment setup and configuration
 * 
 * @package WooAiAssistant
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Import Jest DOM matchers for enhanced assertions
import '@testing-library/jest-dom';

// Mock WordPress globals that might be present in the browser
global.wp = {
  i18n: {
    __: jest.fn((text) => text),
    _x: jest.fn((text, context) => text),
    _n: jest.fn((single, plural, number) => (number === 1 ? single : plural)),
    sprintf: jest.fn((format, ...args) => {
      // Simple sprintf implementation for tests
      let i = 0;
      return format.replace(/%s/g, () => args[i++] || '');
    }),
  },
  hooks: {
    addAction: jest.fn(),
    addFilter: jest.fn(),
    doAction: jest.fn(),
    applyFilters: jest.fn((hook, value) => value),
  },
  apiFetch: jest.fn(() => Promise.resolve({})),
};

// Mock jQuery if needed (WordPress dependency)
global.jQuery = global.$ = jest.fn(() => ({
  ready: jest.fn(),
  on: jest.fn(),
  off: jest.fn(),
  trigger: jest.fn(),
  find: jest.fn(() => ({ length: 0 })),
  addClass: jest.fn(),
  removeClass: jest.fn(),
  toggleClass: jest.fn(),
  attr: jest.fn(),
  data: jest.fn(),
  val: jest.fn(),
  text: jest.fn(),
  html: jest.fn(),
  append: jest.fn(),
  prepend: jest.fn(),
  remove: jest.fn(),
  hide: jest.fn(),
  show: jest.fn(),
  fadeIn: jest.fn(),
  fadeOut: jest.fn(),
}));

// Mock window.wooAiAssistant global object
global.wooAiAssistant = {
  apiUrl: 'http://localhost/wp-json/woo-ai-assistant/v1',
  nonce: 'test-nonce',
  ajaxUrl: 'http://localhost/wp-admin/admin-ajax.php',
  pluginUrl: 'http://localhost/wp-content/plugins/woo-ai-assistant/',
  version: '1.0.0',
  debug: true,
  currentUser: {
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
  },
  currentPage: {
    type: 'product',
    id: 123,
    url: 'http://localhost/product/test-product/',
  },
  strings: {
    chatPlaceholder: 'Type your message...',
    sendButton: 'Send',
    loadingMessage: 'Loading...',
    errorMessage: 'Something went wrong. Please try again.',
    offlineMessage: 'You appear to be offline.',
  },
  limits: {
    maxMessageLength: 1000,
    maxConversationsPerMonth: 100,
    maxConversationLength: 50,
  },
};

// Mock ResizeObserver (used by some UI libraries)
global.ResizeObserver = jest.fn().mockImplementation(() => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn(),
}));

// Mock IntersectionObserver (used for lazy loading)
global.IntersectionObserver = jest.fn().mockImplementation(() => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn(),
}));

// Mock matchMedia for responsive tests
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(), // deprecated
    removeListener: jest.fn(), // deprecated
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
  })),
});

// Mock localStorage and sessionStorage
const localStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
  key: jest.fn(),
  length: 0,
};

const sessionStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
  key: jest.fn(),
  length: 0,
};

Object.defineProperty(window, 'localStorage', {
  value: localStorageMock,
  writable: true,
});

Object.defineProperty(window, 'sessionStorage', {
  value: sessionStorageMock,
  writable: true,
});

// Mock fetch API
global.fetch = jest.fn(() =>
  Promise.resolve({
    ok: true,
    status: 200,
    json: () => Promise.resolve({}),
    text: () => Promise.resolve(''),
    headers: {
      get: jest.fn(),
    },
  })
);

// Mock URL constructor for blob URLs and object URLs
global.URL.createObjectURL = jest.fn(() => 'blob:mock-url');
global.URL.revokeObjectURL = jest.fn();

// Enhanced console methods for better test debugging
const originalConsoleError = console.error;
const originalConsoleWarn = console.warn;

// Filter out common React warnings in tests
console.error = (...args) => {
  if (
    typeof args[0] === 'string' && 
    (args[0].includes('Warning: ReactDOM.render is no longer supported') ||
     args[0].includes('Warning: componentWillReceiveProps') ||
     args[0].includes('Warning: componentWillMount'))
  ) {
    return;
  }
  originalConsoleError.call(console, ...args);
};

console.warn = (...args) => {
  if (
    typeof args[0] === 'string' && 
    args[0].includes('Warning: React.createElement')
  ) {
    return;
  }
  originalConsoleWarn.call(console, ...args);
};

// Global test utilities
global.testUtils = {
  // Helper to create mock component props
  createMockProps: (overrides = {}) => ({
    onClose: jest.fn(),
    onSubmit: jest.fn(),
    onChange: jest.fn(),
    onClick: jest.fn(),
    ...overrides,
  }),

  // Helper to create mock API response
  createMockApiResponse: (data = {}, status = 200) => ({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve({
      success: status >= 200 && status < 300,
      data,
      message: status >= 200 && status < 300 ? 'Success' : 'Error',
      ...data,
    }),
    text: () => Promise.resolve(JSON.stringify(data)),
    headers: {
      get: jest.fn(),
    },
  }),

  // Helper to wait for async operations
  waitFor: (callback, timeout = 5000) => {
    return new Promise((resolve, reject) => {
      const startTime = Date.now();
      const checkCondition = () => {
        try {
          const result = callback();
          if (result) {
            resolve(result);
          } else if (Date.now() - startTime > timeout) {
            reject(new Error('Timeout waiting for condition'));
          } else {
            setTimeout(checkCondition, 10);
          }
        } catch (error) {
          if (Date.now() - startTime > timeout) {
            reject(error);
          } else {
            setTimeout(checkCondition, 10);
          }
        }
      };
      checkCondition();
    });
  },
};

// Setup fake timers for tests that need time control
beforeEach(() => {
  jest.clearAllMocks();
  
  // Reset storage mocks
  localStorageMock.getItem.mockClear();
  localStorageMock.setItem.mockClear();
  localStorageMock.removeItem.mockClear();
  localStorageMock.clear.mockClear();
  
  sessionStorageMock.getItem.mockClear();
  sessionStorageMock.setItem.mockClear();
  sessionStorageMock.removeItem.mockClear();
  sessionStorageMock.clear.mockClear();
  
  // Reset fetch mock
  fetch.mockClear();
});

afterEach(() => {
  // Clean up any remaining timers
  jest.runOnlyPendingTimers();
  jest.useRealTimers();
});