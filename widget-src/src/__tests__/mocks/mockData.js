/**
 * Mock Data for React Component Testing
 * 
 * Provides standardized mock data for testing React components
 * in the Woo AI Assistant widget.
 * 
 * @package WooAiAssistant
 * @subpackage Testing
 * @since 1.0.0
 */

/**
 * Mock conversation data
 */
export const mockConversation = {
  id: 'conv-12345',
  userId: 123,
  status: 'active',
  createdAt: '2024-08-30T10:00:00Z',
  updatedAt: '2024-08-30T10:30:00Z',
  messages: [
    {
      id: 'msg-1',
      type: 'assistant',
      content: 'Hi! I\'m your AI shopping assistant. How can I help you today?',
      timestamp: '2024-08-30T10:00:00Z'
    },
    {
      id: 'msg-2',
      type: 'user',
      content: 'I\'m looking for a new laptop',
      timestamp: '2024-08-30T10:01:00Z'
    },
    {
      id: 'msg-3',
      type: 'assistant',
      content: 'Great! I can help you find the perfect laptop. What will you primarily use it for?',
      timestamp: '2024-08-30T10:01:30Z'
    }
  ]
};

/**
 * Mock user data
 */
export const mockUser = {
  id: 123,
  name: 'John Doe',
  email: 'john.doe@example.com',
  isLoggedIn: true,
  capabilities: ['customer'],
  preferences: {
    theme: 'light',
    language: 'en'
  }
};

/**
 * Mock product data
 */
export const mockProducts = [
  {
    id: 101,
    name: 'Gaming Laptop Pro',
    price: 1299.99,
    currency: 'USD',
    description: 'High-performance gaming laptop with RTX graphics',
    image: 'https://example.com/laptop-pro.jpg',
    inStock: true,
    categories: ['Electronics', 'Computers', 'Laptops'],
    attributes: {
      brand: 'TechBrand',
      model: 'GLP-2024',
      processor: 'Intel i7',
      ram: '16GB',
      storage: '1TB SSD'
    }
  },
  {
    id: 102,
    name: 'Business Ultrabook',
    price: 899.99,
    currency: 'USD',
    description: 'Lightweight ultrabook perfect for business professionals',
    image: 'https://example.com/ultrabook.jpg',
    inStock: true,
    categories: ['Electronics', 'Computers', 'Laptops'],
    attributes: {
      brand: 'ProBrand',
      model: 'BU-2024',
      processor: 'Intel i5',
      ram: '8GB',
      storage: '512GB SSD'
    }
  }
];

/**
 * Mock chat messages with different types
 */
export const mockMessages = [
  {
    id: 'msg-welcome',
    type: 'assistant',
    content: 'Welcome to our store! How can I assist you today?',
    timestamp: '2024-08-30T09:00:00Z',
    metadata: {
      isWelcome: true
    }
  },
  {
    id: 'msg-user-query',
    type: 'user',
    content: 'I need help choosing a smartphone',
    timestamp: '2024-08-30T09:01:00Z'
  },
  {
    id: 'msg-product-suggestion',
    type: 'assistant',
    content: 'I found some great smartphones for you!',
    timestamp: '2024-08-30T09:01:30Z',
    products: [
      {
        id: 201,
        name: 'Smartphone X',
        price: 699.99,
        image: 'https://example.com/phone-x.jpg'
      }
    ]
  },
  {
    id: 'msg-typing',
    type: 'assistant',
    content: '',
    timestamp: '2024-08-30T09:02:00Z',
    isTyping: true
  }
];

/**
 * Mock API responses
 */
export const mockApiResponses = {
  success: {
    data: { message: 'Operation completed successfully' },
    success: true,
    status: 200
  },
  error: {
    data: { message: 'An error occurred' },
    success: false,
    status: 400
  },
  conversationHistory: {
    data: {
      conversation: mockConversation,
      messages: mockMessages
    },
    success: true,
    status: 200
  },
  productSearch: {
    data: {
      products: mockProducts,
      total: 2,
      page: 1
    },
    success: true,
    status: 200
  }
};

/**
 * Mock WordPress settings
 */
export const mockWordPressSettings = {
  siteUrl: 'https://example.com',
  adminUrl: 'https://example.com/wp-admin',
  ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
  restUrl: 'https://example.com/wp-json',
  nonce: 'test-nonce-12345',
  currentUser: mockUser,
  woocommerce: {
    currency: 'USD',
    currencySymbol: '$',
    decimals: 2,
    priceFormat: '%1$s%2$s'
  }
};

/**
 * Mock widget settings
 */
export const mockWidgetSettings = {
  position: 'bottom-right',
  theme: 'light',
  primaryColor: '#007cba',
  secondaryColor: '#666666',
  animation: 'slide',
  welcomeMessage: 'Hi! How can I help you today?',
  placeholder: 'Type your message...',
  maxMessages: 100,
  typingDelay: 1000,
  enabled: true,
  showOnPages: ['shop', 'product', 'cart'],
  hideForLoggedOut: false
};

/**
 * Mock event data for testing user interactions
 */
export const mockEvents = {
  click: {
    type: 'click',
    preventDefault: jest.fn(),
    stopPropagation: jest.fn(),
    target: { tagName: 'BUTTON' }
  },
  keyPress: {
    type: 'keypress',
    key: 'Enter',
    keyCode: 13,
    preventDefault: jest.fn(),
    stopPropagation: jest.fn()
  },
  input: {
    type: 'input',
    target: { value: 'test message' },
    preventDefault: jest.fn()
  },
  resize: {
    type: 'resize',
    target: window
  }
};

/**
 * Mock localStorage data
 */
export const mockLocalStorage = {
  'woo-ai-assistant-conversation': JSON.stringify({
    id: 'conv-stored',
    lastMessage: 'Hello there!',
    timestamp: '2024-08-30T08:00:00Z'
  }),
  'woo-ai-assistant-preferences': JSON.stringify({
    theme: 'dark',
    position: 'bottom-left',
    soundEnabled: false
  })
};

/**
 * Mock error objects for testing error handling
 */
export const mockErrors = {
  network: new Error('Network request failed'),
  validation: new Error('Validation failed: Required field missing'),
  auth: new Error('Authentication required'),
  api: new Error('API returned error: 500 Internal Server Error'),
  timeout: new Error('Request timeout')
};

/**
 * Mock component props for different scenarios
 */
export const mockProps = {
  chatWindow: {
    isOpen: true,
    onClose: jest.fn(),
    onSend: jest.fn(),
    messages: mockMessages,
    isLoading: false,
    error: null
  },
  messageInput: {
    value: '',
    onChange: jest.fn(),
    onSend: jest.fn(),
    placeholder: 'Type your message...',
    disabled: false,
    maxLength: 500
  },
  productCard: {
    product: mockProducts[0],
    onAddToCart: jest.fn(),
    onViewDetails: jest.fn(),
    showPrice: true,
    compact: false
  }
};

// Export all mock data as default
export default {
  mockConversation,
  mockUser,
  mockProducts,
  mockMessages,
  mockApiResponses,
  mockWordPressSettings,
  mockWidgetSettings,
  mockEvents,
  mockLocalStorage,
  mockErrors,
  mockProps
};