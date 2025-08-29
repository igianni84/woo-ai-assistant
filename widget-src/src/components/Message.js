/**
 * Message Component
 * 
 * Individual chat message component for user and bot messages
 * with timestamp display and proper accessibility support
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { useState, useEffect, useRef } from 'react';
import PropTypes from 'prop-types';
import ProductCard from './ProductCard';

/**
 * Message component that displays individual chat messages
 * 
 * @component
 * @param {Object} props - Component properties
 * @param {Object} props.message - Message object containing content and metadata
 * @param {string} props.message.id - Unique message identifier
 * @param {string} props.message.content - Message text content
 * @param {'user'|'bot'} props.message.type - Message type (user or bot)
 * @param {string} [props.message.timestamp] - ISO timestamp string
 * @param {Array} [props.message.products] - Array of product objects to display
 * @param {Object} [props.message.metadata] - Additional message metadata
 * @param {boolean} [props.showTimestamp=true] - Whether to show timestamp
 * @param {boolean} [props.showAvatar=true] - Whether to show avatar for bot messages
 * @param {string} [props.conversationId] - Current conversation ID for product actions
 * @param {Function} [props.onProductAction] - Callback for product actions
 * @param {boolean} [props.isStreaming=false] - Whether this message is currently streaming
 * @param {number} [props.streamingProgress=0] - Streaming progress (0-1)
 * @param {boolean} [props.enableStreamingAnimation=true] - Whether to show streaming animations
 * 
 * @returns {JSX.Element} Message component
 * 
 * @example
 * <Message
 *   message={{
 *     id: 'msg_123',
 *     content: 'Hello, how can I help?',
 *     type: 'bot',
 *     timestamp: '2023-08-26T10:30:00Z'
 *   }}
 *   showTimestamp={true}
 *   showAvatar={true}
 * />
 */
