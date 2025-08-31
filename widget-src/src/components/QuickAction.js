/**
 * Quick Action Component
 *
 * Reusable action button component for various quick actions like
 * add to cart, apply coupon, view product, etc. Features loading states,
 * different variants, sizes, and proper accessibility.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useCallback } from 'react';
import PropTypes from 'prop-types';

/**
 * Quick Action Component
 *
 * @component
 * @param {Object} props - Component properties
 * @param {string} props.type - Action type (add-to-cart, apply-coupon, view-product, etc.)
 * @param {string} props.label - Button label text
 * @param {JSX.Element} props.icon - Icon element to display
 * @param {Function} props.onClick - Click handler function
 * @param {boolean} props.disabled - Whether button is disabled
 * @param {boolean} props.loading - Whether button is in loading state
 * @param {string} props.size - Button size ('small', 'medium', 'large')
 * @param {string} props.variant - Button variant ('primary', 'secondary', 'outline', 'ghost')
 * @param {boolean} props.primary - Whether this is a primary action (deprecated, use variant)
 * @param {boolean} props.fullWidth - Whether button should be full width
 * @param {string} props.loadingText - Text to show during loading
 * @param {Object} props.data - Additional data for the action
 * @param {string} props.className - Additional CSS classes
 * @param {string} props.ariaLabel - Custom aria-label for accessibility
 * @returns {JSX.Element} Quick action button component
 */
const QuickAction = ({
  type,
  label,
  icon,
  onClick,
  disabled = false,
  loading = false,
  size = 'medium',
  variant = 'secondary',
  primary = false, // Deprecated: use variant="primary"
  fullWidth = false,
  loadingText,
  data = {},
  className = '',
  ariaLabel,
  ...props
}) => {
  // Local loading state for individual actions
  const [isProcessing, setIsProcessing] = useState(false);

  // Resolve variant (handle legacy primary prop)
  const resolvedVariant = primary ? 'primary' : variant;

  // Handle click with loading state management
  const handleClick = useCallback(async (event) => {
    event.preventDefault();

    if (disabled || loading || isProcessing || !onClick) {
      return;
    }

    setIsProcessing(true);

    try {
      await onClick({ type, data, event });
    } catch (error) {
      // Error handling is done in parent component
      // Just ensure we reset the loading state
    } finally {
      setIsProcessing(false);
    }
  }, [disabled, loading, isProcessing, onClick, type, data]);

  // Get button classes
  const getButtonClasses = () => {
    const baseClass = 'woo-ai-assistant-quick-action';
    const classes = [baseClass];

    // Add type-specific class
    if (type) classes.push(`${baseClass}--${type}`);

    // Add variant class
    classes.push(`${baseClass}--${resolvedVariant}`);

    // Add size class
    classes.push(`${baseClass}--${size}`);

    // Add state classes
    if (disabled) classes.push(`${baseClass}--disabled`);
    if (loading || isProcessing) classes.push(`${baseClass}--loading`);
    if (fullWidth) classes.push(`${baseClass}--full-width`);

    // Add custom classes
    if (className) classes.push(className);

    return classes.join(' ');
  };

  // Get loading text
  const getLoadingText = () => {
    if (loadingText) return loadingText;

    // Default loading text based on action type
    switch (type) {
      case 'add-to-cart':
        return 'Adding...';
      case 'apply-coupon':
        return 'Applying...';
      case 'view-product':
        return 'Loading...';
      case 'checkout':
        return 'Redirecting...';
      case 'remove-item':
        return 'Removing...';
      default:
        return 'Processing...';
    }
  };

  // Get aria label
  const getAriaLabel = () => {
    if (ariaLabel) return ariaLabel;

    if (loading || isProcessing) {
      return `${getLoadingText()} ${label}`;
    }

    return label;
  };

  // Render icon with loading state
  const renderIcon = () => {
    if (loading || isProcessing) {
      return <LoadingSpinner size={size} />;
    }

    return icon;
  };

  // Render button content
  const renderContent = () => {
    const showText = size !== 'small' || (!icon && !loading && !isProcessing);
    const displayText = (loading || isProcessing) ? getLoadingText() : label;

    return (
      <>
        {renderIcon()}
        {showText && (
          <span className="woo-ai-assistant-quick-action-text">
            {displayText}
          </span>
        )}
      </>
    );
  };

  return (
    <button
      type="button"
      className={getButtonClasses()}
      onClick={handleClick}
      disabled={disabled || loading || isProcessing}
      aria-label={getAriaLabel()}
      data-action-type={type}
      {...props}
    >
      {renderContent()}
    </button>
  );
};

/**
 * Loading Spinner Component
 */
