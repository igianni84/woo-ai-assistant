/**
 * Message Component Tests
 * 
 * Comprehensive test suite for the Message component
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import Message from './Message';

describe('Message Component', () => {
  const mockUserMessage = {
    id: 'msg_123',
    content: 'Hello, this is a test message',
    type: 'user',
    timestamp: '2023-08-26T10:30:00Z',
  };

  const mockBotMessage = {
    id: 'msg_456',
    content: 'Hi! How can I help you today?',
    type: 'bot',
    timestamp: '2023-08-26T10:31:00Z',
  };

  describe('Basic Rendering', () => {
    it('should render user message correctly', () => {
      render(<Message message={mockUserMessage} />);
      
      expect(screen.getByText(mockUserMessage.content)).toBeInTheDocument();
      expect(screen.getByLabelText('Your message')).toHaveClass('user-message');
    });

    it('should render bot message correctly', () => {
      render(<Message message={mockBotMessage} />);
      
      expect(screen.getByText(mockBotMessage.content)).toBeInTheDocument();
      expect(screen.getByLabelText('Assistant message')).toHaveClass('bot-message');
    });

    it('should display message content in message-text wrapper', () => {
      render(<Message message={mockUserMessage} />);
      
      const messageContent = screen.getByText(mockUserMessage.content);
      expect(messageContent.closest('.message-text')).toBeInTheDocument();
    });
  });

  describe('Avatar Display', () => {
    it('should show avatar for bot messages by default', () => {
      render(<Message message={mockBotMessage} />);
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).toBeInTheDocument();
      expect(avatar.querySelector('svg')).toBeInTheDocument();
    });

    it('should not show avatar for user messages', () => {
      render(<Message message={mockUserMessage} />);
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).not.toBeInTheDocument();
    });

    it('should hide avatar when showAvatar is false', () => {
      render(<Message message={mockBotMessage} showAvatar={false} />);
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).not.toBeInTheDocument();
    });

    it('should show avatar for bot message when showAvatar is true', () => {
      render(<Message message={mockBotMessage} showAvatar={true} />);
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).toBeInTheDocument();
    });
  });

  describe('Timestamp Display', () => {
    it('should show timestamp by default when provided', () => {
      render(<Message message={mockUserMessage} />);
      
      const timestamp = screen.getByRole('time');
      expect(timestamp).toBeInTheDocument();
      expect(timestamp).toHaveAttribute('dateTime', mockUserMessage.timestamp);
    });

    it('should hide timestamp when showTimestamp is false', () => {
      render(<Message message={mockUserMessage} showTimestamp={false} />);
      
      expect(screen.queryByRole('time')).not.toBeInTheDocument();
    });

    it('should not show timestamp when not provided', () => {
      const messageWithoutTimestamp = { ...mockUserMessage };
      delete messageWithoutTimestamp.timestamp;
      
      render(<Message message={messageWithoutTimestamp} />);
      
      expect(screen.queryByRole('time')).not.toBeInTheDocument();
    });

    it('should format timestamp correctly', () => {
      render(<Message message={mockUserMessage} />);
      
      const timestamp = screen.getByRole('time');
      // Check that it shows formatted time (will depend on locale)
      expect(timestamp.textContent).toMatch(/\d{1,2}:\d{2}/);
    });

    it('should handle invalid timestamp gracefully', () => {
      const messageWithInvalidTimestamp = {
        ...mockUserMessage,
        timestamp: 'invalid-timestamp',
      };
      
      // Mock console.warn to avoid console output during tests
      const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
      
      render(<Message message={messageWithInvalidTimestamp} />);
      
      expect(screen.queryByRole('time')).not.toBeInTheDocument();
      expect(consoleSpy).toHaveBeenCalledWith(
        'Invalid timestamp format:', 
        'invalid-timestamp'
      );
      
      consoleSpy.mockRestore();
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA labels for user messages', () => {
      render(<Message message={mockUserMessage} />);
      
      expect(screen.getByLabelText('Your message')).toBeInTheDocument();
    });

    it('should have proper ARIA labels for bot messages', () => {
      render(<Message message={mockBotMessage} />);
      
      expect(screen.getByLabelText('Assistant message')).toBeInTheDocument();
      expect(screen.getByRole('log')).toBeInTheDocument();
    });

    it('should have data-message-id attribute', () => {
      render(<Message message={mockUserMessage} />);
      
      const messageElement = screen.getByLabelText('Your message');
      expect(messageElement).toHaveAttribute('data-message-id', mockUserMessage.id);
    });

    it('should have proper title attribute on timestamp', () => {
      render(<Message message={mockUserMessage} />);
      
      const timestamp = screen.getByRole('time');
      expect(timestamp).toHaveAttribute('title');
      // Title should contain full date/time
      expect(timestamp.getAttribute('title')).toContain('2023');
    });
  });

  describe('CSS Classes', () => {
    it('should apply correct CSS classes for user message', () => {
      render(<Message message={mockUserMessage} />);
      
      const messageElement = screen.getByLabelText('Your message');
      expect(messageElement).toHaveClass('message', 'user-message');
    });

    it('should apply correct CSS classes for bot message', () => {
      render(<Message message={mockBotMessage} />);
      
      const messageElement = screen.getByLabelText('Assistant message');
      expect(messageElement).toHaveClass('message', 'bot-message');
    });
  });

  describe('PropTypes and Error Handling', () => {
    it('should handle numeric message IDs', () => {
      const messageWithNumericId = { ...mockUserMessage, id: 123 };
      
      render(<Message message={messageWithNumericId} />);
      
      const messageElement = screen.getByLabelText('Your message');
      expect(messageElement).toHaveAttribute('data-message-id', '123');
    });

    it('should handle messages without timestamp', () => {
      const messageWithoutTimestamp = {
        id: 'test',
        content: 'Test message',
        type: 'user',
      };
      
      render(<Message message={messageWithoutTimestamp} />);
      
      expect(screen.getByText('Test message')).toBeInTheDocument();
      expect(screen.queryByRole('time')).not.toBeInTheDocument();
    });
  });

  describe('Component Integration', () => {
    it('should render multiple messages correctly', () => {
      const { rerender } = render(<Message message={mockUserMessage} />);
      
      expect(screen.getByText(mockUserMessage.content)).toBeInTheDocument();
      
      rerender(<Message message={mockBotMessage} />);
      
      expect(screen.getByText(mockBotMessage.content)).toBeInTheDocument();
    });
  });
});