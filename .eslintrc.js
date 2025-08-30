/**
 * ESLint Configuration for Woo AI Assistant
 * 
 * This configuration extends React best practices and enforces
 * consistent code quality standards across the project.
 *
 * @package WooAiAssistant
 * @subpackage Configuration
 * @since 1.0.0
 */

module.exports = {
    env: {
        browser: true,
        es2021: true,
        node: true,
        jest: true
    },
    extends: [
        'eslint:recommended',
        'plugin:react/recommended',
        'plugin:react-hooks/recommended',
        'plugin:jsx-a11y/recommended'
    ],
    parser: '@babel/eslint-parser',
    parserOptions: {
        ecmaFeatures: {
            jsx: true
        },
        ecmaVersion: 'latest',
        sourceType: 'module',
        requireConfigFile: false,
        babelOptions: {
            presets: ['@babel/preset-react']
        }
    },
    plugins: [
        'react',
        'react-hooks',
        'jsx-a11y'
    ],
    rules: {
        // React rules
        'react/react-in-jsx-scope': 'off', // Not needed with React 17+
        'react/prop-types': 'warn',
        'react/jsx-uses-react': 'off',
        'react/jsx-uses-vars': 'error',
        'react/jsx-key': 'error',
        'react/no-unused-state': 'warn',
        'react/no-direct-mutation-state': 'error',
        'react/jsx-no-bind': ['warn', {
            allowArrowFunctions: true,
            allowFunctions: false,
            allowBind: false
        }],
        
        // React Hooks rules
        'react-hooks/rules-of-hooks': 'error',
        'react-hooks/exhaustive-deps': 'warn',
        
        // Accessibility rules
        'jsx-a11y/no-autofocus': 'off', // Disabled for chat input
        'jsx-a11y/click-events-have-key-events': 'warn',
        'jsx-a11y/no-noninteractive-element-interactions': 'warn',
        
        // General JavaScript rules
        'no-console': ['warn', { allow: ['warn', 'error'] }],
        'no-debugger': 'error',
        'no-unused-vars': ['warn', { 
            varsIgnorePattern: '^_',
            argsIgnorePattern: '^_' 
        }],
        'no-var': 'error',
        'prefer-const': 'warn',
        'prefer-template': 'warn',
        'object-shorthand': 'warn',
        'arrow-spacing': 'warn',
        'comma-dangle': ['warn', 'never'],
        'semi': ['warn', 'always'],
        'quotes': ['warn', 'single', { avoidEscape: true }],
        'indent': ['warn', 2, { SwitchCase: 1 }],
        'no-trailing-spaces': 'warn',
        'eol-last': 'warn',
        
        // WordPress/PHP integration considerations
        'no-undef': ['error', {
            typeof: false
        }]
    },
    settings: {
        react: {
            version: 'detect'
        }
    },
    globals: {
        // WordPress globals
        wp: 'readonly',
        jQuery: 'readonly',
        $: 'readonly',
        ajaxurl: 'readonly',
        
        // Plugin-specific globals
        wooAiAssistant: 'readonly',
        wooAiAdmin: 'readonly',
        
        // Development globals
        process: 'readonly'
    },
    overrides: [
        {
            files: ['**/*.test.js', '**/*.test.jsx', '**/*.spec.js', '**/*.spec.jsx'],
            env: {
                jest: true
            },
            rules: {
                'no-console': 'off'
            }
        },
        {
            files: ['webpack.config.js', 'jest.config.js', '.eslintrc.js'],
            env: {
                node: true
            },
            rules: {
                'no-console': 'off'
            }
        }
    ],
    ignorePatterns: [
        'node_modules/',
        'assets/js/*.min.js',
        'vendor/',
        'dist/',
        'coverage/'
    ]
};