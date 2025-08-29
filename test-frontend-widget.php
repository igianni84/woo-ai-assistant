<?php
/**
 * Frontend Widget Loading Test
 * 
 * Simulates frontend widget loading to test if the fixes work
 */

// Set up basic WordPress-like environment
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
}

// Include WordPress configuration if available
$wp_config_path = ABSPATH . 'wp-config.php';
if (file_exists($wp_config_path)) {
    require_once($wp_config_path);
}

// Define our constants manually for testing
define('WOO_AI_ASSISTANT_VERSION', '1.0.0');
define('WOO_AI_ASSISTANT_PLUGIN_FILE', __FILE__);
define('WOO_AI_ASSISTANT_PLUGIN_DIR_PATH', dirname(__FILE__) . '/');
define('WOO_AI_ASSISTANT_PLUGIN_DIR_URL', 'http://localhost:8888/wp/wp-content/plugins/woo-ai-assistant/');
define('WOO_AI_ASSISTANT_ASSETS_URL', WOO_AI_ASSISTANT_PLUGIN_DIR_URL . 'assets/');
define('WOO_AI_ASSISTANT_ASSETS_PATH', WOO_AI_ASSISTANT_PLUGIN_DIR_PATH . 'assets/');

// Auto-detect development mode
$isDevelopment = 
    (isset($_SERVER['SERVER_NAME']) && (
        strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
        strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false ||
        strpos($_SERVER['SERVER_NAME'], '.local') !== false ||
        strpos($_SERVER['SERVER_NAME'], '.dev') !== false
    )) ||
    (isset($_SERVER['SERVER_SOFTWARE']) && (
        strpos($_SERVER['SERVER_SOFTWARE'], 'MAMP') !== false ||
        strpos($_SERVER['SERVER_SOFTWARE'], 'XAMPP') !== false ||
        strpos($_SERVER['SERVER_SOFTWARE'], 'WAMP') !== false
    ));

