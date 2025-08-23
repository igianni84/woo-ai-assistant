/**
 * API Service
 * 
 * Service layer for communicating with the WordPress REST API
 * 
 * @package WooAiAssistant
 * @subpackage Services
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React, { createContext, useContext } from 'react';
import PropTypes from 'prop-types';

// API Context
const ApiContext = createContext(null);

// Custom hook to use the API service
export const useApi = () => {
  const context = useContext(ApiContext);
  if (!context) {
    throw new Error('useApi must be used within an ApiProvider');
  }
  return context;
};

// API Provider Component
export const ApiProvider = ({ children, baseUrl, nonce, debug = false }) => {
  // Create API service instance
  const api = {
    baseUrl,
    nonce,
    debug,

    // Generic request method
    async request(endpoint, options = {}) {
      const url = `${baseUrl}/${endpoint.replace(/^\//, '')}`;
      const config = {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
          ...options.headers,
        },
        ...options,
      };

      if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
      }

      try {
        if (debug) {
          console.log('API Request:', { url, config });
        }

        const response = await fetch(url, config);
        const data = await response.json();

        if (debug) {
          console.log('API Response:', { url, response, data });
        }

        if (!response.ok) {
          throw new Error(data.message || 'API request failed');
        }

        return data;
      } catch (error) {
        if (debug) {
          console.error('API Error:', { url, error });
        }
        throw error;
      }
    },

    // Placeholder methods - will be implemented in later tasks
    async sendMessage(message, conversationId) {
      // Placeholder implementation
      return this.request('chat/send', {
        method: 'POST',
        body: { message, conversationId },
      });
    },

    async getConversation(conversationId) {
      // Placeholder implementation
      return this.request(`chat/conversation/${conversationId}`);
    },

    async getConversations() {
      // Placeholder implementation
      return this.request('chat/conversations');
    },
  };

  return (
    <ApiContext.Provider value={api}>
      {children}
    </ApiContext.Provider>
  );
};

ApiProvider.propTypes = {
  children: PropTypes.node.isRequired,
  baseUrl: PropTypes.string.isRequired,
  nonce: PropTypes.string.isRequired,
  debug: PropTypes.bool,
};

ApiProvider.defaultProps = {
  debug: false,
};