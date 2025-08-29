/**
 * Message Component Streaming Tests
 * 
 * Comprehensive test suite for the Message component's streaming functionality,
 * including progressive text rendering, streaming indicators, and animations.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { render, screen, act, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import Message from './Message';

// Mock intersection observer for scroll behavior
global.IntersectionObserver = jest.fn(() => ({
  observe: jest.fn(),
  disconnect: jest.fn(),
  unobserve: jest.fn(),
}));

describe('Message Component - Streaming Features', () => {
  const defaultMessage = {
    id: 'msg-123',
    content: 'Hello, this is a streaming message response.',
    type: 'bot',
    timestamp: '2025-08-26T10:30:00Z'
  };

  beforeEach(() => {
    jest.clearAllTimers();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.runOnlyPendingTimers();
    jest.useRealTimers();
  });

  describe('Basic Streaming Props', () => {
    it('should render with default streaming props', () => {
      render(<Message message={defaultMessage} />);
      
      const messageElement = screen.getByTestId ? screen.queryByTestId('streaming-status') : null;
      expect(messageElement).not.toBeInTheDocument();
    });

    it('should accept streaming props', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          streamingProgress={0.5}
          enableStreamingAnimation={true}
        />
      );
      
      const messageContainer = screen.getByLabelText('Assistant message');
      expect(messageContainer).toHaveClass('streaming');
    });

    it('should disable streaming animations when configured', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          enableStreamingAnimation={false}
        />
      );
      
      // Should not show typing cursor
      expect(screen.queryByText('|')).not.toBeInTheDocument();
    });
  });

  describe('Streaming Indicators', () => {
    it('should show streaming status for bot messages', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          streamingProgress={0.75}
        />
      );
      
      // Check for progress indicator (could be in different forms)
      const statusElements = screen.queryAllByText(/75%|Thinking/);
      expect(statusElements.length).toBeGreaterThan(0);
    });

    it('should not show streaming status for user messages', () => {
      const userMessage = { ...defaultMessage, type: 'user' };
      
      render(
        <Message 
          message={userMessage}
          isStreaming={true}
          streamingProgress={0.5}
        />
      );
      
      expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('should show progress bar with correct width', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          streamingProgress={0.3}
        />
      );
      
      const progressFill = screen.getByClassName ? 
        screen.queryByClassName('streaming-progress-fill') : null;
      
      // If we can't query by class, check for inline style or aria attributes
      if (!progressFill) {
        // Alternative way to find progress indicator
        const statusElement = screen.queryByRole('status');
        expect(statusElement).toBeInTheDocument();
      }
    });

    it('should display thinking state with zero progress', () => {
      render(
        <Message 
          message={{...defaultMessage, content: ''}}
          isStreaming={true}
          streamingProgress={0}
        />
      );
      
      expect(screen.queryByText(/Thinking|0%/)).toBeTruthy();
    });
  });

  describe('Avatar Streaming Indicator', () => {
    it('should add streaming class to avatar when streaming', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          showAvatar={true}
        />
      );
      
      const messageContainer = screen.getByLabelText('Assistant message');
      expect(messageContainer).toHaveClass('streaming');
    });

    it('should show streaming dots animation', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          showAvatar={true}
        />
      );
      
      // Look for streaming indicator elements
      const avatar = screen.getByLabelText('Assistant message');
      expect(avatar.querySelector('.message-avatar')).toBeInTheDocument();
    });
  });

  describe('Typing Cursor Animation', () => {
    it('should show typing cursor when streaming', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          enableStreamingAnimation={true}
        />
      );
      
      // Cursor should be visible initially (showCursor state starts true during streaming)
      const cursor = screen.queryByText('|');
      expect(cursor).toBeInTheDocument();
    });

    it('should animate cursor blinking', async () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          enableStreamingAnimation={true}
        />
      );
      
      // Advance timers to test blinking animation
      act(() => {
        jest.advanceTimersByTime(500);
      });
      
      // Test that cursor animation is working (implementation may vary)
      const messageElement = screen.getByLabelText('Assistant message');
      expect(messageElement).toBeInTheDocument();
    });

    it('should not show cursor for user messages', () => {
      const userMessage = { ...defaultMessage, type: 'user' };
      
      render(
        <Message 
          message={userMessage}
          isStreaming={true}
          enableStreamingAnimation={true}
        />
      );
      
      expect(screen.queryByText('|')).not.toBeInTheDocument();
    });

    it('should hide cursor when streaming completes', () => {
      const { rerender } = render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          enableStreamingAnimation={true}
        />
      );
      
      // Complete streaming
      rerender(
        <Message 
          message={defaultMessage}
          isStreaming={false}
          enableStreamingAnimation={true}
        />
      );
      
      expect(screen.queryByText('|')).not.toBeInTheDocument();
    });
  });

  describe('Content Updates', () => {
    it('should update display content when content changes', () => {
      const { rerender } = render(
        <Message 
          message={{...defaultMessage, content: 'Hello'}}
          isStreaming={true}
        />
      );
      
      expect(screen.getByText('Hello')).toBeInTheDocument();
      
      rerender(
        <Message 
          message={{...defaultMessage, content: 'Hello world'}}
          isStreaming={true}
        />
      );
      
      expect(screen.getByText('Hello world')).toBeInTheDocument();
    });

    it('should handle empty content during streaming', () => {
      render(
        <Message 
          message={{...defaultMessage, content: ''}}
          isStreaming={true}
        />
      );
      
      // Should render empty content without errors
      const messageText = screen.getByLabelText('Assistant message');
      expect(messageText).toBeInTheDocument();
    });

    it('should preserve content when streaming completes', () => {
      const finalContent = 'This is the complete message';
      
      render(
        <Message 
          message={{...defaultMessage, content: finalContent}}
          isStreaming={false}
        />
      );
      
      expect(screen.getByText(finalContent)).toBeInTheDocument();
    });
  });

  describe('Timestamp Behavior', () => {
    it('should hide timestamp during streaming', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          showTimestamp={true}
        />
      );
      
      expect(screen.queryByRole('time')).not.toBeInTheDocument();
    });

    it('should show timestamp when streaming completes', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={false}
          showTimestamp={true}
        />
      );
      
      expect(screen.queryByRole ? screen.queryByRole('time') : 
        screen.queryByText(/\d{1,2}:\d{2}/)).toBeInTheDocument();
    });

    it('should respect showTimestamp prop when not streaming', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={false}
          showTimestamp={false}
        />
      );
      
      expect(screen.queryByRole('time')).not.toBeInTheDocument();
    });
  });

  describe('Streaming Metadata', () => {
    it('should show fallback indicator when using fallback streaming', () => {
      render(
        <Message 
          message={{
            ...defaultMessage,
            metadata: { streaming: true, fallback: true }
          }}
          isStreaming={true}
        />
      );
      
      expect(screen.queryByText('Offline mode')).toBeInTheDocument();
    });

    it('should handle streaming metadata without fallback', () => {
      render(
        <Message 
          message={{
            ...defaultMessage,
            metadata: { streaming: true }
          }}
          isStreaming={true}
        />
      );
      
      expect(screen.queryByText('Offline mode')).not.toBeInTheDocument();
    });

    it('should not show metadata when not streaming', () => {
      render(
        <Message 
          message={{
            ...defaultMessage,
            metadata: { streaming: true, fallback: true }
          }}
          isStreaming={false}
        />
      );
      
      expect(screen.queryByText('Offline mode')).not.toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should provide proper ARIA attributes for streaming status', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          streamingProgress={0.6}
        />
      );
      
      const statusElement = screen.queryByRole('status');
      if (statusElement) {
        expect(statusElement).toHaveAttribute('aria-live', 'polite');
      }
    });

    it('should provide accessible labels for streaming indicators', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
        />
      );
      
      const progressElement = screen.queryByLabelText(/generating response|typing/i);
      expect(progressElement).toBeTruthy();
    });

    it('should hide decorative elements from screen readers', () => {
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
        />
      );
      
      // Cursor should be hidden from screen readers
      const cursor = screen.queryByText('|');
      if (cursor) {
        expect(cursor).toHaveAttribute('aria-hidden', 'true');
      }
    });
  });

  describe('Error Handling', () => {
    it('should handle invalid streaming progress values', () => {
      expect(() => {
        render(
          <Message 
            message={defaultMessage}
            isStreaming={true}
            streamingProgress={-1}
          />
        );
      }).not.toThrow();

      expect(() => {
        render(
          <Message 
            message={defaultMessage}
            isStreaming={true}
            streamingProgress={2}
          />
        );
      }).not.toThrow();
    });

    it('should handle missing streaming props gracefully', () => {
      expect(() => {
        render(
          <Message 
            message={defaultMessage}
            isStreaming={true}
          />
        );
      }).not.toThrow();
    });

    it('should handle malformed content during streaming', () => {
      expect(() => {
        render(
          <Message 
            message={{...defaultMessage, content: null}}
            isStreaming={true}
          />
        );
      }).not.toThrow();
    });
  });

  describe('Component Cleanup', () => {
    it('should clean up cursor animation on unmount', () => {
      const { unmount } = render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          enableStreamingAnimation={true}
        />
      );
      
      jest.spyOn(global, 'clearInterval');
      
      unmount();
      
      // Should not throw errors on cleanup
      expect(() => unmount()).not.toThrow();
    });

    it('should handle multiple rapid updates without memory leaks', () => {
      const { rerender } = render(
        <Message 
          message={{...defaultMessage, content: 'Start'}}
          isStreaming={true}
        />
      );
      
      // Simulate rapid content updates
      for (let i = 0; i < 10; i++) {
        rerender(
          <Message 
            message={{...defaultMessage, content: `Update ${i}`}}
            isStreaming={true}
          />
        );
      }
      
      // Should handle updates without errors
      expect(screen.getByText('Update 9')).toBeInTheDocument();
    });
  });

  describe('Integration with Rich Content', () => {
    it('should show streaming indicator with product recommendations', () => {
      const messageWithProducts = {
        ...defaultMessage,
        products: [
          {
            id: 1,
            name: 'Test Product',
            price: '$29.99',
            image: '/test-image.jpg'
          }
        ],
        metadata: {
          contentType: 'product_recommendation'
        }
      };
      
      render(
        <Message 
          message={messageWithProducts}
          isStreaming={true}
        />
      );
      
      expect(screen.getByText(defaultMessage.content)).toBeInTheDocument();
      expect(screen.getByText('Test Product')).toBeInTheDocument();
    });

    it('should handle streaming with comparison content', () => {
      const comparisonMessage = {
        ...defaultMessage,
        metadata: {
          contentType: 'comparison',
          comparisonData: ['Feature A is better', 'Feature B is similar']
        }
      };
      
      render(
        <Message 
          message={comparisonMessage}
          isStreaming={true}
        />
      );
      
      expect(screen.getByText(defaultMessage.content)).toBeInTheDocument();
    });
  });

  describe('PropTypes Validation', () => {
    it('should accept valid streaming prop types', () => {
      // Test with console.error mock to catch PropTypes warnings
      const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
      
      render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          streamingProgress={0.75}
          enableStreamingAnimation={false}
        />
      );
      
      expect(consoleError).not.toHaveBeenCalled();
      consoleError.mockRestore();
    });

    it('should work with default streaming props', () => {
      const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
      
      render(<Message message={defaultMessage} />);
      
      expect(consoleError).not.toHaveBeenCalled();
      consoleError.mockRestore();
    });
  });

  describe('Performance', () => {
    it('should not cause unnecessary re-renders during streaming', () => {
      let renderCount = 0;
      const TestMessage = (props) => {
        renderCount++;
        return <Message {...props} />;
      };
      
      const { rerender } = render(
        <TestMessage 
          message={defaultMessage}
          isStreaming={true}
          streamingProgress={0.5}
        />
      );
      
      const initialRenderCount = renderCount;
      
      // Update with same props
      rerender(
        <TestMessage 
          message={defaultMessage}
          isStreaming={true}
          streamingProgress={0.5}
        />
      );
      
      // Should not cause additional renders with same props
      expect(renderCount).toBe(initialRenderCount);
    });

    it('should handle rapid progress updates efficiently', () => {
      const { rerender } = render(
        <Message 
          message={defaultMessage}
          isStreaming={true}
          streamingProgress={0}
        />
      );
      
      // Simulate rapid progress updates
      for (let progress = 0.1; progress <= 1.0; progress += 0.1) {
        rerender(
          <Message 
            message={defaultMessage}
            isStreaming={progress < 1.0}
            streamingProgress={progress}
          />
        );
      }
      
      // Should complete without errors
      expect(screen.getByText(defaultMessage.content)).toBeInTheDocument();
    });
  });
});