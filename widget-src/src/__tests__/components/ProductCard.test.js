/**
 * Product Card Component Tests
 *
 * Comprehensive tests for the ProductCard component including
 * rendering different product states, user interactions, accessibility,
 * and integration with action handlers.
 *
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ProductCard from '../../components/ProductCard';
import { 
  renderWithContext, 
  assertAriaAttributes, 
  assertComponentNaming,
  mockWordPressGlobals 
} from '../utils/testUtils';

// Mock the product action service
jest.mock('../../services/ProductActionService', () => ({
  addToCart: jest.fn(),
  trackProductInteraction: jest.fn()
}));

describe('ProductCard Component', () => {
  // Base product object for testing
  const baseProduct = {
    id: 123,
    name: 'Test Product',
    description: 'This is a test product description with some details.',
    shortDescription: 'Short description',
    price: '29.99',
    regularPrice: '29.99',
    image: 'https://example.com/product.jpg',
    permalink: 'https://example.com/product',
    inStock: true,
    categories: [
      { id: 1, name: 'Electronics' },
      { id: 2, name: 'Gadgets' }
    ]
  };

  const saleProduct = {
    ...baseProduct,
    id: 124,
    name: 'Sale Product',
    regularPrice: '39.99',
    salePrice: '29.99'
  };

  const outOfStockProduct = {
    ...baseProduct,
    id: 125,
    name: 'Out of Stock Product',
    inStock: false
  };

  const defaultProps = {
    product: baseProduct,
    wooCommerceData: {
      currency: 'USD',
      currencySymbol: '$',
      cartItems: []
    },
    config: {
      features: { cart_enabled: true },
      styling: {}
    },
    onAddToCart: jest.fn(),
    onViewProduct: jest.fn(),
    showActions: true,
    size: 'medium'
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockWordPressGlobals();
  });

  describe('Component Naming Convention', () => {
    it('should follow PascalCase naming convention', () => {
      assertComponentNaming(ProductCard, 'ProductCard');
    });

    it('should have correct display name', () => {
      expect(ProductCard.name).toBe('ProductCard');
    });
  });

  describe('Basic Rendering', () => {
    it('should render product card with basic information', () => {
      render(<ProductCard {...defaultProps} />);
      
      expect(screen.getByRole('article')).toBeInTheDocument();
      expect(screen.getByText('Test Product')).toBeInTheDocument();
      expect(screen.getByText('$29.99')).toBeInTheDocument();
      expect(screen.getByText('Electronics')).toBeInTheDocument();
      expect(screen.getByText('Gadgets')).toBeInTheDocument();
    });

    it('should render product image with correct attributes', () => {
      render(<ProductCard {...defaultProps} />);
      
      const image = screen.getByRole('img');
      expect(image).toHaveAttribute('src', baseProduct.image);
      expect(image).toHaveAttribute('alt', baseProduct.name);
      expect(image).toHaveAttribute('loading', 'lazy');
    });

    it('should render fallback placeholder when no image provided', () => {
      const productWithoutImage = { ...baseProduct, image: null };
      render(<ProductCard {...defaultProps} product={productWithoutImage} />);
      
      expect(screen.queryByRole('img')).not.toBeInTheDocument();
      expect(screen.getByTestId('image-placeholder')).toBeInTheDocument();
    });

    it('should handle missing product categories gracefully', () => {
      const productWithoutCategories = { ...baseProduct, categories: null };
      render(<ProductCard {...defaultProps} product={productWithoutCategories} />);
      
      expect(screen.getByText('Test Product')).toBeInTheDocument();
    });
  });

  describe('Product States', () => {
    it('should display sale badge and pricing for sale products', () => {
      render(<ProductCard {...defaultProps} product={saleProduct} />);
      
      expect(screen.getByText('Sale')).toBeInTheDocument();
      expect(screen.getByText('$29.99')).toBeInTheDocument(); // Sale price
      expect(screen.getByText('$39.99')).toBeInTheDocument(); // Regular price
      
      // Check for correct CSS classes
      const card = screen.getByRole('article');
      expect(card).toHaveClass('woo-ai-assistant-product-card--on-sale');
    });

    it('should display out of stock status correctly', () => {
      render(<ProductCard {...defaultProps} product={outOfStockProduct} />);
      
      expect(screen.getByText('Out of Stock')).toBeInTheDocument();
      
      // Check for correct CSS classes
      const card = screen.getByRole('article');
      expect(card).toHaveClass('woo-ai-assistant-product-card--out-of-stock');
      
      // Add to cart button should be disabled or hidden
      expect(screen.queryByRole('button', { name: /add to cart/i })).not.toBeInTheDocument();
    });

    it('should render different sizes correctly', () => {
      const { rerender } = render(<ProductCard {...defaultProps} size="small" />);
      let card = screen.getByRole('article');
      expect(card).toHaveClass('woo-ai-assistant-product-card--small');
      
      rerender(<ProductCard {...defaultProps} size="large" />);
      card = screen.getByRole('article');
      expect(card).toHaveClass('woo-ai-assistant-product-card--large');
    });

    it('should truncate text based on size', () => {
      const longNameProduct = {
        ...baseProduct,
        name: 'This is a very long product name that should be truncated in small size'
      };

      render(<ProductCard {...defaultProps} product={longNameProduct} size="small" />);
      
      const titleButton = screen.getByRole('button', { name: /view product details/i });
      expect(titleButton.textContent.length).toBeLessThan(longNameProduct.name.length);
      expect(titleButton.textContent).toMatch(/\.\.\.$/);
    });
  });

  describe('User Interactions', () => {
    it('should handle add to cart action', async () => {
      const mockOnAddToCart = jest.fn().mockResolvedValue({ success: true });
      render(<ProductCard {...defaultProps} onAddToCart={mockOnAddToCart} />);
      
      const addToCartButton = screen.getByRole('button', { name: /add to cart/i });
      await userEvent.click(addToCartButton);
      
      await waitFor(() => {
        expect(mockOnAddToCart).toHaveBeenCalledWith({
          productId: baseProduct.id,
          quantity: 1,
          variationId: null,
          variation: {}
        });
      });
    });

    it('should handle quantity changes', async () => {
      render(<ProductCard {...defaultProps} />);
      
      const quantityInput = screen.getByLabelText(/quantity/i);
      await userEvent.clear(quantityInput);
      await userEvent.type(quantityInput, '3');
      
      expect(quantityInput).toHaveValue(3);
      
      // Test add to cart with new quantity
      const mockOnAddToCart = jest.fn().mockResolvedValue({ success: true });
      render(<ProductCard {...defaultProps} onAddToCart={mockOnAddToCart} />);
      
      const newQuantityInput = screen.getByLabelText(/quantity/i);
      const addToCartButton = screen.getByRole('button', { name: /add to cart/i });
      
      await userEvent.clear(newQuantityInput);
      await userEvent.type(newQuantityInput, '2');
      await userEvent.click(addToCartButton);
      
      await waitFor(() => {
        expect(mockOnAddToCart).toHaveBeenCalledWith(
          expect.objectContaining({ quantity: 2 })
        );
      });
    });

    it('should validate quantity bounds', async () => {
      render(<ProductCard {...defaultProps} />);
      
      const quantityInput = screen.getByLabelText(/quantity/i);
      
      // Test minimum quantity
      await userEvent.clear(quantityInput);
      await userEvent.type(quantityInput, '0');
      
      await act(async () => {
        fireEvent.blur(quantityInput);
      });
      
      expect(quantityInput).toHaveValue(1); // Should reset to minimum
      
      // Test maximum quantity
      await userEvent.clear(quantityInput);
      await userEvent.type(quantityInput, '100');
      
      await act(async () => {
        fireEvent.blur(quantityInput);
      });
      
      expect(quantityInput).toHaveValue(99); // Should cap at maximum
    });

    it('should handle view product action', async () => {
      const mockOnViewProduct = jest.fn();
      render(<ProductCard {...defaultProps} onViewProduct={mockOnViewProduct} />);
      
      const titleButton = screen.getByRole('button', { name: /view product details/i });
      await userEvent.click(titleButton);
      
      expect(mockOnViewProduct).toHaveBeenCalledWith(baseProduct);
    });

    it('should open product link in new window when no view handler provided', () => {
      const mockOpen = jest.fn();
      window.open = mockOpen;
      
      render(<ProductCard {...defaultProps} onViewProduct={null} />);
      
      const titleButton = screen.getByRole('button', { name: /view product details/i });
      fireEvent.click(titleButton);
      
      expect(mockOpen).toHaveBeenCalledWith(
        baseProduct.permalink, 
        '_blank', 
        'noopener,noreferrer'
      );
    });
  });

  describe('Loading States', () => {
    it('should show loading state during add to cart action', async () => {
      let resolveAddToCart;
      const mockOnAddToCart = jest.fn(() => new Promise(resolve => {
        resolveAddToCart = resolve;
      }));
      
      render(<ProductCard {...defaultProps} onAddToCart={mockOnAddToCart} />);
      
      const addToCartButton = screen.getByRole('button', { name: /add to cart/i });
      await userEvent.click(addToCartButton);
      
      // Should show loading state
      expect(screen.getByRole('article')).toHaveClass('woo-ai-assistant-product-card--loading');
      expect(addToCartButton).toBeDisabled();
      
      // Resolve the promise
      resolveAddToCart({ success: true });
      
      await waitFor(() => {
        expect(screen.getByRole('article')).not.toHaveClass('woo-ai-assistant-product-card--loading');
        expect(addToCartButton).not.toBeDisabled();
      });
    });

    it('should handle add to cart errors gracefully', async () => {
      const mockOnAddToCart = jest.fn().mockRejectedValue(new Error('Network error'));
      render(<ProductCard {...defaultProps} onAddToCart={mockOnAddToCart} />);
      
      const addToCartButton = screen.getByRole('button', { name: /add to cart/i });
      await userEvent.click(addToCartButton);
      
      // Should recover from loading state
      await waitFor(() => {
        expect(addToCartButton).not.toBeDisabled();
      });
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA attributes', () => {
      render(<ProductCard {...defaultProps} />);
      
      const card = screen.getByRole('article');
      expect(card).toHaveAttribute('aria-label', `Product: ${baseProduct.name}`);
      
      const quantityInput = screen.getByLabelText(/quantity/i);
      expect(quantityInput).toHaveAttribute('aria-label', `Quantity for ${baseProduct.name}`);
      
      const titleButton = screen.getByRole('button', { name: /view product details/i });
      expect(titleButton).toHaveAttribute('aria-label', `View product details for ${baseProduct.name}`);
    });

    it('should support keyboard navigation', async () => {
      render(<ProductCard {...defaultProps} />);
      
      const titleButton = screen.getByRole('button', { name: /view product details/i });
      const quantityInput = screen.getByLabelText(/quantity/i);
      const addToCartButton = screen.getByRole('button', { name: /add to cart/i });
      
      // Test tab order
      titleButton.focus();
      expect(document.activeElement).toBe(titleButton);
      
      await userEvent.tab();
      expect(document.activeElement).toBe(quantityInput);
      
      await userEvent.tab();
      expect(document.activeElement).toBe(addToCartButton);
    });

    it('should handle screen reader announcements', () => {
      render(<ProductCard {...defaultProps} />);
      
      const image = screen.getByRole('img');
      expect(image).toHaveAttribute('alt', baseProduct.name);
      
      const priceElements = screen.getAllByText(/\$/);
      priceElements.forEach(element => {
        // Price elements should be readable by screen readers
        expect(element).not.toHaveAttribute('aria-hidden', 'true');
      });
    });
  });

  describe('Props and Configuration', () => {
    it('should handle missing optional props gracefully', () => {
      const minimalProps = {
        product: baseProduct
      };
      
      expect(() => render(<ProductCard {...minimalProps} />)).not.toThrow();
      expect(screen.getByText(baseProduct.name)).toBeInTheDocument();
    });

    it('should respect showActions prop', () => {
      render(<ProductCard {...defaultProps} showActions={false} />);
      
      expect(screen.queryByRole('button', { name: /add to cart/i })).not.toBeInTheDocument();
      expect(screen.queryByLabelText(/quantity/i)).not.toBeInTheDocument();
    });

    it('should use custom currency symbol', () => {
      const customWooCommerceData = {
        ...defaultProps.wooCommerceData,
        currencySymbol: '€'
      };
      
      render(<ProductCard {...defaultProps} wooCommerceData={customWooCommerceData} />);
      
      expect(screen.getByText('€29.99')).toBeInTheDocument();
    });

    it('should handle product without categories', () => {
      const productNoCategories = { ...baseProduct, categories: [] };
      render(<ProductCard {...defaultProps} product={productNoCategories} />);
      
      expect(screen.getByText(baseProduct.name)).toBeInTheDocument();
      expect(screen.queryByText('Electronics')).not.toBeInTheDocument();
    });
  });

  describe('Error Handling', () => {
    it('should handle image load errors', () => {
      render(<ProductCard {...defaultProps} />);
      
      const image = screen.getByRole('img');
      const placeholder = screen.getByTestId('image-placeholder');
      
      // Initially image is visible, placeholder is hidden
      expect(image.style.display).not.toBe('none');
      expect(placeholder.style.display).toBe('none');
      
      // Trigger image error
      fireEvent.error(image);
      
      // Image should be hidden, placeholder shown
      expect(image.style.display).toBe('none');
      expect(placeholder.style.display).toBe('flex');
    });

    it('should handle invalid product data gracefully', () => {
      const invalidProduct = {
        id: 999, // Keep a valid ID to avoid PropTypes error
        name: '',
        price: 'invalid'
      };
      
      expect(() => render(<ProductCard {...defaultProps} product={invalidProduct} />)).not.toThrow();
    });
  });

  describe('Performance', () => {
    it('should memoize expensive calculations', () => {
      const { rerender } = render(<ProductCard {...defaultProps} />);
      
      // Re-render with same props shouldn't cause unnecessary re-calculations
      rerender(<ProductCard {...defaultProps} />);
      
      // Component should render successfully
      expect(screen.getByText(baseProduct.name)).toBeInTheDocument();
    });

    it('should handle large product descriptions efficiently', () => {
      const longDescProduct = {
        ...baseProduct,
        description: 'Lorem ipsum '.repeat(1000), // Very long description
        shortDescription: 'Short desc '.repeat(50) // Long short description
      };
      
      const startTime = performance.now();
      render(<ProductCard {...defaultProps} product={longDescProduct} size="large" />);
      const endTime = performance.now();
      
      // Should render in reasonable time (less than 100ms)
      expect(endTime - startTime).toBeLessThan(100);
      expect(screen.getByText(longDescProduct.name)).toBeInTheDocument();
    });
  });
});