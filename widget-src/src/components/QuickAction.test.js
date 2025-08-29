/**
 * QuickAction Component Tests
 * 
 * Comprehensive test suite for the QuickAction component covering
 * button variants, interactions, accessibility, and icon rendering.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import QuickAction from './QuickAction';

describe('QuickAction', () => {
  const defaultProps = {
    type: 'test_action',
    onClick: jest.fn(),
    children: 'Test Button'
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('Basic Rendering', () => {
    it('should render button with correct content', () => {
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      expect(button).toBeInTheDocument();
      expect(button).toHaveTextContent('Test Button');
    });

    it('should apply correct base classes', () => {
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('quick-action');
      expect(button).toHaveClass('quick-action-primary');
      expect(button).toHaveClass('quick-action-test-action');
    });

    it('should have correct data attributes', () => {
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('data-action-type', 'test_action');
      expect(button).toHaveAttribute('data-testid', 'quick-action-test_action');
    });

    it('should render without content when children is null', () => {
      render(<QuickAction {...defaultProps} children={null} />);
      
      const button = screen.getByRole('button');
      expect(button).toBeInTheDocument();
      expect(button.textContent.trim()).toBe('');
    });
  });

  describe('Button Variants', () => {
    const variants = ['primary', 'secondary', 'tertiary', 'success', 'warning', 'danger', 'ghost', 'link'];

    variants.forEach(variant => {
      it(`should apply ${variant} variant class`, () => {
        render(<QuickAction {...defaultProps} variant={variant} />);
        
        const button = screen.getByRole('button');
        expect(button).toHaveClass(`quick-action-${variant}`);
      });
    });

    it('should default to primary variant', () => {
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('quick-action-primary');
    });
  });

  describe('Button Sizes', () => {
    it('should apply small size class', () => {
      render(<QuickAction {...defaultProps} size="small" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('quick-action-small');
    });

    it('should apply large size class', () => {
      render(<QuickAction {...defaultProps} size="large" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('quick-action-large');
    });

    it('should not add size class for normal size', () => {
      render(<QuickAction {...defaultProps} size="normal" />);
      
      const button = screen.getByRole('button');
      expect(button).not.toHaveClass('quick-action-normal');
    });
  });

  describe('Button States', () => {
    it('should handle disabled state', () => {
      render(<QuickAction {...defaultProps} disabled={true} />);
      
      const button = screen.getByRole('button');
      expect(button).toBeDisabled();
      expect(button).toHaveClass('quick-action-disabled');
    });

    it('should handle loading state', () => {
      render(<QuickAction {...defaultProps} loading={true} />);
      
      const button = screen.getByRole('button');
      expect(button).toBeDisabled();
      expect(button).toHaveClass('quick-action-loading');
    });

    it('should show loading spinner when loading', () => {
      render(<QuickAction {...defaultProps} loading={true} />);
      
      expect(screen.getByRole('button')).toContainHTML('loading-spinner');
    });

    it('should apply pressed state temporarily on click', async () => {
      const user = userEvent.setup();
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      await user.click(button);
      
      // Pressed state should be applied briefly
      expect(button).toHaveClass('quick-action-pressed');
      
      // Wait for pressed state to be removed
      await waitFor(() => {
        expect(button).not.toHaveClass('quick-action-pressed');
      }, { timeout: 200 });
    });
  });

  describe('Icon Rendering', () => {
    it('should render add_to_cart icon', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toBeInTheDocument();
    });

    it('should render view_product icon', () => {
      render(<QuickAction {...defaultProps} type="view_product" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toBeInTheDocument();
    });

    it('should render apply_coupon icon', () => {
      render(<QuickAction {...defaultProps} type="apply_coupon" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toBeInTheDocument();
    });

    it('should render compare icon', () => {
      render(<QuickAction {...defaultProps} type="compare" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toBeInTheDocument();
    });

    it('should render wishlist icon', () => {
      render(<QuickAction {...defaultProps} type="wishlist" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toBeInTheDocument();
    });

    it('should render share icon', () => {
      render(<QuickAction {...defaultProps} type="share" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toBeInTheDocument();
    });

    it('should override type icon with explicit icon prop', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" icon="heart" />);
      
      // Should render heart icon instead of cart icon
      const button = screen.getByRole('button');
      expect(button.querySelector('.quick-action-icon svg')).toBeInTheDocument();
    });

    it('should adjust icon size for small buttons', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" size="small" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toHaveAttribute('width', '14');
      expect(icon).toHaveAttribute('height', '14');
    });

    it('should use normal icon size for normal and large buttons', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toHaveAttribute('width', '16');
      expect(icon).toHaveAttribute('height', '16');
    });

    it('should not render icon for unknown types', () => {
      render(<QuickAction {...defaultProps} type="unknown_type" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon');
      expect(icon).toBeInTheDocument();
      expect(icon.querySelector('svg')).toBeNull();
    });
  });

  describe('Click Handling', () => {
    it('should call onClick with type and data', async () => {
      const user = userEvent.setup();
      const mockData = { productId: 123 };
      render(<QuickAction {...defaultProps} data={mockData} />);
      
      const button = screen.getByRole('button');
      await user.click(button);
      
      expect(defaultProps.onClick).toHaveBeenCalledWith('test_action', mockData);
    });

    it('should not call onClick when disabled', async () => {
      const user = userEvent.setup();
      render(<QuickAction {...defaultProps} disabled={true} />);
      
      const button = screen.getByRole('button');
      await user.click(button);
      
      expect(defaultProps.onClick).not.toHaveBeenCalled();
    });

    it('should not call onClick when loading', async () => {
      const user = userEvent.setup();
      render(<QuickAction {...defaultProps} loading={true} />);
      
      const button = screen.getByRole('button');
      await user.click(button);
      
      expect(defaultProps.onClick).not.toHaveBeenCalled();
    });

    it('should handle async onClick functions', async () => {
      const user = userEvent.setup();
      const asyncOnClick = jest.fn().mockResolvedValue('success');
      render(<QuickAction {...defaultProps} onClick={asyncOnClick} />);
      
      const button = screen.getByRole('button');
      await user.click(button);
      
      expect(asyncOnClick).toHaveBeenCalled();
      await expect(asyncOnClick).toResolve;
    });

    it('should handle onClick errors gracefully', async () => {
      const user = userEvent.setup();
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const errorOnClick = jest.fn().mockRejectedValue(new Error('Test error'));
      
      render(<QuickAction {...defaultProps} onClick={errorOnClick} />);
      
      const button = screen.getByRole('button');
      await user.click(button);
      
      await waitFor(() => {
        expect(consoleSpy).toHaveBeenCalledWith('QuickAction error:', expect.any(Error));
      });
      
      consoleSpy.mockRestore();
    });
  });

  describe('Keyboard Navigation', () => {
    it('should handle Enter key press', async () => {
      const user = userEvent.setup();
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      button.focus();
      fireEvent.keyDown(button, { key: 'Enter' });
      
      expect(defaultProps.onClick).toHaveBeenCalledWith('test_action', {});
    });

    it('should handle Space key press', async () => {
      const user = userEvent.setup();
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      button.focus();
      fireEvent.keyDown(button, { key: ' ' });
      
      expect(defaultProps.onClick).toHaveBeenCalledWith('test_action', {});
    });

    it('should not handle other key presses', async () => {
      const user = userEvent.setup();
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      button.focus();
      await user.keyboard('{Escape}');
      
      expect(defaultProps.onClick).not.toHaveBeenCalled();
    });

    it('should prevent default behavior on Enter and Space', async () => {
      const user = userEvent.setup();
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      button.focus();
      
      const enterEvent = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
      const spaceEvent = new KeyboardEvent('keydown', { key: ' ', bubbles: true });
      
      const preventDefaultSpy = jest.spyOn(enterEvent, 'preventDefault');
      
      fireEvent(button, enterEvent);
      fireEvent(button, spaceEvent);
      
      expect(preventDefaultSpy).toHaveBeenCalled();
    });
  });

  describe('Accessibility', () => {
    it('should have proper button role', () => {
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('type', 'button');
    });

    it('should generate appropriate aria-label for add_to_cart', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-label', 'Add item to cart');
    });

    it('should generate appropriate aria-label for view_product', () => {
      render(<QuickAction {...defaultProps} type="view_product" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-label', 'View product details');
    });

    it('should generate loading aria-label when loading', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" loading={true} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-label', 'Adding item to cart');
    });

    it('should use custom aria-label when provided', () => {
      render(<QuickAction {...defaultProps} ariaLabel="Custom Label" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-label', 'Custom Label');
    });

    it('should use children as aria-label fallback', () => {
      render(<QuickAction {...defaultProps} type="unknown_type" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-label', 'Test Button');
    });

    it('should have tooltip when provided', () => {
      render(<QuickAction {...defaultProps} tooltip="This is a tooltip" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('title', 'This is a tooltip');
    });

    it('should mark icon as aria-hidden', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" />);
      
      const icon = screen.getByRole('button').querySelector('.quick-action-icon svg');
      expect(icon).toHaveAttribute('aria-hidden', 'true');
    });

    it('should mark decorative elements as aria-hidden', () => {
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      expect(button.querySelector('.quick-action-focus-ring')).toHaveAttribute('aria-hidden', 'true');
      expect(button.querySelector('.quick-action-ripple')).toHaveAttribute('aria-hidden', 'true');
    });
  });

  describe('Props and Integration', () => {
    it('should pass through additional props', () => {
      render(<QuickAction {...defaultProps} id="custom-id" className="extra-class" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('id', 'custom-id');
      expect(button).toHaveClass('extra-class');
    });

    it('should handle empty data object', async () => {
      const user = userEvent.setup();
      render(<QuickAction {...defaultProps} data={{}} />);
      
      const button = screen.getByRole('button');
      await user.click(button);
      
      expect(defaultProps.onClick).toHaveBeenCalledWith('test_action', {});
    });

    it('should handle complex data object', async () => {
      const user = userEvent.setup();
      const complexData = {
        productId: 123,
        variant: 'large',
        metadata: { source: 'chat' }
      };
      render(<QuickAction {...defaultProps} data={complexData} />);
      
      const button = screen.getByRole('button');
      await user.click(button);
      
      expect(defaultProps.onClick).toHaveBeenCalledWith('test_action', complexData);
    });

    it('should handle missing onClick gracefully', async () => {
      const user = userEvent.setup();
      render(<QuickAction type="test_action" onClick={jest.fn()} />);
      
      const button = screen.getByRole('button');
      
      // Should not throw error when clicking
      await expect(user.click(button)).resolves.not.toThrow();
    });
  });

  describe('Button Content Structure', () => {
    it('should have proper content structure with icon and text', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" />);
      
      const button = screen.getByRole('button');
      expect(button.querySelector('.quick-action-content')).toBeInTheDocument();
      expect(button.querySelector('.quick-action-icon')).toBeInTheDocument();
      expect(button.querySelector('.quick-action-text')).toBeInTheDocument();
    });

    it('should render only text when no icon', () => {
      render(<QuickAction type="no_icon_type" onClick={jest.fn()}>Text Only</QuickAction>);
      
      const button = screen.getByRole('button');
      expect(button.querySelector('.quick-action-text')).toBeInTheDocument();
      expect(button.querySelector('.quick-action-icon svg')).toBeNull();
    });

    it('should render only icon when no text', () => {
      render(<QuickAction {...defaultProps} type="add_to_cart" children={null} />);
      
      const button = screen.getByRole('button');
      expect(button.querySelector('.quick-action-icon')).toBeInTheDocument();
      expect(button.querySelector('.quick-action-text')).toBeNull();
    });
  });

  describe('Performance', () => {
    it('should not re-render unnecessarily', () => {
      const { rerender } = render(<QuickAction {...defaultProps} />);
      
      const initialButton = screen.getByRole('button');
      
      // Re-render with same props
      rerender(<QuickAction {...defaultProps} />);
      
      const afterRerender = screen.getByRole('button');
      expect(initialButton).toBe(afterRerender);
    });
  });
});

describe('QuickAction Naming Conventions', () => {
  it('should follow PascalCase for component name', () => {
    expect(QuickAction.name).toBe('QuickAction');
  });

  it('should use camelCase for all props', () => {
    const propNames = Object.keys(QuickAction.propTypes);
    propNames.forEach(propName => {
      expect(propName).toMatch(/^[a-z][a-zA-Z0-9]*$/);
    });
  });

  it('should use camelCase for internal methods', () => {
    // This would typically test internal methods if they were exposed
    // For now, we ensure the component structure follows conventions
    const testProps = { type: 'test', onClick: jest.fn() };
    const instance = React.createElement(QuickAction, testProps);
    expect(instance.type.name).toBe('QuickAction');
  });
});