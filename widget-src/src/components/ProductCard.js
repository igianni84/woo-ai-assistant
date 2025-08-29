/**
 * ProductCard Component
 * 
 * Displays product information within chat messages with rich interactions
 * including add to cart, coupon application, and comparison features.
 * Fully integrated with ApiService for seamless WooCommerce integration.
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useCallback, useEffect } from 'react';
import PropTypes from 'prop-types';
import QuickAction from './QuickAction';

/**
 * ProductCard component for displaying product information in chat
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {Object} props.product - Product data object
 * @param {number} props.product.id - Product ID
 * @param {string} props.product.name - Product name
 * @param {string} props.product.price - Product price (formatted)
 * @param {string} [props.product.regularPrice] - Regular price (for sale products)
 * @param {string} [props.product.image] - Product image URL
 * @param {string} [props.product.description] - Short product description
 * @param {string} [props.product.permalink] - Product URL
 * @param {boolean} [props.product.inStock] - Stock status
 * @param {Array} [props.product.attributes] - Product attributes
 * @param {Array} [props.product.categories] - Product categories
 * @param {string} [props.conversationId] - Current conversation ID
 * @param {Function} props.onAction - Callback for product actions
 * @param {boolean} [props.showActions=true] - Whether to show action buttons
 * @param {boolean} [props.enableComparison=false] - Enable comparison mode
 * @param {boolean} [props.isCompact=false] - Compact display mode
 * @param {string} [props.size='normal'] - Card size ('compact', 'normal', 'detailed')
 * 
 * @returns {JSX.Element} ProductCard component
 * 
 * @example
 * <ProductCard
 *   product={{
 *     id: 123,
 *     name: 'Premium T-Shirt',
 *     price: '$29.99',
 *     image: '/uploads/tshirt.jpg',
 *     description: 'High quality cotton t-shirt',
 *     inStock: true
 *   }}
 *   conversationId="conv_123"
 *   onAction={(action, data) => console.log(action, data)}
 *   showActions={true}
 * />
 */
