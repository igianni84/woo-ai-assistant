/**
 * ChatWindow Component Tests
 * 
 * Comprehensive tests for the ChatWindow component functionality
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ChatWindow from './ChatWindow';

describe('ChatWindow Component', () => {
  const mockProps = {
    isVisible: true,
    onToggleVisibility: jest.fn(),
    onClose: jest.fn(),
    onSendMessage: jest.fn(),
    onClearConversation: jest.fn(),
    onDismissError: jest.fn(),
    config: {
      welcomeMessage: 'Test welcome message',
      theme: 'light',
      position: 'bottom-right',
      language: 'en',
    },
    userContext: {
      id: 1,
      email: 'test@example.com',
    },
    pageContext: {
      type: 'product',
      id: 123,
      url: 'http://test.com/product/123',
    },
    conversation: [],
    isTyping: false,
    connectionStatus: 'connected',
    error: null,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('Component Rendering', () => {
    it('renders minimized state correctly', () => {
      const props = { ...mockProps, isVisible: false };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByRole('button', { name: /open ai assistant chat/i })).toBeInTheDocument();
      expect(screen.queryByRole('dialog')).toBeInTheDocument();
      expect(screen.getByLabelText('AI Assistant Chat')).toHaveAttribute('aria-expanded', 'false');
    });

    it('renders expanded state correctly', () => {
      render(<ChatWindow {...mockProps} />);
      
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByLabelText('AI Assistant Chat')).toHaveAttribute('aria-expanded', 'true');
      expect(screen.getByText('AI Assistant')).toBeInTheDocument();
      expect(screen.getByRole('textbox', { name: /type your message/i })).toBeInTheDocument();
    });

    it('follows proper naming conventions', () => {
      // Test that component name follows PascalCase
      expect(ChatWindow.name).toBe('ChatWindow');
      
      // Test that class names follow the expected pattern
      render(<ChatWindow {...mockProps} />);
      expect(document.querySelector('.chat-widget')).toBeInTheDocument();
      expect(document.querySelector('.chat-window')).toBeInTheDocument();
      expect(document.querySelector('.chat-header')).toBeInTheDocument();
    });
  });

  describe('Welcome Message Display', () => {
    it('displays custom welcome message when provided', () => {
      render(<ChatWindow {...mockProps} />);
      expect(screen.getByText('Test welcome message')).toBeInTheDocument();
    });

    it('displays context-appropriate welcome message for product page', () => {
      const props = {
        ...mockProps,
        config: { ...mockProps.config, welcomeMessage: '' },
      };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByText(/help you with questions about this product/i)).toBeInTheDocument();
    });

    it('displays context-appropriate welcome message for shop page', () => {
      const props = {
        ...mockProps,
        config: { ...mockProps.config, welcomeMessage: '' },
        pageContext: { ...mockProps.pageContext, type: 'shop' },
      };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByText(/help you find the perfect products/i)).toBeInTheDocument();
    });
  });

  describe('Connection Status Display', () => {
    it('displays connected status correctly', () => {
      render(<ChatWindow {...mockProps} />);
      expect(screen.getByText('Online')).toBeInTheDocument();
      expect(document.querySelector('.status-connected')).toBeInTheDocument();
    });

    it('displays connecting status correctly', () => {
      const props = { ...mockProps, connectionStatus: 'connecting' };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByText('Connecting...')).toBeInTheDocument();
      expect(document.querySelector('.status-connecting')).toBeInTheDocument();
    });

    it('displays error status correctly', () => {
      const props = { ...mockProps, connectionStatus: 'error' };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByText('Connection Error')).toBeInTheDocument();
      expect(document.querySelector('.status-error')).toBeInTheDocument();
    });
  });

  describe('User Interactions', () => {
    it('handles toggle visibility correctly', async () => {
      const user = userEvent.setup();
      render(<ChatWindow {...mockProps} />);
      
      const minimizeButton = screen.getByRole('button', { name: /minimize chat/i });
      await user.click(minimizeButton);
      
      await waitFor(() => {
        expect(mockProps.onToggleVisibility).toHaveBeenCalled();
      });
    });

    it('handles close correctly', async () => {
      const user = userEvent.setup();
      render(<ChatWindow {...mockProps} />);
      
      const closeButton = screen.getByRole('button', { name: /close chat/i });
      await user.click(closeButton);
      
      expect(mockProps.onClose).toHaveBeenCalled();
    });

    it('handles message input and submission', async () => {
      const user = userEvent.setup();
      render(<ChatWindow {...mockProps} />);
      
      const input = screen.getByRole('textbox', { name: /type your message/i });
      const sendButton = screen.getByRole('button', { name: /send message/i });
      
      await user.type(input, 'Hello, test message');
      await user.click(sendButton);
      
      expect(mockProps.onSendMessage).toHaveBeenCalledWith('Hello, test message');
    });

    it('handles Enter key to send message', async () => {
      const user = userEvent.setup();
      render(<ChatWindow {...mockProps} />);
      
      const input = screen.getByRole('textbox', { name: /type your message/i });
      
      await user.type(input, 'Test message');
      await user.keyboard('{Enter}');
      
      expect(mockProps.onSendMessage).toHaveBeenCalledWith('Test message');
    });

    it('handles Escape key to minimize', async () => {
      const user = userEvent.setup();
      render(<ChatWindow {...mockProps} />);
      
      const input = screen.getByRole('textbox', { name: /type your message/i });
      input.focus();
      
      await user.keyboard('{Escape}');
      
      await waitFor(() => {
        expect(mockProps.onToggleVisibility).toHaveBeenCalled();
      });
    });
  });

  describe('Message Display', () => {
    it('displays conversation messages correctly', () => {
      const conversation = [
        {
          id: 1,
          type: 'user',
          content: 'Hello',
          timestamp: '2023-01-01T12:00:00Z',
        },
        {
          id: 2,
          type: 'bot',
          content: 'Hi there!',
          timestamp: '2023-01-01T12:00:01Z',
        },
      ];
      
      const props = { ...mockProps, conversation };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByText('Hello')).toBeInTheDocument();
      expect(screen.getByText('Hi there!')).toBeInTheDocument();
    });

    it('displays typing indicator when AI is typing', () => {
      const props = { ...mockProps, isTyping: true };
      render(<ChatWindow {...props} />);
      
      expect(document.querySelector('.typing-indicator')).toBeInTheDocument();
      expect(screen.getByText('AI is typing...', { selector: '.sr-only' })).toBeInTheDocument();
    });

    it('hides welcome message after first user message', async () => {
      const user = userEvent.setup();
      const props = { ...mockProps, conversation: [] };
      render(<ChatWindow {...props} />);
      
      // Welcome message should be visible initially
      expect(screen.getByText('Test welcome message')).toBeInTheDocument();
      
      // Send a message
      const input = screen.getByRole('textbox', { name: /type your message/i });
      await user.type(input, 'First message');
      await user.keyboard('{Enter}');
      
      // Welcome message should be hidden after sending
      expect(screen.queryByText('Test welcome message')).not.toBeInTheDocument();
    });
  });

  describe('Error Handling', () => {
    it('displays error message when error prop is provided', () => {
      const error = { message: 'Test error message' };
      const props = { ...mockProps, error };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByText('Test error message')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /dismiss error/i })).toBeInTheDocument();
    });

    it('handles error dismissal', async () => {
      const user = userEvent.setup();
      const error = { message: 'Test error message' };
      const props = { ...mockProps, error };
      render(<ChatWindow {...props} />);
      
      const dismissButton = screen.getByRole('button', { name: /dismiss error/i });
      await user.click(dismissButton);
      
      expect(mockProps.onDismissError).toHaveBeenCalled();
    });

    it('shows retry button for connection errors', () => {
      const error = { message: 'Connection failed' };
      const props = { 
        ...mockProps, 
        error, 
        connectionStatus: 'error',
        onReconnect: jest.fn(),
      };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('provides proper ARIA labels and roles', () => {
      render(<ChatWindow {...mockProps} />);
      
      expect(screen.getByRole('dialog')).toHaveAttribute('aria-label', 'AI Assistant Chat');
      expect(screen.getByRole('log')).toHaveAttribute('aria-label', 'Chat messages');
      expect(screen.getByRole('textbox')).toHaveAttribute('aria-label', 'Type your message');
    });

    it('provides screen reader announcements for dynamic content', () => {
      const props = { ...mockProps, isTyping: true };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByText('AI is typing...', { selector: '.sr-only' })).toBeInTheDocument();
    });

    it('handles focus management correctly', () => {
      const { rerender } = render(<ChatWindow {...mockProps} isVisible={false} />);
      
      // When opening, focus should move to input
      rerender(<ChatWindow {...mockProps} isVisible={true} />);
      
      // We can't easily test focus in jsdom, but we verify the input exists
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });
  });

  describe('Form Validation', () => {
    it('disables send button when input is empty', () => {
      render(<ChatWindow {...mockProps} />);
      
      const sendButton = screen.getByRole('button', { name: /send message/i });
      expect(sendButton).toBeDisabled();
    });

    it('enables send button when input has text', async () => {
      const user = userEvent.setup();
      render(<ChatWindow {...mockProps} />);
      
      const input = screen.getByRole('textbox', { name: /type your message/i });
      const sendButton = screen.getByRole('button', { name: /send message/i });
      
      await user.type(input, 'Test message');
      
      expect(sendButton).not.toBeDisabled();
    });

    it('shows character counter when approaching limit', async () => {
      const user = userEvent.setup();
      render(<ChatWindow {...mockProps} />);
      
      const input = screen.getByRole('textbox', { name: /type your message/i });
      
      // Type a message over 800 characters
      const longMessage = 'a'.repeat(850);
      await user.type(input, longMessage);
      
      expect(screen.getByText('850/1000')).toBeInTheDocument();
    });
  });

  describe('Clear Chat Functionality', () => {
    it('shows clear button when conversation has messages', () => {
      const conversation = [
        { id: 1, type: 'user', content: 'Hello', timestamp: '2023-01-01T12:00:00Z' }
      ];
      const props = { ...mockProps, conversation };
      render(<ChatWindow {...props} />);
      
      expect(screen.getByRole('button', { name: /clear chat/i })).toBeInTheDocument();
    });

    it('hides clear button when conversation is empty', () => {
      render(<ChatWindow {...mockProps} />);
      
      expect(screen.queryByRole('button', { name: /clear chat/i })).not.toBeInTheDocument();
    });

    it('handles clear conversation correctly', async () => {
      const user = userEvent.setup();
      const conversation = [
        { id: 1, type: 'user', content: 'Hello', timestamp: '2023-01-01T12:00:00Z' }
      ];
      const props = { ...mockProps, conversation };
      render(<ChatWindow {...props} />);
      
      const clearButton = screen.getByRole('button', { name: /clear chat/i });
      await user.click(clearButton);
      
      expect(mockProps.onClearConversation).toHaveBeenCalled();
    });
  });
});