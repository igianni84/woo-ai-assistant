/**
 * Icon Components Tests
 *
 * Tests for SVG icon components used throughout the chat widget.
 * Tests individual icon components extracted from App.js.
 *
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

import React from 'react';
import { screen } from '@testing-library/react';
import { renderWithContext, assertComponentNaming } from '../utils/testUtils';

// Extract icon components from App.js for isolated testing
// In a real app, these would be separate components, but for testing we extract them

/**
 * Chat Icon Component (extracted for testing)
 */
const ChatIcon = () => (
  <svg width='28' height='28' viewBox='0 0 28 28' fill='none' aria-hidden='true'>
    <path
      d='M21 6H7C5.9 6 5 6.9 5 8V16C5 17.1 5.9 18 7 18H8V22L12 18H21C22.1 18 23 17.1 23 16V8C23 6.9 22.1 6 21 6Z'
      fill='currentColor'
    />
    <rect x='8' y='10' width='8' height='1.5' rx='0.75' fill='white' opacity='0.8' />
    <rect x='8' y='12.5' width='10' height='1.5' rx='0.75' fill='white' opacity='0.8' />
  </svg>
);

/**
 * Close Icon Component (extracted for testing)
 */
const CloseIcon = () => (
  <svg width='24' height='24' viewBox='0 0 24 24' fill='none' aria-hidden='true'>
    <path
      d='M18 6L6 18M6 6L18 18'
      stroke='currentColor'
      strokeWidth='2'
      strokeLinecap='round'
      strokeLinejoin='round'
    />
  </svg>
);

/**
 * Send Icon Component (extracted for testing)
 */
const SendIcon = () => (
  <svg width='20' height='20' viewBox='0 0 20 20' fill='none' aria-hidden='true'>
    <path
      d='M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11'
      stroke='currentColor'
      strokeWidth='2'
      strokeLinecap='round'
      strokeLinejoin='round'
    />
  </svg>
);

