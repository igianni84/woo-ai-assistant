<?php
/**
 * Woo AI Assistant - Debug Dashboard
 * 
 * Comprehensive debugging interface for development
 * Access: http://localhost:8888/wp/wp-content/plugins/woo-ai-assistant/debug-dashboard.php
 */

// Load WordPress
require_once '/Applications/MAMP/htdocs/wp/wp-load.php';

// Load plugin classes
require_once __DIR__ . '/src/Common/DevelopmentConfig.php';
require_once __DIR__ . '/src/Common/ApiConfiguration.php';
require_once __DIR__ . '/src/Api/LicenseManager.php';

use WooAiAssistant\Common\DevelopmentConfig;
use WooAiAssistant\Api\LicenseManager;
use WooAiAssistant\Common\ApiConfiguration;

$devConfig = DevelopmentConfig::getInstance();
$licenseManager = LicenseManager::getInstance();
$apiConfig = ApiConfiguration::getInstance();

// Check if plugin is active
$plugin_active = is_plugin_active('woo-ai-assistant/woo-ai-assistant.php');

// Get environment info
$env_file_exists = file_exists(__DIR__ . '/.env.development.local');
$env_data = $env_file_exists ? parse_ini_file(__DIR__ . '/.env.development.local') : [];

// Check widget files
$widget_js_exists = file_exists(__DIR__ . '/assets/js/widget.min.js');
$widget_css_exists = file_exists(__DIR__ . '/assets/css/widget.min.css');

