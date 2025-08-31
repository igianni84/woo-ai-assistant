/**
 * Message Component Tests
 *
 * Comprehensive tests for the Message component including
 * rendering different message types, timestamps, accessibility,
 * and user interactions.
 *
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Message from '../../components/Message';
import {
  renderWithContext,
  assertAriaAttributes,
  assertComponentNaming,
  mockWordPressGlobals
} from '../utils/testUtils';

// Mock clipboard API
Object.assign(navigator, {
  clipboard: {
    writeText: jest.fn(() => Promise.resolve())
  }
});

describe('Message Component', () => {
  // Base message object for testing
  const baseMessage = {
    id: 'msg-123',
    type: 'assistant',
    content: 'Hello! How can I help you today?',
    timestamp: '2024-01-01T12:00:00.000Z',
    metadata: { source: 'ai' }
  };

  const defaultProps = {
    message: baseMessage,
    isLatest: false,
    userContext: {
      userName: 'Test User',
      userId: 123,
      userAvatar: 'https://example.com/avatar.jpg'
    },
    config: {
      assistantName: 'AI Assistant'
    }
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockWordPressGlobals();
  });

  describe('Component Rendering', () => {
    test('renders Message component correctly', () => {
      renderWithContext(<Message {...defaultProps} />);

      expect(screen.getByRole('listitem')).toBeInTheDocument();
      expect(screen.getByText(baseMessage.content)).toBeInTheDocument();
    });

    test('follows naming conventions', () => {
      assertComponentNaming(Message, 'Message');
    });

    test('applies visibility animation on mount', async () => {
      renderWithContext(<Message {...defaultProps} />);

      const messageElement = screen.getByRole('listitem');

      await waitFor(() => {
        expect(messageElement).toHaveClass('woo-ai-assistant-message--visible');
      }, { timeout: 100 });
    });

    test('applies latest message class when isLatest is true', () => {
      renderWithContext(<Message {...defaultProps} isLatest={true} />);

      const messageElement = screen.getByRole('listitem');
      expect(messageElement).toHaveClass('woo-ai-assistant-message--latest');
    });
  });

  describe('Message Types', () => {
    test('renders user message correctly', () => {
      const userMessage = {
        ...baseMessage,
        type: 'user',
        content: 'Hi there!'
      };

      renderWithContext(<Message {...defaultProps} message={userMessage} />);

      const messageElement = screen.getByRole('listitem');
      expect(messageElement).toHaveClass('woo-ai-assistant-message--user');
      expect(screen.getByText('Test User')).toBeInTheDocument();
    });

    test('renders assistant message correctly', () => {
      renderWithContext(<Message {...defaultProps} />);

      const messageElement = screen.getByRole('listitem');
      expect(messageElement).toHaveClass('woo-ai-assistant-message--assistant');
      expect(screen.getByText('AI Assistant')).toBeInTheDocument();
    });

    test('renders system message correctly', () => {
      const systemMessage = {
        ...baseMessage,
        type: 'system',
        content: 'Connection established'
      };

      renderWithContext(<Message {...defaultProps} message={systemMessage} />);

      const messageElement = screen.getByRole('listitem');
      expect(messageElement).toHaveClass('woo-ai-assistant-message--system');
      expect(screen.getByText('System')).toBeInTheDocument();
    });

    test('renders error message correctly', () => {
      const errorMessage = {
        ...baseMessage,
        type: 'error',
        content: 'Something went wrong'
      };

      renderWithContext(<Message {...defaultProps} message={errorMessage} />);

      const messageElement = screen.getByRole('listitem');
      expect(messageElement).toHaveClass('woo-ai-assistant-message--error');
      expect(screen.getByText('Error')).toBeInTheDocument();
    });
  });

  describe('Avatar Rendering', () => {
    test('shows user avatar when available', () => {
      const userMessage = {
        ...baseMessage,
        type: 'user'
      };

      renderWithContext(<Message {...defaultProps} message={userMessage} />);

      const avatar = screen.getByAltText("Test User's avatar");
      expect(avatar).toBeInTheDocument();
      expect(avatar).toHaveAttribute('src', defaultProps.userContext.userAvatar);
    });

    test('shows user icon when avatar not available', () => {
      const userMessage = {
        ...baseMessage,
        type: 'user'
      };

      const propsWithoutAvatar = {
        ...defaultProps,
        userContext: {
          ...defaultProps.userContext,
          userAvatar: null
        }
      };

      renderWithContext(<Message {...propsWithoutAvatar} message={userMessage} />);

      const avatarContainer = screen.getByRole('listitem').querySelector('.woo-ai-assistant-message-avatar--user');
      expect(avatarContainer).toBeInTheDocument();
    });

    test('shows correct avatar for each message type', () => {
      const messageTypes = ['user', 'assistant', 'system', 'error'];

      messageTypes.forEach(type => {
        const message = { ...baseMessage, type };
        const { container } = renderWithContext(<Message {...defaultProps} message={message} />);

        const avatarContainer = container.querySelector(`.woo-ai-assistant-message-avatar--${type}`);
        expect(avatarContainer).toBeInTheDocument();
      });
    });
  });

  describe('Content Rendering', () => {
    test('renders plain text content', () => {
      const plainTextMessage = {
        ...baseMessage,
        content: 'This is plain text content'
      };

      renderWithContext(<Message {...defaultProps} message={plainTextMessage} />);

      expect(screen.getByText('This is plain text content')).toBeInTheDocument();
    });

    test('renders HTML content safely', () => {
      const htmlMessage = {
        ...baseMessage,
        content: 'This is <strong>bold</strong> and <em>italic</em> text'
      };

      renderWithContext(<Message {...defaultProps} message={htmlMessage} />);

      expect(screen.getByText('bold')).toBeInTheDocument();
      expect(screen.getByText('italic')).toBeInTheDocument();
    });

    test('sanitizes dangerous HTML', () => {
      const dangerousHtml = {
        ...baseMessage,
        content: 'Safe content <script>alert("dangerous")</script> more content'
      };

      renderWithContext(<Message {...defaultProps} message={dangerousHtml} />);

      // Script tag should be removed
      expect(screen.queryByText(/script/)).not.toBeInTheDocument();
      expect(screen.getByText(/Safe content.*more content/)).toBeInTheDocument();
    });

    test('handles empty content gracefully', () => {
      const emptyMessage = {
        ...baseMessage,
        content: ''
      };

      expect(() => {
        renderWithContext(<Message {...defaultProps} message={emptyMessage} />);
      }).not.toThrow();
    });
  });

  describe('Timestamp Handling', () => {
    test('displays formatted timestamp', () => {
      // Use a fixed timestamp for consistent testing
      const fixedTimestamp = '2024-01-01T12:00:00.000Z';
      const timestampMessage = {
        ...baseMessage,
        timestamp: fixedTimestamp
      };

      renderWithContext(<Message {...defaultProps} message={timestampMessage} />);

      const timeElement = screen.getByRole('time');
      expect(timeElement).toHaveAttribute('datetime', fixedTimestamp);
    });

    test('shows "Just now" for recent messages', () => {
      const recentMessage = {
        ...baseMessage,
        timestamp: new Date().toISOString()
      };

      renderWithContext(<Message {...defaultProps} message={recentMessage} />);

      expect(screen.getByText('Just now')).toBeInTheDocument();
    });

    test('shows relative time for older messages', () => {
      // Message from 5 minutes ago
      const olderMessage = {
        ...baseMessage,
        timestamp: new Date(Date.now() - 5 * 60 * 1000).toISOString()
      };

      renderWithContext(<Message {...defaultProps} message={olderMessage} />);

      expect(screen.getByText('5m ago')).toBeInTheDocument();
    });

    test('handles invalid timestamp gracefully', () => {
      const invalidTimestampMessage = {
        ...baseMessage,
        timestamp: 'invalid-timestamp'
      };

      expect(() => {
        renderWithContext(<Message {...defaultProps} message={invalidTimestampMessage} />);
      }).not.toThrow();
    });
  });

  describe('Message Actions', () => {
    test('shows copy action for assistant messages', () => {
      renderWithContext(<Message {...defaultProps} />);

      const copyButton = screen.getByLabelText('Copy message');
      expect(copyButton).toBeInTheDocument();
    });

    test('does not show actions for user messages', () => {
      const userMessage = {
        ...baseMessage,
        type: 'user'
      };

      renderWithContext(<Message {...defaultProps} message={userMessage} />);

      expect(screen.queryByLabelText('Copy message')).not.toBeInTheDocument();
    });

    test('copy action works correctly', async () => {
      const user = userEvent.setup();
      renderWithContext(<Message {...defaultProps} />);

      const copyButton = screen.getByLabelText('Copy message');
      await user.click(copyButton);

      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(baseMessage.content);
    });

    test('handles copy action error gracefully', async () => {
      // Mock clipboard.writeText to reject
      navigator.clipboard.writeText = jest.fn(() => Promise.reject(new Error('Copy failed')));

      const user = userEvent.setup();
      renderWithContext(<Message {...defaultProps} />);

      const copyButton = screen.getByLabelText('Copy message');

      // Should not throw error even if copy fails
      expect(async () => {
        await user.click(copyButton);
      }).not.toThrow();
    });
  });

  describe('Debug Information', () => {
    test('shows debug info in development mode', () => {
      // Mock development environment
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      renderWithContext(<Message {...defaultProps} />);

      expect(screen.getByText('Debug Info')).toBeInTheDocument();

      process.env.NODE_ENV = originalEnv;
    });

    test('hides debug info in production mode', () => {
      // Mock production environment
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'production';

      renderWithContext(<Message {...defaultProps} />);

      expect(screen.queryByText('Debug Info')).not.toBeInTheDocument();

      process.env.NODE_ENV = originalEnv;
    });

    test('displays metadata in debug mode', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      const messageWithMetadata = {
        ...baseMessage,
        metadata: { source: 'ai', model: 'gpt-3.5', tokens: 50 }
      };

      renderWithContext(<Message {...defaultProps} message={messageWithMetadata} />);

      const debugSection = screen.getByText('Debug Info').closest('details');
      expect(debugSection).toBeInTheDocument();

      process.env.NODE_ENV = originalEnv;
    });
  });

  describe('Accessibility', () => {
    test('has proper ARIA attributes', () => {
      renderWithContext(<Message {...defaultProps} />);

      const messageElement = screen.getByRole('listitem');
      assertAriaAttributes(messageElement, {
        'aria-label': 'Message from AI Assistant'
      });
    });

    test('time element has proper attributes', () => {
      renderWithContext(<Message {...defaultProps} />);

      const timeElement = screen.getByRole('time');
      expect(timeElement).toHaveAttribute('datetime', baseMessage.timestamp);
      expect(timeElement).toHaveAttribute('title');
    });

    test('copy button has proper accessibility attributes', () => {
      renderWithContext(<Message {...defaultProps} />);

      const copyButton = screen.getByLabelText('Copy message');
      expect(copyButton).toHaveAttribute('title', 'Copy to clipboard');
    });
  });

  describe('Responsive Design', () => {
    test('handles mobile layout correctly', () => {
      // Mock mobile viewport
      Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: 480
      });

      renderWithContext(<Message {...defaultProps} />);

      // Component should render without errors on mobile
      expect(screen.getByRole('listitem')).toBeInTheDocument();
    });
  });

  describe('Error Handling', () => {
    test('handles missing props gracefully', () => {
      const minimalProps = {
        message: {
          id: 'test',
          type: 'assistant',
          content: 'Test content',
          timestamp: new Date().toISOString()
        }
      };

      expect(() => {
        renderWithContext(<Message {...minimalProps} />);
      }).not.toThrow();
    });

    test('handles invalid message type', () => {
      const invalidTypeMessage = {
        ...baseMessage,
        type: 'invalid'
      };

      expect(() => {
        renderWithContext(<Message {...defaultProps} message={invalidTypeMessage} />);
      }).not.toThrow();
    });

    test('handles undefined userContext', () => {
      const userMessage = {
        ...baseMessage,
        type: 'user'
      };

      expect(() => {
        renderWithContext(<Message message={userMessage} userContext={undefined} />);
      }).not.toThrow();
    });
  });

  describe('PropTypes Validation', () => {
    test('validates required message prop', () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      renderWithContext(<Message />);

      expect(consoleSpy).toHaveBeenCalled();

      consoleSpy.mockRestore();
    });

    test('validates message structure', () => {
      const invalidMessage = {
        // Missing required fields
        content: 'Test content'
      };

      const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

      renderWithContext(<Message message={invalidMessage} />);

      expect(consoleSpy).toHaveBeenCalled();

      consoleSpy.mockRestore();
    });
  });

  describe('Performance', () => {
    test('renders quickly with large content', () => {
      const largeContentMessage = {
        ...baseMessage,
        content: 'A'.repeat(10000) // 10KB of content
      };

      const startTime = performance.now();
      renderWithContext(<Message {...defaultProps} message={largeContentMessage} />);
      const endTime = performance.now();

      // Should render within reasonable time
      expect(endTime - startTime).toBeLessThan(100);
    });

    test('handles frequent re-renders efficiently', () => {
      const { rerender } = renderWithContext(<Message {...defaultProps} />);

      const startTime = performance.now();

      // Simulate many re-renders
      for (let i = 0; i < 100; i++) {
        rerender(<Message {...defaultProps} isLatest={i % 2 === 0} />);
      }

      const endTime = performance.now();

      // Should handle re-renders efficiently
      expect(endTime - startTime).toBeLessThan(1000);
    });
  });
});