const LoadingSpinner = ({ size = 'medium' }) => {
  const getSizeProps = () => {
    switch (size) {
      case 'small':
        return { width: 12, height: 12, strokeWidth: 2 };
      case 'large':
        return { width: 20, height: 20, strokeWidth: 2 };
      default:
        return { width: 16, height: 16, strokeWidth: 2 };
    }
  };

  const { width, height, strokeWidth } = getSizeProps();

  return (
    <svg
      className="woo-ai-assistant-loading-spinner"
      width={width}
      height={height}
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <circle
        className="woo-ai-assistant-loading-spinner-track"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        strokeWidth={strokeWidth}
        opacity="0.2"
      />
      <circle
        className="woo-ai-assistant-loading-spinner-head"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        strokeWidth={strokeWidth}
        strokeLinecap="round"
        strokeDasharray="60"
        strokeDashoffset="40"
      />
    </svg>
  );
};

/**
 * Quick Action Group Component
 * Container for grouping multiple quick actions
 */
export const QuickActionGroup = ({
  children,
  direction = 'horizontal',
  spacing = 'medium',
  className = '',
  ...props
}) => {
  const getGroupClasses = () => {
    const baseClass = 'woo-ai-assistant-quick-action-group';
    const classes = [baseClass];

    classes.push(`${baseClass}--${direction}`);
    classes.push(`${baseClass}--spacing-${spacing}`);

    if (className) classes.push(className);

    return classes.join(' ');
  };

  return (
    <div
      className={getGroupClasses()}
      role="group"
      {...props}
    >
      {children}
    </div>
  );
};

/**
 * Predefined Quick Actions
 * Common action configurations for easy reuse
 */

/**
 * Add to Cart Quick Action
 */
export const AddToCartAction = ({ productId, quantity = 1, ...props }) => (
  <QuickAction
    type="add-to-cart"
    label="Add to Cart"
    icon={<CartIcon />}
    variant="primary"
    data={{ productId, quantity }}
    {...props}
  />
);

/**
 * Apply Coupon Quick Action
 */
export const ApplyCouponAction = ({ couponCode, ...props }) => (
  <QuickAction
    type="apply-coupon"
    label="Apply Coupon"
    icon={<CouponIcon />}
    variant="secondary"
    data={{ couponCode }}
    {...props}
  />
);

/**
 * View Product Quick Action
 */
export const ViewProductAction = ({ productId, ...props }) => (
  <QuickAction
    type="view-product"
    label="View Details"
    icon={<ViewIcon />}
    variant="outline"
    data={{ productId }}
    {...props}
  />
);

/**
 * Checkout Quick Action
 */
export const CheckoutAction = ({ ...props }) => (
  <QuickAction
    type="checkout"
    label="Checkout"
    icon={<CheckoutIcon />}
    variant="primary"
    size="large"
    {...props}
  />
);

// Icon Components

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
 * Coupon Icon Component
 */
const CouponIcon = () => (
  <svg
    width="16"
    height="16"
    viewBox="0 0 16 16"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M2 6a1 1 0 011-1h10a1 1 0 011 1v.5a.5.5 0 01-.5.5.5.5 0 00-.5.5v2a.5.5 0 00.5.5.5.5 0 01.5.5V11a1 1 0 01-1 1H3a1 1 0 01-1-1v-.5a.5.5 0 01.5-.5.5.5 0 00.5-.5V7a.5.5 0 00-.5-.5.5.5 0 01-.5-.5V6z"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <path d="M6 8h4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
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

/**
 * Checkout Icon Component
 */
const CheckoutIcon = () => (
  <svg
    width="16"
    height="16"
    viewBox="0 0 16 16"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M14 6H2l1 8h10l1-8zM5 6V4a3 3 0 016 0v2M6 10h4"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

// PropTypes
QuickAction.propTypes = {
  type: PropTypes.string.isRequired,
  label: PropTypes.string.isRequired,
  icon: PropTypes.element,
  onClick: PropTypes.func.isRequired,
  disabled: PropTypes.bool,
  loading: PropTypes.bool,
  size: PropTypes.oneOf(['small', 'medium', 'large']),
  variant: PropTypes.oneOf(['primary', 'secondary', 'outline', 'ghost']),
  primary: PropTypes.bool,
  fullWidth: PropTypes.bool,
  loadingText: PropTypes.string,
  data: PropTypes.object,
  className: PropTypes.string,
  ariaLabel: PropTypes.string
};

QuickActionGroup.propTypes = {
  children: PropTypes.node.isRequired,
  direction: PropTypes.oneOf(['horizontal', 'vertical']),
  spacing: PropTypes.oneOf(['small', 'medium', 'large']),
  className: PropTypes.string
};

// Predefined actions PropTypes
AddToCartAction.propTypes = {
  productId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  quantity: PropTypes.number
};

ApplyCouponAction.propTypes = {
  couponCode: PropTypes.string.isRequired
};

ViewProductAction.propTypes = {
  productId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired
};

export default QuickAction;
