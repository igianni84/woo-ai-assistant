/**
 * useKeyboardNavigation Hook
 * 
 * Custom React hook for managing keyboard navigation and accessibility
 * 
 * @package WooAiAssistant
 * @subpackage Hooks
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import { useEffect } from 'react';

export const useKeyboardNavigation = ({
  isVisible,
  onToggleVisibility,
  onEscape,
}) => {
  useEffect(() => {
    const handleKeyDown = (event) => {
      // Handle Escape key
      if (event.key === 'Escape' && isVisible && onEscape) {
        event.preventDefault();
        onEscape();
      }

      // Handle Ctrl+Shift+C or Cmd+Shift+C to toggle chat (accessibility shortcut)
      if (
        (event.ctrlKey || event.metaKey) && 
        event.shiftKey && 
        event.key.toLowerCase() === 'c' &&
        onToggleVisibility
      ) {
        event.preventDefault();
        onToggleVisibility();
      }

      // Handle Enter key for quick actions (can be extended later)
      if (event.key === 'Enter' && event.target.matches('[data-chat-action]')) {
        event.preventDefault();
        event.target.click();
      }
    };

    // Add event listener
    document.addEventListener('keydown', handleKeyDown);

    // Cleanup
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isVisible, onToggleVisibility, onEscape]);

  // Focus management for when widget opens/closes
  useEffect(() => {
    if (isVisible) {
      // When widget opens, focus the first focusable element
      const widget = document.querySelector('.woo-ai-assistant-app[data-visible="true"]');
      if (widget) {
        const firstFocusable = widget.querySelector(
          'button, input, textarea, select, [tabindex]:not([tabindex="-1"])'
        );
        if (firstFocusable) {
          firstFocusable.focus();
        }
      }
    }
  }, [isVisible]);
};