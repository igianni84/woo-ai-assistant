/**
 * Quick Action Component Tests
 *
 * Comprehensive tests for the QuickAction component including
 * different action types, loading states, variants, sizes,
 * accessibility, and predefined action components.
 *
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import QuickAction, { 
  QuickActionGroup, 
  AddToCartAction, 
  ApplyCouponAction, 
  ViewProductAction, 
  CheckoutAction 
} from '../../components/QuickAction';
import { 
  renderWithContext, 
  assertAriaAttributes, 
  assertComponentNaming,
  mockWordPressGlobals 
} from '../utils/testUtils';

describe('QuickAction Component', () => {
  const defaultProps = {
    type: 'test-action',
    label: 'Test Action',
    onClick: jest.fn()
  };

  const mockIcon = <span data-testid="mock-icon">Icon</span>;

  beforeEach(() => {
    jest.clearAllMocks();
    mockWordPressGlobals();
  });

  describe('Component Naming Convention', () => {
    it('should follow PascalCase naming convention', () => {
      assertComponentNaming(QuickAction, 'QuickAction');
    });

    it('should have correct display name', () => {
      expect(QuickAction.name).toBe('QuickAction');
    });
  });

  describe('Basic Rendering', () => {
    it('should render button with correct label and type', () => {
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveTextContent('Test Action');
      expect(button).toHaveAttribute('data-action-type', 'test-action');
      expect(button).toHaveAttribute('type', 'button');
    });

    it('should render with icon when provided', () => {
      render(<QuickAction {...defaultProps} icon={mockIcon} />);
      
      expect(screen.getByTestId('mock-icon')).toBeInTheDocument();
      expect(screen.getByText('Test Action')).toBeInTheDocument();
    });

    it('should render without text for small size with icon', () => {
      render(<QuickAction {...defaultProps} icon={mockIcon} size="small" />);
      
      expect(screen.getByTestId('mock-icon')).toBeInTheDocument();
      expect(screen.queryByText('Test Action')).not.toBeInTheDocument();
    });

    it('should always show text when no icon is provided', () => {
      render(<QuickAction {...defaultProps} size="small" />);
      
      expect(screen.getByText('Test Action')).toBeInTheDocument();
    });
  });

  describe('Variants and Sizes', () => {
    it('should apply correct CSS classes for variants', () => {
      const { rerender } = render(<QuickAction {...defaultProps} variant="primary" />);
      let button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--primary');

      rerender(<QuickAction {...defaultProps} variant="secondary" />);
      button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--secondary');

      rerender(<QuickAction {...defaultProps} variant="outline" />);
      button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--outline');

      rerender(<QuickAction {...defaultProps} variant="ghost" />);
      button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--ghost');
    });

    it('should handle legacy primary prop', () => {
      render(<QuickAction {...defaultProps} primary={true} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--primary');
    });

    it('should apply correct size classes', () => {
      const { rerender } = render(<QuickAction {...defaultProps} size="small" />);
      let button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--small');

      rerender(<QuickAction {...defaultProps} size="medium" />);
      button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--medium');

      rerender(<QuickAction {...defaultProps} size="large" />);
      button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--large');
    });

    it('should apply full width class when specified', () => {
      render(<QuickAction {...defaultProps} fullWidth={true} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--full-width');
    });

    it('should apply custom class names', () => {
      render(<QuickAction {...defaultProps} className="custom-class" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('custom-class');
    });
  });

  describe('Loading States', () => {
    it('should show loading state when loading prop is true', () => {
      render(<QuickAction {...defaultProps} loading={true} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--loading');
      expect(button).toBeDisabled();
      expect(screen.getByText('Processing...')).toBeInTheDocument();
    });

    it('should show custom loading text', () => {
      render(<QuickAction {...defaultProps} loading={true} loadingText="Saving..." />);
      
      expect(screen.getByText('Saving...')).toBeInTheDocument();
    });

    it('should show action-specific loading text', () => {
      const { rerender } = render(
        <QuickAction {...defaultProps} type="add-to-cart" loading={true} />
      );
      expect(screen.getByText('Adding...')).toBeInTheDocument();

      rerender(<QuickAction {...defaultProps} type="apply-coupon" loading={true} />);
      expect(screen.getByText('Applying...')).toBeInTheDocument();

      rerender(<QuickAction {...defaultProps} type="checkout" loading={true} />);
      expect(screen.getByText('Redirecting...')).toBeInTheDocument();
    });

    it('should show loading spinner instead of icon when loading', () => {
      render(<QuickAction {...defaultProps} icon={mockIcon} loading={true} />);
      
      expect(screen.queryByTestId('mock-icon')).not.toBeInTheDocument();
      expect(screen.getByRole('img', { hidden: true })).toHaveClass('woo-ai-assistant-loading-spinner');
    });

    it('should handle internal loading state during async operations', async () => {
      let resolveClick;
      const asyncOnClick = jest.fn(() => new Promise(resolve => {
        resolveClick = resolve;
      }));
      
      render(<QuickAction {...defaultProps} onClick={asyncOnClick} />);
      
      const button = screen.getByRole('button');
      await userEvent.click(button);
      
      // Should show loading state
      await waitFor(() => {
        expect(button).toHaveClass('woo-ai-assistant-quick-action--loading');
        expect(button).toBeDisabled();
      });
      
      // Resolve the promise
      resolveClick();
      
      // Should return to normal state
      await waitFor(() => {
        expect(button).not.toHaveClass('woo-ai-assistant-quick-action--loading');
        expect(button).not.toBeDisabled();
      });
    });
  });

  describe('User Interactions', () => {
    it('should call onClick handler with correct parameters', async () => {
      const mockOnClick = jest.fn();
      const testData = { productId: 123 };
      
      render(<QuickAction {...defaultProps} onClick={mockOnClick} data={testData} />);
      
      const button = screen.getByRole('button');
      await userEvent.click(button);
      
      expect(mockOnClick).toHaveBeenCalledWith({
        type: 'test-action',
        data: testData,
        event: expect.any(Object)
      });
    });

    it('should prevent default event behavior', async () => {
      const mockOnClick = jest.fn();
      render(<QuickAction {...defaultProps} onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      const clickEvent = new MouseEvent('click', { bubbles: true });
      const preventDefaultSpy = jest.spyOn(clickEvent, 'preventDefault');
      
      fireEvent(button, clickEvent);
      
      expect(preventDefaultSpy).toHaveBeenCalled();
    });

    it('should not call onClick when disabled', async () => {
      const mockOnClick = jest.fn();
      render(<QuickAction {...defaultProps} onClick={mockOnClick} disabled={true} />);
      
      const button = screen.getByRole('button');
      await userEvent.click(button);
      
      expect(mockOnClick).not.toHaveBeenCalled();
      expect(button).toBeDisabled();
    });

    it('should not call onClick when loading', async () => {
      const mockOnClick = jest.fn();
      render(<QuickAction {...defaultProps} onClick={mockOnClick} loading={true} />);
      
      const button = screen.getByRole('button');
      await userEvent.click(button);
      
      expect(mockOnClick).not.toHaveBeenCalled();
    });

    it('should handle onClick errors gracefully', async () => {
      const mockOnClick = jest.fn().mockRejectedValue(new Error('Test error'));
      
      render(<QuickAction {...defaultProps} onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      
      // Should not throw
      expect(async () => {
        await userEvent.click(button);
      }).not.toThrow();
      
      // Should return to normal state even after error
      await waitFor(() => {
        expect(button).not.toBeDisabled();
      });
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA attributes', () => {
      render(<QuickAction {...defaultProps} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-label', 'Test Action');
    });

    it('should use custom aria-label when provided', () => {
      render(<QuickAction {...defaultProps} ariaLabel="Custom Label" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-label', 'Custom Label');
    });

    it('should update aria-label for loading state', () => {
      render(<QuickAction {...defaultProps} loading={true} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-label', 'Processing... Test Action');
    });

    it('should support keyboard navigation', async () => {
      const mockOnClick = jest.fn();
      render(<QuickAction {...defaultProps} onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      button.focus();
      
      expect(document.activeElement).toBe(button);
      
      // Should activate on Enter
      await userEvent.keyboard('{Enter}');
      expect(mockOnClick).toHaveBeenCalled();
      
      jest.clearAllMocks();
      
      // Should activate on Space
      await userEvent.keyboard(' ');
      expect(mockOnClick).toHaveBeenCalled();
    });
  });

  describe('Data Attributes', () => {
    it('should set correct data attributes', () => {
      render(<QuickAction {...defaultProps} type="add-to-cart" />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('data-action-type', 'add-to-cart');
    });

    it('should pass through additional props', () => {
      render(<QuickAction {...defaultProps} data-testid="custom-test-id" />);
      
      expect(screen.getByTestId('custom-test-id')).toBeInTheDocument();
    });
  });
});

describe('QuickActionGroup Component', () => {
  const mockActions = [
    <QuickAction key="1" type="action1" label="Action 1" onClick={jest.fn()} />,
    <QuickAction key="2" type="action2" label="Action 2" onClick={jest.fn()} />,
    <QuickAction key="3" type="action3" label="Action 3" onClick={jest.fn()} />
  ];

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should render group with correct structure', () => {
    render(<QuickActionGroup>{mockActions}</QuickActionGroup>);
    
    const group = screen.getByRole('group');
    expect(group).toHaveClass('woo-ai-assistant-quick-action-group');
    expect(group).toHaveClass('woo-ai-assistant-quick-action-group--horizontal');
    expect(group).toHaveClass('woo-ai-assistant-quick-action-group--spacing-medium');
  });

  it('should apply direction classes', () => {
    const { rerender } = render(
      <QuickActionGroup direction="vertical">{mockActions}</QuickActionGroup>
    );
    
    let group = screen.getByRole('group');
    expect(group).toHaveClass('woo-ai-assistant-quick-action-group--vertical');

    rerender(<QuickActionGroup direction="horizontal">{mockActions}</QuickActionGroup>);
    group = screen.getByRole('group');
    expect(group).toHaveClass('woo-ai-assistant-quick-action-group--horizontal');
  });

  it('should apply spacing classes', () => {
    const { rerender } = render(
      <QuickActionGroup spacing="small">{mockActions}</QuickActionGroup>
    );
    
    let group = screen.getByRole('group');
    expect(group).toHaveClass('woo-ai-assistant-quick-action-group--spacing-small');

    rerender(<QuickActionGroup spacing="large">{mockActions}</QuickActionGroup>);
    group = screen.getByRole('group');
    expect(group).toHaveClass('woo-ai-assistant-quick-action-group--spacing-large');
  });

  it('should render all child actions', () => {
    render(<QuickActionGroup>{mockActions}</QuickActionGroup>);
    
    expect(screen.getByText('Action 1')).toBeInTheDocument();
    expect(screen.getByText('Action 2')).toBeInTheDocument();
    expect(screen.getByText('Action 3')).toBeInTheDocument();
  });
});

describe('Predefined Action Components', () => {
  const mockOnClick = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('AddToCartAction', () => {
    it('should render with correct props and defaults', () => {
      render(<AddToCartAction productId={123} onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveTextContent('Add to Cart');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--primary');
      expect(button).toHaveAttribute('data-action-type', 'add-to-cart');
    });

    it('should pass product data correctly', async () => {
      render(<AddToCartAction productId={123} quantity={2} onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      await userEvent.click(button);
      
      expect(mockOnClick).toHaveBeenCalledWith({
        type: 'add-to-cart',
        data: { productId: 123, quantity: 2 },
        event: expect.any(Object)
      });
    });
  });

  describe('ApplyCouponAction', () => {
    it('should render with correct props and defaults', () => {
      render(<ApplyCouponAction couponCode="SAVE20" onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveTextContent('Apply Coupon');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--secondary');
      expect(button).toHaveAttribute('data-action-type', 'apply-coupon');
    });

    it('should pass coupon data correctly', async () => {
      render(<ApplyCouponAction couponCode="SAVE20" onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      await userEvent.click(button);
      
      expect(mockOnClick).toHaveBeenCalledWith({
        type: 'apply-coupon',
        data: { couponCode: 'SAVE20' },
        event: expect.any(Object)
      });
    });
  });

  describe('ViewProductAction', () => {
    it('should render with correct props and defaults', () => {
      render(<ViewProductAction productId={123} onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveTextContent('View Details');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--outline');
      expect(button).toHaveAttribute('data-action-type', 'view-product');
    });
  });

  describe('CheckoutAction', () => {
    it('should render with correct props and defaults', () => {
      render(<CheckoutAction onClick={mockOnClick} />);
      
      const button = screen.getByRole('button');
      expect(button).toHaveTextContent('Checkout');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--primary');
      expect(button).toHaveClass('woo-ai-assistant-quick-action--large');
      expect(button).toHaveAttribute('data-action-type', 'checkout');
    });
  });
});

describe('Performance and Edge Cases', () => {
  it('should handle rapid clicks gracefully', async () => {
    const mockOnClick = jest.fn().mockResolvedValue({});
    render(<QuickAction type="test" label="Test" onClick={mockOnClick} />);
    
    const button = screen.getByRole('button');
    
    // Rapid clicks should not cause multiple calls
    await userEvent.click(button);
    await userEvent.click(button);
    await userEvent.click(button);
    
    // Only first click should register (others ignored during loading)
    expect(mockOnClick).toHaveBeenCalledTimes(1);
  });

  it('should handle missing onClick gracefully', () => {
    expect(() => {
      render(<QuickAction type="test" label="Test" />);
    }).not.toThrow();
    
    const button = screen.getByRole('button');
    expect(() => {
      fireEvent.click(button);
    }).not.toThrow();
  });

  it('should render with minimal props', () => {
    render(<QuickAction type="minimal" label="Minimal" onClick={jest.fn()} />);
    
    expect(screen.getByText('Minimal')).toBeInTheDocument();
    expect(screen.getByRole('button')).toBeInTheDocument();
  });
});