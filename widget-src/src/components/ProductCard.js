/**
 * Product Card Component
 *
 * Displays product information in the chat interface including image,
 * title, price, description, and action buttons. Features responsive design,
 * accessibility support, and integration with WooCommerce cart actions.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useCallback } from 'react';
import PropTypes from 'prop-types';
import QuickAction from './QuickAction';

/**
 * Product Card Component
 *
 * @component
 * @param {Object} props - Component properties
 * @param {Object} props.product - Product object
 * @param {number} props.product.id - Product ID
 * @param {string} props.product.name - Product name
 * @param {string} props.product.description - Product description
 * @param {string} props.product.shortDescription - Product short description
 * @param {string} props.product.price - Product price (formatted)
 * @param {string} props.product.regularPrice - Product regular price
 * @param {string} props.product.salePrice - Product sale price
 * @param {string} props.product.image - Product image URL
 * @param {string} props.product.permalink - Product page URL
 * @param {boolean} props.product.inStock - Whether product is in stock
 * @param {Object} props.product.variations - Product variations if any
 * @param {Array} props.product.categories - Product categories
 * @param {Object} props.wooCommerceData - WooCommerce context data
 * @param {Object} props.config - Widget configuration
 * @param {Function} props.onAddToCart - Add to cart handler
 * @param {Function} props.onViewProduct - View product handler
 * @param {boolean} props.showActions - Whether to show action buttons
 * @param {string} props.size - Card size variant ('small', 'medium', 'large')
 * @returns {JSX.Element} Product card component
 */
