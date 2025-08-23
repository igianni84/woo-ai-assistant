/**
 * Error Boundary Component
 * 
 * React error boundary for graceful error handling in the chat widget
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import PropTypes from 'prop-types';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { 
      hasError: false, 
      error: null,
      errorInfo: null,
    };
  }

  static getDerivedStateFromError(error) {
    // Update state so the next render will show the fallback UI
    return { hasError: true };
  }

  componentDidCatch(error, errorInfo) {
    // Save error details
    this.setState({
      error,
      errorInfo,
    });

    // Call parent error handler if provided
    if (this.props.onError) {
      this.props.onError({
        error,
        errorInfo,
        timestamp: new Date().toISOString(),
      });
    }

    // Log to console in development
    if (process.env.NODE_ENV === 'development') {
      console.error('Woo AI Assistant Error Boundary caught an error:', error, errorInfo);
    }
  }

  render() {
    if (this.state.hasError) {
      // Custom fallback UI
      if (this.props.fallback) {
        return this.props.fallback;
      }

      // Default fallback UI
      return (
        <div 
          className="error-boundary-fallback"
          role="alert"
          aria-live="polite"
        >
          <div className="error-content">
            <h4>Something went wrong</h4>
            <p>
              The chat widget encountered an error. Please refresh the page to try again.
            </p>
            
            <div className="error-actions">
              <button
                className="retry-button"
                onClick={() => window.location.reload()}
                type="button"
              >
                Refresh Page
              </button>
              
              {this.props.onRetry && (
                <button
                  className="retry-button"
                  onClick={() => {
                    this.setState({ hasError: false, error: null, errorInfo: null });
                    this.props.onRetry();
                  }}
                  type="button"
                >
                  Try Again
                </button>
              )}
            </div>

            {process.env.NODE_ENV === 'development' && this.state.error && (
              <details className="error-details">
                <summary>Error Details (Development Only)</summary>
                <div className="error-stack">
                  <h5>Error:</h5>
                  <pre>{this.state.error.toString()}</pre>
                  
                  {this.state.errorInfo && (
                    <>
                      <h5>Component Stack:</h5>
                      <pre>{this.state.errorInfo.componentStack}</pre>
                    </>
                  )}
                </div>
              </details>
            )}
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

ErrorBoundary.propTypes = {
  children: PropTypes.node.isRequired,
  onError: PropTypes.func,
  onRetry: PropTypes.func,
  fallback: PropTypes.node,
};

ErrorBoundary.defaultProps = {
  onError: null,
  onRetry: null,
  fallback: null,
};

export default ErrorBoundary;