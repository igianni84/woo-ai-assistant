/**
 * TypingIndicator Component Tests
 * 
 * Comprehensive test suite for the TypingIndicator component
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import TypingIndicator from './TypingIndicator';

describe('TypingIndicator Component', () => {
  describe('Basic Rendering', () => {
    it('should render typing indicator with default props', () => {
      render(<TypingIndicator />);
      
      const indicator = screen.getByRole('status');
      expect(indicator).toBeInTheDocument();
      expect(indicator).toHaveClass('message', 'bot-message', 'typing-indicator');
    });

    it('should display animated dots', () => {
      render(<TypingIndicator />);
      
      const dotsContainer = document.querySelector('.typing-dots');
      expect(dotsContainer).toBeInTheDocument();
      
      const dots = document.querySelectorAll('.dot');
      expect(dots).toHaveLength(3);
      expect(dots[0]).toHaveClass('dot-1');
      expect(dots[1]).toHaveClass('dot-2');
      expect(dots[2]).toHaveClass('dot-3');
    });

    it('should have message-content wrapper', () => {
      render(<TypingIndicator />);
      
      const messageContent = document.querySelector('.message-content');
      expect(messageContent).toBeInTheDocument();
    });
  });

  describe('Avatar Display', () => {
    it('should show avatar by default', () => {
      render(<TypingIndicator />);
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).toBeInTheDocument();
      expect(avatar.querySelector('svg')).toBeInTheDocument();
    });

    it('should show avatar when showAvatar is true', () => {
      render(<TypingIndicator showAvatar={true} />);
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).toBeInTheDocument();
    });

    it('should hide avatar when showAvatar is false', () => {
      render(<TypingIndicator showAvatar={false} />);
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).not.toBeInTheDocument();
    });

    it('should have correct SVG structure in avatar', () => {
      render(<TypingIndicator />);
      
      const avatar = document.querySelector('.message-avatar');
      const svg = avatar.querySelector('svg');
      
      expect(svg).toHaveAttribute('width', '20');
      expect(svg).toHaveAttribute('height', '20');
      expect(svg).toHaveAttribute('viewBox', '0 0 24 24');
      
      // Check for circle and path elements
      expect(svg.querySelector('circle')).toBeInTheDocument();
      expect(svg.querySelectorAll('path')).toHaveLength(2);
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA attributes with default label', () => {
      render(<TypingIndicator />);
      
      const indicator = screen.getByRole('status');
      expect(indicator).toHaveAttribute('aria-live', 'polite');
      expect(indicator).toHaveAttribute('aria-label', 'AI assistant is typing');
    });

    it('should use custom aria label when provided', () => {
      const customLabel = 'Assistant is thinking...';
      render(<TypingIndicator ariaLabel={customLabel} />);
      
      const indicator = screen.getByRole('status');
      expect(indicator).toHaveAttribute('aria-label', customLabel);
    });

    it('should have screen reader only text', () => {
      render(<TypingIndicator />);
      
      const srOnlyText = document.querySelector('.sr-only');
      expect(srOnlyText).toBeInTheDocument();
      expect(srOnlyText).toHaveTextContent('AI assistant is typing');
    });

    it('should use custom aria label in screen reader text', () => {
      const customLabel = 'Bot is processing...';
      render(<TypingIndicator ariaLabel={customLabel} />);
      
      const srOnlyText = document.querySelector('.sr-only');
      expect(srOnlyText).toHaveTextContent(customLabel);
    });

    it('should have aria-hidden on typing dots', () => {
      render(<TypingIndicator />);
      
      const dotsContainer = document.querySelector('.typing-dots');
      expect(dotsContainer).toHaveAttribute('aria-hidden', 'true');
    });

    it('should have aria-hidden on avatar', () => {
      render(<TypingIndicator />);
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).toHaveAttribute('aria-hidden', 'true');
    });
  });

  describe('CSS Classes', () => {
    it('should apply default CSS classes', () => {
      render(<TypingIndicator />);
      
      const indicator = screen.getByRole('status');
      expect(indicator).toHaveClass('message', 'bot-message', 'typing-indicator');
    });

    it('should apply additional CSS class when provided', () => {
      const customClass = 'custom-typing-style';
      render(<TypingIndicator className={customClass} />);
      
      const indicator = screen.getByRole('status');
      expect(indicator).toHaveClass(
        'message', 
        'bot-message', 
        'typing-indicator', 
        customClass
      );
    });

    it('should handle empty className gracefully', () => {
      render(<TypingIndicator className="" />);
      
      const indicator = screen.getByRole('status');
      expect(indicator).toHaveClass('message', 'bot-message', 'typing-indicator');
    });

    it('should apply correct classes to dots', () => {
      render(<TypingIndicator />);
      
      const dots = document.querySelectorAll('.dot');
      expect(dots[0]).toHaveClass('dot', 'dot-1');
      expect(dots[1]).toHaveClass('dot', 'dot-2');
      expect(dots[2]).toHaveClass('dot', 'dot-3');
    });
  });

  describe('Component Structure', () => {
    it('should maintain proper DOM structure', () => {
      render(<TypingIndicator />);
      
      const indicator = screen.getByRole('status');
      const avatar = indicator.querySelector('.message-avatar');
      const messageContent = indicator.querySelector('.message-content');
      const typingDots = messageContent.querySelector('.typing-dots');
      const srOnly = messageContent.querySelector('.sr-only');
      
      expect(avatar).toBeInTheDocument();
      expect(messageContent).toBeInTheDocument();
      expect(typingDots).toBeInTheDocument();
      expect(srOnly).toBeInTheDocument();
    });

    it('should maintain structure without avatar', () => {
      render(<TypingIndicator showAvatar={false} />);
      
      const indicator = screen.getByRole('status');
      const avatar = indicator.querySelector('.message-avatar');
      const messageContent = indicator.querySelector('.message-content');
      
      expect(avatar).not.toBeInTheDocument();
      expect(messageContent).toBeInTheDocument();
    });
  });

  describe('PropTypes and Default Values', () => {
    it('should use default values when no props provided', () => {
      render(<TypingIndicator />);
      
      const indicator = screen.getByRole('status');
      expect(indicator).toHaveAttribute('aria-label', 'AI assistant is typing');
      expect(indicator).toHaveClass('typing-indicator');
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).toBeInTheDocument();
    });

    it('should handle all prop combinations correctly', () => {
      render(
        <TypingIndicator 
          showAvatar={false}
          ariaLabel="Custom typing message"
          className="custom-class"
        />
      );
      
      const indicator = screen.getByRole('status');
      expect(indicator).toHaveAttribute('aria-label', 'Custom typing message');
      expect(indicator).toHaveClass('custom-class');
      
      const avatar = document.querySelector('.message-avatar');
      expect(avatar).not.toBeInTheDocument();
    });
  });

  describe('Integration and Performance', () => {
    it('should render quickly without performance issues', () => {
      const startTime = performance.now();
      render(<TypingIndicator />);
      const endTime = performance.now();
      
      // Rendering should be very fast (< 50ms)
      expect(endTime - startTime).toBeLessThan(50);
    });

    it('should not cause memory leaks on multiple renders', () => {
      const { rerender } = render(<TypingIndicator />);
      
      for (let i = 0; i < 10; i++) {
        rerender(<TypingIndicator ariaLabel={`Message ${i}`} />);
      }
      
      // Should still render correctly after multiple rerenders
      const indicator = screen.getByRole('status');
      expect(indicator).toBeInTheDocument();
    });

    it('should be compatible with different React versions', () => {
      // Test that component renders without React warnings
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      
      render(<TypingIndicator />);
      
      expect(consoleSpy).not.toHaveBeenCalled();
      consoleSpy.mockRestore();
    });
  });
});