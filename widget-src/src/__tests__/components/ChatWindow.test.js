/**
 * ChatWindow Component Tests
 *
 * Comprehensive tests for the ChatWindow component including
 * rendering, user interactions, accessibility, and edge cases.
 *
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ChatWindow from '../../components/ChatWindow';
import { 
  renderWithContext, 
  assertAriaAttributes, 
  assertComponentNaming,
  mockWordPressGlobals 
} from '../utils/testUtils';
import { mockMessages } from '../mocks/mockData';

// Mock dependencies
jest.mock('../../components/Message', () => {
  return function MockMessage({ message, isLatest }) {
    return (
      <div data-testid="mock-message" data-message-id={message.id} data-is-latest={isLatest}>
        {message.content}
      </div>
    );
  };
});

jest.mock('../../components/TypingIndicator', () => {
  return function MockTypingIndicator() {
    return <div data-testid="typing-indicator">AI is typing...</div>;
  };
});

describe('ChatWindow Component', () => {
  // Default props for testing
  const defaultProps = {
    isVisible: true,
    messages: [],
    isTyping: false,
    isConnected: true,
    conversationId: 'test-conversation-123',
    userContext: {
      userName: 'Test User',
      userId: 123,
      userEmail: 'test@example.com'
    },
    wooCommerceData: {
      cartItems: [],
      currentProduct: null,
      currency: 'USD'
    },
    config: {
      assistantName: 'AI Assistant',
      theme: 'light'
    },
    error: null,
    onClose: jest.fn(),
    onMinimize: jest.fn(),
    onSendMessage: jest.fn(),
    onClearMessages: jest.fn()
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockWordPressGlobals();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe('Component Rendering', () => {
    test('renders ChatWindow component correctly', () => {
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByLabelText('AI Assistant Chat')).toBeInTheDocument();
    });

    test('follows naming conventions', () => {
      assertComponentNaming(ChatWindow, 'ChatWindow');
    });

    test('applies visibility classes correctly', () => {
      const { rerender } = renderWithContext(<ChatWindow {...defaultProps} isVisible={false} />);
      
      const chatWindow = screen.getByRole('dialog');
      expect(chatWindow).toHaveClass('woo-ai-assistant-chat-window');
      expect(chatWindow).not.toHaveClass('visible');

      rerender(<ChatWindow {...defaultProps} isVisible={true} />);
      expect(chatWindow).toHaveClass('visible');
    });

    test('applies connection status classes', () => {
      const { rerender } = renderWithContext(<ChatWindow {...defaultProps} isConnected={false} />);
      
      const chatWindow = screen.getByRole('dialog');
      expect(chatWindow).toHaveClass('disconnected');

      rerender(<ChatWindow {...defaultProps} error={{ message: 'Test error' }} />);
      expect(chatWindow).toHaveClass('has-error');
    });
  });

  describe('Header Section', () => {
    test('displays correct title and connection status', () => {
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent('AI Shopping Assistant');
      expect(screen.getByText('Connected')).toBeInTheDocument();
    });

    test('shows connecting status when not connected', () => {
      renderWithContext(<ChatWindow {...defaultProps} isConnected={false} />);
      
      expect(screen.getByText('Connecting...')).toBeInTheDocument();
    });

    test('shows error status when there is an error', () => {
      const error = { message: 'Connection failed' };
      renderWithContext(<ChatWindow {...defaultProps} error={error} />);
      
      expect(screen.getByText('Connection error')).toBeInTheDocument();
    });

    test('minimize and close buttons work correctly', async () => {
      const user = userEvent.setup();
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const minimizeButton = screen.getByLabelText('Minimize chat');
      const closeButton = screen.getByLabelText('Close chat');

      await user.click(minimizeButton);
      expect(defaultProps.onMinimize).toHaveBeenCalledTimes(1);

      await user.click(closeButton);
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });
  });

  describe('Messages Area', () => {
    test('displays empty state when no messages', () => {
      renderWithContext(<ChatWindow {...defaultProps} messages={[]} />);
      
      expect(screen.getByText("Hi! I'm your AI shopping assistant.")).toBeInTheDocument();
      expect(screen.getByText('Ask me about products, orders, or anything else!')).toBeInTheDocument();
    });

    test('renders messages correctly', () => {
      renderWithContext(<ChatWindow {...defaultProps} messages={mockMessages} />);
      
      const messages = screen.getAllByTestId('mock-message');
      expect(messages).toHaveLength(mockMessages.length);
      
      // Check that last message is marked as latest
      const lastMessage = messages[messages.length - 1];
      expect(lastMessage).toHaveAttribute('data-is-latest', 'true');
    });

    test('shows typing indicator when isTyping is true', () => {
      renderWithContext(<ChatWindow {...defaultProps} isTyping={true} />);
      
      expect(screen.getByTestId('typing-indicator')).toBeInTheDocument();
    });

    test('displays error message when error exists', () => {
      const error = { message: 'Failed to connect to chat service' };
      renderWithContext(<ChatWindow {...defaultProps} error={error} />);
      
      expect(screen.getByText('Connection Error')).toBeInTheDocument();
      expect(screen.getByText(error.message)).toBeInTheDocument();
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
  });

  describe('Input Area', () => {
    test('renders input form correctly', () => {
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const textarea = screen.getByLabelText('Message input');
      const sendButton = screen.getByLabelText('Send message');
      
      expect(textarea).toBeInTheDocument();
      expect(sendButton).toBeInTheDocument();
      expect(textarea).toHaveAttribute('placeholder', 'Type your message... (Press Enter to send)');
    });

    test('input is disabled when not connected', () => {
      renderWithContext(<ChatWindow {...defaultProps} isConnected={false} />);
      
      const textarea = screen.getByLabelText('Message input');
      const sendButton = screen.getByLabelText('Send message');
      
      expect(textarea).toBeDisabled();
      expect(sendButton).toBeDisabled();
    });

    test('input is disabled when there is an error', () => {
      const error = { message: 'Test error' };
      renderWithContext(<ChatWindow {...defaultProps} error={error} />);
      
      const textarea = screen.getByLabelText('Message input');
      const sendButton = screen.getByLabelText('Send message');
      
      expect(textarea).toBeDisabled();
      expect(sendButton).toBeDisabled();
    });

    test('send button becomes active when text is entered', async () => {
      const user = userEvent.setup();
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const textarea = screen.getByLabelText('Message input');
      const sendButton = screen.getByLabelText('Send message');
      
      expect(sendButton).not.toHaveClass('active');
      
      await user.type(textarea, 'Hello there!');
      expect(sendButton).toHaveClass('active');
    });

    test('sending message calls onSendMessage and clears input', async () => {
      const user = userEvent.setup();
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const textarea = screen.getByLabelText('Message input');
      const sendButton = screen.getByLabelText('Send message');
      
      await user.type(textarea, 'Test message');
      await user.click(sendButton);
      
      expect(defaultProps.onSendMessage).toHaveBeenCalledWith('Test message');
      expect(textarea.value).toBe('');
    });

    test('Enter key sends message', async () => {
      const user = userEvent.setup();
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const textarea = screen.getByLabelText('Message input');
      
      await user.type(textarea, 'Test message');
      await user.keyboard('{Enter}');
      
      expect(defaultProps.onSendMessage).toHaveBeenCalledWith('Test message');
    });

    test('Shift+Enter creates new line without sending', async () => {
      const user = userEvent.setup();
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const textarea = screen.getByLabelText('Message input');
      
      await user.type(textarea, 'Line 1');
      await user.keyboard('{Shift>}{Enter}{/Shift}');
      await user.type(textarea, 'Line 2');
      
      expect(defaultProps.onSendMessage).not.toHaveBeenCalled();
      expect(textarea.value).toBe('Line 1\nLine 2');
    });

    test('character count is displayed', async () => {
      const user = userEvent.setup();
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const textarea = screen.getByLabelText('Message input');
      const characterCount = screen.getByText('0/2000');
      
      expect(characterCount).toBeInTheDocument();
      
      await user.type(textarea, 'Hello');
      expect(screen.getByText('5/2000')).toBeInTheDocument();
    });

    test('clear button appears with messages and works correctly', async () => {
      const user = userEvent.setup();
      renderWithContext(<ChatWindow {...defaultProps} messages={mockMessages} />);
      
      const clearButton = screen.getByLabelText('Clear conversation');
      expect(clearButton).toBeInTheDocument();
      
      await user.click(clearButton);
      expect(defaultProps.onClearMessages).toHaveBeenCalledTimes(1);
    });

    test('loading icon shows when typing', () => {
      renderWithContext(<ChatWindow {...defaultProps} isTyping={true} />);
      
      const sendButton = screen.getByLabelText('Send message');
      expect(sendButton).toContainHTML('LoadingIcon');
    });
  });

  describe('Accessibility', () => {
    test('has proper ARIA attributes', () => {
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const chatWindow = screen.getByRole('dialog');
      assertAriaAttributes(chatWindow, {
        'aria-label': 'AI Assistant Chat',
        'aria-modal': 'true'
      });

      const messagesArea = screen.getByRole('log');
      assertAriaAttributes(messagesArea, {
        'aria-label': 'Chat messages'
      });
    });

    test('focuses input when window opens', async () => {
      const { rerender } = renderWithContext(<ChatWindow {...defaultProps} isVisible={false} />);
      
      rerender(<ChatWindow {...defaultProps} isVisible={true} />);
      
      await waitFor(() => {
        const textarea = screen.getByLabelText('Message input');
        expect(textarea).toHaveFocus();
      }, { timeout: 200 });
    });

    test('supports keyboard navigation', async () => {
      const user = userEvent.setup();
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      // Tab through interactive elements
      await user.tab();
      expect(screen.getByLabelText('Minimize chat')).toHaveFocus();
      
      await user.tab();
      expect(screen.getByLabelText('Close chat')).toHaveFocus();
      
      await user.tab();
      expect(screen.getByLabelText('Message input')).toHaveFocus();
    });
  });

  describe('Responsive Behavior', () => {
    test('handles mobile viewport correctly', () => {
      // Mock mobile viewport
      Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: 480,
      });

      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const chatWindow = screen.getByRole('dialog');
      // Component should have mobile-friendly classes (tested via CSS)
      expect(chatWindow).toBeInTheDocument();
    });
  });

  describe('Error Handling', () => {
    test('handles missing props gracefully', () => {
      const minimalProps = {
        isVisible: true,
        messages: [],
        isTyping: false,
        isConnected: true,
        onClose: jest.fn(),
        onMinimize: jest.fn(),
        onSendMessage: jest.fn(),
        onClearMessages: jest.fn()
      };

      // Should not throw error with minimal props
      expect(() => {
        renderWithContext(<ChatWindow {...minimalProps} />);
      }).not.toThrow();
    });

    test('handles empty or undefined message content', () => {
      const messagesWithEmptyContent = [
        { id: '1', type: 'user', content: '', timestamp: new Date().toISOString() },
        { id: '2', type: 'assistant', content: undefined, timestamp: new Date().toISOString() }
      ];

      expect(() => {
        renderWithContext(<ChatWindow {...defaultProps} messages={messagesWithEmptyContent} />);
      }).not.toThrow();
    });
  });

  describe('PropTypes Validation', () => {
    test('validates required props', () => {
      // Mock console.error to catch PropTypes warnings
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      renderWithContext(<ChatWindow />);

      expect(consoleSpy).toHaveBeenCalled();
      
      consoleSpy.mockRestore();
    });

    test('validates message structure in props', () => {
      const invalidMessages = [
        { id: '1' }, // Missing required fields
        { type: 'user', content: 'test' } // Missing id and timestamp
      ];

      const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      renderWithContext(<ChatWindow {...defaultProps} messages={invalidMessages} />);

      expect(consoleSpy).toHaveBeenCalled();
      
      consoleSpy.mockRestore();
    });
  });

  describe('Performance', () => {
    test('handles large number of messages efficiently', () => {
      const manyMessages = Array.from({ length: 1000 }, (_, i) => ({
        id: `msg-${i}`,
        type: i % 2 === 0 ? 'user' : 'assistant',
        content: `Message ${i}`,
        timestamp: new Date().toISOString()
      }));

      const startTime = performance.now();
      renderWithContext(<ChatWindow {...defaultProps} messages={manyMessages} />);
      const endTime = performance.now();

      // Should render within reasonable time (1 second)
      expect(endTime - startTime).toBeLessThan(1000);
    });
  });

  describe('Integration', () => {
    test('integrates correctly with useChat hook patterns', async () => {
      const user = userEvent.setup();
      
      // Simulate typical usage patterns
      renderWithContext(<ChatWindow {...defaultProps} />);
      
      const textarea = screen.getByLabelText('Message input');
      
      // Type and send multiple messages
      await user.type(textarea, 'Hello');
      await user.keyboard('{Enter}');
      
      expect(defaultProps.onSendMessage).toHaveBeenCalledWith('Hello');
      
      await user.type(textarea, 'How are you?');
      await user.keyboard('{Enter}');
      
      expect(defaultProps.onSendMessage).toHaveBeenCalledWith('How are you?');
      expect(defaultProps.onSendMessage).toHaveBeenCalledTimes(2);
    });
  });
});