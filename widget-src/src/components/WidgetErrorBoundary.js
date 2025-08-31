/**
 * Widget Error Boundary Component
 *
 * Catches JavaScript errors in the widget component tree and displays
 * a fallback UI instead of crashing the entire widget.
 *
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import PropTypes from 'prop-types';

/**
 * Widget Error Boundary Class Component
 *
 * @component
 */
class WidgetErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
      retryCount: 0
    };

    // Bind methods
    this.handleRetry = this.handleRetry.bind(this);
    this.handleReportError = this.handleReportError.bind(this);
  }

  /**
   * Static method to update state when an error occurs
   *
   * @param {Error} error - The error that occurred
   * @returns {Object} New state object
   */
  static getDerivedStateFromError(error) {
    return {
      hasError: true,
      error
    };
  }

  /**
   * Handle component errors and log them
   *
   * @param {Error} error - The error that occurred
   * @param {Object} errorInfo - Error information including component stack
   */
  componentDidCatch(error, errorInfo) {
    // Log error for debugging (development only)
    if (process.env.NODE_ENV === 'development') {
      console.error('Woo AI Assistant Widget Error:', error, errorInfo);
    }

    // Update state with error info
    this.setState({
      error,
      errorInfo
    });

    // Send error to logging service if available
    if (window.wooAiAssistant?.logError) {
      window.wooAiAssistant.logError('widget_error', {
        error: error.toString(),
        stack: error.stack,
        componentStack: errorInfo.componentStack,
        retryCount: this.state.retryCount,
        userAgent: navigator.userAgent,
        url: window.location.href
      });
    }

    // Track error in analytics
    if (window.wooAiAssistant?.trackEvent) {
      window.wooAiAssistant.trackEvent('widget_error', {
        error_type: error.name,
        error_message: error.message,
        component_stack: errorInfo.componentStack,
        retry_count: this.state.retryCount
      });
    }
  }

  /**
   * Handle retry attempt
   */
  handleRetry() {
    this.setState(prevState => ({
      hasError: false,
      error: null,
      errorInfo: null,
      retryCount: prevState.retryCount + 1
    }));
  }

  /**
   * Handle error reporting
   */
  handleReportError() {
    const { error, errorInfo } = this.state;

    // Create error report
    const errorReport = {
      error: error?.toString(),
      stack: error?.stack,
      componentStack: errorInfo?.componentStack,
      userAgent: navigator.userAgent,
      url: window.location.href,
      timestamp: new Date().toISOString()
    };

    // Copy to clipboard if supported
    if (navigator.clipboard) {
      navigator.clipboard.writeText(JSON.stringify(errorReport, null, 2))
        .then(() => {
          alert('Error report copied to clipboard. Please paste this in your support request.');
        })
        .catch(() => {
          // Fallback: show error details in alert
          alert(`Error Report:\n\n${JSON.stringify(errorReport, null, 2)}`);
        });
    } else {
      // Fallback for older browsers
      alert(`Error Report:\n\n${JSON.stringify(errorReport, null, 2)}`);
    }
  }

  render() {
    const { hasError, error, retryCount } = this.state;
    const { children, fallback } = this.props;

    if (hasError) {
      // Custom fallback component if provided
      if (fallback) {
        return fallback(error, this.handleRetry, this.handleReportError);
      }

      // Default error UI
      return (
        <div
          className="woo-ai-assistant-error-boundary"
          role="alert"
          aria-live="assertive"
        >
          <div className="woo-ai-assistant-error-content">
            <div className="woo-ai-assistant-error-icon">
              <ErrorIcon />
            </div>

            <div className="woo-ai-assistant-error-message">
              <h3>Chat Temporarily Unavailable</h3>
              <p>
                We're experiencing a technical issue. Please try refreshing or contact support if the problem persists.
              </p>

              {process.env.NODE_ENV === 'development' && (
                <details className="woo-ai-assistant-error-details">
                  <summary>Error Details (Development)</summary>
                  <pre>{error?.toString()}</pre>
                  {error?.stack && (
                    <pre className="error-stack">{error.stack}</pre>
                  )}
                </details>
              )}
            </div>

            <div className="woo-ai-assistant-error-actions">
              <button
                type="button"
                className="woo-ai-assistant-btn woo-ai-assistant-btn-primary"
                onClick={this.handleRetry}
                disabled={retryCount >= 3}
              >
                {retryCount >= 3 ? 'Max Retries Reached' : 'Try Again'}
              </button>

              <button
                type="button"
                className="woo-ai-assistant-btn woo-ai-assistant-btn-secondary"
                onClick={this.handleReportError}
              >
                Report Issue
              </button>
            </div>
          </div>
        </div>
      );
    }

    return children;
  }
}

/**
 * Error Icon Component
 */
const ErrorIcon = () => (
  <svg
    width="48"
    height="48"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <circle
      cx="12"
      cy="12"
      r="10"
      stroke="currentColor"
      strokeWidth="2"
    />
    <line
      x1="12"
      y1="8"
      x2="12"
      y2="12"
      stroke="currentColor"
      strokeWidth="2"
    />
    <circle
      cx="12"
      cy="16"
      r="1"
      fill="currentColor"
    />
  </svg>
);

// PropTypes
WidgetErrorBoundary.propTypes = {
  children: PropTypes.node.isRequired,
  fallback: PropTypes.func
};

export default WidgetErrorBoundary;
