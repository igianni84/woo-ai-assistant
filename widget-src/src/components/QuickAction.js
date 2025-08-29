/**
 * QuickAction Component
 * 
 * Reusable action button component for product interactions within the chat.
 * Provides consistent styling, loading states, and accessibility features
 * for all product-related actions like add to cart, apply coupon, etc.
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useCallback } from 'react';
import PropTypes from 'prop-types';

/**
 * QuickAction component for rendering action buttons with consistent styling
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {string} props.type - Action type identifier
 * @param {Function} props.onClick - Click handler function
 * @param {React.ReactNode} props.children - Button content
 * @param {boolean} [props.disabled=false] - Whether button is disabled
 * @param {boolean} [props.loading=false] - Whether button is in loading state
 * @param {string} [props.variant='primary'] - Button style variant
 * @param {string} [props.size='normal'] - Button size
 * @param {string} [props.icon] - Optional icon identifier
 * @param {string} [props.ariaLabel] - Optional aria-label override
 * @param {string} [props.tooltip] - Optional tooltip text
 * @param {Object} [props.data] - Additional data to pass to onClick
 * 
 * @returns {JSX.Element} QuickAction button component
 * 
 * @example
 * <QuickAction
 *   type="add_to_cart"
 *   onClick={(type, data) => handleAction(type, data)}
 *   variant="primary"
 *   icon="cart"
 *   loading={isLoading}
 *   data={{ productId: 123 }}
 * >
 *   Add to Cart
 * </QuickAction>
 */
const QuickAction = ({ 
  type,
  onClick,
  children,
  disabled = false,
  loading = false,
  variant = 'primary',
  size = 'normal',
  icon = null,
  ariaLabel,
  tooltip,
  data = {},
  ...restProps
}) => {
  const [isPressed, setIsPressed] = useState(false);

  /**
   * Handles button click with loading state management
   * 
   * @param {Event} event - Click event
   */
  const handleClick = useCallback(async (event) => {
    event.preventDefault();
    
    if (disabled || loading) {
      return;
    }

    setIsPressed(true);
    
    try {
      if (onClick) {
        await onClick(type, data);
      }
    } catch (error) {
      console.error('QuickAction error:', error);
    } finally {
      // Reset pressed state after animation
      setTimeout(() => setIsPressed(false), 150);
    }
  }, [onClick, type, data, disabled, loading]);

  /**
   * Handles keyboard interaction
   * 
   * @param {Event} event - Keyboard event
   */
  const handleKeyDown = useCallback((event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      handleClick(event);
    }
  }, [handleClick]);

  /**
   * Gets the appropriate icon SVG based on type and icon prop
   * 
   * @returns {JSX.Element|null} Icon SVG element
   */
  const renderIcon = () => {
    const iconType = icon || type;
    const iconProps = {
      width: size === 'small' ? '14' : '16',
      height: size === 'small' ? '14' : '16',
      viewBox: '0 0 24 24',
      fill: 'none',
      stroke: 'currentColor',
      strokeWidth: '2',
      strokeLinecap: 'round',
      strokeLinejoin: 'round',
      'aria-hidden': 'true'
    };

    switch (iconType) {
      case 'add_to_cart':
      case 'cart':
        return (
          <svg {...iconProps}>
            <circle cx="9" cy="21" r="1"/>
            <circle cx="20" cy="21" r="1"/>
            <path d="m1 1 4 4 2 1 4 13 9 0"/>
            <path d="m6 8 15 0"/>
          </svg>
        );
        
      case 'view_product':
      case 'view':
      case 'eye':
        return (
          <svg {...iconProps}>
            <path d="m1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        );
        
      case 'apply_coupon':
      case 'coupon':
      case 'tag':
        return (
          <svg {...iconProps}>
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
            <line x1="7" y1="7" x2="7.01" y2="7"/>
          </svg>
        );
        
      case 'compare':
        return (
          <svg {...iconProps}>
            <polyline points="5 9 2 12 5 15"/>
            <polyline points="9 5 12 2 15 5"/>
            <polyline points="15 19 12 22 9 19"/>
            <polyline points="19 9 22 12 19 15"/>
            <line x1="2" y1="12" x2="22" y2="12"/>
            <line x1="12" y1="2" y2="22"/>
          </svg>
        );
        
      case 'wishlist':
      case 'heart':
        return (
          <svg {...iconProps}>
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        );
        
      case 'share':
        return (
          <svg {...iconProps}>
            <circle cx="18" cy="5" r="3"/>
            <circle cx="6" cy="12" r="3"/>
            <circle cx="18" cy="19" r="3"/>
            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
          </svg>
        );
        
      case 'loading':
        return (
          <svg {...iconProps} className="spinner">
            <circle cx="12" cy="12" r="10" opacity="0.25"/>
            <path d="m15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0" opacity="0.75"/>
          </svg>
        );
        
      default:
        return null;
    }
  };

  /**
   * Renders loading spinner
   * 
   * @returns {JSX.Element} Loading spinner element
   */
  const renderLoadingSpinner = () => (
    <svg 
      width={size === 'small' ? '14' : '16'} 
      height={size === 'small' ? '14' : '16'}
      viewBox="0 0 24 24" 
      fill="none"
      className="loading-spinner"
      aria-hidden="true"
    >
      <circle 
        cx="12" 
        cy="12" 
        r="10" 
        stroke="currentColor" 
        strokeWidth="2" 
        opacity="0.25"
      />
      <path 
        d="m15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0" 
        fill="currentColor" 
        opacity="0.75"
      />
    </svg>
  );

  /**
   * Determines button CSS classes based on props
   * 
   * @returns {string} CSS class string
   */
  const getButtonClasses = () => {
    const classes = ['quick-action'];
    
    // Add variant class
    classes.push(`quick-action-${variant}`);
    
    // Add size class
    if (size !== 'normal') {
      classes.push(`quick-action-${size}`);
    }
    
    // Add state classes
    if (disabled) {
      classes.push('quick-action-disabled');
    }
    
    if (loading) {
      classes.push('quick-action-loading');
    }
    
    if (isPressed) {
      classes.push('quick-action-pressed');
    }
    
    // Add type-specific class
    classes.push(`quick-action-${type.replace(/_/g, '-')}`);
    
    return classes.join(' ');
  };

  /**
   * Gets the appropriate aria-label for the button
   * 
   * @returns {string} Aria-label text
   */
  const getAriaLabel = () => {
    if (ariaLabel) {
      return ariaLabel;
    }
    
    // Generate aria-label based on type and loading state
    const actionLabels = {
      add_to_cart: loading ? 'Adding item to cart' : 'Add item to cart',
      view_product: loading ? 'Opening product details' : 'View product details',
      apply_coupon: loading ? 'Applying coupon code' : 'Apply coupon code',
      compare: 'Compare this product',
      wishlist: 'Add to wishlist',
      share: 'Share this product'
    };
    
    return actionLabels[type] || (typeof children === 'string' ? children : 'Action button');
  };

  return (
    <button
      type="button"
      className={getButtonClasses()}
      onClick={handleClick}
      onKeyDown={handleKeyDown}
      disabled={disabled || loading}
      aria-label={getAriaLabel()}
      title={tooltip}
      data-action-type={type}
      data-testid={`quick-action-${type}`}
      {...restProps}
    >
      {/* Button Content */}
      <span className="quick-action-content">
        {/* Icon */}
        {(icon || type) && (
          <span className="quick-action-icon">
            {loading ? renderLoadingSpinner() : renderIcon()}
          </span>
        )}
        
        {/* Text */}
        {children && (
          <span className="quick-action-text">
            {children}
          </span>
        )}
      </span>
      
      {/* Focus Ring */}
      <span className="quick-action-focus-ring" aria-hidden="true"/>
      
      {/* Ripple Effect */}
      <span className="quick-action-ripple" aria-hidden="true"/>
    </button>
  );
};