const Message = ({ 
  message, 
  showTimestamp = true, 
  showAvatar = true,
  conversationId,
  onProductAction,
  isStreaming = false,
  streamingProgress = 0,
  enableStreamingAnimation = true
}) => {
  const { id, content, type, timestamp, products, metadata } = message;
  
  // Streaming state management
  const [displayContent, setDisplayContent] = useState(content);
  const [showCursor, setShowCursor] = useState(false);
  const contentRef = useRef(null);
  const cursorIntervalRef = useRef(null);
  
  /**
   * Handle streaming content updates
   */
  useEffect(() => {
    if (isStreaming && type === 'bot' && enableStreamingAnimation) {
      // Show blinking cursor during streaming
      setShowCursor(true);
      
      // Start cursor blinking animation
      cursorIntervalRef.current = setInterval(() => {
        setShowCursor(prev => !prev);
      }, 500);
      
      // Update display content
      setDisplayContent(content);
    } else {
      // Hide cursor when not streaming
      setShowCursor(false);
      
      // Clear cursor animation
      if (cursorIntervalRef.current) {
        clearInterval(cursorIntervalRef.current);
        cursorIntervalRef.current = null;
      }
      
      // Update display content
      setDisplayContent(content);
    }
  }, [content, isStreaming, type, enableStreamingAnimation]);

  /**
   * Cleanup cursor animation on unmount
   */
  useEffect(() => {
    return () => {
      if (cursorIntervalRef.current) {
        clearInterval(cursorIntervalRef.current);
      }
    };
  }, []);

  /**
   * Scroll to content when it updates during streaming
   */
  useEffect(() => {
    if (isStreaming && contentRef.current) {
      // Smooth scroll to show new content
      contentRef.current.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
        inline: 'nearest'
      });
    }
  }, [displayContent, isStreaming]);
  
  /**
   * Formats timestamp for display
   * @returns {string} Formatted time string
   */
  const formatTime = () => {
    if (!timestamp) return '';
    
    try {
      return new Date(timestamp).toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit' 
      });
    } catch (error) {
      console.warn('Invalid timestamp format:', timestamp);
      return '';
    }
  };

  /**
   * Renders bot avatar SVG with streaming indicator
   * @returns {JSX.Element} Avatar SVG element
   */
  const renderBotAvatar = () => (
    <div className={`message-avatar ${isStreaming ? 'streaming' : ''}`} aria-hidden="true">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" fill="currentColor"/>
        <path 
          d="M8 14s1.5 2 4 2 4-2 4-2" 
          stroke="white" 
          strokeWidth="2" 
          strokeLinecap="round"
        />
        <path 
          d="M9 9h.01M15 9h.01" 
          stroke="white" 
          strokeWidth="2" 
          strokeLinecap="round"
        />
      </svg>
      {isStreaming && (
        <div className="streaming-indicator" title="Assistant is typing...">
          <div className="streaming-dot"></div>
          <div className="streaming-dot"></div>
          <div className="streaming-dot"></div>
        </div>
      )}
    </div>
  );

  /**
   * Renders streaming status indicator
   * @returns {JSX.Element|null} Streaming status element
   */
  const renderStreamingStatus = () => {
    if (!isStreaming || type !== 'bot') return null;

    return (
      <div className="streaming-status" role="status" aria-live="polite">
        <div className="streaming-progress-bar">
          <div 
            className="streaming-progress-fill" 
            style={{ width: `${Math.max(5, streamingProgress * 100)}%` }}
          />
        </div>
        <span className="streaming-status-text" aria-label="Assistant is generating response">
          {streamingProgress > 0 ? `${Math.round(streamingProgress * 100)}%` : 'Thinking...'}
        </span>
      </div>
    );
  };

  /**
   * Renders typing cursor for streaming messages
   * @returns {JSX.Element|null} Cursor element
   */
  const renderTypingCursor = () => {
    if (!isStreaming || type !== 'bot' || !showCursor || !enableStreamingAnimation) {
      return null;
    }

    return <span className="typing-cursor" aria-hidden="true">|</span>;
  };

  /**
   * Renders products grid when products are included in message
   * @returns {JSX.Element|null} Products grid element
   */
  const renderProducts = () => {
    if (!products || !Array.isArray(products) || products.length === 0) {
      return null;
    }

    // Determine grid layout based on number of products
    const gridClass = products.length === 1 ? 'products-grid-single' :
                     products.length === 2 ? 'products-grid-double' :
                     'products-grid-multiple';

    return (
      <div className={`message-products ${gridClass}`} role="region" aria-label="Product recommendations">
        {products.map((product, index) => (
          <ProductCard
            key={product.id || `product-${index}`}
            product={product}
            conversationId={conversationId}
            onAction={onProductAction}
            size={products.length === 1 ? 'detailed' : products.length <= 2 ? 'normal' : 'compact'}
            showActions={!!onProductAction}
          />
        ))}
      </div>
    );
  };

  /**
   * Renders message content with rich formatting support and streaming
   * @returns {JSX.Element} Formatted content element
   */
  const renderContent = () => {
    const contentWithCursor = (text) => (
      <>
        {text}
        {renderTypingCursor()}
      </>
    );

    // Handle rich content types
    if (metadata && metadata.contentType) {
      switch (metadata.contentType) {
        case 'product_recommendation':
          return (
            <div className="message-rich-content">
              <div className="message-text">
                {contentWithCursor(displayContent)}
              </div>
              {renderProducts()}
            </div>
          );
        case 'comparison':
          return (
            <div className="message-rich-content">
              <div className="message-text">
                {contentWithCursor(displayContent)}
              </div>
              {renderProducts()}
              {metadata.comparisonData && (
                <div className="comparison-summary">
                  <h4>Comparison Summary</h4>
                  <ul>
                    {metadata.comparisonData.map((item, index) => (
                      <li key={index}>{item}</li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          );
        default:
          return (
            <div className="message-text">
              {contentWithCursor(displayContent)}
              {renderProducts()}
            </div>
          );
      }
    }

    // Standard text content with optional products
    return (
      <div className="message-text">
        {contentWithCursor(displayContent)}
        {renderProducts()}
      </div>
    );
  };

  return (
    <div 
      className={`message ${type === 'user' ? 'user-message' : 'bot-message'} ${isStreaming ? 'streaming' : ''}`}
      data-message-id={id}
      role={type === 'bot' ? 'log' : undefined}
      aria-label={type === 'user' ? 'Your message' : 'Assistant message'}
      ref={contentRef}
    >
      {/* Bot Avatar */}
      {type === 'bot' && showAvatar && renderBotAvatar()}
      
      {/* Message Content */}
      <div className="message-content">
        {/* Streaming Status */}
        {renderStreamingStatus()}
        
        {renderContent()}
        
        {/* Timestamp - don't show during streaming */}
        {showTimestamp && timestamp && !isStreaming && (
          <time 
            className="message-timestamp" 
            dateTime={timestamp}
            title={new Date(timestamp).toLocaleString()}
          >
            {formatTime()}
          </time>
        )}
        
        {/* Streaming metadata */}
        {isStreaming && metadata?.streaming && (
          <div className="streaming-metadata" aria-hidden="true">
            {metadata.fallback && <span className="fallback-indicator">Offline mode</span>}
          </div>
        )}
      </div>
    </div>
  );
};

Message.propTypes = {
  /**
   * Message object containing all message data
   */
  message: PropTypes.shape({
    /**
     * Unique identifier for the message
     */
    id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    
    /**
     * The text content of the message
     */
    content: PropTypes.string.isRequired,
    
    /**
     * Type of message - either user or bot
     */
    type: PropTypes.oneOf(['user', 'bot']).isRequired,
    
    /**
     * ISO timestamp string for when message was sent
     */
    timestamp: PropTypes.string,
    
    /**
     * Array of product objects to display in message
     */
    products: PropTypes.arrayOf(PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
      name: PropTypes.string.isRequired,
      price: PropTypes.string.isRequired,
      image: PropTypes.string,
      description: PropTypes.string
    })),
    
    /**
     * Additional message metadata
     */
    metadata: PropTypes.shape({
      contentType: PropTypes.string,
      comparisonData: PropTypes.array
    })
  }).isRequired,
  
  /**
   * Whether to display the message timestamp
   */
  showTimestamp: PropTypes.bool,
  
  /**
   * Whether to show avatar for bot messages
   */
  showAvatar: PropTypes.bool,
  
  /**
   * Current conversation ID for product actions
   */
  conversationId: PropTypes.string,
  
  /**
   * Callback function for handling product actions
   */
  onProductAction: PropTypes.func,
  
  /**
   * Whether this message is currently streaming
   */
  isStreaming: PropTypes.bool,
  
  /**
   * Streaming progress (0-1)
   */
  streamingProgress: PropTypes.number,
  
  /**
   * Whether to show streaming animations
   */
  enableStreamingAnimation: PropTypes.bool,
};

Message.defaultProps = {
  showTimestamp: true,
  showAvatar: true,
  conversationId: null,
  onProductAction: null,
  isStreaming: false,
  streamingProgress: 0,
  enableStreamingAnimation: true,
};

export default Message;