/**
 * Chat Load Test Scenario
 * 
 * Load testing scenario focused on chat functionality, simulating
 * multiple concurrent conversations with the AI assistant.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import http from 'k6/http';
import ws from 'k6/ws';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { getConfig, getRandomTestData, validateEnvironment } from '../load-test-config.js';

// Validate environment before starting
validateEnvironment();

// Get configuration
const config = getConfig('chat');
export const options = {
  stages: config.stages,
  thresholds: config.thresholds,
};

// Custom metrics for chat testing
const chatResponseTime = new Trend('chat_response_time');
const chatSuccessRate = new Rate('chat_success_rate');
const concurrentChats = new Counter('concurrent_chats');
const messagesSent = new Counter('messages_sent');
const messagesReceived = new Counter('messages_received');

/**
 * Setup function - runs once per VU
 */
export function setup() {
  console.log('Setting up chat load test environment...');
  
  // Verify the chat endpoint is accessible
  const response = http.get(`${config.baseUrl}${config.apiEndpoint}/health`);
  check(response, {
    'health endpoint accessible': (r) => r.status === 200,
  });
  
  return {
    baseUrl: config.baseUrl,
    apiEndpoint: config.apiEndpoint,
    startTime: Date.now()
  };
}

/**
 * Default function - main test scenario
 */
export default function(data) {
  // Get random test data for this iteration
  const testData = getRandomTestData();
  
  // Simulate different user types
  const userTypes = ['guest', 'registered', 'returning'];
  const userType = userTypes[Math.floor(Math.random() * userTypes.length)];
  
  // Run chat conversation
  if (Math.random() < 0.7) {
    // 70% HTTP/REST API conversations
    runHttpChatConversation(data, testData, userType);
  } else {
    // 30% WebSocket conversations (real-time)
    runWebSocketChatConversation(data, testData, userType);
  }
  
  // Random think time between conversations
  sleep(Math.random() * 5 + 1); // 1-6 seconds
}

/**
 * HTTP-based chat conversation
 */
function runHttpChatConversation(data, testData, userType) {
  const startTime = Date.now();
  concurrentChats.add(1);
  
  try {
    // 1. Initialize conversation
    const initResponse = initializeConversation(data, testData, userType);
    if (!initResponse.success) return;
    
    const conversationId = initResponse.conversationId;
    
    // 2. Send 2-5 messages in the conversation
    const messageCount = Math.floor(Math.random() * 4) + 2;
    
    for (let i = 0; i < messageCount; i++) {
      const message = config.testMessages[Math.floor(Math.random() * config.testMessages.length)];
      
      const success = sendChatMessage(data, conversationId, message, testData);
      if (!success) break;
      
      // Short pause between messages
      sleep(Math.random() * 2 + 0.5);
    }
    
    // 3. End conversation (optional)
    if (Math.random() < 0.3) {
      endConversation(data, conversationId);
    }
    
    const totalTime = Date.now() - startTime;
    chatResponseTime.add(totalTime);
    chatSuccessRate.add(1);
    
  } catch (error) {
    console.error(`Chat conversation failed: ${error.message}`);
    chatSuccessRate.add(0);
  } finally {
    concurrentChats.add(-1);
  }
}

/**
 * WebSocket-based chat conversation
 */
