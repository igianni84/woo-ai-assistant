/**
 * Webpack Configuration for Woo AI Assistant React Widget
 * 
 * Optimized for WordPress integration with <50KB bundle size requirement
 * 
 * @package WooAiAssistant
 * @since 1.0.0
 * @author Claude Code Assistant
 */

const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';
  const isDevelopment = !isProduction;

  const config = {
    // Entry points for code splitting optimization
    entry: {
      'widget-core': './widget-src/src/core.js',    // Core functionality <30KB
      'widget-chat': './widget-src/src/chat.js',    // Chat features (lazy-loaded)
      'widget-products': './widget-src/src/products.js', // Product features (lazy-loaded)
      'widget': './widget-src/src/index.js',        // Main entry point
    },

    // Output configuration
    output: {
      path: path.resolve(__dirname, 'assets/js'),
      filename: isProduction ? '[name].min.js' : '[name].js',
      chunkFilename: isProduction ? '[name].[contenthash].chunk.js' : '[name].chunk.js',
      clean: true,
      // WordPress compatibility - avoid conflicts
      library: 'WooAiAssistant',
      libraryTarget: 'window',
      publicPath: '',
    },

    // Development server configuration
    devServer: {
      static: {
        directory: path.join(__dirname, 'assets'),
      },
      compress: true,
      port: 3001,
      hot: true,
      open: false,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
        'Access-Control-Allow-Headers': 'X-Requested-With, content-type, Authorization',
      },
    },

    // Module rules for different file types
    module: {
      rules: [
        // JavaScript/TypeScript files
        {
          test: /\.(js|jsx|ts|tsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                ['@babel/preset-env', {
                  targets: {
                    browsers: ['> 1%', 'last 2 versions', 'not dead', 'not ie 11'],
                  },
                  modules: false,
                  useBuiltIns: 'entry',
                  corejs: 3,
                }],
                ['@babel/preset-react', {
                  runtime: 'automatic',
                }],
                '@babel/preset-typescript',
              ],
              plugins: [
                // React refresh for development
                ...(isDevelopment ? ['react-refresh/babel'] : []),
              ],
            },
          },
        },

        // CSS/SCSS files
        {
          test: /\.(css|scss|sass)$/,
          use: [
            isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
            {
              loader: 'css-loader',
              options: {
                modules: {
                  auto: true,
                  localIdentName: isProduction 
                    ? '[hash:base64:8]' 
                    : '[name]__[local]--[hash:base64:5]',
                },
                sourceMap: isDevelopment,
              },
            },
            {
              loader: 'sass-loader',
              options: {
                sourceMap: isDevelopment,
                sassOptions: {
                  outputStyle: isProduction ? 'compressed' : 'expanded',
                },
              },
            },
          ],
        },

        // Image and font files
        {
          test: /\.(png|jpe?g|gif|svg|webp)$/i,
          type: 'asset/resource',
          generator: {
            filename: '../images/[name].[hash][ext]',
          },
        },
        {
          test: /\.(woff|woff2|eot|ttf|otf)$/i,
          type: 'asset/resource',
          generator: {
            filename: '../fonts/[name].[hash][ext]',
          },
        },
      ],
    },

    // Resolve configuration
    resolve: {
      extensions: ['.tsx', '.ts', '.jsx', '.js', '.json'],
      alias: {
        '@': path.resolve(__dirname, 'widget-src/src'),
        '@components': path.resolve(__dirname, 'widget-src/src/components'),
        '@hooks': path.resolve(__dirname, 'widget-src/src/hooks'),
        '@services': path.resolve(__dirname, 'widget-src/src/services'),
        '@utils': path.resolve(__dirname, 'widget-src/src/utils'),
        '@styles': path.resolve(__dirname, 'widget-src/src/styles'),
      },
    },

    // External dependencies (WordPress globals and React)
    externals: {
      // React libraries (can be loaded from WordPress or CDN)
      react: 'React',
      'react-dom': 'ReactDOM',
      'react/jsx-runtime': 'ReactJSXRuntime',
      
      // WordPress globals to reduce bundle size
      jquery: 'jQuery',
      lodash: 'lodash',
      moment: 'moment',
      
      // WordPress specific
      '@wordpress/hooks': 'wp.hooks',
      '@wordpress/i18n': 'wp.i18n',
    },

    // Plugins
    plugins: [
      // Define environment variables
      new webpack.DefinePlugin({
        'process.env.NODE_ENV': JSON.stringify(argv.mode || 'development'),
        'process.env.WOO_AI_DEBUG': JSON.stringify(process.env.WOO_AI_DEBUG || 'false'),
      }),

      // Extract CSS in production
      ...(isProduction ? [
        new MiniCssExtractPlugin({
          filename: '../css/widget.min.css',
          chunkFilename: '../css/[name].[contenthash].css',
        }),
      ] : []),

      // Bundle analyzer (only when --analyze flag is used)
      ...(env && env.analyze ? [
        new BundleAnalyzerPlugin({
          analyzerMode: 'static',
          openAnalyzer: false,
          reportFilename: '../reports/bundle-analysis.html',
        }),
      ] : []),

      // WordPress compatibility
      new webpack.ProvidePlugin({
        // Make React available globally for JSX
        React: 'react',
      }),
    ],

    // Optimization configuration
    optimization: {
      minimize: isProduction,
      minimizer: [
        // JavaScript minification
        new TerserPlugin({
          terserOptions: {
            compress: {
              drop_console: isProduction,
              drop_debugger: true,
              pure_funcs: isProduction ? ['console.log', 'console.warn'] : [],
            },
            mangle: {
              safari10: true,
            },
            format: {
              comments: false,
            },
          },
          extractComments: false,
        }),

        // CSS minification
        new CssMinimizerPlugin({
          minimizerOptions: {
            preset: [
              'default',
              {
                discardComments: { removeAll: true },
              },
            ],
          },
        }),
      ],

      // Enable intelligent code splitting for performance optimization
      splitChunks: {
        chunks: 'async', // Only split async chunks for lazy loading
        minSize: 1000,   // Minimum chunk size 1KB
        maxSize: 15000,  // Maximum chunk size 15KB
        minChunks: 1,
        maxAsyncRequests: 5,
        maxInitialRequests: 3,
        cacheGroups: {
          // Vendor libraries chunk (shared dependencies)
          vendor: {
            test: /[\\/]node_modules[\\/]/,
            name: 'vendor',
            chunks: 'all',
            priority: 20,
            maxSize: 20000, // 20KB limit for vendor chunk
          },
          // Common components chunk
          common: {
            name: 'common',
            minChunks: 2,
            chunks: 'all',
            priority: 10,
            maxSize: 10000, // 10KB limit for common chunk
          },
          // Chat-specific chunk (lazy loaded)
          chat: {
            test: /[\\/]src[\\/](components|hooks)[\\/](Chat|Message|Typing)/,
            name: 'chat-bundle',
            chunks: 'async',
            priority: 15,
            maxSize: 12000, // 12KB limit
          },
          // Product-specific chunk (lazy loaded)
          products: {
            test: /[\\/]src[\\/]components[\\/](Product|Quick)/,
            name: 'products-bundle',
            chunks: 'async',
            priority: 15,
            maxSize: 8000, // 8KB limit
          },
        },
      },

      // Runtime chunk
      runtimeChunk: false, // Keep everything in main bundle for WordPress compatibility
    },

    // Performance hints
    performance: {
      hints: 'warning',
      maxAssetSize: 51200, // 50KB limit
      maxEntrypointSize: 51200, // 50KB limit
      assetFilter: (assetFilename) => {
        // Only check JavaScript files for size
        return assetFilename.endsWith('.js');
      },
    },

    // Development configuration
    devtool: isDevelopment ? 'eval-cheap-module-source-map' : false,

    // Stats configuration
    stats: {
      children: false,
      chunks: false,
      chunkModules: false,
      colors: true,
      entrypoints: false,
      hash: false,
      modules: false,
      reasons: false,
      timings: true,
      version: false,
      warnings: true,
      errors: true,
    },

    // Cache configuration for faster builds
    cache: {
      type: 'filesystem',
      buildDependencies: {
        config: [__filename],
      },
    },
  };

  return config;
};