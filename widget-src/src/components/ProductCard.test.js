/**
 * ProductCard Component Tests
 * 
 * Comprehensive test suite for the ProductCard component covering
 * rendering, interactions, accessibility, and integration scenarios.
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
import ProductCard from './ProductCard';

// Mock the QuickAction component
jest.mock('./QuickAction', () => {
  return function MockQuickAction({ children, onClick, type, disabled, loading, ...props }) {
    return (
      <button 
        data-testid={`quick-action-${type}`}
        onClick={onClick}
        disabled={disabled || loading}
        {...props}
      >
{children}
      </button>
    );
  };
});

describe('ProductCard', () => {
  const mockProduct = {
    id: 123,
    name: 'Test Product',
    price: '$29.99',
    regularPrice: '$39.99',
    image: '/test-image.jpg',
    description: 'This is a test product description',
    permalink: 'https://example.com/product/test',
    inStock: true,
    attributes: [
      { name: 'Color', value: 'Blue' },
      { name: 'Size', value: 'Large' }
    ],
    categories: [
      { id: 1, name: 'Clothing' },
      { id: 2, name: 'T-Shirts' }
    ]
  };

  const defaultProps = {
    product: mockProduct,
    conversationId: 'conv_123',
    onAction: jest.fn()
  };

  beforeEach(() => {
    jest.clearAllMocks();
    // Mock window.open for view product tests
    window.open = jest.fn();
  });

  describe('Component Rendering', () => {
    it('should render product card with all basic information', () => {
      render(<ProductCard {...defaultProps} />);
      
      expect(screen.getByTestId('product-card')).toBeInTheDocument();
      expect(screen.getByText('Test Product')).toBeInTheDocument();
      expect(screen.getByTestId('current-price')).toHaveTextContent('$29.99');
      expect(screen.getByTestId('regular-price')).toHaveTextContent('$39.99');
      expect(screen.getByText('This is a test product description')).toBeInTheDocument();
    });

    it('should render product image with correct attributes', () => {
      render(<ProductCard {...defaultProps} />);
      
      const image = screen.getByRole('img');
      expect(image).toHaveAttribute('src', '/test-image.jpg');
      expect(image).toHaveAttribute('alt', 'Test Product');
      expect(image).toHaveAttribute('loading', 'lazy');
    });

    it('should render placeholder when no image provided', () => {
      const productWithoutImage = { ...mockProduct, image: null };
      render(<ProductCard {...defaultProps} product={productWithoutImage} />);
      
      expect(screen.queryByRole('img')).not.toBeInTheDocument();
      expect(screen.getByText('Test Product')).toBeInTheDocument();
    });

    it('should render stock status correctly', () => {
      render(<ProductCard {...defaultProps} />);
      
      const stockStatus = screen.getByTestId('stock-status');
      expect(stockStatus).toHaveTextContent('In Stock');
      expect(stockStatus).toHaveClass('in-stock');
    });

    it('should render out of stock status correctly', () => {
      const outOfStockProduct = { ...mockProduct, inStock: false };
      render(<ProductCard {...defaultProps} product={outOfStockProduct} />);
      
      const stockStatus = screen.getByTestId('stock-status');
      expect(stockStatus).toHaveTextContent('Out of Stock');
      expect(stockStatus).toHaveClass('out-of-stock');
    });
  });

  describe('Component Variants', () => {
    it('should apply compact styling when size is compact', () => {
      render(<ProductCard {...defaultProps} size="compact" />);
      
      const card = screen.getByTestId('product-card');
      expect(card).toHaveClass('product-card-compact');
    });

    it('should apply detailed styling when size is detailed', () => {
      render(<ProductCard {...defaultProps} size="detailed" />);
      
      const card = screen.getByTestId('product-card');
      expect(card).toHaveClass('product-card-detailed');
    });

    it('should hide description in compact mode', () => {
      render(<ProductCard {...defaultProps} size="compact" />);
      
      expect(screen.queryByText('This is a test product description')).not.toBeInTheDocument();
    });

    it('should show quantity selector in detailed mode', () => {
      render(<ProductCard {...defaultProps} size="detailed" />);
      
      expect(screen.getByLabelText('Quantity')).toBeInTheDocument();
    });

    it('should hide actions when showActions is false', () => {
      render(<ProductCard {...defaultProps} showActions={false} />);
      
      expect(screen.queryByTestId('add-to-cart-btn')).not.toBeInTheDocument();
      expect(screen.queryByTestId('view-product-btn')).not.toBeInTheDocument();
    });
  });

  describe('Product Actions', () => {
    it('should handle add to cart action', async () => {
      const user = userEvent.setup();
      render(<ProductCard {...defaultProps} />);
      
      const addToCartBtn = screen.getByTestId('add-to-cart-btn');
      await user.click(addToCartBtn);
      
      expect(defaultProps.onAction).toHaveBeenCalledWith('add_to_cart', {
        productId: 123,
        quantity: 1,
        selectedVariation: null,
        conversationId: 'conv_123',
        variation: null
      });
    });

    it('should handle view product action', async () => {
      const user = userEvent.setup();
      render(<ProductCard {...defaultProps} />);
      
      const viewProductBtn = screen.getByTestId('view-product-btn');
      await user.click(viewProductBtn);
      
      expect(window.open).toHaveBeenCalledWith(
        'https://example.com/product/test', 
        '_blank', 
        'noopener,noreferrer'
      );
      expect(defaultProps.onAction).toHaveBeenCalledWith('view_product', {
        productId: 123,
        quantity: 1,
        selectedVariation: null,
        conversationId: 'conv_123',
        source: 'chat_card'
      });
    });

    it('should handle coupon toggle action', async () => {
      const user = userEvent.setup();
      render(<ProductCard {...defaultProps} />);
      
      const couponBtn = screen.getByTestId('coupon-toggle-btn');
      await user.click(couponBtn);
      
      expect(screen.getByTestId('coupon-field')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('Enter coupon code')).toBeInTheDocument();
    });

    it('should apply coupon when code is entered', async () => {
      const user = userEvent.setup();
      render(<ProductCard {...defaultProps} />);
      
      // Open coupon field
      const couponBtn = screen.getByTestId('coupon-toggle-btn');
      await user.click(couponBtn);
      
      // Wait for coupon field to appear
      await waitFor(() => {
        expect(screen.getByTestId('coupon-field')).toBeInTheDocument();
      });
      
      // Enter coupon code
      const couponInput = screen.getByPlaceholderText('Enter coupon code');
      fireEvent.change(couponInput, { target: { value: 'SAVE20' } });
      
      // Wait for the input value to be updated
      await waitFor(() => {
        expect(couponInput).toHaveValue('SAVE20');
      });
      
      // Apply coupon
      const applyBtn = screen.getByLabelText('Apply coupon');
      
      // Wait for the button to be enabled (since it's disabled when couponCode is empty)
      await waitFor(() => {
        expect(applyBtn).not.toBeDisabled();
      });
      
      await user.click(applyBtn);
      
      expect(defaultProps.onAction).toHaveBeenCalledWith('apply_coupon', {
        productId: 123,
        quantity: 1,
        selectedVariation: null,
        conversationId: 'conv_123',
        couponCode: 'SAVE20'
      });
    });

    it('should disable add to cart when out of stock', () => {
      const outOfStockProduct = { ...mockProduct, inStock: false };
      render(<ProductCard {...defaultProps} product={outOfStockProduct} />);
      
      const addToCartBtn = screen.getByTestId('add-to-cart-btn');
      expect(addToCartBtn).toBeDisabled();
    });
  });

  describe('Error Handling', () => {
    it('should display error message when action fails', async () => {
      const user = userEvent.setup();
      const failingOnAction = jest.fn().mockRejectedValue(new Error('Network error'));
      render(<ProductCard {...defaultProps} onAction={failingOnAction} />);
      
      const addToCartBtn = screen.getByTestId('add-to-cart-btn');
      await user.click(addToCartBtn);
      
      await waitFor(() => {
        expect(screen.getByTestId('action-error')).toBeInTheDocument();
        expect(screen.getByTestId('action-error')).toHaveTextContent('Network error');
      });
    });

    it('should show loading state during action execution', async () => {
      const user = userEvent.setup();
      let resolvePromise;
      const slowOnAction = jest.fn(() => new Promise(resolve => { resolvePromise = resolve; }));
      
      render(<ProductCard {...defaultProps} onAction={slowOnAction} />);
      
      const addToCartBtn = screen.getByTestId('add-to-cart-btn');
      await user.click(addToCartBtn);
      
      expect(screen.getByText('Adding...')).toBeInTheDocument();
      expect(addToCartBtn).toBeDisabled();
      
      resolvePromise && resolvePromise();
    });

    it('should handle image load error gracefully', () => {
      render(<ProductCard {...defaultProps} />);
      
      const image = screen.getByRole('img');
      fireEvent.error(image);
      
      // Image should be hidden and placeholder should be shown
      expect(image.style.display).toBe('none');
    });
  });

  describe('Comparison Mode', () => {
    it('should render comparison checkbox when enabled', () => {
      render(<ProductCard {...defaultProps} enableComparison={true} />);
      
      const checkbox = screen.getByLabelText('Compare Test Product');
      expect(checkbox).toBeInTheDocument();
      expect(checkbox).toHaveAttribute('type', 'checkbox');
    });

    it('should handle comparison toggle', async () => {
      const user = userEvent.setup();
      render(<ProductCard {...defaultProps} enableComparison={true} />);
      
      const checkbox = screen.getByLabelText('Compare Test Product');
      await user.click(checkbox);
      
      expect(defaultProps.onAction).toHaveBeenCalledWith('toggle_comparison', {
        productId: 123,
        quantity: 1,
        selectedVariation: null,
        conversationId: 'conv_123',
        selected: true
      });
    });

    it('should apply selected styling when product is selected', async () => {
      const user = userEvent.setup();
      render(<ProductCard {...defaultProps} enableComparison={true} />);
      
      const checkbox = screen.getByLabelText('Compare Test Product');
      await user.click(checkbox);
      
      const card = screen.getByTestId('product-card');
      expect(card).toHaveClass('product-card-selected');
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA labels and roles', () => {
      render(<ProductCard {...defaultProps} />);
      
      const card = screen.getByTestId('product-card');
      expect(card).toHaveAttribute('role', 'article');
      expect(card).toHaveAttribute('aria-labelledby', 'product-title-123');
      
      const title = screen.getByText('Test Product');
      expect(title).toHaveAttribute('id', 'product-title-123');
    });

    it('should support keyboard navigation for product title', async () => {
      const user = userEvent.setup();
      render(<ProductCard {...defaultProps} />);
      
      const title = screen.getByText('Test Product');
      expect(title).toHaveAttribute('tabIndex', '0');
      expect(title).toHaveAttribute('role', 'button');
      
      title.focus();
      fireEvent.keyPress(title, { key: 'Enter', charCode: 13 });
      
      expect(window.open).toHaveBeenCalled();
    });

    it('should have screen reader friendly stock status', () => {
      render(<ProductCard {...defaultProps} />);
      
      const stockStatus = screen.getByTestId('stock-status');
      expect(stockStatus).toHaveAttribute('aria-label', 'In Stock');
    });

    it('should have proper form labels', async () => {
      const user = userEvent.setup();
      render(<ProductCard {...defaultProps} size="detailed" />);
      
      const quantitySelect = screen.getByLabelText('Quantity');
      expect(quantitySelect).toBeInTheDocument();
    });
  });

  describe('PropTypes and Default Props', () => {
    it('should handle missing optional props gracefully', () => {
      const minimalProduct = {
        id: 123,
        name: 'Test Product',
        price: '$29.99'
      };
      
      render(<ProductCard product={minimalProduct} onAction={jest.fn()} />);
      
      expect(screen.getByText('Test Product')).toBeInTheDocument();
      expect(screen.getByText('$29.99')).toBeInTheDocument();
    });

    it('should use default props when not provided', () => {
      render(<ProductCard {...defaultProps} />);
      
      const card = screen.getByTestId('product-card');
      expect(card).toHaveClass('product-card');
      expect(card).not.toHaveClass('product-card-compact');
      expect(screen.getByTestId('add-to-cart-btn')).toBeInTheDocument();
    });
  });

  describe('Component Integration', () => {
    it('should integrate properly with conversation context', () => {
      render(<ProductCard {...defaultProps} conversationId="test-conv" />);
      
      const card = screen.getByTestId('product-card');
      expect(card).toHaveAttribute('data-product-id', '123');
    });

    it('should handle missing conversation ID gracefully', async () => {
      const user = userEvent.setup();
      const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
      
      render(<ProductCard {...defaultProps} conversationId={null} />);
      
      const addToCartBtn = screen.getByTestId('add-to-cart-btn');
      await user.click(addToCartBtn);
      
      expect(consoleSpy).toHaveBeenCalledWith(
        'ProductCard: Missing onAction callback or conversationId'
      );
      
      consoleSpy.mockRestore();
    });

    it('should handle missing onAction callback gracefully', async () => {
      const user = userEvent.setup();
      const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
      
      render(<ProductCard {...defaultProps} onAction={null} />);
      
      const addToCartBtn = screen.getByTestId('add-to-cart-btn');
      await user.click(addToCartBtn);
      
      expect(consoleSpy).toHaveBeenCalledWith(
        'ProductCard: Missing onAction callback or conversationId'
      );
      
      consoleSpy.mockRestore();
    });
  });

  describe('Performance and Optimization', () => {
    it('should not re-render unnecessarily when props do not change', () => {
      const { rerender } = render(<ProductCard {...defaultProps} />);
      
      const initialRender = screen.getByTestId('product-card');
      
      // Re-render with same props
      rerender(<ProductCard {...defaultProps} />);
      
      const afterRerender = screen.getByTestId('product-card');
      expect(initialRender).toBe(afterRerender);
    });

    it('should handle large descriptions efficiently', () => {
      const longDescription = 'A'.repeat(500);
      const productWithLongDesc = { 
        ...mockProduct, 
        description: longDescription 
      };
      
      render(<ProductCard {...defaultProps} product={productWithLongDesc} />);
      
      // Should truncate long descriptions in normal mode
      expect(screen.getByText(/A{100}\.\.\.$/)).toBeInTheDocument();
    });
  });
});

describe('ProductCard Naming Conventions', () => {
  it('should follow PascalCase for component name', () => {
    expect(ProductCard.name).toBe('ProductCard');
  });

  it('should use camelCase for all props', () => {
    const propNames = Object.keys(ProductCard.propTypes);
    propNames.forEach(propName => {
      // Skip props that are objects (like product.inStock)
      if (!propName.includes('.')) {
        expect(propName).toMatch(/^[a-z][a-zA-Z0-9]*$/);
      }
    });
  });
});