const ProductCard = ({
  product,
  wooCommerceData = {},
  config = {},
  onAddToCart,
  onViewProduct,
  showActions = true,
  size = 'medium'
}) => {
  // Local state
  const [isLoading, setIsLoading] = useState(false);
  const [quantity, setQuantity] = useState(1);
  const [selectedVariation, setSelectedVariation] = useState(null);

  // Handle add to cart action
  const handleAddToCart = useCallback(async () => {
    if (!onAddToCart || isLoading || !product.inStock) {
      return;
    }

    setIsLoading(true);

    try {
      await onAddToCart({
        productId: product.id,
        quantity,
        variationId: selectedVariation?.id || null,
        variation: selectedVariation?.attributes || {}
      });
    } catch (error) {
      // Error handling is done in parent component
    } finally {
      setIsLoading(false);
    }
  }, [onAddToCart, isLoading, product.inStock, product.id, quantity, selectedVariation]);

  // Handle view product action
  const handleViewProduct = useCallback(() => {
    if (onViewProduct) {
      onViewProduct(product);
    } else if (product.permalink) {
      window.open(product.permalink, '_blank', 'noopener,noreferrer');
    }
  }, [onViewProduct, product]);

  // Handle quantity change
  const handleQuantityChange = useCallback((e) => {
    const newQuantity = Math.max(1, parseInt(e.target.value) || 1);
    setQuantity(newQuantity);
  }, []);

  // Check if product is on sale
  const isOnSale = product.salePrice && product.regularPrice &&
                   parseFloat(product.salePrice) < parseFloat(product.regularPrice);

  // Get price display
  const getPriceDisplay = () => {
    if (isOnSale) {
      return (
        <div className="woo-ai-assistant-product-price woo-ai-assistant-product-price--sale">
          <span className="woo-ai-assistant-product-price-sale">
            {wooCommerceData.currencySymbol || '$'}{product.salePrice}
          </span>
          <span className="woo-ai-assistant-product-price-regular">
            {wooCommerceData.currencySymbol || '$'}{product.regularPrice}
          </span>
        </div>
      );
    }
    return (
      <div className="woo-ai-assistant-product-price">
        <span className="woo-ai-assistant-product-price-current">
          {wooCommerceData.currencySymbol || '$'}{product.price || product.regularPrice}
        </span>
      </div>
    );
  };

  // Get stock status display
  const getStockStatus = () => {
    if (!product.inStock) {
      return (
        <div className="woo-ai-assistant-product-stock woo-ai-assistant-product-stock--out">
          Out of Stock
        </div>
      );
    }
    return null;
  };

  // Get card classes
  const getCardClasses = () => {
    const baseClass = 'woo-ai-assistant-product-card';
    const classes = [baseClass, `${baseClass}--${size}`];

    if (isOnSale) classes.push(`${baseClass}--on-sale`);
    if (!product.inStock) classes.push(`${baseClass}--out-of-stock`);
    if (isLoading) classes.push(`${baseClass}--loading`);

    return classes.join(' ');
  };

  // Truncate text helper
  const truncateText = (text, maxLength) => {
    if (!text || text.length <= maxLength) return text;
    return `${text.substring(0, maxLength)  }...`;
  };

  return (
    <div
      className={getCardClasses()}
      role="article"
      aria-label={`Product: ${product.name}`}
    >
      {/* Sale Badge */}
      {isOnSale && (
        <div className="woo-ai-assistant-product-badge woo-ai-assistant-product-badge--sale">
          <SaleIcon />
          <span>Sale</span>
        </div>
      )}

      {/* Product Image */}
      <div className="woo-ai-assistant-product-image-container">
        {product.image ? (
          <img
            src={product.image}
            alt={product.name}
            className="woo-ai-assistant-product-image"
            loading="lazy"
            onError={(e) => {
              e.target.style.display = 'none';
              e.target.nextSibling.style.display = 'flex';
            }}
          />
        ) : null}

        {/* Fallback placeholder */}
        <div
          className="woo-ai-assistant-product-image-placeholder"
          style={{ display: product.image ? 'none' : 'flex' }}
          data-testid="image-placeholder"
        >
          <ImagePlaceholderIcon />
        </div>
      </div>

      {/* Product Info */}
      <div className="woo-ai-assistant-product-info">
        {/* Product Title */}
        <h3 className="woo-ai-assistant-product-title">
          <button
            className="woo-ai-assistant-product-title-link"
            onClick={handleViewProduct}
            aria-label={`View product details for ${product.name}`}
          >
            {size === 'small'
              ? truncateText(product.name, 40)
              : product.name
            }
          </button>
        </h3>

        {/* Product Categories */}
        {product.categories && product.categories.length > 0 && (
          <div className="woo-ai-assistant-product-categories">
            {product.categories.slice(0, 2).map((category, index) => (
              <span key={category.id || index} className="woo-ai-assistant-product-category">
                {category.name}
              </span>
            ))}
          </div>
        )}

        {/* Price and Stock */}
        <div className="woo-ai-assistant-product-pricing">
          {getPriceDisplay()}
          {getStockStatus()}
        </div>

        {/* Product Description */}
        {(product.shortDescription || product.description) && size !== 'small' && (
          <div className="woo-ai-assistant-product-description">
            {truncateText(
              product.shortDescription || product.description,
              size === 'medium' ? 100 : 200
            )}
          </div>
        )}

        {/* Actions */}
        {showActions && product.inStock && (
          <div className="woo-ai-assistant-product-actions">
            {/* Quantity Selector */}
            <div className="woo-ai-assistant-product-quantity">
              <label
                htmlFor={`qty-${product.id}`}
                className="woo-ai-assistant-product-quantity-label"
              >
                Qty:
              </label>
              <input
                id={`qty-${product.id}`}
                type="number"
                min="1"
                max="99"
                value={quantity}
                onChange={handleQuantityChange}
                className="woo-ai-assistant-product-quantity-input"
                aria-label={`Quantity for ${product.name}`}
              />
            </div>

            {/* Action Buttons */}
            <div className="woo-ai-assistant-product-buttons">
              <QuickAction
                type="add-to-cart"
                label="Add to Cart"
                icon={<CartIcon />}
                onClick={handleAddToCart}
                disabled={isLoading || !product.inStock}
                loading={isLoading}
                size={size === 'small' ? 'small' : 'medium'}
                primary
              />

              <QuickAction
                type="view-product"
                label="View Details"
                icon={<ViewIcon />}
                onClick={handleViewProduct}
                size={size === 'small' ? 'small' : 'medium'}
                variant="outline"
              />
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

// Icon Components

/**
 * Sale Icon Component
 */
const SaleIcon = () => (
  <svg
    width="12"
    height="12"
    viewBox="0 0 12 12"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M2 2l8 8M5 2H2v3M10 7v3H7"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

/**
 * Image Placeholder Icon Component
 */
const ImagePlaceholderIcon = () => (
  <svg
    width="32"
    height="32"
    viewBox="0 0 32 32"
    fill="none"
    aria-hidden="true"
  >
    <rect width="32" height="32" rx="4" fill="currentColor" opacity="0.1"/>
    <path
      d="M8 12l4 4 8-8M8 24h16a2 2 0 002-2V10a2 2 0 00-2-2H8a2 2 0 00-2 2v12a2 2 0 002 2z"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

/**
 * Cart Icon Component
 */
const CartIcon = () => (
  <svg
    width="16"
    height="16"
    viewBox="0 0 16 16"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M1 1h2l.4 2M3 3h11l-1 7H4L3 3z"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <circle cx="5" cy="14" r="1" stroke="currentColor" strokeWidth="1.5"/>
    <circle cx="12" cy="14" r="1" stroke="currentColor" strokeWidth="1.5"/>
  </svg>
);

/**
 * View Icon Component
 */
const ViewIcon = () => (
  <svg
    width="16"
    height="16"
    viewBox="0 0 16 16"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <circle cx="8" cy="8" r="2" stroke="currentColor" strokeWidth="1.5"/>
  </svg>
);

// PropTypes
ProductCard.propTypes = {
  product: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    name: PropTypes.string.isRequired,
    description: PropTypes.string,
    shortDescription: PropTypes.string,
    price: PropTypes.string,
    regularPrice: PropTypes.string,
    salePrice: PropTypes.string,
    image: PropTypes.string,
    permalink: PropTypes.string,
    inStock: PropTypes.bool,
    variations: PropTypes.object,
    categories: PropTypes.arrayOf(PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
      name: PropTypes.string.isRequired
    }))
  }).isRequired,
  wooCommerceData: PropTypes.shape({
    currency: PropTypes.string,
    currencySymbol: PropTypes.string,
    cartItems: PropTypes.array
  }),
  config: PropTypes.shape({
    features: PropTypes.object,
    styling: PropTypes.object
  }),
  onAddToCart: PropTypes.func,
  onViewProduct: PropTypes.func,
  showActions: PropTypes.bool,
  size: PropTypes.oneOf(['small', 'medium', 'large'])
};

export default ProductCard;
