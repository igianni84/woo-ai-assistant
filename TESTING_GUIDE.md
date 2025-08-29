# 🧪 Guida Completa al Testing - Woo AI Assistant

## 📋 Indice
1. [Setup Iniziale](#setup-iniziale)
2. [Configurazione Sviluppo](#configurazione-sviluppo)
3. [Testing del Widget Frontend](#testing-del-widget-frontend)
4. [Testing delle Funzionalità Core](#testing-delle-funzionalità-core)
5. [Risoluzione Problemi](#risoluzione-problemi)
6. [Strumenti di Debug](#strumenti-di-debug)

---

## 🚀 Setup Iniziale

### Prerequisiti
- MAMP con PHP 8.2.20
- WordPress installato in `/Applications/MAMP/htdocs/wp`
- WooCommerce attivato
- Plugin Woo AI Assistant nella directory `wp-content/plugins/woo-ai-assistant`

### 1. Attivazione Plugin
```bash
# Vai nella directory del plugin
cd /Applications/MAMP/htdocs/wp/wp-content/plugins/woo-ai-assistant

# Verifica che il plugin sia attivato
wp plugin list --path=/Applications/MAMP/htdocs/wp
```

Se non è attivato:
1. Vai su **WordPress Admin → Plugin**
2. Trova "Woo AI Assistant"
3. Clicca **Attiva**

---

## ⚙️ Configurazione Sviluppo

### 2. Configurazione API Keys (per Sviluppatori)

**IMPORTANTE:** Le API keys NON vanno nei settings del plugin! Usa il file di configurazione sviluppo:

#### Passo 1: Crea il file di configurazione locale
```bash
# Se non esiste già
cp .env.development .env.development.local
```

#### Passo 2: Modifica `.env.development.local` con le tue API keys:
```env
# API Keys per Sviluppo
OPENROUTER_API_KEY=sk-or-v1-tua-chiave-openrouter
OPENAI_API_KEY=sk-tua-chiave-openai
PINECONE_API_KEY=tua-chiave-pinecone
PINECONE_ENVIRONMENT=us-east-1
GOOGLE_API_KEY=tua-chiave-google-per-gemini

# Lascia questi come sono
BYPASS_LICENSE_CHECK=true
GRANT_UNLIMITED_FEATURES=true
WIDGET_SHOW_ON_ALL_PAGES=true
```

### 3. Verifica Configurazione

Esegui il test di configurazione:
```bash
php test-dev-config.php
```

Dovresti vedere:
```
✅ Development Mode: ENABLED
✅ License Bypass: ACTIVE
✅ API Keys Loaded: YES
```

---

## 🎨 Testing del Widget Frontend

### 4. Verifica Caricamento Widget

#### Metodo 1: Test Page Dedicata
Apri nel browser:
```
http://localhost:8888/wp/wp-content/plugins/woo-ai-assistant/test-frontend-widget.php
```

Dovresti vedere:
- ✅ Un pulsante chat in basso a destra
- ✅ Messaggio "Development Mode Active"
- ✅ Status checks tutti verdi

#### Metodo 2: Sito WordPress Frontend
1. Vai su qualsiasi pagina del sito: `http://localhost:8888/wp`
2. Guarda in basso a destra per il widget chat
3. Apri la console del browser (F12)
4. Cerca messaggi che iniziano con `[Woo AI Assistant]`

### 5. Testing Interattivo del Chat

#### Senza API Keys Configurate:
1. Clicca sul widget chat
2. Dovresti vedere: "Chat in modalità sviluppo - API non configurate"
3. Puoi comunque testare l'interfaccia utente

#### Con API Keys Configurate:
1. Assicurati di aver inserito le API keys in `.env.development.local`
2. Ricarica la pagina
3. Clicca sul widget chat
4. Scrivi un messaggio di test: "Ciao, funzioni?"
5. Dovresti ricevere una risposta dall'AI

---

## 🔧 Testing delle Funzionalità Core

### 6. Knowledge Base Indexing

#### Test Manuale:
1. Vai su **WordPress Admin → Woo AI Assistant → Settings**
2. Clicca tab **Knowledge Base**
3. Clicca **"Reindex Knowledge Base"**
4. Controlla il log per verificare l'indicizzazione

#### Test via Script:
```bash
php test-kb-indexing.php
```

### 7. Conversation Handler

Test della gestione conversazioni:
```php
// Crea file test-conversation.php
<?php
require_once 'wp-load.php';

use WooAiAssistant\Chatbot\ConversationHandler;
use WooAiAssistant\Common\DevelopmentConfig;

// In dev mode, bypassa tutti i controlli
$devConfig = DevelopmentConfig::getInstance();
if ($devConfig->isDevelopmentMode()) {
    echo "✅ Development Mode Active\n";
    
    // Testa creazione conversazione
    $handler = ConversationHandler::getInstance();
    $conversationId = $handler->createConversation(0); // Guest user
    echo "✅ Conversation Created: $conversationId\n";
    
    // Testa invio messaggio
    $response = $handler->handleUserMessage(
        $conversationId, 
        "Test message in development"
    );
    echo "✅ Message Handled\n";
}
```

---

## 🐛 Risoluzione Problemi

### Widget Non Visibile

#### Checklist:
1. **Plugin Attivato?**
   ```bash
   wp plugin is-active woo-ai-assistant --path=/Applications/MAMP/htdocs/wp
   ```

2. **File JS/CSS Esistono?**
   ```bash
   ls -la assets/js/widget.min.js
   ls -la assets/css/widget.min.css
   ```
   
   Se non esistono, builda gli assets:
   ```bash
   npm install
   npm run build
   ```

3. **Errori Console Browser?**
   - Apri DevTools (F12)
   - Controlla tab Console
   - Cerca errori rossi

4. **Development Mode Attivo?**
   ```bash
   php widget-debug.php
   ```

### API Non Funzionanti

1. **Verifica File Config Esiste:**
   ```bash
   cat .env.development.local
   ```

2. **Test API Keys:**
   ```php
   // test-api-keys.php
   <?php
   require_once 'src/Common/DevelopmentConfig.php';
   $config = WooAiAssistant\Common\DevelopmentConfig::getInstance();
   
   echo "OpenRouter Key: " . ($config->getOpenRouterKey() ? '✅' : '❌') . "\n";
   echo "OpenAI Key: " . ($config->getOpenAiKey() ? '✅' : '❌') . "\n";
   echo "Pinecone Key: " . ($config->getPineconeKey() ? '✅' : '❌') . "\n";
   ```

### License Key Issues

**In modalità sviluppo, QUALSIASI valore funziona!**

Se richiede comunque una license key:
1. Inserisci qualsiasi testo (es: "dev-license-123")
2. Verifica che `BYPASS_LICENSE_CHECK=true` in `.env.development.local`
3. Controlla development mode:
   ```php
   php -r "define('ABSPATH', '/Applications/MAMP/htdocs/wp/'); 
           require 'src/Common/DevelopmentConfig.php'; 
           var_dump(WooAiAssistant\Common\DevelopmentConfig::getInstance()->isDevelopmentMode());"
   ```

---

## 🛠️ Strumenti di Debug

### Debug Dashboard
Crea un file `debug-dashboard.php`:
```php
<?php
require_once '/Applications/MAMP/htdocs/wp/wp-load.php';
require_once 'src/Common/DevelopmentConfig.php';

use WooAiAssistant\Common\DevelopmentConfig;
use WooAiAssistant\Api\LicenseManager;
use WooAiAssistant\Common\ApiConfiguration;

$devConfig = DevelopmentConfig::getInstance();
$licenseManager = LicenseManager::getInstance();
$apiConfig = ApiConfiguration::getInstance();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Woo AI Assistant - Debug Dashboard</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .status { padding: 10px; margin: 5px; border-radius: 5px; }
        .ok { background: #d4edda; }
        .warning { background: #fff3cd; }
        .error { background: #f8d7da; }
    </style>
</head>
<body>
    <h1>🛠️ Woo AI Assistant Debug Dashboard</h1>
    
    <h2>Environment Status</h2>
    <div class="status <?php echo $devConfig->isDevelopmentMode() ? 'ok' : 'error'; ?>">
        Development Mode: <?php echo $devConfig->isDevelopmentMode() ? '✅ ACTIVE' : '❌ INACTIVE'; ?>
    </div>
    
    <div class="status <?php echo $licenseManager->isLicenseValid() ? 'ok' : 'warning'; ?>">
        License Status: <?php echo $licenseManager->isLicenseValid() ? '✅ VALID' : '⚠️ BYPASSED'; ?>
    </div>
    
    <h2>API Configuration</h2>
    <div class="status <?php echo $apiConfig->getOpenRouterKey() ? 'ok' : 'warning'; ?>">
        OpenRouter: <?php echo $apiConfig->getOpenRouterKey() ? '✅ Configured' : '⚠️ Not Set'; ?>
    </div>
    
    <div class="status <?php echo $apiConfig->getOpenAiKey() ? 'ok' : 'warning'; ?>">
        OpenAI: <?php echo $apiConfig->getOpenAiKey() ? '✅ Configured' : '⚠️ Not Set'; ?>
    </div>
    
    <div class="status <?php echo $apiConfig->getPineconeKey() ? 'ok' : 'warning'; ?>">
        Pinecone: <?php echo $apiConfig->getPineconeKey() ? '✅ Configured' : '⚠️ Not Set'; ?>
    </div>
    
    <h2>Widget Status</h2>
    <div class="status ok">
        Widget Should Load: ✅ YES (Development Mode)
    </div>
    
    <h2>Quick Actions</h2>
    <p>
        <a href="/wp/wp-admin/admin.php?page=woo-ai-assistant">📊 Admin Dashboard</a> |
        <a href="/wp/wp-admin/admin.php?page=woo-ai-assistant-settings">⚙️ Settings</a> |
        <a href="/wp">🏠 Frontend (Check Widget)</a>
    </p>
    
    <h2>Logs</h2>
    <pre><?php 
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);
        // Show last 50 lines related to Woo AI Assistant
        $lines = explode("\n", $logs);
        $relevant = array_filter($lines, function($line) {
            return stripos($line, 'woo') !== false || stripos($line, 'ai') !== false;
        });
        echo htmlspecialchars(implode("\n", array_slice($relevant, -50)));
    }
    ?></pre>
</body>
</html>
```

Accedi al dashboard: `http://localhost:8888/wp/wp-content/plugins/woo-ai-assistant/debug-dashboard.php`

---

## 📝 Note Importanti per lo Sviluppo

### Cosa NON Fare:
- ❌ **NON inserire API keys nei Settings del plugin** (sono per il cliente finale)
- ❌ **NON committare `.env.development.local`** nel repository
- ❌ **NON usare API keys di produzione** in sviluppo

### Cosa Fare:
- ✅ **Usa sempre `.env.development.local`** per le configurazioni di sviluppo
- ✅ **Testa in modalità sviluppo** prima di passare a produzione
- ✅ **Controlla i log** per debug dettagliato
- ✅ **Usa i tool di test** forniti per verificare ogni componente

### Modalità di Testing:

1. **Testing Senza API (UI Only)**
   - Non configurare API keys
   - Testa solo l'interfaccia utente
   - Verifica caricamento widget e interazioni base

2. **Testing Con Mock Data**
   - Set `ENABLE_MOCK_RESPONSES=true` in `.env.development.local`
   - Riceverai risposte simulate senza consumare API

3. **Testing Completo**
   - Configura tutte le API keys
   - Testa funzionalità complete end-to-end
   - Verifica integrazione con servizi esterni

---

## 🆘 Supporto

Se riscontri problemi:

1. **Controlla il Debug Dashboard** per overview immediata
2. **Leggi i log** in `wp-content/debug.log`
3. **Usa widget-debug.php** per diagnostica dettagliata
4. **Verifica la console del browser** per errori JavaScript

### File di Test Disponibili:
- `test-dev-config.php` - Verifica configurazione sviluppo
- `test-frontend-widget.php` - Test visuale del widget
- `widget-debug.php` - Diagnostica completa widget
- `debug-dashboard.php` - Dashboard di debug completo

---

## 🎯 Prossimi Passi

Dopo aver verificato che tutto funziona in modalità sviluppo:

1. **Implementa il tuo Intermediate Server** (Task 3.x del roadmap)
2. **Configura le API di produzione** sul server
3. **Testa con license key reali** (quando il sistema sarà pronto)
4. **Rimuovi la modalità sviluppo** per il deployment

Buon testing! 🚀