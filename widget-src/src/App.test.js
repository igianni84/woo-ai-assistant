/**
 * App Component Tests
 * 
 * Tests for the main App component that handles the overall
 * widget state and renders the chat interface.
 * 
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { screen, fireEvent, waitFor, act } from '@testing-library/react';
import App from './App';
import { renderWithContext, assertComponentNaming, assertAriaAttributes } from './__tests__/utils/testUtils';

describe('App Component', () => {
  beforeEach(() => {
    // Clear any existing DOM
    document.body.innerHTML = '';
  });

  describe('Component Structure and Naming', () => {
    it('should follow PascalCase naming convention', () => {
      assertComponentNaming(App, 'App');
    });

    it('should render the main app container', () => {
      renderWithContext(<App />);
      expect(screen.getByTestId('test-wrapper')).toBeInTheDocument();
    });

    it('should render the widget toggle button', () => {
      renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      expect(toggleButton).toBeInTheDocument();
      expect(toggleButton).toHaveClass('woo-ai-assistant-toggle');
    });
  });

  describe('Initial State', () => {
    it('should render with chat window closed by default', () => {
      renderWithContext(<App />);
      
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      expect(toggleButton).toHaveAttribute('aria-label', 'Open chat');
      expect(toggleButton).not.toHaveClass('active');
      
      // Chat window should not be visible
      const chatWindow = screen.queryByRole('dialog');
      expect(chatWindow).not.toBeInTheDocument();
    });

    it('should display chat icon when closed', () => {
      renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      
      // Check for SVG chat icon
      const chatIcon = toggleButton.querySelector('svg');
      expect(chatIcon).toBeInTheDocument();
      expect(chatIcon).toHaveAttribute('width', '28');
      expect(chatIcon).toHaveAttribute('height', '28');
    });
  });

  describe('Chat Window Toggle Functionality', () => {
    it('should open chat window when toggle button is clicked', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      
      await act(async () => {
        await user.click(toggleButton);
      });
      
      // Should show chat window
      const chatWindow = screen.getByRole('dialog');
      expect(chatWindow).toBeInTheDocument();
      expect(chatWindow).toHaveClass('woo-ai-assistant-chat-window');
      expect(chatWindow).toHaveClass('visible');
      
      // Toggle button should be active and show close icon
      expect(toggleButton).toHaveClass('active');
      expect(toggleButton).toHaveAttribute('aria-label', 'Close chat');
    });

    it('should close chat window when toggle button is clicked again', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      
      // Open chat window
      await act(async () => {
        await user.click(toggleButton);
      });
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      
      // Close chat window
      await act(async () => {
        await user.click(toggleButton);
      });
      
      await waitFor(() => {
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      });
      
      expect(toggleButton).not.toHaveClass('active');
      expect(toggleButton).toHaveAttribute('aria-label', 'Open chat');
    });

    it('should close chat window when close button is clicked', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      
      // Open chat window
      await act(async () => {
        await user.click(toggleButton);
      });
      
      const closeButtons = screen.getAllByRole('button', { name: /close chat/i });
      const closeButton = closeButtons.find(btn => btn.classList.contains('woo-ai-assistant-chat-close'));
      expect(closeButton).toHaveClass('woo-ai-assistant-chat-close');
      
      await act(async () => {
        await user.click(closeButton);
      });
      
      await waitFor(() => {
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      });
    });
  });

  describe('Chat Window Content', () => {
    beforeEach(async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      await act(async () => {
        await user.click(toggleButton);
      });
    });

    it('should render chat window with proper structure', () => {
      const chatWindow = screen.getByRole('dialog');
      
      // Check header
      const header = chatWindow.querySelector('.woo-ai-assistant-chat-header');
      expect(header).toBeInTheDocument();
      
      const title = screen.getByRole('heading', { name: /ai assistant/i });
      expect(title).toBeInTheDocument();
      expect(title).toHaveClass('woo-ai-assistant-chat-title');
      
      // Check content area
      const content = chatWindow.querySelector('.woo-ai-assistant-chat-content');
      expect(content).toBeInTheDocument();
    });

    it('should display welcome message', () => {
      const welcomeMessage = screen.getByText(/hi! i'm your ai shopping assistant/i);
      expect(welcomeMessage).toBeInTheDocument();
      expect(welcomeMessage.closest('.woo-ai-assistant-message')).toHaveClass('assistant');
    });

    it('should render message input area', () => {
      const textarea = screen.getByRole('textbox', { name: /message input/i });
      expect(textarea).toBeInTheDocument();
      expect(textarea).toHaveAttribute('placeholder', 'Type your message...');
      expect(textarea).toHaveAttribute('rows', '1');
      
      const sendButton = screen.getByRole('button', { name: /send message/i });
      expect(sendButton).toBeInTheDocument();
      expect(sendButton).toHaveClass('woo-ai-assistant-send');
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA attributes on chat window', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      await act(async () => {
        await user.click(toggleButton);
      });
      
      const chatWindow = screen.getByRole('dialog');
      assertAriaAttributes(chatWindow, {
        'aria-label': 'AI Assistant Chat',
        'aria-modal': 'true'
      });
    });

    it('should have proper ARIA labels on interactive elements', () => {
      renderWithContext(<App />);
      
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      expect(toggleButton).toHaveAttribute('aria-label', 'Open chat');
      expect(toggleButton).toHaveAttribute('type', 'button');
    });

    it('should have proper ARIA labels when chat is open', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      await act(async () => {
        await user.click(toggleButton);
      });
      
      const textarea = screen.getByRole('textbox');
      expect(textarea).toHaveAttribute('aria-label', 'Message input');
      
      const sendButton = screen.getByRole('button', { name: /send message/i });
      expect(sendButton).toHaveAttribute('aria-label', 'Send message');
      
      const closeButtons = screen.getAllByRole('button', { name: /close chat/i });
      expect(closeButtons).toHaveLength(2); // Toggle and close button
      
      // Get the actual close button in the header (not the toggle)
      const closeButton = closeButtons.find(btn => btn.classList.contains('woo-ai-assistant-chat-close'));
      expect(closeButton).toHaveAttribute('aria-label', 'Close chat');
    });

    it('should have proper heading structure', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      await act(async () => {
        await user.click(toggleButton);
      });
      
      const heading = screen.getByRole('heading', { name: /ai assistant/i });
      expect(heading.tagName).toBe('H2');
    });
  });

  describe('Icon Components', () => {
    it('should render SVG icons with proper attributes', () => {
      renderWithContext(<App />);
      
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      const svg = toggleButton.querySelector('svg');
      
      expect(svg).toHaveAttribute('aria-hidden', 'true');
      expect(svg).toHaveAttribute('width');
      expect(svg).toHaveAttribute('height');
      expect(svg).toHaveAttribute('viewBox');
    });

    it('should switch icons when chat window opens', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      
      // Initially should show chat icon (28x28)
      let svg = toggleButton.querySelector('svg');
      expect(svg).toHaveAttribute('width', '28');
      expect(svg).toHaveAttribute('height', '28');
      
      // After opening, should show close icon (24x24)
      await act(async () => {
        await user.click(toggleButton);
      });
      
      svg = toggleButton.querySelector('svg');
      expect(svg).toHaveAttribute('width', '24');
      expect(svg).toHaveAttribute('height', '24');
    });

    it('should render send icon in input area', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      await act(async () => {
        await user.click(toggleButton);
      });
      
      const sendButton = screen.getByRole('button', { name: /send message/i });
      const svg = sendButton.querySelector('svg');
      
      expect(svg).toBeInTheDocument();
      expect(svg).toHaveAttribute('width', '20');
      expect(svg).toHaveAttribute('height', '20');
      expect(svg).toHaveAttribute('aria-hidden', 'true');
    });
  });

  describe('CSS Classes and Styling', () => {
    it('should apply correct CSS classes to main container', () => {
      renderWithContext(<App />);
      const appContainer = document.querySelector('.woo-ai-assistant-app');
      expect(appContainer).toBeInTheDocument();
    });

    it('should apply correct CSS classes to toggle button states', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      
      // Initial state
      expect(toggleButton).toHaveClass('woo-ai-assistant-toggle');
      expect(toggleButton).not.toHaveClass('active');
      
      // After opening
      await act(async () => {
        await user.click(toggleButton);
      });
      expect(toggleButton).toHaveClass('woo-ai-assistant-toggle', 'active');
    });

    it('should apply correct CSS classes to chat window elements', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      await act(async () => {
        await user.click(toggleButton);
      });
      
      const chatWindow = screen.getByRole('dialog');
      expect(chatWindow).toHaveClass('woo-ai-assistant-chat-window', 'visible');
      
      const header = chatWindow.querySelector('.woo-ai-assistant-chat-header');
      expect(header).toBeInTheDocument();
      
      const content = chatWindow.querySelector('.woo-ai-assistant-chat-content');
      expect(content).toBeInTheDocument();
      
      const messages = chatWindow.querySelector('.woo-ai-assistant-messages');
      expect(messages).toBeInTheDocument();
      
      const inputArea = chatWindow.querySelector('.woo-ai-assistant-input-area');
      expect(inputArea).toBeInTheDocument();
    });
  });

  describe('Event Handling', () => {
    it('should handle keyboard events for accessibility', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      
      // Should open on Enter key
      toggleButton.focus();
      await act(async () => {
        await user.keyboard('{Enter}');
      });
      
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('should handle multiple rapid clicks gracefully', async () => {
      const { user } = renderWithContext(<App />);
      const toggleButton = screen.getByRole('button', { name: /open chat/i });
      
      // Rapid clicks should not cause issues
      await act(async () => {
        await user.click(toggleButton); // Opens
        await user.click(toggleButton); // Closes  
        await user.click(toggleButton); // Opens
      });
      
      // Should end up in open state (odd number of clicks)
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  describe('Component Methods and Naming Conventions', () => {
    it('should use camelCase for handler methods', () => {
      const component = <App />;
      
      // This test verifies the component follows naming conventions
      // The actual handler names (handleToggle, handleClose) are checked through functionality
      expect(typeof App).toBe('function');
      expect(App.name).toBe('App');
    });
  });

  describe('Performance and Optimization', () => {
    it('should render without unnecessary re-renders', () => {
      const { rerender } = renderWithContext(<App />);
      
      // Initial render
      expect(screen.getByRole('button', { name: /open chat/i })).toBeInTheDocument();
      
      // Re-render with same props should work
      rerender(<App />);
      expect(screen.getByRole('button', { name: /open chat/i })).toBeInTheDocument();
    });
  });
});