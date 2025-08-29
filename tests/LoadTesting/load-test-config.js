/**
 * Load Testing Configuration
 * 
 * Configuration for load testing the Woo AI Assistant plugin using k6.
 * Tests various scenarios including chat interactions, API endpoints,
 * and concurrent user sessions.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

/**
 * Base configuration for all load tests
 */
export const baseConfig = {
  // WordPress site configuration
  baseUrl: __ENV.WP_BASE_URL || 'http://localhost:8888/wp',
  apiEndpoint: '/wp-json/woo-ai-assistant/v1',
  
  // Authentication for WordPress admin
  adminUser: __ENV.WP_ADMIN_USER || 'admin',
  adminPass: __ENV.WP_ADMIN_PASS || 'password',
  
  // Test user credentials
  testUser: {
    username: 'testcustomer',
    password: 'testpass123'
  },
  
  // API configuration
  apiTimeout: '30s',
  httpTimeout: '60s',
  
  // Test data
  testMessages: [
    'Hello, I need help finding products',
    'What are your best selling items?',
    'Can you help me with shipping information?',
    'I need assistance with my order',
    'Tell me about product warranties',
    'What payment methods do you accept?',
    'How can I track my order?',
    'I need help with returns'
  ],
  
  // Performance thresholds
  thresholds: {
    // HTTP request duration
    http_req_duration: ['p(95)<2000'], // 95% under 2s
    http_req_duration_chat: ['p(95)<5000'], // Chat responses under 5s
    
    // Request rate
    http_req_rate: ['rate>10'], // At least 10 requests/second
    
    // Error rate
    http_req_failed: ['rate<0.1'], // Less than 10% errors
    
    // WebSocket metrics (for real-time chat)
    ws_connecting: ['p(95)<1000'],
    ws_msgs_received: ['rate>5'],
    
    // Custom metrics
    chat_response_time: ['p(95)<3000'],
    successful_conversations: ['rate>0.9'],
  }
};

/**
 * Smoke test configuration - Quick verification
 */
export const smokeTestConfig = {
  stages: [
    { duration: '2m', target: 2 }, // Warm up with 2 users
    { duration: '1m', target: 2 }, // Stay at 2 users
  ],
  thresholds: {
    ...baseConfig.thresholds,
    http_req_duration: ['p(95)<1000'], // Stricter for smoke test
  }
};

/**
 * Load test configuration - Normal expected load
 */
export const loadTestConfig = {
  stages: [
    { duration: '5m', target: 20 }, // Ramp up to 20 users
    { duration: '10m', target: 20 }, // Stay at 20 users
    { duration: '5m', target: 0 }, // Ramp down
  ],
  thresholds: baseConfig.thresholds
};

/**
 * Stress test configuration - Beyond normal capacity
 */
export const stressTestConfig = {
  stages: [
    { duration: '5m', target: 50 }, // Ramp up to 50 users
    { duration: '10m', target: 50 }, // Stay at 50 users
    { duration: '5m', target: 100 }, // Spike to 100 users
    { duration: '5m', target: 100 }, // Stay at spike
    { duration: '10m', target: 0 }, // Gradual recovery
  ],
  thresholds: {
    ...baseConfig.thresholds,
    http_req_duration: ['p(95)<5000'], // More lenient under stress
    http_req_failed: ['rate<0.2'], // Allow higher error rate
  }
};

/**
 * Spike test configuration - Sudden load increases
 */
export const spikeTestConfig = {
  stages: [
    { duration: '2m', target: 10 }, // Normal load
    { duration: '1m', target: 200 }, // Sudden spike
    { duration: '2m', target: 10 }, // Back to normal
    { duration: '1m', target: 300 }, // Larger spike
    { duration: '2m', target: 10 }, // Recovery
  ],
  thresholds: {
    ...baseConfig.thresholds,
    http_req_duration: ['p(95)<8000'], // Very lenient for spikes
    http_req_failed: ['rate<0.3'], // Higher error tolerance
  }
};

/**
 * Endurance test configuration - Long-term stability
 */
export const enduranceTestConfig = {
  stages: [
    { duration: '10m', target: 20 }, // Ramp up
    { duration: '4h', target: 20 }, // Long steady load
    { duration: '10m', target: 0 }, // Ramp down
  ],
  thresholds: {
    ...baseConfig.thresholds,
    // Memory leak detection
    memory_usage: ['value<500'], // Memory under 500MB
    response_time_trend: ['slope<0.1'], // Response time shouldn't degrade
  }
};

/**
 * API-focused test configuration
 */
export const apiTestConfig = {
  stages: [
    { duration: '3m', target: 30 },
    { duration: '10m', target: 30 },
    { duration: '3m', target: 0 },
  ],
  thresholds: {
    ...baseConfig.thresholds,
    // API-specific thresholds
    http_req_duration_api: ['p(95)<1500'],
    api_success_rate: ['rate>0.95'],
  }
};

/**
 * Chat-focused test configuration
 */
export const chatTestConfig = {
  stages: [
    { duration: '5m', target: 15 },
    { duration: '15m', target: 15 },
    { duration: '5m', target: 0 },
  ],
  thresholds: {
    ...baseConfig.thresholds,
    // Chat-specific thresholds
    chat_response_time: ['p(95)<4000'],
    chat_success_rate: ['rate>0.90'],
    concurrent_chats: ['value<100'],
  }
};

/**
 * Database-focused test configuration
 */
export const databaseTestConfig = {
  stages: [
    { duration: '5m', target: 25 },
    { duration: '10m', target: 25 },
    { duration: '5m', target: 0 },
  ],
  thresholds: {
    ...baseConfig.thresholds,
    // Database-specific thresholds
    db_query_duration: ['p(95)<500'],
    db_connection_pool: ['value<20'],
    db_deadlocks: ['count<5'],
  }
};

/**
 * Get configuration by test type
 * 
 * @param {string} testType - Type of test to run
 * @returns {Object} Test configuration
 */
export function getConfig(testType) {
  const configs = {
    smoke: smokeTestConfig,
    load: loadTestConfig,
    stress: stressTestConfig,
    spike: spikeTestConfig,
    endurance: enduranceTestConfig,
    api: apiTestConfig,
    chat: chatTestConfig,
    database: databaseTestConfig
  };
  
  if (!configs[testType]) {
    throw new Error(`Unknown test type: ${testType}`);
  }
  
  return {
    ...baseConfig,
    ...configs[testType]
  };
}

/**
 * Test environment validation
 */
export function validateEnvironment() {
  const required = ['WP_BASE_URL'];
  const missing = required.filter(env => !__ENV[env]);
  
  if (missing.length > 0) {
    throw new Error(`Missing required environment variables: ${missing.join(', ')}`);
  }
}

/**
 * Generate random test data
 */
export function getRandomTestData() {
  return {
    message: baseConfig.testMessages[Math.floor(Math.random() * baseConfig.testMessages.length)],
    userId: Math.floor(Math.random() * 1000) + 1,
    sessionId: `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
    timestamp: new Date().toISOString()
  };
}