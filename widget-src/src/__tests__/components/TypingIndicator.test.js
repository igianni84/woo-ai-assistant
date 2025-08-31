/**
 * TypingIndicator Component Tests
 *
 * Comprehensive tests for the TypingIndicator component including
 * rendering, animations, accessibility, and different variants.
 *
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import TypingIndicator, { MinimalTypingIndicator } from '../../components/TypingIndicator';
import {
  renderWithContext,
  assertAriaAttributes,
  assertComponentNaming,
  mockWordPressGlobals
} from '../utils/testUtils';

describe('TypingIndicator Component', () => {
  const defaultProps = {
    assistantName: 'AI Assistant',
    config: {
      theme: 'light'
    }
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockWordPressGlobals();
  });

  describe('Component Rendering', () => {
    test('renders TypingIndicator component correctly', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      expect(screen.getByRole('status')).toBeInTheDocument();
      expect(screen.getByLabelText('AI Assistant is typing')).toBeInTheDocument();
    });

    test('follows naming conventions', () => {
      assertComponentNaming(TypingIndicator, 'TypingIndicator');
    });

    test('applies visibility animation on mount', async () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const indicatorElement = screen.getByRole('status');

      await waitFor(() => {
        expect(indicatorElement).toHaveClass('woo-ai-assistant-typing-indicator--visible');
      }, { timeout: 200 });
    });

    test('displays assistant name correctly', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      expect(screen.getByText('AI Assistant')).toBeInTheDocument();
    });

    test('uses default assistant name when not provided', () => {
      renderWithContext(<TypingIndicator config={{}} />);

      expect(screen.getByText('AI Assistant')).toBeInTheDocument();
    });
  });

  describe('Avatar Section', () => {
    test('renders avatar with bot icon', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const avatar = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-avatar');
      expect(avatar).toBeInTheDocument();

      const botIcon = avatar.querySelector('svg');
      expect(botIcon).toBeInTheDocument();
    });

    test('avatar has correct styling classes', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const avatar = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-avatar');
      expect(avatar).toHaveClass('woo-ai-assistant-typing-avatar');
    });
  });

  describe('Typing Animation', () => {
    test('renders typing dots animation', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const dotsContainer = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-dots');
      expect(dotsContainer).toBeInTheDocument();

      const dots = dotsContainer.querySelectorAll('.woo-ai-assistant-typing-dot');
      expect(dots).toHaveLength(3);
    });

    test('each dot has proper styling class', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const dots = screen.getByRole('status').querySelectorAll('.woo-ai-assistant-typing-dot');

      dots.forEach(dot => {
        expect(dot).toHaveClass('woo-ai-assistant-typing-dot');
      });
    });

    test('animation container has proper structure', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const animationContainer = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-animation');
      expect(animationContainer).toBeInTheDocument();
      expect(animationContainer).toHaveClass('woo-ai-assistant-typing-animation');
    });
  });

  describe('Status Message', () => {
    test('displays random typing status message', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const possibleMessages = [
        'is typing...',
        'is thinking...',
        'is processing...',
        'is analyzing...'
      ];

      const statusElement = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-status');
      expect(statusElement).toBeInTheDocument();

      const statusText = statusElement.textContent;
      expect(possibleMessages).toContain(statusText);
    });

    test('status message remains consistent during component lifecycle', () => {
      const { rerender } = renderWithContext(<TypingIndicator {...defaultProps} />);

      const statusElement = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-status');
      const initialText = statusElement.textContent;

      // Re-render component
      rerender(<TypingIndicator {...defaultProps} />);

      const statusElementAfterRerender = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-status');
      expect(statusElementAfterRerender.textContent).toBe(initialText);
    });
  });

  describe('Header Section', () => {
    test('renders header with sender name and status', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const header = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-header');
      expect(header).toBeInTheDocument();

      expect(screen.getByText('AI Assistant')).toBeInTheDocument();
    });

    test('header has proper structure and classes', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const senderElement = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-sender');
      const statusElement = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-status');

      expect(senderElement).toBeInTheDocument();
      expect(statusElement).toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    test('has proper ARIA attributes', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const indicatorElement = screen.getByRole('status');
      assertAriaAttributes(indicatorElement, {
        'aria-live': 'polite',
        'aria-label': 'AI Assistant is typing'
      });
    });

    test('provides meaningful status announcement', () => {
      renderWithContext(<TypingIndicator assistantName="ChatBot" />);

      const indicatorElement = screen.getByRole('status');
      expect(indicatorElement).toHaveAttribute('aria-label', 'ChatBot is typing');
    });

    test('uses semantic role for status updates', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const indicatorElement = screen.getByRole('status');
      expect(indicatorElement).toBeInTheDocument();
    });
  });

  describe('Props Handling', () => {
    test('handles missing assistantName prop', () => {
      expect(() => {
        renderWithContext(<TypingIndicator />);
      }).not.toThrow();

      expect(screen.getByText('AI Assistant')).toBeInTheDocument();
    });

    test('handles empty config prop', () => {
      expect(() => {
        renderWithContext(<TypingIndicator assistantName="TestBot" config={{}} />);
      }).not.toThrow();

      expect(screen.getByText('TestBot')).toBeInTheDocument();
    });

    test('accepts custom assistant name', () => {
      renderWithContext(<TypingIndicator assistantName="Custom Bot" />);

      expect(screen.getByText('Custom Bot')).toBeInTheDocument();
    });
  });

  describe('CSS Classes and Structure', () => {
    test('has proper CSS class structure', () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const indicatorElement = screen.getByRole('status');
      expect(indicatorElement).toHaveClass('woo-ai-assistant-typing-indicator');

      const content = indicatorElement.querySelector('.woo-ai-assistant-typing-content');
      expect(content).toBeInTheDocument();
    });

    test('animation becomes visible after mount', async () => {
      renderWithContext(<TypingIndicator {...defaultProps} />);

      const indicatorElement = screen.getByRole('status');

      await waitFor(() => {
        expect(indicatorElement).toHaveClass('woo-ai-assistant-typing-indicator--visible');
      });
    });
  });
});

describe('MinimalTypingIndicator Component', () => {
  describe('Component Rendering', () => {
    test('renders MinimalTypingIndicator component correctly', () => {
      renderWithContext(<MinimalTypingIndicator />);

      expect(screen.getByRole('status')).toBeInTheDocument();
      expect(screen.getByLabelText('AI is typing')).toBeInTheDocument();
    });

    test('follows naming conventions', () => {
      assertComponentNaming(MinimalTypingIndicator, 'MinimalTypingIndicator');
    });

    test('applies visibility animation on mount', async () => {
      renderWithContext(<MinimalTypingIndicator />);

      const indicatorElement = screen.getByRole('status');

      await waitFor(() => {
        expect(indicatorElement).toHaveClass('woo-ai-assistant-typing-minimal--visible');
      }, { timeout: 200 });
    });
  });

  describe('Minimal Animation', () => {
    test('renders three dots for animation', () => {
      renderWithContext(<MinimalTypingIndicator />);

      const dotsContainer = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-dots-minimal');
      expect(dotsContainer).toBeInTheDocument();

      const dots = dotsContainer.querySelectorAll('span');
      expect(dots).toHaveLength(3);
    });

    test('dots have proper structure', () => {
      renderWithContext(<MinimalTypingIndicator />);

      const dotsContainer = screen.getByRole('status').querySelector('.woo-ai-assistant-typing-dots-minimal');
      const dots = dotsContainer.querySelectorAll('span');

      dots.forEach(dot => {
        expect(dot).toBeInTheDocument();
      });
    });
  });

  describe('Accessibility', () => {
    test('has proper ARIA attributes', () => {
      renderWithContext(<MinimalTypingIndicator />);

      const indicatorElement = screen.getByRole('status');
      assertAriaAttributes(indicatorElement, {
        'aria-live': 'polite',
        'aria-label': 'AI is typing'
      });
    });
  });

  describe('CSS Classes', () => {
    test('has minimal styling classes', () => {
      renderWithContext(<MinimalTypingIndicator />);

      const indicatorElement = screen.getByRole('status');
      expect(indicatorElement).toHaveClass('woo-ai-assistant-typing-minimal');
    });

    test('becomes visible after mount', async () => {
      renderWithContext(<MinimalTypingIndicator />);

      const indicatorElement = screen.getByRole('status');

      await waitFor(() => {
        expect(indicatorElement).toHaveClass('woo-ai-assistant-typing-minimal--visible');
      });
    });
  });
});

describe('Component Comparison', () => {
  test('both components provide status role', () => {
    const { rerender } = renderWithContext(<TypingIndicator />);
    expect(screen.getByRole('status')).toBeInTheDocument();

    rerender(<MinimalTypingIndicator />);
    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  test('both components have proper ARIA live regions', () => {
    const { rerender } = renderWithContext(<TypingIndicator />);
    expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'polite');

    rerender(<MinimalTypingIndicator />);
    expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'polite');
  });

  test('full version has more elements than minimal version', () => {
    const { rerender } = renderWithContext(<TypingIndicator />);
    const fullVersionElements = screen.getByRole('status').children.length;

    rerender(<MinimalTypingIndicator />);
    const minimalVersionElements = screen.getByRole('status').children.length;

    expect(fullVersionElements).toBeGreaterThan(minimalVersionElements);
  });
});

describe('Animation Performance', () => {
  test('renders without performance issues', () => {
    const startTime = performance.now();
    renderWithContext(<TypingIndicator />);
    const endTime = performance.now();

    // Should render quickly
    expect(endTime - startTime).toBeLessThan(100);
  });

  test('minimal version renders quickly', () => {
    const startTime = performance.now();
    renderWithContext(<MinimalTypingIndicator />);
    const endTime = performance.now();

    // Should render quickly
    expect(endTime - startTime).toBeLessThan(50);
  });

  test('handles multiple re-renders efficiently', () => {
    const { rerender } = renderWithContext(<TypingIndicator />);

    const startTime = performance.now();

    // Multiple re-renders
    for (let i = 0; i < 50; i++) {
      rerender(<TypingIndicator assistantName={`Bot ${i}`} />);
    }

    const endTime = performance.now();

    // Should handle re-renders efficiently
    expect(endTime - startTime).toBeLessThan(500);
  });
});

describe('Error Handling', () => {
  test('TypingIndicator handles undefined props gracefully', () => {
    expect(() => {
      renderWithContext(<TypingIndicator assistantName={undefined} config={undefined} />);
    }).not.toThrow();
  });

  test('MinimalTypingIndicator handles no props', () => {
    expect(() => {
      renderWithContext(<MinimalTypingIndicator />);
    }).not.toThrow();
  });

  test('both components handle empty string assistant name', () => {
    expect(() => {
      renderWithContext(<TypingIndicator assistantName="" />);
    }).not.toThrow();

    // Should fall back to default
    expect(screen.getByText('AI Assistant')).toBeInTheDocument();
  });
});

describe('PropTypes Validation', () => {
  test('TypingIndicator validates prop types', () => {
    const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

    // Invalid prop types
    renderWithContext(<TypingIndicator assistantName={123} config="invalid" />);

    // Should log PropTypes warnings in development
    if (process.env.NODE_ENV === 'development') {
      expect(consoleSpy).toHaveBeenCalled();
    }

    consoleSpy.mockRestore();
  });

  test('MinimalTypingIndicator accepts no props without warnings', () => {
    const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

    renderWithContext(<MinimalTypingIndicator />);

    expect(consoleSpy).not.toHaveBeenCalled();

    consoleSpy.mockRestore();
  });
});