// Get recent logs
$log_file = WP_CONTENT_DIR . '/debug.log';
$recent_logs = '';
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $lines = explode("\n", $logs);
    $relevant = array_filter($lines, function($line) {
        return stripos($line, 'woo') !== false || 
               stripos($line, 'ai') !== false || 
               stripos($line, 'assistant') !== false;
    });
    $recent_logs = implode("\n", array_slice($relevant, -30));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Woo AI Assistant - Debug Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        .content {
            padding: 30px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #dee2e6;
        }
        .card h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        .status {
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status.ok {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .icon {
            font-size: 1.2em;
            margin-right: 8px;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-block;
        }
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn.secondary {
            background: #6c757d;
        }
        .btn.secondary:hover {
            background: #5a6268;
        }
        .code-block {
            background: #282c34;
            color: #abb2bf;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.9em;
            margin-top: 10px;
        }
        .logs {
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.85em;
            line-height: 1.6;
        }
        .quick-test {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .quick-test h3 {
            margin-bottom: 15px;
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        .test-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px;
            text-align: center;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .test-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 5px;
        }
        .badge.dev {
            background: #28a745;
            color: white;
        }
        .badge.prod {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ Woo AI Assistant Debug Dashboard</h1>
            <p>Sistema di diagnostica e testing per sviluppatori</p>
            <?php if ($devConfig->isDevelopmentMode()): ?>
                <span class="badge dev">DEVELOPMENT MODE</span>
            <?php else: ?>
                <span class="badge prod">PRODUCTION MODE</span>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <!-- Status Overview -->
            <div class="grid">
                <!-- Plugin Status -->
                <div class="card">
                    <h3>📦 Stato Plugin</h3>
                    <div class="status <?php echo $plugin_active ? 'ok' : 'error'; ?>">
                        <span><span class="icon"><?php echo $plugin_active ? '✅' : '❌'; ?></span> Plugin</span>
                        <strong><?php echo $plugin_active ? 'ATTIVO' : 'DISATTIVATO'; ?></strong>
                    </div>
                    <div class="status <?php echo $devConfig->isDevelopmentMode() ? 'ok' : 'warning'; ?>">
                        <span><span class="icon">🔧</span> Modalità</span>
                        <strong><?php echo $devConfig->isDevelopmentMode() ? 'SVILUPPO' : 'PRODUZIONE'; ?></strong>
                    </div>
                    <div class="status <?php echo $licenseManager->isLicenseValid() ? 'ok' : 'warning'; ?>">
                        <span><span class="icon">🔑</span> Licenza</span>
                        <strong><?php echo $licenseManager->isLicenseValid() ? 'VALIDA' : 'BYPASSATA'; ?></strong>
                    </div>
                </div>
                
                <!-- Configuration Status -->
                <div class="card">
                    <h3>⚙️ Configurazione</h3>
                    <div class="status <?php echo $env_file_exists ? 'ok' : 'error'; ?>">
                        <span><span class="icon">📄</span> .env.development.local</span>
                        <strong><?php echo $env_file_exists ? 'PRESENTE' : 'MANCANTE'; ?></strong>
                    </div>
                    <div class="status <?php echo $apiConfig->getOpenRouterKey() ? 'ok' : 'warning'; ?>">
                        <span><span class="icon">🤖</span> OpenRouter API</span>
                        <strong><?php echo $apiConfig->getOpenRouterKey() ? 'CONFIGURATA' : 'NON CONFIGURATA'; ?></strong>
                    </div>
                    <div class="status <?php echo $apiConfig->getOpenAiKey() ? 'ok' : 'warning'; ?>">
                        <span><span class="icon">🧠</span> OpenAI API</span>
                        <strong><?php echo $apiConfig->getOpenAiKey() ? 'CONFIGURATA' : 'NON CONFIGURATA'; ?></strong>
                    </div>
                    <div class="status <?php echo $apiConfig->getPineconeKey() ? 'ok' : 'warning'; ?>">
                        <span><span class="icon">📊</span> Pinecone API</span>
                        <strong><?php echo $apiConfig->getPineconeKey() ? 'CONFIGURATA' : 'NON CONFIGURATA'; ?></strong>
                    </div>
                </div>
                
                <!-- Widget Status -->
                <div class="card">
                    <h3>💬 Stato Widget</h3>
                    <div class="status <?php echo $widget_js_exists ? 'ok' : 'error'; ?>">
                        <span><span class="icon">📜</span> JavaScript</span>
                        <strong><?php echo $widget_js_exists ? 'COMPILATO' : 'MANCANTE'; ?></strong>
                    </div>
                    <div class="status <?php echo $widget_css_exists ? 'ok' : 'error'; ?>">
                        <span><span class="icon">🎨</span> CSS</span>
                        <strong><?php echo $widget_css_exists ? 'COMPILATO' : 'MANCANTE'; ?></strong>
                    </div>
                    <div class="status <?php echo $devConfig->isDevelopmentMode() ? 'ok' : 'info'; ?>">
                        <span><span class="icon">👁️</span> Visibilità</span>
                        <strong><?php echo $devConfig->isDevelopmentMode() ? 'FORZATA' : 'STANDARD'; ?></strong>
                    </div>
                    <?php if (!$widget_js_exists || !$widget_css_exists): ?>
                    <div class="code-block">
                        # Compila gli assets mancanti:
                        npm install
                        npm run build
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-test">
                <h3>⚡ Azioni Rapide</h3>
                <div class="test-grid">
                    <a href="<?php echo home_url(); ?>" target="_blank" class="test-btn">
                        🏠 Vai al Frontend
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=woo-ai-assistant'); ?>" target="_blank" class="test-btn">
                        📊 Admin Dashboard
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=woo-ai-assistant-settings'); ?>" target="_blank" class="test-btn">
                        ⚙️ Impostazioni
                    </a>
                    <a href="test-frontend-widget.php" target="_blank" class="test-btn">
                        🧪 Test Widget
                    </a>
                    <a href="widget-debug.php" target="_blank" class="test-btn">
                        🐛 Debug Widget
                    </a>
                    <a href="test-dev-config.php" target="_blank" class="test-btn">
                        🔧 Test Config
                    </a>
                </div>
            </div>
            
            <!-- Configuration Details -->
            <?php if ($env_file_exists): ?>
            <div class="card" style="margin-top: 20px;">
                <h3>📋 Dettagli Configurazione</h3>
                <div class="code-block">
Development Mode: <?php echo $devConfig->isDevelopmentMode() ? 'YES' : 'NO'; ?>

License Bypass: <?php echo isset($env_data['BYPASS_LICENSE_CHECK']) && $env_data['BYPASS_LICENSE_CHECK'] === 'true' ? 'ENABLED' : 'DISABLED'; ?>

Widget Debug: <?php echo isset($env_data['WIDGET_DEBUG_MODE']) && $env_data['WIDGET_DEBUG_MODE'] === 'true' ? 'ENABLED' : 'DISABLED'; ?>

API Keys Status:
- OpenRouter: <?php echo !empty($env_data['OPENROUTER_API_KEY']) && $env_data['OPENROUTER_API_KEY'] !== 'your_openrouter_key_here' ? '✅ SET' : '❌ NOT SET'; ?>

- OpenAI: <?php echo !empty($env_data['OPENAI_API_KEY']) && $env_data['OPENAI_API_KEY'] !== 'your_openai_key_here' ? '✅ SET' : '❌ NOT SET'; ?>

- Pinecone: <?php echo !empty($env_data['PINECONE_API_KEY']) && $env_data['PINECONE_API_KEY'] !== 'your_pinecone_key_here' ? '✅ SET' : '❌ NOT SET'; ?>

- Google: <?php echo !empty($env_data['GOOGLE_API_KEY']) && $env_data['GOOGLE_API_KEY'] !== 'your_google_api_key_here' ? '✅ SET' : '❌ NOT SET'; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card" style="margin-top: 20px;">
                <h3>⚠️ Configurazione Mancante</h3>
                <p style="margin-bottom: 10px;">Il file di configurazione sviluppo non è stato trovato. Crealo con:</p>
                <div class="code-block">
cd <?php echo __DIR__; ?>

cp .env.development .env.development.local
# Modifica .env.development.local con le tue API keys
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Logs -->
            <div class="card" style="margin-top: 20px;">
                <h3>📜 Log Recenti (Woo AI Assistant)</h3>
                <?php if ($recent_logs): ?>
                <div class="logs">
                    <?php echo htmlspecialchars($recent_logs); ?>
                </div>
                <?php else: ?>
                <p style="color: #6c757d; font-style: italic;">Nessun log recente trovato.</p>
                <?php endif; ?>
            </div>
            
            <!-- Testing Instructions -->
            <div class="card" style="margin-top: 20px;">
                <h3>📝 Istruzioni Testing Rapido</h3>
                <ol style="margin-left: 20px; line-height: 1.8;">
                    <li><strong>Configura API Keys:</strong> Modifica <code>.env.development.local</code> con le tue chiavi</li>
                    <li><strong>Verifica Widget:</strong> Vai al <a href="<?php echo home_url(); ?>" target="_blank">frontend</a> e cerca il widget in basso a destra</li>
                    <li><strong>Test Chat:</strong> Clicca sul widget e invia un messaggio di test</li>
                    <li><strong>Debug:</strong> Apri la console del browser (F12) per vedere i messaggi di debug</li>
                    <li><strong>Knowledge Base:</strong> Vai su <a href="<?php echo admin_url('admin.php?page=woo-ai-assistant-settings#knowledge_base'); ?>" target="_blank">Settings → Knowledge Base</a> e clicca "Reindex"</li>
                </ol>
                
                <div style="margin-top: 15px; padding: 15px; background: #e7f3ff; border-left: 4px solid #2196F3; border-radius: 4px;">
                    <strong>💡 Suggerimento:</strong> In modalità sviluppo, tutte le funzionalità premium sono sbloccate e la licenza è bypassata automaticamente.
                </div>
            </div>
            
            <!-- Actions -->
            <div class="actions">
                <a href="TESTING_GUIDE.md" class="btn" target="_blank">📖 Guida Completa Testing</a>
                <a href="DEVELOPMENT_CONFIG_README.md" class="btn secondary" target="_blank">🔧 Documentazione Config</a>
                <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=woo-ai-assistant/woo-ai-assistant.php'), 'deactivate-plugin_woo-ai-assistant/woo-ai-assistant.php'); ?>" class="btn secondary" onclick="return confirm('Sei sicuro di voler disattivare il plugin?')">⏹️ Disattiva Plugin</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh logs every 5 seconds
        setInterval(() => {
            const logsElement = document.querySelector('.logs');
            if (logsElement) {
                // You could implement AJAX refresh here if needed
            }
        }, 5000);
        
        console.log('%c🛠️ Woo AI Assistant Debug Mode Active', 'color: #667eea; font-size: 16px; font-weight: bold;');
        console.log('Development Mode:', <?php echo $devConfig->isDevelopmentMode() ? 'true' : 'false'; ?>);
        console.log('License Valid:', <?php echo $licenseManager->isLicenseValid() ? 'true' : 'false'; ?>);
    </script>
</body>
</html>