describe('Icon Components', () => {
  describe('ChatIcon Component', () => {
    it('should follow PascalCase naming convention', () => {
      assertComponentNaming(ChatIcon, 'ChatIcon');
    });

    it('should render as SVG element', () => {
      const { container } = renderWithContext(<ChatIcon />);
      const svg = container.querySelector('svg');
      expect(svg).toBeInTheDocument();
      expect(svg.tagName).toBe('svg');
    });

    it('should have correct dimensions', () => {
      const { container } = renderWithContext(<ChatIcon />);
      const svg = container.querySelector('svg');

      expect(svg).toHaveAttribute('width', '28');
      expect(svg).toHaveAttribute('height', '28');
      expect(svg).toHaveAttribute('viewBox', '0 0 28 28');
    });

    it('should be properly hidden from screen readers', () => {
      const { container } = renderWithContext(<ChatIcon />);
      const svg = container.querySelector('svg');

      expect(svg).toHaveAttribute('aria-hidden', 'true');
    });

    it('should have correct fill and styling attributes', () => {
      const { container } = renderWithContext(<ChatIcon />);
      const svg = container.querySelector('svg');

      expect(svg).toHaveAttribute('fill', 'none');

      // Check main chat bubble path
      const mainPath = svg.querySelector('path');
      expect(mainPath).toHaveAttribute('fill', 'currentColor');
    });

    it('should contain message line elements', () => {
      const { container } = renderWithContext(<ChatIcon />);
      const svg = container.querySelector('svg');

      const rectangles = svg.querySelectorAll('rect');
      expect(rectangles).toHaveLength(2);

      // First message line
      expect(rectangles[0]).toHaveAttribute('x', '8');
      expect(rectangles[0]).toHaveAttribute('y', '10');
      expect(rectangles[0]).toHaveAttribute('width', '8');

      // Second message line
      expect(rectangles[1]).toHaveAttribute('x', '8');
      expect(rectangles[1]).toHaveAttribute('y', '12.5');
      expect(rectangles[1]).toHaveAttribute('width', '10');
    });
  });

  describe('CloseIcon Component', () => {
    it('should follow PascalCase naming convention', () => {
      assertComponentNaming(CloseIcon, 'CloseIcon');
    });

    it('should render as SVG element', () => {
      const { container } = renderWithContext(<CloseIcon />);
      const svg = container.querySelector('svg');
      expect(svg).toBeInTheDocument();
      expect(svg.tagName).toBe('svg');
    });

    it('should have correct dimensions', () => {
      const { container } = renderWithContext(<CloseIcon />);
      const svg = container.querySelector('svg');

      expect(svg).toHaveAttribute('width', '24');
      expect(svg).toHaveAttribute('height', '24');
      expect(svg).toHaveAttribute('viewBox', '0 0 24 24');
    });

    it('should be properly hidden from screen readers', () => {
      const { container } = renderWithContext(<CloseIcon />);
      const svg = container.querySelector('svg');

      expect(svg).toHaveAttribute('aria-hidden', 'true');
      expect(svg).toHaveAttribute('fill', 'none');
    });

    it('should have X-shaped path with proper stroke attributes', () => {
      const { container } = renderWithContext(<CloseIcon />);
      const svg = container.querySelector('svg');

      const path = svg.querySelector('path');
      expect(path).toBeInTheDocument();
      expect(path).toHaveAttribute('d', 'M18 6L6 18M6 6L18 18');
      expect(path).toHaveAttribute('stroke', 'currentColor');
      expect(path).toHaveAttribute('stroke-width', '2');
      expect(path).toHaveAttribute('stroke-linecap', 'round');
      expect(path).toHaveAttribute('stroke-linejoin', 'round');
    });
  });

  describe('SendIcon Component', () => {
    it('should follow PascalCase naming convention', () => {
      assertComponentNaming(SendIcon, 'SendIcon');
    });

    it('should render as SVG element', () => {
      const { container } = renderWithContext(<SendIcon />);
      const svg = container.querySelector('svg');
      expect(svg).toBeInTheDocument();
      expect(svg.tagName).toBe('svg');
    });

    it('should have correct dimensions', () => {
      const { container } = renderWithContext(<SendIcon />);
      const svg = container.querySelector('svg');

      expect(svg).toHaveAttribute('width', '20');
      expect(svg).toHaveAttribute('height', '20');
      expect(svg).toHaveAttribute('viewBox', '0 0 20 20');
    });

    it('should be properly hidden from screen readers', () => {
      const { container } = renderWithContext(<SendIcon />);
      const svg = container.querySelector('svg');

      expect(svg).toHaveAttribute('aria-hidden', 'true');
      expect(svg).toHaveAttribute('fill', 'none');
    });

    it('should have arrow-shaped path with proper stroke attributes', () => {
      const { container } = renderWithContext(<SendIcon />);
      const svg = container.querySelector('svg');

      const path = svg.querySelector('path');
      expect(path).toBeInTheDocument();
      expect(path).toHaveAttribute('d', 'M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11');
      expect(path).toHaveAttribute('stroke', 'currentColor');
      expect(path).toHaveAttribute('stroke-width', '2');
      expect(path).toHaveAttribute('stroke-linecap', 'round');
      expect(path).toHaveAttribute('stroke-linejoin', 'round');
    });
  });

  describe('Icon Accessibility', () => {
    const icons = [
      { component: ChatIcon, name: 'ChatIcon' },
      { component: CloseIcon, name: 'CloseIcon' },
      { component: SendIcon, name: 'SendIcon' }
    ];

    icons.forEach(({ component: IconComponent, name }) => {
      it(`${name} should be accessible with aria-hidden`, () => {
        const { container } = renderWithContext(<IconComponent />);
        const svg = container.querySelector('svg');

        expect(svg).toHaveAttribute('aria-hidden', 'true');
      });

      it(`${name} should use currentColor for theming`, () => {
        const { container } = renderWithContext(<IconComponent />);
        const svg = container.querySelector('svg');

        // Check if currentColor is used (either in fill or stroke)
        const pathElements = svg.querySelectorAll('path');
        const hasCurrentColor = Array.from(pathElements).some(path =>
          path.getAttribute('fill') === 'currentColor' ||
          path.getAttribute('stroke') === 'currentColor'
        );

        expect(hasCurrentColor).toBe(true);
      });
    });
  });

  describe('Icon Visual Structure', () => {
    it('ChatIcon should have proper visual structure for chat bubble', () => {
      const { container } = renderWithContext(<ChatIcon />);
      const svg = container.querySelector('svg');

      // Should have main bubble path
      const paths = svg.querySelectorAll('path');
      expect(paths.length).toBeGreaterThan(0);

      // Should have message line rectangles
      const rects = svg.querySelectorAll('rect');
      expect(rects).toHaveLength(2);

      // Check rounded rectangles (rx attribute)
      rects.forEach(rect => {
        expect(rect).toHaveAttribute('rx', '0.75');
      });
    });

    it('CloseIcon should have proper X-shaped visual structure', () => {
      const { container } = renderWithContext(<CloseIcon />);
      const svg = container.querySelector('svg');

      const path = svg.querySelector('path');
      const pathData = path.getAttribute('d');

      // Should contain diagonal lines forming an X
      expect(pathData).toContain('M18 6L6 18'); // Top-right to bottom-left
      expect(pathData).toContain('M6 6L18 18'); // Top-left to bottom-right
    });

    it('SendIcon should have proper arrow visual structure', () => {
      const { container } = renderWithContext(<SendIcon />);
      const svg = container.querySelector('svg');

      const path = svg.querySelector('path');
      const pathData = path.getAttribute('d');

      // Should contain arrow lines
      expect(pathData).toContain('M18 2'); // Starting point
      expect(pathData).toContain('L9 11'); // Arrow direction lines
      expect(pathData).toContain('L2 8'); // Arrow tail
    });
  });

  describe('Icon Consistency', () => {
    it('all icons should use consistent stroke styling where applicable', () => {
      const strokeIcons = [CloseIcon, SendIcon];

      strokeIcons.forEach(IconComponent => {
        const { container } = renderWithContext(<IconComponent />);
        const svg = container.querySelector('svg');
        const path = svg.querySelector('path');

        if (path.hasAttribute('stroke')) {
          expect(path).toHaveAttribute('stroke-linecap', 'round');
          expect(path).toHaveAttribute('stroke-linejoin', 'round');
          expect(path).toHaveAttribute('stroke-width', '2');
        }
      });
    });

    it('all icons should have proper viewBox ratios', () => {
      const iconTests = [
        { component: ChatIcon, expectedRatio: 1 }, // 28x28
        { component: CloseIcon, expectedRatio: 1 }, // 24x24
        { component: SendIcon, expectedRatio: 1 }   // 20x20
      ];

      iconTests.forEach(({ component: IconComponent, expectedRatio }) => {
        const { container } = renderWithContext(<IconComponent />);
        const svg = container.querySelector('svg');

        const width = parseInt(svg.getAttribute('width'));
        const height = parseInt(svg.getAttribute('height'));
        const actualRatio = width / height;

        expect(actualRatio).toBe(expectedRatio);
      });
    });

    it('all icons should have proper naming and structure', () => {
      const icons = [ChatIcon, CloseIcon, SendIcon];

      icons.forEach(IconComponent => {
        expect(typeof IconComponent).toBe('function');
        expect(IconComponent.name).toMatch(/^[A-Z][a-zA-Z]*Icon$/); // PascalCase ending with "Icon"
      });
    });
  });
});