function runWebSocketChatConversation(data, testData, userType) {
  const startTime = Date.now();
  concurrentChats.add(1);
  
  try {
    const wsUrl = `ws://localhost:8888/wp-json/woo-ai-assistant/v1/chat/ws?session=${testData.sessionId}`;
    
    const response = ws.connect(wsUrl, {}, function (socket) {
      socket.on('open', () => {
        console.log('WebSocket connection established');
        
        // Send authentication if registered user
        if (userType === 'registered') {
          socket.send(JSON.stringify({
            type: 'auth',
            data: { userId: testData.userId }
          }));
        }
        
        // Send initial message
        const initialMessage = config.testMessages[0];
        socket.send(JSON.stringify({
          type: 'message',
          data: { message: initialMessage, timestamp: testData.timestamp }
        }));
        messagesSent.add(1);
      });
      
      socket.on('message', (message) => {
        const data = JSON.parse(message);
        messagesReceived.add(1);
        
        if (data.type === 'response') {
          const responseTime = Date.now() - startTime;
          chatResponseTime.add(responseTime);
          
          // Send follow-up message 30% of the time
          if (Math.random() < 0.3) {
            const followUp = config.testMessages[Math.floor(Math.random() * config.testMessages.length)];
            socket.send(JSON.stringify({
              type: 'message',
              data: { message: followUp, timestamp: new Date().toISOString() }
            }));
            messagesSent.add(1);
          }
        }
      });
      
      socket.on('error', (error) => {
        console.error(`WebSocket error: ${error}`);
        chatSuccessRate.add(0);
      });
      
      socket.on('close', () => {
        console.log('WebSocket connection closed');
      });
      
      // Keep connection alive for conversation duration
      socket.setTimeout(() => {
        socket.close();
      }, 30000); // 30 seconds max
    });
    
    check(response, {
      'WebSocket connection successful': (r) => r && r.status === 101,
    });
    
  } catch (error) {
    console.error(`WebSocket conversation failed: ${error.message}`);
    chatSuccessRate.add(0);
  } finally {
    concurrentChats.add(-1);
  }
}

/**
 * Initialize a new chat conversation
 */
function initializeConversation(data, testData, userType) {
  const params = {
    headers: {
      'Content-Type': 'application/json',
      'User-Agent': `LoadTest-${userType}`,
    },
    timeout: config.apiTimeout,
  };
  
  const payload = JSON.stringify({
    user_type: userType,
    session_id: testData.sessionId,
    user_id: userType === 'registered' ? testData.userId : null,
    context: {
      page: 'shop',
      product_id: null,
      cart_items: []
    }
  });
  
  const response = http.post(
    `${data.baseUrl}${data.apiEndpoint}/chat/init`,
    payload,
    params
  );
  
  const success = check(response, {
    'conversation init status 200': (r) => r.status === 200,
    'conversation init has ID': (r) => {
      try {
        const json = JSON.parse(r.body);
        return json.conversation_id !== undefined;
      } catch {
        return false;
      }
    },
  });
  
  if (success) {
    const responseData = JSON.parse(response.body);
    return {
      success: true,
      conversationId: responseData.conversation_id
    };
  }
  
  return { success: false };
}

/**
 * Send a chat message and wait for response
 */
function sendChatMessage(data, conversationId, message, testData) {
  const startTime = Date.now();
  
  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
    timeout: config.httpTimeout,
  };
  
  const payload = JSON.stringify({
    conversation_id: conversationId,
    message: message,
    timestamp: testData.timestamp,
    context: {
      page: 'shop'
    }
  });
  
  const response = http.post(
    `${data.baseUrl}${data.apiEndpoint}/chat/message`,
    payload,
    params
  );
  
  const responseTime = Date.now() - startTime;
  chatResponseTime.add(responseTime);
  messagesSent.add(1);
  
  const success = check(response, {
    'message status 200': (r) => r.status === 200,
    'response has content': (r) => {
      try {
        const json = JSON.parse(r.body);
        return json.response && json.response.length > 0;
      } catch {
        return false;
      }
    },
    'response time acceptable': (r) => responseTime < 5000,
  });
  
  if (success) {
    messagesReceived.add(1);
  }
  
  return success;
}

/**
 * End a chat conversation
 */
function endConversation(data, conversationId) {
  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
    timeout: config.apiTimeout,
  };
  
  const payload = JSON.stringify({
    conversation_id: conversationId,
    reason: 'user_ended'
  });
  
  const response = http.post(
    `${data.baseUrl}${data.apiEndpoint}/chat/end`,
    payload,
    params
  );
  
  check(response, {
    'conversation end status 200': (r) => r.status === 200,
  });
}

/**
 * Teardown function - runs once after all iterations
 */
export function teardown(data) {
  console.log('Chat load test completed');
  console.log(`Test duration: ${(Date.now() - data.startTime) / 1000}s`);
}