define('WOO_AI_DEVELOPMENT_MODE', $isDevelopment);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Woo AI Assistant Widget Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        /* Basic widget styling to test appearance */
        .woo-ai-widget-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            width: 60px;
            height: 60px;
            background: #007cba;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            box-shadow: 0 2px 12px rgba(0,124,186,0.3);
            transition: all 0.3s ease;
        }
        .woo-ai-widget-container:hover {
            background: #005a87;
            transform: scale(1.05);
        }
        .widget-demo {
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🤖 Woo AI Assistant Widget Frontend Test</h1>
        
        <?php
        // Test status display
        echo '<div class="debug-info">';
        echo '<h3>Test Environment Status</h3>';
        
        if (WOO_AI_DEVELOPMENT_MODE) {
            echo '<div class="status success">✅ Development Mode: ACTIVE</div>';
        } else {
            echo '<div class="status warning">⚠️ Development Mode: INACTIVE</div>';
        }
        
        echo '<div class="status info">🌍 Server Name: ' . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . '</div>';
        echo '<div class="status info">💻 Server Software: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '</div>';
        echo '<div class="status info">📁 Assets URL: ' . WOO_AI_ASSISTANT_ASSETS_URL . '</div>';
        
        // Check asset files
        $cssFile = WOO_AI_ASSISTANT_ASSETS_PATH . 'css/widget.min.css';
        $jsFile = WOO_AI_ASSISTANT_ASSETS_PATH . 'js/widget.min.js';
        
        if (file_exists($cssFile)) {
            echo '<div class="status success">✅ Widget CSS: Found (' . number_format(filesize($cssFile)) . ' bytes)</div>';
        } else {
            echo '<div class="status error">❌ Widget CSS: Missing</div>';
        }
        
        if (file_exists($jsFile)) {
            echo '<div class="status success">✅ Widget JS: Found (' . number_format(filesize($jsFile)) . ' bytes)</div>';
        } else {
            echo '<div class="status error">❌ Widget JS: Missing</div>';
        }
        
        echo '</div>';
        ?>
        
        <h2>Widget Loading Simulation</h2>
        <p>This test simulates how the widget would appear on a frontend page with development mode active.</p>
        
        <?php if (WOO_AI_DEVELOPMENT_MODE): ?>
            <div class="status success">
                <strong>Development Mode Benefits:</strong>
                <ul>
                    <li>✅ License validation bypassed</li>
                    <li>✅ All features enabled</li>
                    <li>✅ Widget loads regardless of API configuration</li>
                    <li>✅ Enhanced debug logging active</li>
                    <li>✅ Loading conditions bypassed</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <h3>Widget Container Test</h3>
        <div class="debug-info">
            <p>The widget should appear as a blue circle in the bottom-right corner of this page.</p>
            <p>In development mode, the widget will:</p>
            <ul>
                <li>Load without requiring API keys</li>
                <li>Show debug information in browser console</li>
                <li>Display a development notice</li>
                <li>Have all features enabled</li>
            </ul>
        </div>
        
        <?php if (file_exists($cssFile) && file_exists($jsFile)): ?>
            <!-- Load actual widget CSS -->
            <link rel="stylesheet" href="<?php echo WOO_AI_ASSISTANT_ASSETS_URL; ?>css/widget.min.css">
            
            <!-- Widget Container (manually rendered for testing) -->
            <div id="woo-ai-assistant-widget-root" 
                 class="woo-ai-widget-container" 
                 role="complementary" 
                 aria-label="AI Chat Assistant"
                 data-widget-initialized="false"
                 data-debug-mode="<?php echo WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false'; ?>">
                <div class="widget-demo">💬</div>
                <noscript>
                    <p>This AI chat widget requires JavaScript to function properly.</p>
                </noscript>
            </div>
            
            <!-- Load widget JavaScript -->
            <script>
                // Widget configuration for testing
                window.wooAiAssistantWidget = {
                    apiEndpoint: '<?php echo rest_url('woo-ai-assistant/v1/'); ?>',
                    nonce: 'test-nonce-12345',
                    userId: 0,
                    siteInfo: {
                        name: 'Test Site',
                        url: '<?php echo get_home_url(); ?>',
                        language: 'en',
                        currency: 'USD',
                        page_type: 'test',
                        is_mobile: false
                    },
                    config: {
                        position: 'bottom-right',
                        theme: 'auto',
                        zIndex: 9999,
                        mobileEnabled: true,
                        animationEnabled: true,
                        debugMode: <?php echo WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false'; ?>
                    },
                    strings: {
                        chat_title: 'AI Assistant',
                        type_message: 'Type your message...',
                        send_button: 'Send',
                        connecting: 'Connecting...',
                        demo_message: '<?php echo WOO_AI_DEVELOPMENT_MODE ? 'Development Mode: This is a demo of the AI Assistant widget.' : ''; ?>'
                    },
                    features: {
                        basic_chat: true,
                        product_search: true,
                        faq_answers: true,
                        proactive_engagement: <?php echo WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false'; ?>,
                        custom_messages: <?php echo WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false'; ?>
                    },
                    development: {
                        isDevelopment: <?php echo WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false'; ?>,
                        isLocal: true,
                        debugLogging: <?php echo WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false'; ?>
                    }
                };
                
                // Simple widget initialization test
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('🤖 Woo AI Assistant Widget Test Page Loaded');
                    console.log('Development Mode:', <?php echo WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false'; ?>);
                    console.log('Widget Config:', window.wooAiAssistantWidget);
                    
                    const widget = document.getElementById('woo-ai-assistant-widget-root');
                    if (widget) {
                        console.log('✅ Widget container found');
                        widget.setAttribute('data-widget-initialized', 'true');
                        
                        // Add click handler for demo
                        widget.addEventListener('click', function() {
                            alert('🤖 Widget clicked! In a real implementation, this would open the chat interface.\n\nDevelopment Mode: <?php echo WOO_AI_DEVELOPMENT_MODE ? 'Active' : 'Inactive'; ?>');
                        });
                    } else {
                        console.error('❌ Widget container not found');
                    }
                });
            </script>
            
            <?php if (WOO_AI_DEVELOPMENT_MODE): ?>
                <!-- Development Mode Notice -->
                <div class="status warning" style="position: fixed; top: 20px; right: 20px; z-index: 10000; max-width: 300px;">
                    <strong>🚧 Development Mode</strong><br>
                    Widget is running in development mode with all features enabled.
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="status error">
                <strong>❌ Cannot load widget:</strong> Asset files are missing. Please build the widget assets.
            </div>
        <?php endif; ?>
        
        <h3>Debug Information</h3>
        <pre><?php
        echo "Environment Variables:\n";
        echo "WOO_AI_DEVELOPMENT_MODE: " . (WOO_AI_DEVELOPMENT_MODE ? 'true' : 'false') . "\n";
        echo "WOO_AI_ASSISTANT_VERSION: " . WOO_AI_ASSISTANT_VERSION . "\n";
        echo "WOO_AI_ASSISTANT_ASSETS_URL: " . WOO_AI_ASSISTANT_ASSETS_URL . "\n";
        echo "\nServer Information:\n";
        echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'undefined') . "\n";
        echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'undefined') . "\n";
        echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'undefined') . "\n";
        echo "\nFile Paths:\n";
        echo "CSS File: " . $cssFile . " (" . (file_exists($cssFile) ? 'exists' : 'missing') . ")\n";
        echo "JS File: " . $jsFile . " (" . (file_exists($jsFile) ? 'exists' : 'missing') . ")\n";
        ?></pre>
    </div>
</body>
</html>