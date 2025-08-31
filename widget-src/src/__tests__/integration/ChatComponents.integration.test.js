/**
 * Chat Components Integration Tests
 *
 * Integration tests that verify all chat components work together
 * correctly, including ChatWindow, Message, TypingIndicator,
 * and their interactions with the App component.
 *
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import App from '../../App';
import { 
  renderWithContext, 
  mockWordPressGlobals,
  createMockApiResponse 
} from '../utils/testUtils';
import { mockMessages, mockUser, mockWidgetSettings } from '../mocks/mockData';

// Mock the useChat hook for integration testing
jest.mock('../../hooks/useChat', () => ({
  useChat: jest.fn(() => ({
    messages: [],
    isTyping: false,
    isConnected: true,
    conversationId: 'test-conv-123',
    sendMessage: jest.fn(),
    clearMessages: jest.fn(),
    retry: jest.fn(),
    error: null,
    isLoading: false,
    canSend: true,
    messageCount: 0,
    hasUnreadMessages: false
  }))
}));

import { useChat } from '../../hooks/useChat';

describe('Chat Components Integration', () => {
  const defaultAppProps = {
    userContext: {
      userId: 123,
      userName: 'Test User',
      isLoggedIn: true,
      currentPage: '/product/123'
    },
    wooCommerceData: {
      cartItems: [],
      currentProduct: {
        id: 123,
        name: 'Test Product',
        price: '$99.99'
      },
      currency: 'USD'
    },
    config: {
      apiUrl: '/wp-json/woo-ai-assistant/v1',
      nonce: 'test-nonce',
      assistantName: 'AI Assistant',
      features: {
        productRecommendations: true,
        cartActions: true
      }
    }
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockWordPressGlobals();
    
    // Reset useChat mock
    useChat.mockReturnValue({
      messages: [],
      isTyping: false,
      isConnected: true,
      conversationId: 'test-conv-123',
      sendMessage: jest.fn(),
      clearMessages: jest.fn(),
      retry: jest.fn(),
      error: null,
      isLoading: false,
      canSend: true,
      messageCount: 0,
      hasUnreadMessages: false
    });
  });

  describe('Basic Integration', () => {
    test('App renders with chat components when opened', async () => {
      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Initially closed
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Chat window should be visible
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByLabelText('AI Assistant Chat')).toBeInTheDocument();
    });

    test('ChatWindow renders with proper components structure', async () => {
      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Verify all main components are present
      expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent(/AI Shopping Assistant/i);
      expect(screen.getByRole('log')).toBeInTheDocument(); // Messages area
      expect(screen.getByLabelText('Message input')).toBeInTheDocument();
      expect(screen.getByLabelText('Send message')).toBeInTheDocument();
    });
  });

  describe('Message Display Integration', () => {
    test('displays messages correctly when provided by useChat', async () => {
      const testMessages = [
        {
          id: 'msg-1',
          type: 'assistant',
          content: 'Hello! How can I help?',
          timestamp: new Date().toISOString()
        },
        {
          id: 'msg-2',
          type: 'user',
          content: 'I need help with products',
          timestamp: new Date().toISOString()
        }
      ];

      useChat.mockReturnValue({
        messages: testMessages,
        isTyping: false,
        isConnected: true,
        conversationId: 'test-conv-123',
        sendMessage: jest.fn(),
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: true,
        messageCount: testMessages.length,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Verify messages are displayed
      expect(screen.getByText('Hello! How can I help?')).toBeInTheDocument();
      expect(screen.getByText('I need help with products')).toBeInTheDocument();
    });

    test('shows empty state when no messages', async () => {
      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Should show empty state
      expect(screen.getByText("Hi! I'm your AI shopping assistant.")).toBeInTheDocument();
      expect(screen.getByText('Ask me about products, orders, or anything else!')).toBeInTheDocument();
    });
  });

  describe('Typing Indicator Integration', () => {
    test('shows typing indicator when isTyping is true', async () => {
      useChat.mockReturnValue({
        messages: [],
        isTyping: true,
        isConnected: true,
        conversationId: 'test-conv-123',
        sendMessage: jest.fn(),
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: true,
        messageCount: 0,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Should show typing indicator
      expect(screen.getByRole('status')).toBeInTheDocument();
      expect(screen.getByLabelText(/is typing/i)).toBeInTheDocument();
    });

    test('hides typing indicator when isTyping is false', async () => {
      useChat.mockReturnValue({
        messages: [],
        isTyping: false,
        isConnected: true,
        conversationId: 'test-conv-123',
        sendMessage: jest.fn(),
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: true,
        messageCount: 0,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Should not show typing indicator
      expect(screen.queryByLabelText(/is typing/i)).not.toBeInTheDocument();
    });
  });

  describe('Message Sending Integration', () => {
    test('sending message calls useChat sendMessage function', async () => {
      const mockSendMessage = jest.fn();
      
      useChat.mockReturnValue({
        messages: [],
        isTyping: false,
        isConnected: true,
        conversationId: 'test-conv-123',
        sendMessage: mockSendMessage,
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: true,
        messageCount: 0,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Type and send message
      const input = screen.getByLabelText('Message input');
      const sendButton = screen.getByLabelText('Send message');
      
      await user.type(input, 'Hello there!');
      await user.click(sendButton);
      
      expect(mockSendMessage).toHaveBeenCalledWith('Hello there!');
    });

    test('Enter key sends message through useChat', async () => {
      const mockSendMessage = jest.fn();
      
      useChat.mockReturnValue({
        messages: [],
        isTyping: false,
        isConnected: true,
        conversationId: 'test-conv-123',
        sendMessage: mockSendMessage,
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: true,
        messageCount: 0,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Type message and press Enter
      const input = screen.getByLabelText('Message input');
      await user.type(input, 'Test message');
      await user.keyboard('{Enter}');
      
      expect(mockSendMessage).toHaveBeenCalledWith('Test message');
    });

    test('cannot send message when not connected', async () => {
      const mockSendMessage = jest.fn();
      
      useChat.mockReturnValue({
        messages: [],
        isTyping: false,
        isConnected: false,
        conversationId: 'test-conv-123',
        sendMessage: mockSendMessage,
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: false,
        messageCount: 0,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Input and send button should be disabled
      const input = screen.getByLabelText('Message input');
      const sendButton = screen.getByLabelText('Send message');
      
      expect(input).toBeDisabled();
      expect(sendButton).toBeDisabled();
    });
  });

  describe('Error Handling Integration', () => {
    test('displays error message from useChat', async () => {
      const testError = {
        type: 'connection_failed',
        message: 'Failed to connect to chat service'
      };

      useChat.mockReturnValue({
        messages: [],
        isTyping: false,
        isConnected: false,
        conversationId: 'test-conv-123',
        sendMessage: jest.fn(),
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: testError,
        isLoading: false,
        canSend: false,
        messageCount: 0,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Should show error message
      expect(screen.getByText('Connection Error')).toBeInTheDocument();
      expect(screen.getByText(testError.message)).toBeInTheDocument();
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });

    test('shows connection status correctly', async () => {
      useChat.mockReturnValue({
        messages: [],
        isTyping: false,
        isConnected: false,
        conversationId: null,
        sendMessage: jest.fn(),
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: false,
        messageCount: 0,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Should show connecting status
      expect(screen.getByText('Connecting...')).toBeInTheDocument();
    });
  });

  describe('Clear Messages Integration', () => {
    test('clear button calls useChat clearMessages function', async () => {
      const mockClearMessages = jest.fn();
      
      useChat.mockReturnValue({
        messages: mockMessages,
        isTyping: false,
        isConnected: true,
        conversationId: 'test-conv-123',
        sendMessage: jest.fn(),
        clearMessages: mockClearMessages,
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: true,
        messageCount: mockMessages.length,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Click clear button
      const clearButton = screen.getByLabelText('Clear conversation');
      await user.click(clearButton);
      
      expect(mockClearMessages).toHaveBeenCalledTimes(1);
    });

    test('clear button only appears when there are messages', async () => {
      const { rerender } = renderWithContext(<App {...defaultAppProps} />);
      
      const user = userEvent.setup();
      
      // Open chat with no messages
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      expect(screen.queryByLabelText('Clear conversation')).not.toBeInTheDocument();
      
      // Update to have messages
      useChat.mockReturnValue({
        messages: mockMessages,
        isTyping: false,
        isConnected: true,
        conversationId: 'test-conv-123',
        sendMessage: jest.fn(),
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: true,
        messageCount: mockMessages.length,
        hasUnreadMessages: false
      });
      
      rerender(<App {...defaultAppProps} />);
      
      expect(screen.getByLabelText('Clear conversation')).toBeInTheDocument();
    });
  });

  describe('Responsive Integration', () => {
    test('components respond to mobile viewport', async () => {
      // Mock mobile viewport
      Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: 480,
      });

      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Should render without errors on mobile
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByLabelText('Message input')).toBeInTheDocument();
    });
  });

  describe('Accessibility Integration', () => {
    test('keyboard navigation works across components', async () => {
      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Tab through interactive elements
      await user.tab(); // Should focus minimize button
      expect(screen.getByLabelText('Minimize chat')).toHaveFocus();
      
      await user.tab(); // Should focus close button
      expect(screen.getByLabelText('Close chat')).toHaveFocus();
      
      await user.tab(); // Should focus input
      expect(screen.getByLabelText('Message input')).toHaveFocus();
    });

    test('ARIA roles and labels are properly set across components', async () => {
      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Verify ARIA attributes
      expect(screen.getByRole('dialog')).toHaveAttribute('aria-label', 'AI Assistant Chat');
      expect(screen.getByRole('log')).toHaveAttribute('aria-label', 'Chat messages');
      expect(screen.getByLabelText('Message input')).toBeInTheDocument();
    });
  });

  describe('Performance Integration', () => {
    test('components render efficiently with many messages', async () => {
      const manyMessages = Array.from({ length: 100 }, (_, i) => ({
        id: `msg-${i}`,
        type: i % 2 === 0 ? 'user' : 'assistant',
        content: `Message ${i}`,
        timestamp: new Date().toISOString()
      }));

      useChat.mockReturnValue({
        messages: manyMessages,
        isTyping: false,
        isConnected: true,
        conversationId: 'test-conv-123',
        sendMessage: jest.fn(),
        clearMessages: jest.fn(),
        retry: jest.fn(),
        error: null,
        isLoading: false,
        canSend: true,
        messageCount: manyMessages.length,
        hasUnreadMessages: false
      });

      const user = userEvent.setup();
      
      const startTime = performance.now();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      const endTime = performance.now();
      
      // Should render within reasonable time even with many messages
      expect(endTime - startTime).toBeLessThan(1000);
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  describe('Props Passing Integration', () => {
    test('App passes props correctly to ChatWindow', async () => {
      const user = userEvent.setup();
      renderWithContext(<App {...defaultAppProps} />);
      
      // Open chat
      const toggleButton = screen.getByLabelText(/Open AI Assistant Chat/i);
      await user.click(toggleButton);
      
      // Verify props are passed correctly (evidenced by proper display)
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByText('AI Shopping Assistant')).toBeInTheDocument();
      expect(screen.getByText('Connected')).toBeInTheDocument();
    });

    test('useChat hook receives correct configuration', () => {
      renderWithContext(<App {...defaultAppProps} />);
      
      // Verify useChat was called with correct parameters
      expect(useChat).toHaveBeenCalledWith({
        userContext: defaultAppProps.userContext,
        wooCommerceData: defaultAppProps.wooCommerceData,
        config: defaultAppProps.config
      });
    });
  });
});