const ProductCard = ({ 
  product,
  conversationId,
  onAction,
  showActions = true,
  enableComparison = false,
  isCompact = false,
  size = 'normal'
}) => {
  const [isLoading, setIsLoading] = useState(false);
  const [actionError, setActionError] = useState(null);
  const [isSelected, setIsSelected] = useState(false);
  const [quantity, setQuantity] = useState(1);
  const [selectedVariation, setSelectedVariation] = useState(null);
  const [showCouponField, setShowCouponField] = useState(false);
  const [couponCode, setCouponCode] = useState('');

  const {
    id,
    name,
    price,
    regularPrice,
    image,
    description,
    permalink,
    inStock = true,
    attributes = [],
    categories = [],
    variations = []
  } = product;

  /**
   * Handles product actions with error handling and loading states
   * 
   * @param {string} actionType - Type of action to execute
   * @param {Object} actionData - Data for the action
   */
  const handleAction = useCallback(async (actionType, actionData) => {
    if (!onAction || !conversationId) {
      console.warn('ProductCard: Missing onAction callback or conversationId');
      return;
    }

    setIsLoading(true);
    setActionError(null);

    try {
      const result = await onAction(actionType, {
        productId: id,
        quantity,
        selectedVariation,
        conversationId,
        ...actionData
      });

      // Handle successful action
      if (result && result.success) {
        // Trigger success feedback (could be a toast notification)
        if (actionType === 'add_to_cart') {
          // Reset quantity after successful add to cart
          setQuantity(1);
        }
      }

    } catch (error) {
      console.error('ProductCard action failed:', error);
      setActionError(error.message || 'Action failed. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [onAction, conversationId, id, quantity, selectedVariation]);

  /**
   * Handles add to cart action
   */
  const handleAddToCart = useCallback(() => {
    handleAction('add_to_cart', {
      quantity,
      variation: selectedVariation
    });
  }, [handleAction, quantity, selectedVariation]);

  /**
   * Handles view product action
   */
  const handleViewProduct = useCallback(() => {
    if (permalink) {
      window.open(permalink, '_blank', 'noopener,noreferrer');
    }
    
    // Track view action
    handleAction('view_product', {
      source: 'chat_card'
    });
  }, [handleAction, permalink]);

  /**
   * Handles coupon application
   */
  const handleApplyCoupon = useCallback(async () => {
    if (!couponCode.trim()) {
      setActionError('Please enter a coupon code');
      return;
    }

    await handleAction('apply_coupon', {
      couponCode: couponCode.trim()
    });

    // Clear coupon field and hide it
    setCouponCode('');
    setShowCouponField(false);
  }, [handleAction, couponCode]);

  /**
   * Handles product comparison selection
   */
  const handleComparisonToggle = useCallback(() => {
    setIsSelected(!isSelected);
    handleAction('toggle_comparison', {
      selected: !isSelected
    });
  }, [handleAction, isSelected]);

  /**
   * Formats price display with sale price highlighting
   * 
   * @returns {JSX.Element} Formatted price element
   */
  const renderPrice = () => {
    const hasRegularPrice = regularPrice && regularPrice !== price;
    
    return (
      <div className="product-card-price">
        <span className="current-price" data-testid="current-price">
          {price}
        </span>
        {hasRegularPrice && (
          <span className="regular-price" data-testid="regular-price">
            {regularPrice}
          </span>
        )}
      </div>
    );
  };

  /**
   * Renders product image with fallback
   * 
   * @returns {JSX.Element} Image element
   */
  const renderImage = () => {
    if (!image) {
      return (
        <div className="product-card-image-placeholder" aria-hidden="true">
          <svg width="60" height="60" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" strokeWidth="2"/>
            <circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor" strokeWidth="2"/>
            <polyline points="21,15 16,10 5,21" stroke="currentColor" strokeWidth="2"/>
          </svg>
        </div>
      );
    }

    return (
      <img 
        src={image} 
        alt={name}
        className="product-card-image"
        loading="lazy"
        onError={(e) => {
          // Fallback to placeholder on image error
          e.target.style.display = 'none';
          e.target.nextSibling.style.display = 'flex';
        }}
      />
    );
  };

  /**
   * Renders product attributes
   * 
   * @returns {JSX.Element|null} Attributes element
   */
  const renderAttributes = () => {
    if (!attributes || attributes.length === 0) {
      return null;
    }

    return (
      <div className="product-card-attributes">
        {attributes.slice(0, 3).map((attr, index) => (
          <span key={index} className="attribute-tag">
            {attr.name}: {attr.value}
          </span>
        ))}
        {attributes.length > 3 && (
          <span className="attribute-more">
            +{attributes.length - 3} more
          </span>
        )}
      </div>
    );
  };

  /**
   * Renders stock status indicator
   * 
   * @returns {JSX.Element} Stock status element
   */
  const renderStockStatus = () => {
    const stockClass = inStock ? 'in-stock' : 'out-of-stock';
    const stockText = inStock ? 'In Stock' : 'Out of Stock';
    
    return (
      <span 
        className={`stock-status ${stockClass}`}
        data-testid="stock-status"
        aria-label={stockText}
      >
        <span className="stock-indicator" aria-hidden="true"></span>
        {stockText}
      </span>
    );
  };

  /**
   * Renders coupon application field
   * 
   * @returns {JSX.Element|null} Coupon field element
   */
  const renderCouponField = () => {
    if (!showCouponField) {
      return null;
    }

    return (
      <div className="coupon-field" data-testid="coupon-field">
        <input
          type="text"
          value={couponCode}
          onChange={(e) => setCouponCode(e.target.value)}
          placeholder="Enter coupon code"
          className="coupon-input"
          disabled={isLoading}
          onKeyPress={(e) => {
            if (e.key === 'Enter') {
              handleApplyCoupon();
            }
          }}
          aria-label="Coupon code"
        />
        <button
          type="button"
          onClick={handleApplyCoupon}
          disabled={isLoading || !couponCode.trim()}
          className="apply-coupon-btn"
          aria-label="Apply coupon"
        >
          Apply
        </button>
      </div>
    );
  };

  /**
   * Determines card CSS classes based on props
   * 
   * @returns {string} CSS class string
   */
  const getCardClasses = () => {
    const classes = ['product-card'];
    
    if (size === 'compact' || isCompact) {
      classes.push('product-card-compact');
    } else if (size === 'detailed') {
      classes.push('product-card-detailed');
    }
    
    if (isSelected && enableComparison) {
      classes.push('product-card-selected');
    }
    
    if (!inStock) {
      classes.push('product-card-out-of-stock');
    }
    
    if (isLoading) {
      classes.push('product-card-loading');
    }
    
    return classes.join(' ');
  };

  return (
    <article 
      className={getCardClasses()}
      data-product-id={id}
      data-testid="product-card"
      aria-labelledby={`product-title-${id}`}
      role="article"
    >
      {/* Comparison checkbox */}
      {enableComparison && (
        <div className="comparison-checkbox">
          <input
            type="checkbox"
            id={`compare-${id}`}
            checked={isSelected}
            onChange={handleComparisonToggle}
            aria-label={`Compare ${name}`}
          />
          <label htmlFor={`compare-${id}`} className="sr-only">
            Compare this product
          </label>
        </div>
      )}

      {/* Product Image */}
      <div className="product-card-image-container" onClick={handleViewProduct}>
        {renderImage()}
        <div className="product-card-image-placeholder" style={{display: 'none'}} aria-hidden="true">
          <svg width="60" height="60" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" strokeWidth="2"/>
            <circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor" strokeWidth="2"/>
            <polyline points="21,15 16,10 5,21" stroke="currentColor" strokeWidth="2"/>
          </svg>
        </div>
      </div>

      {/* Product Info */}
      <div className="product-card-info">
        <header className="product-card-header">
          <h3 
            id={`product-title-${id}`}
            className="product-card-title"
            onClick={handleViewProduct}
            role="button"
            tabIndex="0"
            onKeyPress={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                handleViewProduct();
              }
            }}
          >
            {name}
          </h3>
          {renderPrice()}
        </header>

        {/* Description */}
        {description && size !== 'compact' && (
          <p className="product-card-description">
            {description.length > 100 && size !== 'detailed' 
              ? `${description.substring(0, 100)}...` 
              : description
            }
          </p>
        )}

        {/* Attributes */}
        {size === 'detailed' && renderAttributes()}

        {/* Stock Status */}
        <div className="product-card-meta">
          {renderStockStatus()}
          
          {/* Categories */}
          {categories.length > 0 && size !== 'compact' && (
            <div className="product-categories">
              {categories.slice(0, 2).map((category, index) => (
                <span key={index} className="category-tag">
                  {category.name}
                </span>
              ))}
            </div>
          )}
        </div>

        {/* Error Display */}
        {actionError && (
          <div className="action-error" role="alert" data-testid="action-error">
            {actionError}
          </div>
        )}

        {/* Coupon Field */}
        {renderCouponField()}

        {/* Actions */}
        {showActions && (
          <div className="product-card-actions">
            <div className="primary-actions">
              <QuickAction
                type="add_to_cart"
                onClick={handleAddToCart}
                disabled={!inStock || isLoading}
                loading={isLoading}
                variant="primary"
                data-testid="add-to-cart-btn"
              >
                {isLoading ? 'Adding...' : 'Add to Cart'}
              </QuickAction>
              
              <QuickAction
                type="view_product"
                onClick={handleViewProduct}
                disabled={isLoading}
                variant="secondary"
                data-testid="view-product-btn"
              >
                View Details
              </QuickAction>
            </div>

            <div className="secondary-actions">
              <QuickAction
                type="apply_coupon"
                onClick={() => setShowCouponField(!showCouponField)}
                disabled={isLoading}
                variant="tertiary"
                size="small"
                data-testid="coupon-toggle-btn"
              >
                {showCouponField ? 'Cancel' : 'Apply Coupon'}
              </QuickAction>
              
              {/* Quantity selector for detailed view */}
              {size === 'detailed' && inStock && (
                <div className="quantity-selector">
                  <label htmlFor={`quantity-${id}`} className="sr-only">
                    Quantity
                  </label>
                  <select
                    id={`quantity-${id}`}
                    value={quantity}
                    onChange={(e) => setQuantity(parseInt(e.target.value))}
                    disabled={isLoading}
                    className="quantity-input"
                  >
                    {[...Array(10)].map((_, i) => (
                      <option key={i + 1} value={i + 1}>
                        {i + 1}
                      </option>
                    ))}
                  </select>
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Loading Overlay */}
      {isLoading && (
        <div className="product-card-loading-overlay" aria-hidden="true">
          <div className="loading-spinner"></div>
        </div>
      )}
    </article>
  );
};

ProductCard.propTypes = {
  /**
   * Product data object
   */
  product: PropTypes.shape({
    /**
     * Unique product ID
     */
    id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    
    /**
     * Product name/title
     */
    name: PropTypes.string.isRequired,
    
    /**
     * Formatted price string
     */
    price: PropTypes.string.isRequired,
    
    /**
     * Regular price for sale products
     */
    regularPrice: PropTypes.string,
    
    /**
     * Product image URL
     */
    image: PropTypes.string,
    
    /**
     * Short product description
     */
    description: PropTypes.string,
    
    /**
     * Product permalink URL
     */
    permalink: PropTypes.string,
    
    /**
     * Product stock status
     */
    inStock: PropTypes.bool,
    
    /**
     * Product attributes array
     */
    attributes: PropTypes.arrayOf(PropTypes.shape({
      name: PropTypes.string.isRequired,
      value: PropTypes.string.isRequired
    })),
    
    /**
     * Product categories array
     */
    categories: PropTypes.arrayOf(PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
      name: PropTypes.string.isRequired
    })),
    
    /**
     * Product variations array
     */
    variations: PropTypes.array
  }).isRequired,
  
  /**
   * Current conversation ID
   */
  conversationId: PropTypes.string,
  
  /**
   * Callback function for handling product actions
   */
  onAction: PropTypes.func.isRequired,
  
  /**
   * Whether to show action buttons
   */
  showActions: PropTypes.bool,
  
  /**
   * Enable comparison mode
   */
  enableComparison: PropTypes.bool,
  
  /**
   * Compact display mode
   */
  isCompact: PropTypes.bool,
  
  /**
   * Card size variant
   */
  size: PropTypes.oneOf(['compact', 'normal', 'detailed'])
};

ProductCard.defaultProps = {
  conversationId: null,
  showActions: true,
  enableComparison: false,
  isCompact: false,
  size: 'normal'
};

export default ProductCard;