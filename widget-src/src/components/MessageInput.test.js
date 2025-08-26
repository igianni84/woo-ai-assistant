/**
 * MessageInput Component Tests
 * 
 * Comprehensive test suite for the MessageInput component
 * 
 * @package WooAiAssistant
 * @subpackage Components
 * @since 1.0.0
 * @author Claude Code Assistant
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import MessageInput from './MessageInput';

describe('MessageInput Component', () => {
  const mockOnSendMessage = jest.fn();
  const mockOnFocus = jest.fn();
  const mockOnBlur = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('Basic Rendering', () => {
    it('should render input form with default props', () => {
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const form = screen.getByRole('form');
      const textarea = screen.getByRole('textbox');
      const sendButton = screen.getByRole('button', { name: /send/i });
      
      expect(form).toBeInTheDocument();
      expect(textarea).toBeInTheDocument();
      expect(sendButton).toBeInTheDocument();
    });

    it('should display default placeholder text', () => {
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByPlaceholderText('Type your message...');
      expect(textarea).toBeInTheDocument();
    });

    it('should display custom placeholder text', () => {
      const customPlaceholder = 'Enter your question here...';
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          placeholder={customPlaceholder}
        />
      );
      
      const textarea = screen.getByPlaceholderText(customPlaceholder);
      expect(textarea).toBeInTheDocument();
    });

    it('should have proper form structure', () => {
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const form = screen.getByRole('form');
      const inputContainer = form.querySelector('.input-container');
      
      expect(form).toHaveClass('chat-input-form');
      expect(inputContainer).toBeInTheDocument();
    });
  });

  describe('Message Input and Validation', () => {
    it('should update input value when typing', () => {
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      fireEvent.change(textarea, { target: { value: 'Hello world' } });
      
      expect(textarea).toHaveValue('Hello world');
    });

    it('should respect maxLength limit', () => {
      const maxLength = 10;
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          maxLength={maxLength}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      fireEvent.change(textarea, { target: { value: 'This is a very long message that exceeds limit' } });
      
      // The component should limit the input
      expect(textarea.getAttribute('maxLength')).toBe(maxLength.toString());
    });

    it('should enable send button when message is entered', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      const sendButton = screen.getByRole('button', { name: /send/i });
      
      expect(sendButton).toBeDisabled();
      
      await user.type(textarea, 'Test message');
      
      expect(sendButton).toBeEnabled();
    });

    it('should disable send button with only whitespace', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      const sendButton = screen.getByRole('button', { name: /send/i });
      
      await user.type(textarea, '   ');
      
      expect(sendButton).toBeDisabled();
    });
  });

  describe('Message Submission', () => {
    it('should call onSendMessage when form is submitted', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      const form = screen.getByRole('form');
      
      await user.type(textarea, 'Test message');
      fireEvent.submit(form);
      
      expect(mockOnSendMessage).toHaveBeenCalledWith('Test message');
    });

    it('should clear input after sending message', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      const form = screen.getByRole('form');
      
      await user.type(textarea, 'Test message');
      fireEvent.submit(form);
      
      expect(textarea).toHaveValue('');
    });

    it('should send message on Enter key press', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      
      await user.type(textarea, 'Test message');
      await user.keyboard('{Enter}');
      
      expect(mockOnSendMessage).toHaveBeenCalledWith('Test message');
    });

    it('should not send message on Shift+Enter', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      
      await user.type(textarea, 'Test message');
      await user.keyboard('{Shift>}{Enter}{/Shift}');
      
      expect(mockOnSendMessage).not.toHaveBeenCalled();
    });

    it('should trim whitespace from message before sending', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      const form = screen.getByRole('form');
      
      await user.type(textarea, '  Test message  ');
      fireEvent.submit(form);
      
      expect(mockOnSendMessage).toHaveBeenCalledWith('Test message');
    });
  });

  describe('Disabled States', () => {
    it('should disable input when isDisabled is true', () => {
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          isDisabled={true}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      expect(textarea).toBeDisabled();
    });

    it('should disable send button when isTyping is true', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          isTyping={true}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      const sendButton = screen.getByRole('button', { name: /send/i });
      
      await user.type(textarea, 'Test message');
      
      expect(sendButton).toBeDisabled();
    });

    it('should not send message when disabled', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          isDisabled={true}
        />
      );
      
      const form = screen.getByRole('form');
      fireEvent.submit(form);
      
      expect(mockOnSendMessage).not.toHaveBeenCalled();
    });

    it('should not send message when typing', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          isTyping={true}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      const form = screen.getByRole('form');
      
      await user.type(textarea, 'Test message');
      fireEvent.submit(form);
      
      expect(mockOnSendMessage).not.toHaveBeenCalled();
    });
  });

  describe('Character Counter', () => {
    it('should show character counter when threshold is reached', async () => {
      const user = userEvent.setup();
      const showCounterAt = 5;
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          showCounterAt={showCounterAt}
          maxLength={10}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.type(textarea, 'Hello');
      
      const counter = screen.getByRole('status');
      expect(counter).toBeInTheDocument();
      expect(counter).toHaveTextContent('5/10');
    });

    it('should not show character counter below threshold', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          showCounterAt={10}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.type(textarea, 'Hello');
      
      expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('should apply warning class when characters are low', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          maxLength={10}
          showCounterAt={1}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.type(textarea, '1234567890');
      
      const counter = screen.getByRole('status');
      expect(counter).toHaveClass('counter-critical');
    });
  });

  describe('Focus Management', () => {
    it('should auto-focus when autoFocus is true', () => {
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          autoFocus={true}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      
      // Use setTimeout to wait for focus
      waitFor(() => {
        expect(textarea).toHaveFocus();
      });
    });

    it('should call onFocus callback when input is focused', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          onFocus={mockOnFocus}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.click(textarea);
      
      expect(mockOnFocus).toHaveBeenCalled();
    });

    it('should call onBlur callback when input loses focus', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          onBlur={mockOnBlur}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.click(textarea);
      await user.tab(); // Move focus away
      
      expect(mockOnBlur).toHaveBeenCalled();
    });

    it('should add focused class when input is focused', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      const inputContainer = textarea.closest('.input-container');
      
      await user.click(textarea);
      
      expect(inputContainer).toHaveClass('focused');
    });

    it('should refocus input after sending message', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      const form = screen.getByRole('form');
      
      await user.type(textarea, 'Test message');
      fireEvent.submit(form);
      
      // Wait for refocus
      await waitFor(() => {
        expect(textarea).toHaveFocus();
      });
    });
  });

  describe('Helper Text and Hints', () => {
    it('should show keyboard hint when focused', async () => {
      const user = userEvent.setup();
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByRole('textbox');
      await user.click(textarea);
      
      const helper = document.querySelector('.input-helper');
      expect(helper).toBeInTheDocument();
      expect(helper).toHaveTextContent(/Enter.*to send/i);
    });

    it('should not show helper text when disabled', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          isDisabled={true}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.click(textarea);
      
      const helper = document.querySelector('.input-helper');
      expect(helper).not.toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA labels', () => {
      render(<MessageInput onSendMessage={mockOnSendMessage} />);
      
      const textarea = screen.getByLabelText('Type your message');
      const form = screen.getByLabelText('Message input form');
      
      expect(textarea).toBeInTheDocument();
      expect(form).toBeInTheDocument();
    });

    it('should link textarea to character counter', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          showCounterAt={1}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.type(textarea, 'H');
      
      const counter = screen.getByRole('status');
      expect(textarea).toHaveAttribute('aria-describedby', 'char-counter');
      expect(counter).toHaveAttribute('id', 'char-counter');
    });

    it('should provide proper button titles', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput 
          onSendMessage={mockOnSendMessage} 
          isTyping={true}
        />
      );
      
      const sendButton = screen.getByRole('button', { name: /send/i });
      expect(sendButton).toHaveAttribute('title', 'Please wait for AI response');
      
      const textarea = screen.getByRole('textbox');
      await user.type(textarea, 'Test');
      
      expect(sendButton).toHaveAttribute('title', 'Please wait for AI response');
    });
  });

  describe('Component Integration', () => {
    it('should work with all props provided', async () => {
      const user = userEvent.setup();
      render(
        <MessageInput
          onSendMessage={mockOnSendMessage}
          isDisabled={false}
          isTyping={false}
          placeholder="Custom placeholder"
          maxLength={50}
          showCounterAt={40}
          autoFocus={true}
          onFocus={mockOnFocus}
          onBlur={mockOnBlur}
        />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.type(textarea, 'Test message');
      await user.keyboard('{Enter}');
      
      expect(mockOnSendMessage).toHaveBeenCalledWith('Test message');
    });

    it('should handle rapid state changes gracefully', async () => {
      const user = userEvent.setup();
      const { rerender } = render(
        <MessageInput onSendMessage={mockOnSendMessage} isTyping={false} />
      );
      
      const textarea = screen.getByRole('textbox');
      await user.type(textarea, 'Test');
      
      rerender(<MessageInput onSendMessage={mockOnSendMessage} isTyping={true} />);
      rerender(<MessageInput onSendMessage={mockOnSendMessage} isTyping={false} />);
      
      const sendButton = screen.getByRole('button', { name: /send/i });
      expect(sendButton).toBeEnabled();
    });
  });
});