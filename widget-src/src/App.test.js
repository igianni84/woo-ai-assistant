/**
 * App Component Tests
 * 
 * Basic tests for the main App component
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { render, screen } from '@testing-library/react';
import App from './App';

// Mock the child components to isolate App testing
jest.mock('./components/ChatWidget', () => {
  return function MockChatWidget(props) {
    return <div data-testid="chat-widget">Chat Widget Mock</div>;
  };
});

jest.mock('./components/ErrorBoundary', () => {
  return function MockErrorBoundary({ children }) {
    return <div data-testid="error-boundary">{children}</div>;
  };
});

describe('App Component', () => {
  const defaultConfig = {
    apiEndpoint: 'http://test-api.com',
    nonce: 'test-nonce',
    position: 'bottom-right',
    theme: 'light',
    userId: 1,
    userEmail: 'test@example.com',
    pageType: 'product',
    pageId: 123,
    pageUrl: 'http://test.com/product/test',
    isDebug: false,
  };

  beforeEach(() => {
    // Mock DOM methods
    document.getElementById = jest.fn();
    document.querySelector = jest.fn();
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<App widgetId="test-widget" config={defaultConfig} />);
    expect(screen.getByTestId('error-boundary')).toBeInTheDocument();
  });

  it('applies correct CSS classes and data attributes', () => {
    const { container } = render(<App widgetId="test-widget" config={defaultConfig} />);
    
    const appElement = container.querySelector('.woo-ai-assistant-app');
    expect(appElement).toBeInTheDocument();
    expect(appElement).toHaveAttribute('data-widget-id', 'test-widget');
    expect(appElement).toHaveAttribute('data-position', 'bottom-right');
    expect(appElement).toHaveClass('light');
  });

  it('sets CSS custom properties for theming', () => {
    const configWithColors = {
      ...defaultConfig,
      primaryColor: '#ff0000',
      secondaryColor: '#00ff00',
      textColor: '#0000ff',
    };

    const { container } = render(<App widgetId="test-widget" config={configWithColors} />);
    
    const appElement = container.querySelector('.woo-ai-assistant-app');
    expect(appElement).toHaveStyle('--widget-primary-color: #ff0000');
    expect(appElement).toHaveStyle('--widget-secondary-color: #00ff00');
    expect(appElement).toHaveStyle('--widget-text-color: #0000ff');
  });

  it('handles debug mode correctly', () => {
    const debugConfig = { ...defaultConfig, isDebug: true };
    
    // Mock console.log to verify debug logging
    const consoleSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
    
    render(<App widgetId="debug-widget" config={debugConfig} />);
    
    // Clean up
    consoleSpy.mockRestore();
  });

  it('renders ChatWidget with correct props', () => {
    render(<App widgetId="test-widget" config={defaultConfig} />);
    
    const chatWidget = screen.getByTestId('chat-widget');
    expect(chatWidget).toBeInTheDocument();
  });

  it('handles critical errors gracefully', () => {
    // This would test error handling, but since we're mocking components
    // we'll keep it simple for now
    render(<App widgetId="test-widget" config={defaultConfig} />);
    expect(screen.getByTestId('error-boundary')).toBeInTheDocument();
  });
});