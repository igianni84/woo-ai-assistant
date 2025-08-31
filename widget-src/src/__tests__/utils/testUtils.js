/**
 * Test Utilities for React Components
 *
 * Common utilities and helpers for testing React components
 * in the Woo AI Assistant widget.
 *
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Custom render function that wraps components with necessary providers
 *
 * @param {React.ReactElement} ui - The component to render
 * @param {Object} options - Additional render options
 * @returns {Object} Render result with additional utilities
 */
export const renderWithContext = (ui, options = {}) => {
  const { initialState = {}, ...renderOptions } = options;

  // Mock context provider wrapper if needed
  const Wrapper = ({ children }) => {
    return (
      <div data-testid="test-wrapper">
        {children}
      </div>
    );
  };

  const result = render(ui, { wrapper: Wrapper, ...renderOptions });

  return {
    ...result,
    user: userEvent.setup()
  };
};

/**
 * Wait for element to be removed with timeout
 *
 * @param {Function} queryFunction - Query function to check element
 * @param {Object} options - Wait options
 * @returns {Promise} Promise that resolves when element is removed
 */
export const waitForElementToBeRemoved = async (queryFunction, options = {}) => {
  const { timeout = 3000 } = options;

  return new Promise((resolve, reject) => {
    const startTime = Date.now();

    const checkElement = () => {
      const element = queryFunction();

      if (!element) {
        resolve();
        return;
      }

      if (Date.now() - startTime > timeout) {
        reject(new Error('Element was not removed within timeout'));
        return;
      }

      setTimeout(checkElement, 50);
    };

    checkElement();
  });
};

/**
 * Create mock event for testing
 *
 * @param {string} type - Event type
 * @param {Object} properties - Event properties
 * @returns {Object} Mock event object
 */
export const createMockEvent = (type, properties = {}) => ({
  type,
  preventDefault: jest.fn(),
  stopPropagation: jest.fn(),
  target: {
    value: '',
    checked: false,
    ...properties.target
  },
  ...properties
});

/**
 * Mock resize observer for component testing
 *
 * @returns {Object} Mock ResizeObserver
 */
export const mockResizeObserver = () => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn()
});

/**
 * Mock intersection observer for component testing
 *
 * @returns {Object} Mock IntersectionObserver
 */
export const mockIntersectionObserver = () => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn(),
  root: null,
  rootMargin: '0px',
  thresholds: [0]
});

/**
 * Assert that an element has proper ARIA attributes
 *
 * @param {HTMLElement} element - Element to check
 * @param {Object} expectedAttributes - Expected ARIA attributes
 */
export const assertAriaAttributes = (element, expectedAttributes) => {
  Object.entries(expectedAttributes).forEach(([attribute, value]) => {
    const ariaAttribute = attribute.startsWith('aria-') ? attribute : `aria-${attribute}`;
    expect(element).toHaveAttribute(ariaAttribute, value);
  });
};

/**
 * Check if component follows PascalCase naming convention
 *
 * @param {Function} Component - React component
 * @param {string} expectedName - Expected component name
 */
export const assertComponentNaming = (Component, expectedName) => {
  expect(Component.name).toBe(expectedName);

  // Check PascalCase pattern
  const isPascalCase = /^[A-Z][a-zA-Z0-9]*$/.test(expectedName);
  expect(isPascalCase).toBe(true);
};

/**
 * Assert that function names follow camelCase convention
 *
 * @param {Object} obj - Object with methods to check
 * @param {string[]} methodNames - Method names to validate
 */
export const assertMethodNaming = (obj, methodNames) => {
  methodNames.forEach(methodName => {
    expect(typeof obj[methodName]).toBe('function');

    // Check camelCase pattern (starts with lowercase, no underscores)
    const isCamelCase = /^[a-z][a-zA-Z0-9]*$/.test(methodName);
    expect(isCamelCase).toBe(true);
  });
};

/**
 * Create mock API response
 *
 * @param {Object} data - Response data
 * @param {number} status - HTTP status code
 * @param {boolean} success - Success flag
 * @returns {Object} Mock API response
 */
export const createMockApiResponse = (data = {}, status = 200, success = true) => ({
  data,
  status,
  success,
  message: success ? 'Success' : 'Error',
  timestamp: Date.now()
});

/**
 * Mock WordPress globals for testing
 *
 * @param {Object} overrides - Override specific global values
 */
export const mockWordPressGlobals = (overrides = {}) => {
  // Mock scrollIntoView for testing environment
  if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = jest.fn();
  }

  const defaults = {
    wp: {
      i18n: {
        __: jest.fn(text => text),
        _x: jest.fn(text => text),
        _n: jest.fn((single, plural, number) => number === 1 ? single : plural)
      },
      apiFetch: jest.fn(() => Promise.resolve({})),
      hooks: {
        addAction: jest.fn(),
        addFilter: jest.fn(),
        doAction: jest.fn(),
        applyFilters: jest.fn()
      }
    },
    wooAiAssistant: {
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
    }
  };

  Object.assign(global, { ...defaults, ...overrides });
};

// Export all utilities as default
export default {
  renderWithContext,
  waitForElementToBeRemoved,
  createMockEvent,
  mockResizeObserver,
  mockIntersectionObserver,
  assertAriaAttributes,
  assertComponentNaming,
  assertMethodNaming,
  createMockApiResponse,
  mockWordPressGlobals
};
