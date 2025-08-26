/**
 * Babel Configuration for Woo AI Assistant React Widget
 * 
 * Optimized for WordPress compatibility and modern browser support
 * 
 * @package WooAiAssistant
 * @since 1.0.0
 * @author Claude Code Assistant
 */

module.exports = (api) => {
  // Cache configuration based on NODE_ENV
  api.cache(() => process.env.NODE_ENV);
  
  const isProduction = api.env('production');
  const isDevelopment = api.env('development');
  const isTest = api.env('test');

  return {
    // Presets in order of execution
    presets: [
      // ES6+ features with browser compatibility
      [
        '@babel/preset-env',
        {
          // Browser targets (matching package.json browserslist)
          targets: isTest
            ? { node: 'current' }
            : {
                browsers: ['> 1%', 'last 2 versions', 'not dead', 'not ie 11'],
              },
          
          // Module handling
          modules: isTest ? 'commonjs' : false,
          
          // Polyfill strategy
          useBuiltIns: 'usage',
          corejs: 3,
          
          // Debug mode for development
          debug: isDevelopment && process.env.BABEL_DEBUG === 'true',
          
          // Exclude transforms that are not needed
          exclude: [
            '@babel/plugin-transform-typeof-symbol',
          ],
        },
      ],
      
      // React JSX transformation
      [
        '@babel/preset-react',
        {
          // Use automatic JSX runtime (React 17+)
          runtime: 'automatic',
          
          // Development helpers
          development: isDevelopment,
          
          // Import source for JSX
          importSource: 'react',
        },
      ],
      
      // TypeScript support (only for .ts/.tsx files)
      ...(isTest ? [] : [
        [
          '@babel/preset-typescript',
          {
            // Only process .ts/.tsx files, not .js files
            allExtensions: false,
            
            // Generate .d.ts files
            onlyRemoveTypeImports: true,
            
            // Optimize for production
            optimizeConstEnums: isProduction,
            
            // Don't interfere with JSX parsing
            isTSX: false,
          },
        ],
      ]),
    ],

    // Plugins for additional transformations
    plugins: [
      // React Hot Reload for development
      ...(isDevelopment ? [
        'react-refresh/babel',
      ] : []),

      // Production optimizations
      ...(isProduction ? [
        // Remove PropTypes in production
        'babel-plugin-transform-react-remove-prop-types',
        
        // Remove development-only code
        [
          'babel-plugin-transform-remove-console',
          {
            exclude: ['error', 'warn'],
          },
        ],
      ] : []),

      // Class properties and private methods
      '@babel/plugin-transform-class-properties',
      '@babel/plugin-transform-private-methods',
      
      // Object rest/spread
      '@babel/plugin-transform-object-rest-spread',
      
      // Optional chaining and nullish coalescing
      '@babel/plugin-transform-optional-chaining',
      '@babel/plugin-transform-nullish-coalescing-operator',
      
      // Dynamic imports
      '@babel/plugin-syntax-dynamic-import',
      
      // WordPress compatibility
      [
        'babel-plugin-lodash',
        {
          id: ['lodash'],
        },
      ],
    ],

    // Environment-specific configurations
    env: {
      // Development environment
      development: {
        compact: false,
        plugins: [
          'react-refresh/babel',
        ],
      },

      // Production environment
      production: {
        compact: true,
        plugins: [
          'babel-plugin-transform-react-remove-prop-types',
          [
            'babel-plugin-transform-remove-console',
            {
              exclude: ['error', 'warn'],
            },
          ],
        ],
      },

      // Test environment
      test: {
        plugins: [
          // Transform dynamic imports for Jest
          'babel-plugin-dynamic-import-node',
        ],
      },
    },

    // Ignore configuration
    ignore: [
      '**/node_modules/**',
      '**/dist/**',
      '**/coverage/**',
    ],

    // Source maps
    sourceMaps: true,
    inputSourceMap: true,

    // Comments handling
    comments: !isProduction,

    // Minification (handled by Terser in webpack)
    minified: false,
    
    // Compact output in production
    compact: isProduction ? 'auto' : false,
  };
};