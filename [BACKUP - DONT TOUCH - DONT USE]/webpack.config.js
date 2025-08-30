const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';
    
    return {
        entry: {
            widget: './widget-src/src/index.js',
            admin: './widget-src/src/admin.js'
        },
        
        output: {
            filename: 'js/[name].js',
            path: path.resolve(__dirname, 'assets'),
            clean: true
        },
        
        module: {
            rules: [
                {
                    test: /\.(js|jsx)$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: [
                                '@babel/preset-env',
                                ['@babel/preset-react', { runtime: 'automatic' }]
                            ]
                        }
                    }
                },
                {
                    test: /\.(css|scss)$/,
                    use: [
                        isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
                        'css-loader',
                        'sass-loader'
                    ]
                },
                {
                    test: /\.(png|jpg|jpeg|gif|svg)$/i,
                    type: 'asset/resource',
                    generator: {
                        filename: 'images/[name][ext]'
                    }
                }
            ]
        },
        
        plugins: [
            new MiniCssExtractPlugin({
                filename: 'css/[name].css'
            }),
            ...(process.env.ANALYZE ? [new BundleAnalyzerPlugin()] : [])
        ],
        
        optimization: {
            minimizer: isProduction ? [
                new TerserPlugin({
                    terserOptions: {
                        compress: {
                            drop_console: true,
                            drop_debugger: true
                        },
                        output: {
                            comments: false
                        }
                    },
                    extractComments: false
                })
            ] : [],
            splitChunks: {
                chunks: 'all',
                cacheGroups: {
                    vendor: {
                        test: /[\\/]node_modules[\\/]/,
                        name: 'vendor',
                        priority: 10
                    }
                }
            }
        },
        
        resolve: {
            extensions: ['.js', '.jsx'],
            alias: {
                '@': path.resolve(__dirname, 'widget-src/src')
            }
        },
        
        devtool: isProduction ? false : 'source-map',
        
        devServer: {
            port: 3000,
            hot: true,
            static: {
                directory: path.join(__dirname, 'assets')
            },
            headers: {
                'Access-Control-Allow-Origin': '*'
            },
            proxy: {
                '/wp-json': {
                    target: 'http://localhost:8888',
                    changeOrigin: true
                }
            }
        },
        
        stats: {
            colors: true,
            modules: false,
            children: false,
            chunks: false,
            chunkModules: false
        }
    };
};