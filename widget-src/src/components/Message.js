/**
 * Message Component
 *
 * Displays individual chat messages with proper styling, avatars,
 * timestamps, and support for different message types (user, assistant, system, error).
 * Includes rich text rendering and fade-in animations.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import ProductCard from './ProductCard';
import { QuickActionGroup, ApplyCouponAction } from './QuickAction';
import productActionService from '../services/ProductActionService';

/**
 * Message Component
 *
 * @component
 * @param {Object} props - Component properties
 * @param {Object} props.message - Message object
 * @param {string} props.message.id - Unique message ID
 * @param {string} props.message.type - Message type (user, assistant, system, error)
 * @param {string} props.message.content - Message content
 * @param {string} props.message.timestamp - ISO timestamp
 * @param {Object} props.message.metadata - Additional message metadata
 * @param {boolean} props.isLatest - Whether this is the latest message
 * @param {Object} props.userContext - Current user context
 * @param {Object} props.wooCommerceData - WooCommerce data context
 * @param {Object} props.config - Widget configuration
 * @param {Function} props.onActionSuccess - Success callback for actions
 * @param {Function} props.onActionError - Error callback for actions
 * @returns {JSX.Element} Message component
 */
const Message = ({
  message,
  isLatest = false,
  userContext = {},
  wooCommerceData = {},
  config = {},
  onActionSuccess,
  onActionError
}) => {
  const [isVisible, setIsVisible] = useState(false);

  // Fade-in animation effect
  useEffect(() => {
    const timer = setTimeout(() => {
      setIsVisible(true);
    }, 50);
    return () => clearTimeout(timer);
  }, []);

  // Format timestamp for display
  const formatTimestamp = (timestamp) => {
    try {
      const date = new Date(timestamp);
      const now = new Date();
      const diff = now - date;
      const seconds = Math.floor(diff / 1000);
      const minutes = Math.floor(seconds / 60);
      const hours = Math.floor(minutes / 60);
      const days = Math.floor(hours / 24);

      if (seconds < 60) {
        return 'Just now';
      } else if (minutes < 60) {
        return `${minutes}m ago`;
      } else if (hours < 24) {
        return `${hours}h ago`;
      } else if (days < 7) {
        return `${days}d ago`;
      } else {
        return date.toLocaleDateString();
      }
    } catch (error) {
      return '';
    }
  };

  // Get user display name
  const getUserName = () => {
    return userContext.userName || userContext.userDisplayName || 'You';
  };

  // Get assistant name from config
  const getAssistantName = () => {
    return config.assistantName || 'AI Assistant';
  };

  // Handle product actions
  const handleProductAction = async (actionData) => {
    try {
      let result;

      switch (actionData.type) {
        case 'add-to-cart':
          result = await productActionService.addToCart(actionData.data);
          break;
        case 'apply-coupon':
          result = await productActionService.applyCoupon(actionData.data.couponCode);
          break;
        default:
          result = { success: false, error: 'Unknown action type' };
      }

      if (result.success) {
        if (onActionSuccess) {
          onActionSuccess(actionData.type, result);
        }
      } else {
        if (onActionError) {
          onActionError(actionData.type, result.error);
        }
      }
    } catch (error) {
      if (onActionError) {
        onActionError(actionData.type, error.message);
      }
    }
  };

  // Render enhanced message content with product cards and actions
  const renderContent = (content, metadata = {}) => {
    const components = [];

    // Always render the text content first
    if (content) {
      const allowedTags = ['b', 'strong', 'i', 'em', 'br', 'p', 'ul', 'ol', 'li'];
      const sanitizedContent = content.replace(/<(?!\/?(?:b|strong|i|em|br|p|ul|ol|li)\b)[^>]*>/gi, '');

      components.push(
        <div
          key="text-content"
          className="woo-ai-assistant-message-text"
          dangerouslySetInnerHTML={{ __html: sanitizedContent }}
        />
      );
    }

    // Render product cards if present in metadata
    if (metadata.products && Array.isArray(metadata.products)) {
      const productCards = (
        <div key="product-cards" className="woo-ai-assistant-message-products">
          {metadata.products.map((product, index) => (
            <ProductCard
              key={product.id || index}
              product={product}
              wooCommerceData={wooCommerceData}
              config={config}
              onAddToCart={handleProductAction}
              size="medium"
              showActions={true}
            />
          ))}
        </div>
      );
      components.push(productCards);
    }

    // Render suggested coupons if present
    if (metadata.suggestedCoupons && Array.isArray(metadata.suggestedCoupons)) {
      const couponActions = (
        <div key="coupon-actions" className="woo-ai-assistant-message-coupons">
          <div className="woo-ai-assistant-message-coupons-title">
            💰 Available Coupons:
          </div>
          <QuickActionGroup direction="horizontal" spacing="small">
            {metadata.suggestedCoupons.map((coupon, index) => (
              <ApplyCouponAction
                key={coupon.code || index}
                couponCode={coupon.code}
                onClick={handleProductAction}
                size="small"
              />
            ))}
          </QuickActionGroup>
        </div>
      );
      components.push(couponActions);
    }

    // Render quick actions if present
    if (metadata.quickActions && Array.isArray(metadata.quickActions)) {
      const quickActions = (
        <div key="quick-actions" className="woo-ai-assistant-message-actions-list">
          <QuickActionGroup direction="horizontal" spacing="small">
            {metadata.quickActions.map((action, index) => (
              <button
                key={index}
                className="woo-ai-assistant-quick-action woo-ai-assistant-quick-action--small woo-ai-assistant-quick-action--outline"
                onClick={() => handleProductAction(action)}
              >
                {action.label}
              </button>
            ))}
          </QuickActionGroup>
        </div>
      );
      components.push(quickActions);
    }

    return components.length > 0 ? components : null;
  };

  // Get message classes
  const getMessageClasses = () => {
    const baseClass = 'woo-ai-assistant-message';
    const classes = [baseClass, `${baseClass}--${message.type}`];

    if (isVisible) classes.push(`${baseClass}--visible`);
    if (isLatest) classes.push(`${baseClass}--latest`);
    if (message.metadata?.hasError) classes.push(`${baseClass}--error`);

    return classes.join(' ');
  };

  // Get avatar content based on message type
  const renderAvatar = () => {
    switch (message.type) {
      case 'user':
        return (
          <div className="woo-ai-assistant-message-avatar woo-ai-assistant-message-avatar--user">
            {userContext.userAvatar ? (
              <img
                src={userContext.userAvatar}
                alt={`${getUserName()}'s avatar`}
                className="woo-ai-assistant-avatar-image"
              />
            ) : (
              <UserIcon />
            )}
          </div>
        );
      case 'assistant':
        return (
          <div className="woo-ai-assistant-message-avatar woo-ai-assistant-message-avatar--assistant">
            <BotIcon />
          </div>
        );
      case 'system':
        return (
          <div className="woo-ai-assistant-message-avatar woo-ai-assistant-message-avatar--system">
            <SystemIcon />
          </div>
        );
      case 'error':
        return (
          <div className="woo-ai-assistant-message-avatar woo-ai-assistant-message-avatar--error">
            <ErrorIcon />
          </div>
        );
      default:
        return null;
    }
  };

  // Get sender name
  const getSenderName = () => {
    switch (message.type) {
      case 'user':
        return getUserName();
      case 'assistant':
        return getAssistantName();
      case 'system':
        return 'System';
      case 'error':
        return 'Error';
      default:
        return '';
    }
  };

  return (
    <div
      className={getMessageClasses()}
      role="listitem"
      aria-label={`Message from ${getSenderName()}`}
    >
      {renderAvatar()}

      <div className="woo-ai-assistant-message-content">
        <div className="woo-ai-assistant-message-header">
          <span className="woo-ai-assistant-message-sender">
            {getSenderName()}
          </span>
          <time
            className="woo-ai-assistant-message-timestamp"
            dateTime={message.timestamp}
            title={new Date(message.timestamp).toLocaleString()}
          >
            {formatTimestamp(message.timestamp)}
          </time>
        </div>

        <div className="woo-ai-assistant-message-body">
          {renderContent(message.content, message.metadata)}

          {/* Message metadata (for debugging in development) */}
          {process.env.NODE_ENV === 'development' && message.metadata && (
            <details className="woo-ai-assistant-message-debug">
              <summary>Debug Info</summary>
              <pre>{JSON.stringify(message.metadata, null, 2)}</pre>
            </details>
          )}
        </div>

        {/* Message actions (like/dislike, copy, etc.) */}
        {message.type === 'assistant' && (
          <div className="woo-ai-assistant-message-actions">
            <button
              className="woo-ai-assistant-message-action"
              onClick={() => navigator.clipboard?.writeText(message.content)}
              aria-label="Copy message"
              title="Copy to clipboard"
            >
              <CopyIcon />
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

// Icon Components

/**
 * User Icon Component
 */
const UserIcon = () => (
  <svg
    width="20"
    height="20"
    viewBox="0 0 20 20"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M10 2a4 4 0 100 8 4 4 0 000-8zM4 14a6 6 0 1112 0v1a1 1 0 01-1 1H5a1 1 0 01-1-1v-1z"
      fill="currentColor"
    />
  </svg>
);

/**
 * Bot Icon Component
 */
const BotIcon = () => (
  <svg
    width="20"
    height="20"
    viewBox="0 0 20 20"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M10 1.5C11.1046 1.5 12 2.39543 12 3.5V4H13.5C15.7091 4 17.5 5.79086 17.5 8V13.5C17.5 15.7091 15.7091 17.5 13.5 17.5H6.5C4.29086 17.5 2.5 15.7091 2.5 13.5V8C2.5 5.79086 4.29086 4 6.5 4H8V3.5C8 2.39543 8.89543 1.5 10 1.5Z"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <circle cx="7.5" cy="10" r="0.75" fill="currentColor"/>
    <circle cx="12.5" cy="10" r="0.75" fill="currentColor"/>
    <path
      d="M7.5 13.5H12.5"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
    />
  </svg>
);

/**
 * System Icon Component
 */
const SystemIcon = () => (
  <svg
    width="20"
    height="20"
    viewBox="0 0 20 20"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M10 18a8 8 0 100-16 8 8 0 000 16zM10 6v4M10 14h.01"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

/**
 * Error Icon Component
 */
const ErrorIcon = () => (
  <svg
    width="20"
    height="20"
    viewBox="0 0 20 20"
    fill="none"
    aria-hidden="true"
  >
    <circle cx="10" cy="10" r="8" stroke="currentColor" strokeWidth="1.5"/>
    <path d="m6 6 8 8M14 6l-8 8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
  </svg>
);

/**
 * Copy Icon Component
 */
const CopyIcon = () => (
  <svg
    width="14"
    height="14"
    viewBox="0 0 14 14"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M4.5 4.5V2.5a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1h-2M4.5 4.5h-2a1 1 0 00-1 1v6a1 1 0 001 1h6a1 1 0 001-1v-2M4.5 4.5v6.5h4.5"
      stroke="currentColor"
      strokeWidth="1.2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

// PropTypes
Message.propTypes = {
  message: PropTypes.shape({
    id: PropTypes.string.isRequired,
    type: PropTypes.oneOf(['user', 'assistant', 'system', 'error']).isRequired,
    content: PropTypes.string.isRequired,
    timestamp: PropTypes.string.isRequired,
    metadata: PropTypes.shape({
      products: PropTypes.array,
      suggestedCoupons: PropTypes.array,
      quickActions: PropTypes.array,
      hasError: PropTypes.bool,
      source: PropTypes.string
    })
  }).isRequired,
  isLatest: PropTypes.bool,
  userContext: PropTypes.shape({
    userName: PropTypes.string,
    userDisplayName: PropTypes.string,
    userAvatar: PropTypes.string
  }),
  wooCommerceData: PropTypes.shape({
    currency: PropTypes.string,
    currencySymbol: PropTypes.string,
    cartItems: PropTypes.array,
    cartTotal: PropTypes.string
  }),
  config: PropTypes.shape({
    assistantName: PropTypes.string,
    features: PropTypes.object,
    styling: PropTypes.object
  }),
  onActionSuccess: PropTypes.func,
  onActionError: PropTypes.func
};

export default Message;
