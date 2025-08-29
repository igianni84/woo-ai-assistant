/**
 * Chat Bundle Entry Point
 * 
 * Lazy-loaded chat functionality bundle.
 * Contains chat window, messaging, and conversation features.
 * 
 * @package WooAiAssistant
 * @subpackage Widget Chat
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Core chat components (lazy-loaded)
export { default as ChatWindow } from './components/ChatWindow';
export { default as Message } from './components/Message';
export { default as MessageInput } from './components/MessageInput';
export { default as TypingIndicator } from './components/TypingIndicator';

// Chat-specific hooks
export { default as useChat } from './hooks/useChat';
export { default as useKeyboardNavigation } from './hooks/useKeyboardNavigation';

// Streaming service for real-time chat
export { default as StreamingService } from './services/StreamingService';

// Chat-specific styles (loaded only when needed)
import('./styles/chat.css');

/**
 * Chat bundle initialization
 * Sets up chat-specific functionality when loaded
 */
export const initializeChatBundle = () => {
  // Register chat-specific event listeners
  document.addEventListener('woo-ai-assistant:chat-opened', () => {
    // Analytics tracking
    if (window.gtag) {
      window.gtag('event', 'chat_opened', {
        event_category: 'woo_ai_assistant',
        event_label: 'chat_interaction'
      });
    }
  });

  document.addEventListener('woo-ai-assistant:message-sent', (event) => {
    // Track message metrics
    if (window.gtag) {
      window.gtag('event', 'message_sent', {
        event_category: 'woo_ai_assistant',
        event_label: 'user_interaction',
        value: event.detail?.messageLength || 0
      });
    }
  });

  // Set up chat-specific performance monitoring
  if (window.wooAiAssistant?.performanceMonitoring) {
    window.wooAiAssistant.performanceMonitoring.startBenchmark('chat_bundle_loaded');
  }
};

// Auto-initialize when bundle is loaded
if (typeof window !== 'undefined') {
  initializeChatBundle();
}