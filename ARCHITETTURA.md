woo-ai-assistant/
│
├── woo-ai-assistant.php        # File principale del plugin. Inizializza il plugin, definisce le costanti (versione, path) e carica il file di avvio principale (bootstrap).
│
├── uninstall.php               # Script di disinstallazione. Eseguito quando un utente elimina il plugin per pulire il database (rimuovere opzioni, tabelle custom, cron job, ecc.).
│
├── composer.json               # Gestore delle dipendenze PHP (es. per SDK esterni). Definisce l'autoloader PSR-4 per la directory /src.
│
├── package.json                # Gestore delle dipendenze Node.js. Serve per le librerie necessarie a compilare l'app React del widget (React, Webpack/Vite, Babel, etc.).
│
├── webpack.config.js           # (O vite.config.js) File di configurazione per il processo di build che compila il codice sorgente del widget React in file statici JS/CSS pronti per la produzione.
│
├── README.md                   # Documentazione per gli sviluppatori, con istruzioni di installazione, build e descrizione degli endpoint API.
├── ROADMAP.md                  # Roadmap di sviluppo e tracking dei task
├── CLAUDE.md                   # Linee guida di sviluppo, standard di codice e workflow
├── ARCHITETTURA.md            # Questo file - struttura dettagliata del progetto
│
├── docs/                       # Documentazione organizzata del progetto
│   ├── reports/               # Report di test e completion summaries
│   └── specifications/        # Specifiche tecniche e guide
│       ├── PROJECT_SPECIFICATIONS.md  # Specifiche complete del progetto
│       ├── DEVELOPMENT_CONFIG_README.md # Guida configurazione ambiente di sviluppo
│       ├── TESTING_GUIDE.md           # Linee guida per i test
│       └── DEPLOYMENT_CHECKLIST.md    # Checklist per il deployment
│
├── debug/                      # File di test e debugging (non in produzione)
│   └── [test-*.php files]     # File di test vari per debugging locale
│
├─ languages/                   # Directory per l'internazionalizzazione (i18n).
│   └── woo-ai-assistant.pot    # File template (.pot) per le traduzioni del plugin, generato automaticamente dallo scan del codice.
│
├─ assets/                      # Contiene tutti gli asset statici compilati e pronti all'uso. Questa cartella è pubblica.
│   ├─ css/
│   │   ├── admin.css           # Fogli di stile per l'area di amministrazione di WordPress (dashboard, impostazioni).
│   │   └── widget.css          # Foglio di stile compilato per il widget della chat frontend.
│   ├─ js/
│   │   ├── admin.js            # JavaScript per le pagine di amministrazione (es. interazioni nella dashboard, chiamate AJAX).
│   │   └── widget.js           # Bundle JavaScript compilato dell'applicazione React per il widget.
│   └─ images/
│       ├── avatar-default.png  # Avatar di default per il chatbot, usato se l'admin non ne carica uno custom.
│       └── icon.svg            # Icona per il menu di amministrazione di WordPress.
│
├─ templates/                   # Template PHP che possono essere sovrascritti dai temi (best practice WooCommerce).
│   └─ emails/
│       ├── human-takeover.php  # Template email per la notifica di "handoff" all'amministratore, con la trascrizione della chat.
│       └── chat-recap.php      # Template email per il riepilogo della conversazione inviato all'utente (funzione Unlimited).
│
├─ widget-src/                  # Codice sorgente dell'applicazione React per il widget della chat. NON viene caricato direttamente da WordPress, ma compilato in /assets.
│   ├─ public/                  # Asset statici per lo sviluppo del widget.
│   ├─ src/
│   │  ├─ components/           # Componenti React riutilizzabili per costruire la UI.
│   │  │  ├── ChatWindow.js     # Componente principale della finestra della chat.
│   │  │  ├── Message.js        # Renderizza un singolo messaggio (utente o bot), gestendo anche lo streaming del testo.
│   │  │  ├── ProductCard.js    # Componente per la UI delle schede prodotto mostrate in chat, come da requisito.
│   │  │  └── QuickAction.js    # Renderizza un pulsante per azioni rapide (es. "Aggiungi al carrello", "Applica coupon").
│   │  ├─ services/
│   │  │  └── ApiService.js     # Gestisce tutte le chiamate all'API REST del plugin per inviare messaggi, azioni, rating, etc.
│   │  ├─ hooks/
│   │  │  └── useChat.js        # Hook custom per gestire tutta la logica e lo stato della chat (messaggi, stato di "sta scrivendo", etc.).
│   │  ├─ App.js                # Componente radice dell'applicazione React.
│   │  └─ index.js              # Punto di ingresso dell'app React che si monta nel DOM.
│   └─ ...                     # Altri file di configurazione di React (es. .babelrc, .eslintrc).
│
└─ src/                         # Directory principale del codice PHP (backend), strutturata secondo lo standard PSR-4.
    │
    ├─ Main.php                 # Classe principale (singleton) del plugin. Carica tutti i moduli, registra gli hook e coordina il funzionamento.
    │
    ├─ Setup/                   # Classi per la gestione del ciclo di vita del plugin.
    │  ├─ Installer.php        # Logica "Zero-Config". Esegue la prima indicizzazione all'attivazione e imposta i valori di default.
    │  ├─ Activator.php        # Logica eseguita all'attivazione del plugin (es. flush rewrite rules, schedulare cron).
    │  └─ Deactivator.php      # Logica eseguita alla disattivazione (es. rimozione dei cron job).
    │
    ├─ Api/                     # Classi per la comunicazione con servizi esterni.
    │  ├─ IntermediateServerClient.php # Client HTTP per comunicare in modo sicuro con il server intermediario (per chiamate LLM, embeddings, licenze).
    │  └─ LicenseManager.php           # Gestisce la validazione della licenza, i permessi basati sul piano (Free/Pro/Unlimited) e il "graceful degradation".
    │
    ├─ RestApi/                 # Definisce gli endpoint dell'API REST usati dal widget React.
    │  ├─ RestController.php     # Registra tutte le route custom (es. /woo-ai-assistant/v1/...).
    │  └─ Endpoints/
    │     ├─ ChatEndpoint.php     # Gestisce la logica di una richiesta di chat (riceve messaggio, cerca nella KB, chiama l'AI).
    │     ├─ ActionEndpoint.php   # Gestisce le azioni agentiche come aggiungere al carrello o applicare un coupon.
    │     └─ RatingEndpoint.php   # Gestisce la ricezione della valutazione della conversazione (1-5 stelle).
    │
    ├─ KnowledgeBase/           # Tutto ciò che riguarda la gestione della Knowledge Base.
    │  ├─ Manager.php          # Classe principale che orchestra la scansione e l'indicizzazione.
    │  ├─ Scanner.php          # Logica per estrarre contenuti da prodotti, pagine, policy, recensioni, etc.
    │  ├─ Indexer.php          # Prepara (chunking) e invia i dati al server intermediario per la creazione degli embeddings.
    │  ├─ Hooks.php            # Contiene tutti gli hook di WordPress e WooCommerce (es. 'save_post', 'woocommerce_update_product_stock') per gli aggiornamenti "event-driven".
    │  └─ Health.php           # Logica per la funzionalità "Health Score KB" e i relativi suggerimenti.
    │
    ├─ Chatbot/                 # Logica di backend che supporta le funzionalità della chat.
    │  ├─ ConversationHandler.php# Gestisce il contesto e la persistenza della conversazione.
    │  ├─ CouponHandler.php      # Implementa le regole e i guardrail per l'applicazione e la generazione automatica di coupon.
    │  ├─ ProactiveTriggers.php  # Gestisce la logica lato server per i trigger proattivi (exit-intent, inattività, etc.).
    │  └─ Handoff.php            # Gestisce il "takeover umano", ad esempio preparando e inviando l'email all'admin.
    │
    ├─ Admin/                   # Logica e rendering per l'area di amministrazione di WordPress.
    │  ├─ AdminMenu.php        # Crea la voce di menu e le sottomenu nell'admin di WP.
    │  ├─ pages/               # Classi che renderizzano le diverse pagine di amministrazione.
    │  │  ├─ DashboardPage.php  # Pagina con la dashboard e i KPI (resolution rate, assist-conversion, etc.).
    │  │  ├─ SettingsPage.php   # Pagina per le impostazioni (personalizzazione, regole coupon, API keys, etc.).
    │  │  └─ ConversationsLogPage.php # Pagina per visualizzare lo storico delle conversazioni con filtri.
    │  └─ Assets.php             # Registra e accoda gli script (JS) e gli stili (CSS) necessari per le pagine di admin.
    │
    ├─ Frontend/                # Logica PHP per la parte pubblica del sito.
    │  └─ WidgetLoader.php     # Si occupa di accodare gli script/stili del widget React e di passare dati da PHP a JavaScript (es. `wp_localize_script`) come il REST URL, nonce, lingua corrente, etc.
    │
    ├─ Compatibility/           # Moduli per garantire la compatibilità con altri plugin.
    │  ├─ WpmlAndPolylang.php  # Logica specifica per l'integrazione con WPML e Polylang (routing multilingua).
    │  └─ GdprPlugins.php      # Rileva plugin di Cookie/GDPR (es. Complianz, CookieYes) per gestire il consenso.
    │
    └─ Common/                  # Classi di utilità e tratti (traits) condivisi in tutto il plugin.
       ├─ Utils.php            # Funzioni helper generiche.
       └─ Traits/
          └─ Singleton.php     # Un trait riutilizzabile per implementare il design pattern Singleton.