QuickAction.propTypes = {
  /**
   * Action type identifier used for styling and functionality
   */
  type: PropTypes.string.isRequired,
  
  /**
   * Click handler function that receives (type, data) as parameters
   */
  onClick: PropTypes.func.isRequired,
  
  /**
   * Button content (text, elements, etc.)
   */
  children: PropTypes.node,
  
  /**
   * Whether the button is disabled
   */
  disabled: PropTypes.bool,
  
  /**
   * Whether the button is in loading state
   */
  loading: PropTypes.bool,
  
  /**
   * Visual style variant
   */
  variant: PropTypes.oneOf([
    'primary',
    'secondary', 
    'tertiary',
    'success',
    'warning',
    'danger',
    'ghost',
    'link'
  ]),
  
  /**
   * Button size
   */
  size: PropTypes.oneOf(['small', 'normal', 'large']),
  
  /**
   * Optional icon identifier (overrides type-based icon)
   */
  icon: PropTypes.string,
  
  /**
   * Custom aria-label (overrides generated one)
   */
  ariaLabel: PropTypes.string,
  
  /**
   * Tooltip text
   */
  tooltip: PropTypes.string,
  
  /**
   * Additional data to pass to onClick handler
   */
  data: PropTypes.object
};

QuickAction.defaultProps = {
  children: null,
  disabled: false,
  loading: false,
  variant: 'primary',
  size: 'normal',
  icon: null,
  ariaLabel: null,
  tooltip: null,
  data: {}
};

export default QuickAction;