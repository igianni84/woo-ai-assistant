# Woo AI Assistant - Project Specifications

**Version:** 1.0  
**Last Updated:** 2025-08-22  
**Status:** Development Ready  

---

## 📋 Table of Contents

1. [Executive Summary & Vision](#1-executive-summary--vision)
2. [Business Goals & Success Metrics](#2-business-goals--success-metrics)
3. [User Stories & Use Cases](#3-user-stories--use-cases)
4. [Functional Requirements](#4-functional-requirements)
5. [Technical Requirements & Compatibility](#5-technical-requirements--compatibility)
6. [Architecture Overview](#6-architecture-overview)
7. [User Experience Flows](#7-user-experience-flows)
8. [Pricing Plans & Features Matrix](#8-pricing-plans--features-matrix)
9. [Security & Performance Specifications](#9-security--performance-specifications)
10. [Coupon Management System](#10-coupon-management-system)
11. [API Specifications](#11-api-specifications)
12. [Zero-Config Implementation Details](#12-zero-config-implementation-details)
13. [Glossary & Terminology](#13-glossary--terminology)

---

## 1. Executive Summary & Vision

### 1.1 Elevator Pitch
Woo AI Assistant è un plugin per WooCommerce che crea e aggiorna automaticamente una knowledge base (KB) contestuale basata sui contenuti del sito (prodotti, pagine, FAQ, etc.). Utilizza un LLM per fornire assistenza clienti 24/7, guidare gli utenti nel processo d'acquisto e offrire insight all'amministratore. Si rivolge a proprietari di eCommerce, agenzie e sviluppatori che cercano una soluzione di supporto intelligente, multilingua e a zero configurazione per aumentare le conversioni e la produttività.

### 1.2 Core Value Proposition: "Niente Frizioni"
Il nostro vantaggio competitivo non è il chatbot in sé, ma il fatto che crea ed aggiorna costantemente la KB in base ai contenuti del sito. Non richiede sforzi, configurazioni o tecnicismi vari. Una volta installato si auto-configura ed è pronto all'uso.

### 1.3 Target Audience
- **Proprietari di eCommerce** ed agenzie che vogliono assistenza 24/7, multilingua, personalizzabile e nativamente integrata in WooCommerce
- **Sviluppatori di eCommerce** basati su WooCommerce che vogliono offrire questa feature ai suoi clienti  
- **Agenzie web** che sviluppano eCommerce basati su WooCommerce e vogliono offrire questa feature ai loro clienti

### 1.4 Non-Goals (v1.0)
- **Integrazioni con Piattaforme Esterne:** Per la versione iniziale, non è prevista l'integrazione con piattaforme diverse da WooCommerce (es. Shopify, Prestashop) o con CRM/sistemi di email marketing esterni.
- **Sostituzione Completa dell'Agente Umano:** L'obiettivo non è eliminare il supporto umano, ma potenziarlo, gestendo le richieste di primo livello e fornendo un sistema di handoff efficiente per i casi complessi.
- **Personalizzazione Estrema del Modello AI:** La v1 non permetterà agli utenti di scegliere o addestrare modelli AI custom. Si utilizzeranno modelli pre-selezionati per ottimizzare costi e performance.

---

## 2. Business Goals & Success Metrics

### 2.1 Business Goals

#### 2.1.1 Aumentare le Conversioni
Guidare proattivamente gli utenti all'acquisto tramite assistenza contestuale, up-selling e gestione intelligente dei coupon, misurando il tasso di "assist-conversion".

#### 2.1.2 Ridurre i Costi di Supporto  
Diminuire il volume di ticket di supporto ripetitivi (FAQ, policy) di almeno il 50% automatizzando le risposte tramite l'AI.

#### 2.1.3 Incrementare la Produttività dell'Admin
Fornire una dashboard con KPI azionabili e insight sulle domande frequenti per ottimizzare la KB e le strategie di vendita.

#### 2.1.4 Creare un Vantaggio Competitivo
Offrire un'esperienza utente superiore e "senza frizioni" che differenzi il negozio dalla concorrenza.

### 2.2 User Goals

#### 2.2.1 Per l'Amministratore del Negozio
"Voglio un assistente virtuale che si installi in 5 minuti e funzioni da solo, senza che io debba configurare nulla, per liberare il mio tempo e vendere di più."

#### 2.2.2 Per il Cliente Finale
"Voglio risposte immediate e pertinenti alle mie domande su prodotti, spedizioni o resi, a qualsiasi ora, per completare il mio acquisto con fiducia."

### 2.3 Success Metrics & KPIs

#### 2.3.1 Primary Metrics
- **Time-to-Value (TTV):** Tempo dall'installazione alla prima conversazione utile < 15 minuti
- **Resolution Rate:** % di conversazioni concluse con successo dal bot (rating ≥ 4 stelle) senza intervento umano. Target: > 70%
- **Assist-Conversion Rate:** % di ordini effettuati dopo un'interazione con il chatbot
- **Human Takeover Rate:** % di conversazioni inoltrate a un operatore umano. Target: < 20%

#### 2.3.2 Secondary Metrics
- **KB Freshness Lag:** Tempo medio tra l'aggiornamento di un prodotto (es. stock) e la sua corretta indicizzazione nella KB. Target: < 5 minuti
- **Response Time:** Average time to first response (TTFR) per FAQ comuni. Target: < 300ms
- **Widget Performance:** Bundle size < 50KB gzipped
- **User Satisfaction:** Average user rating (1-5 stars)

#### 2.3.3 MVP Acceptance Criteria
- **Automatic Test:** Subito dopo l'install, l'admin vede un test automatico di 10 Q&A (spedizioni/tempi/resi/pagamenti + 6 su catalogo) con punteggio ≥ 80%
- **First Conversion:** Primo assist-conversion registrato entro 48h su store con > 1k visite/giorno

---

## 3. User Stories & Use Cases

### 3.1 Primary User Stories

#### 3.1.1 Marco - Proprietario eCommerce Abbigliamento
"Come Marco, voglio che un cliente che chiede 'Avete t-shirt blu taglia M?' riceva immediatamente una lista dei prodotti disponibili con foto e link, e magari un suggerimento su un pantalone abbinabile, così da aumentare la probabilità di acquisto senza che io debba intervenire."

#### 3.1.2 Giulia - Sviluppatrice Agenzia Web
"Come Giulia, voglio installare un plugin per un mio cliente e avere la certezza che inizi a funzionare subito, indicizzando automaticamente prodotti e policy, per poter offrire un servizio a valore aggiunto senza impiegare ore in configurazione e manutenzione."

#### 3.1.3 Luca - Cliente Negozio Elettronica
"Come Luca, sono indeciso tra due modelli di cuffie. Voglio chiedere al chatbot 'Qual è la differenza tra il modello X e Y?' e ricevere un confronto chiaro su batteria, cancellazione del rumore e prezzo, per poter aggiungere il prodotto giusto al carrello direttamente dalla chat."

### 3.2 Detailed Use Case Examples

#### 3.2.1 Esempio: Negozio di Abbigliamento
**Domanda cliente:** "Avete delle gonne taglia S?"

**Comportamento chatbot:** 
- Cerca tutti i prodotti che sono gonne (basandosi su categorie, tag, nome prodotto, etc) che hanno disponibilità taglia S
- Propone le prime 3 soluzioni
- Propone di "espandere" i risultati in un'apposita pagina per vederli tutti
- Dopo aver mostrato i primi 3 risultati, chiede se sta cercando un colore in particolare, o se la preferisce lunga o corta
- Fa domande contestuali per affinare la ricerca sulla base della risposta del cliente

#### 3.2.2 Esempio: Negozio di Elettronica
**Domanda cliente:** "Avete smartphone Android? Vorrei spendere max 200€"

**Comportamento chatbot:**
- Nonostante l'ecommerce non abbia una categoria "Smartphone" specifica, capisce la domanda
- Cerca prodotti corrispondenti, escludendo iPhone perché sono iOS
- Fa la ricerca nella KB anche in base al prezzo
- Propone sempre i primi 3 risultati
- Fa domande aggiuntive: "Preferisci un colore in particolare? Quale versione minima di Android vuoi? Hai già in mente un brand specifico?"

---

## 4. Functional Requirements

### 4.1 P0 (Core - MVP) Features

#### 4.1.1 Installazione "Zero-Config"
Al momento dell'attivazione, il plugin esegue automaticamente la prima scansione di prodotti, categorie, pagine e impostazioni WooCommerce.

**Implementazione Details:**
- Indicizzazione immediata di prodotti, categorie e pagine chiave (spedizioni, resi, pagamenti) via sitemap + Woo settings
- Policy extraction: risposte di default da zone di spedizione, metodi di pagamento attivi, pagina "Resi/Termini"
- Se policy mancanti, fallback onesto: "il merchant non ha definito X"
- Multilingua auto: leggi lingua sito e, se WPML/Polylang, routing Q&A corretto
- Se manca la traduzione, proponi fallback

#### 4.1.2 Knowledge Base Automatica
Indicizzazione e aggiornamento continuo della KB tramite hook di WooCommerce (es. cambio prezzo/stock) e cron di sicurezza.

**Content Sources:**
- Prodotti, pagine, categorie/tag, FAQ, recensioni, blog
- Policy spedizioni/pagamenti/resi, regole promo/coupon
- Settings WooCommerce (costi e tempi di spedizione, luoghi di spedizione, dati negozio)

#### 4.1.3 Widget Chat Contestuale
Chatbot che riconosce la pagina corrente, l'utente (se loggato) e la lingua (supporto WPML/Polylang).

**Context Awareness:**
- Riconoscimento pagina corrente
- Riconoscimento del sentiment ed adeguamento delle risposte
- Riconoscimento utente loggato per richieste su ordini/stato
- Se l'utente è loggato, l'AI può leggere carrello, storico ordini, indirizzi per risposte personalizzate

#### 4.1.4 Gestione Coupon (Base)
Il bot può applicare coupon esistenti e validi, previa conferma dell'utente. La funzione è disattivata di default.

#### 4.1.5 Handoff Umano Semplice
In caso di difficoltà, il bot propone di contattare il supporto tramite:
- Email a admin WP con transcript + link ordine
- Mailto/WhatsApp precompilato 
- Live chat dedicata nel backend di WordPress per prendere in carico le chat in tempo reale

#### 4.1.6 Dashboard Admin (Base)
Log delle conversazioni con rating (1-5 stelle) e KPI fondamentali:
- Resolution rate, assist-conversion
- Tempo medio risposta
- FAQ ricorrenti
- Mini-dashboard senza setup

### 4.2 P1 (Pro/Unlimited) Features

#### 4.2.1 Trigger Proattivi
Avvio automatico della chat basato su regole configurabili dall'admin:
- Exit-intent detection
- Tempo di inattività (default 60s)
- Scroll profondo (default 2 scroll)
- Trigger OOTB: PDP e carrello preconfigurati

#### 4.2.2 Azioni Avanzate in Chat
- **Aggiunta prodotti al carrello:** Direttamente dalla conversazione (Unlimited, con conferma)
- **Aggiunta alla wishlist:** Ed della stessa come contesto per le chat
- **Upsell/Cross-sell intelligenti:** (Unlimited)

#### 4.2.3 Generazione Automatica Coupon  
Creazione di coupon one-off (con guardrail e limiti) quando il bot rileva:
- Alta probabilità di abbandono o frustrazione
- Sentiment negativo/rischio churn
- Richiede abilitazione esplicita (Unlimited only)

#### 4.2.4 Personalizzazione Grafica e Testuale
Possibilità di modificare:
- Colori, avatar e messaggi predefiniti del chatbot (da Pro)
- Tono di voce (es. formale/informale, amichevole) per allinearlo al brand
- White-label completo (Unlimited)

#### 4.2.5 Health Score KB  
Pannello che analizza la completezza della KB e suggerisce azioni (es. "Manca la pagina Resi, creala da questo template").

#### 4.2.6 Advanced Analytics
- Conversation logs con confidence scores (da Pro)
- Badge "Alta/Media/Bassa" confidenza + snippet della KB usata
- Filtri (non risposte, sentiment negativo, ecc.)
- Sistema di ticketing integrato per gestire richieste complesse (da Pro)

---

## 5. Technical Requirements & Compatibility

### 5.1 Stack Requirements
- **WordPress:** 6.0 or higher
- **WooCommerce:** 7.0 or higher  
- **PHP:** 8.1 or higher
- **Memory:** 128MB minimum (256MB recommended)
- **Frontend:** React 18+, Webpack 5
- **Database:** MySQL/MariaDB with proper indexing

### 5.2 AI & External Services
- **AI Models:** OpenRouter integration
  - Gemini 2.5 Flash (Free/Pro plans)
  - Gemini 2.5 Pro (Unlimited plan)
- **Embeddings:** OpenAI text-embedding-3-small
- **Vector DB:** Pinecone (tenant isolated)
- **Payments:** Stripe integration for licensing
- **Server:** EU-based intermediate server for security/licensing

### 5.3 Performance Requirements
- **FAQ Cache TTFR:** < 300ms for common responses
- **Widget Bundle Size:** < 50KB gzipped
- **KB Freshness:** < 5 minutes lag for product updates
- **Lazy Loading:** Assets load only when needed
- **CDN Ready:** Static assets can be served from CDN

### 5.4 Security Requirements
- **Input Sanitization:** All user inputs sanitized and validated
- **CSRF Protection:** WordPress nonces on all admin actions  
- **Rate Limiting:** API request limits per IP/user
- **Prompt Injection Defense:** AI guardrails against malicious prompts
- **Audit Logging:** All agentic actions logged for review
- **Capability Checks:** Proper WordPress permission handling

### 5.5 Internationalization (i18n)
- **WPML Integration:** Complete integration with routing
- **Polylang Support:** Automatic language detection
- **WordPress Multisite:** Network activation support
- **Textdomain:** `woo-ai-assistant` with .pot file generation

### 5.6 GDPR/Privacy Compliance
- **Data Retention:** Configurable retention policies
- **Export/Delete:** User data extraction for compliance
- **Consenso Esplicito:** Per AI processing
- **Minimizzazione/Anonimizzazione:** Privacy by design
- **Audit Trail:** Complete action logging
- **Cookie Integration:** Rilevazione CookieYes/Complianz con modalità "minima" fino al consenso

---

## 6. Architecture Overview

> **📁 Complete architectural details are specified in `ARCHITETTURA.md`**

### 6.1 High-Level Components

```
woo-ai-assistant/
├── woo-ai-assistant.php        # Main plugin file & bootstrap
├── src/                        # PHP backend (PSR-4: WooAiAssistant\)
│   ├── Main.php                # Singleton orchestrator
│   ├── Setup/                  # Installation & lifecycle
│   ├── Api/                    # External API communication
│   ├── RestApi/                # WordPress REST endpoints
│   ├── KnowledgeBase/          # Content scanning & indexing
│   ├── Chatbot/                # Chat logic & conversation handling
│   ├── Admin/                  # WordPress admin interface
│   ├── Frontend/               # Public site integration
│   ├── Compatibility/          # Third-party plugin compatibility
│   └── Common/                 # Shared utilities & traits
├── widget-src/                 # React frontend source
└── assets/                     # Compiled frontend assets
```

### 6.2 Data Flow Architecture

1. **Plugin WordPress (PHP 8.x)** con integrazione WooCommerce: admin pages, REST API, hook WC, gestione stato/KB
2. **App Chatbot Frontend (React):** widget flottante, responsive, personalizzabile
3. **Server intermediario (EU):** proxy sicuro per LLM/embeddings/vector DB e Stripe/licenze; rate limiting/quote
4. **Motore AI chat via OpenRouter:** Gemini 2.5 Flash (Free/Pro) e Gemini 2.5 Pro (Unlimited)
5. **Embeddings + Vector DB:** OpenAI text-embedding-3-small + Pinecone (tenant isolati)

### 6.3 RAG Pattern Implementation
**Pattern:** RAG con re-ranking per tipo contenuto/lingua/freschezza, prompt optimizer e guardrail di sicurezza.

**Process Flow:**
1. Widget invia messaggio + contesto (pagina, userId, ecc.) all'endpoint REST
2. Backend: embedding query → ricerca KB → costruzione context window (top-k) → chiamata LLM
3. La risposta può includere quick actions (aggiungi al carrello, applica coupon) e card prodotto
4. Persistenza conversazione + rating + eventuale recap email

---

## 7. User Experience Flows

### 7.1 Primary User Flow

**Scenario:** Cliente su pagina prodotto

1. **Trigger:** L'utente naviga in una pagina prodotto
2. **Proactive Engagement:** Dopo 60 secondi di inattività, il widget si apre: "Ciao! Hai domande su questo prodotto?"
3. **User Query:** L'utente chiede: "È disponibile in nero?"
4. **AI Response:** Il bot risponde mostrando la card della variante nera con il pulsante "Aggiungi al carrello"
5. **Follow-up:** L'utente aggiunge al carrello e chiede: "Quali sono i tempi di spedizione?"
6. **Policy Response:** Il bot, leggendo le policy del sito, risponde: "La spedizione standard impiega 3-5 giorni lavorativi"

### 7.2 Edge Cases & UI Guidelines

#### 7.2.1 Risposta Non Trovata
Se il bot non conosce la risposta, deve comunicarlo onestamente: "Non ho trovato questa informazione. Vuoi che invii la tua domanda a un operatore umano?". **Non deve mai inventare.**

#### 7.2.2 Card Prodotto Design
Le card dei prodotti mostrate in chat devono essere:
- Pulite e responsive
- Contenere immagine, titolo, prezzo
- Pulsante di CTA chiaro
- UI apposita graficamente bella da vedere

#### 7.2.3 Performance UX
- Il widget deve caricarsi in modo asincrono
- Le risposte a FAQ comuni devono apparire quasi istantaneamente (<300ms) grazie al caching
- Streaming testo per spostare percezione latenza
- LLM solo quando serve (altrimenti template)

### 7.3 Conversation Management
- **Valutazione finale:** della conversazione (1–5 stelle) e recap via email opzionale
- **Autovalutazione AI:** dalla stessa AI per capire cosa è andato bene e cosa no
- **Handoff umano:** Possibilità di chattare con agente reale
- **Conversation Recovery:** Email conversation summaries (Unlimited feature)

### 7.4 Advanced UX Features (Pro/Unlimited)

#### 7.4.1 Social Media Integration (Pro+)
Espansione per supportare Facebook Messenger, Instagram DM, Telegram e soprattutto WhatsApp Business direttamente dall'inbox di gestione.

#### 7.4.2 Email Chatbot Integration (Pro+)
L'integrazione con il canale email per automatizzare le risposte o le campagne.

#### 7.4.3 Advanced Checkout Integration (Unlimited)
Oltre all'aggiunta al carrello, la possibilità per il bot di guidare il cliente attraverso il checkout integrandosi più profondamente con il processo di pagamento di WooCommerce.

#### 7.4.4 Shipping Tracking (Unlimited)
La capacità del bot di mostrare lo stato di un ordine e i dettagli di tracciamento della spedizione quando un cliente loggato lo chiede.

---

## 8. Pricing Plans & Features Matrix

### 8.1 Plan Specifications

#### 8.1.1 FREE Plan
- **Prezzo:** 0 €/mese
- **Conversazioni:** 30/mese
- **Items indicizzabili:** 30
- **Modello:** Gemini 2.5 Flash
- **Branding:** "Powered by Woo AI Assistant"
- **SLA:** ritardo ~3s
- **Feature:** base (no add-to-cart; no personalizzazione messaggi; trigger OOTB disattivabili ma non personalizzabili; applicazione coupon disponibile SOLO se abilitata)

#### 8.1.2 PRO Plan
- **Prezzo:** 19 €/mese
- **Conversazioni:** 100/mese
- **Items indicizzabili:** 100
- **Modello:** Gemini 2.5 Flash
- **Feature:** base + personalizzazione messaggi predefiniti + trigger proattivi configurabili
- **Branding:** "Powered by Woo AI Assistant"
- **Note:** gestione licenza e billing via Stripe

#### 8.1.3 UNLIMITED Plan
- **Prezzo:** 39 €/mese
- **Conversazioni:** 1000/mese
- **Items indicizzabili:** 2000
- **Modello:** Gemini 2.5 Pro
- **Feature avanzate:** auto-coupon, upsell/cross-sell, add-to-cart via chat, chat-recovery, UI avanzata (white-label)
- **SLA:** immediata
- **Branding:** white-label

#### 8.1.4 MULTI WEBSITE (Agenzie)
- **Pricing:** Preventivo su misura
- **Target:** Agenzie che gestiscono vari ecommerce

### 8.2 Complete Features Matrix

| Functionality | Free | Pro | Unlimited |
|---------------|------|-----|-----------|
| **Modello LLM** | Flash | Flash | Pro |
| **Conversazioni/mese** | 30 | 100 | 1000 |
| **Items indicizzabili** | 30 | 100 | 2000 |
| **Branding** | Powered by | Powered by | White-label |
| **SLA** | Ritardo 3s | Immediata | Immediata |
| **Personalizzazione messaggi predefiniti** | — | ✓ | ✓ |
| **Trigger proattivi** | OOTB (on/off) | ✓ (config) | ✓ (config) |
| **Upsell / Cross-sell** | — | — | ✓ |
| **Auto-coupon su sentiment** | — | — | ✓ |
| **Aggiunta al carrello da chat** | — | — | ✓ |
| **Chat-recovery (consenso)** | — | — | ✓ |
| **UI avanzata / White-label** | — | — | ✓ |

---

## 9. Security & Performance Specifications

### 9.1 Security Implementation

#### 9.1.1 Input Validation & Sanitization
```php
// All user inputs must be sanitized
$user_message = sanitize_textarea_field($_POST['message']);
$conversation_id = absint($_POST['conversation_id']);
$user_email = sanitize_email($_POST['email']);
```

#### 9.1.2 Nonce Verification
```php
// All AJAX requests must include nonce verification
if (!wp_verify_nonce($_POST['nonce'], 'woo_ai_chat_action')) {
    wp_die('Security check failed');
}
```

#### 9.1.3 Capability Checks
```php
// Proper capability verification
if (!current_user_can('manage_woocommerce')) {
    wp_die('Insufficient permissions');
}
```

#### 9.1.4 Rate Limiting
- API request limits per IP/user
- Rate-limit per sessione/IP/utente (es. N tentativi/ora)
- Limiti per canale (es. solo da widget chat)

#### 9.1.5 Prompt Injection Defense
- AI guardrails against malicious prompts
- Guardrail avanzati e vincoli di contenuto per non fornire certi tipi di informazioni (es. consigli medici)
- Strategia chiara per gestire domande "fuori contesto" o assurde

### 9.2 Performance Optimization

#### 9.2.1 Caching Strategy
```php
// Multi-level caching implementation
$cache_key = "woo_ai_products_{$page}_{$per_page}";
$products = wp_cache_get($cache_key, 'woo_ai_assistant');

if (false === $products) {
    $products = $this->fetchProducts($page, $per_page);
    wp_cache_set($cache_key, $products, 'woo_ai_assistant', HOUR_IN_SECONDS);
}
```

#### 9.2.2 Database Optimization
```php
// Use prepared statements
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}woo_ai_conversations WHERE user_id = %d AND created_at > %s",
    $user_id,
    $date_threshold
));
```

#### 9.2.3 Frontend Performance
- **Bundle Size:** < 50KB gzipped for widget JS
- **Lazy Loading:** Assets load only when needed
- **React Optimization:** Use React.memo for expensive components
- **Event Handler Optimization:** Use useCallback for event handlers

### 9.3 Database Schema Requirements

#### 9.3.1 Core Tables (6 total)
1. **woo_ai_conversations** - Conversation tracking
2. **woo_ai_knowledge_base** - Indexed content
3. **woo_ai_settings** - Plugin configuration
4. **woo_ai_logs** - Action audit trail
5. **woo_ai_analytics** - Performance metrics
6. **woo_ai_user_sessions** - Session management

#### 9.3.2 Indexing Strategy
- Proper database indexes for all query patterns
- Efficient schema design for conversation lookup
- Optimized embeddings storage and retrieval

---

## 10. Coupon Management System

> **🎯 Critical Feature:** This system is core to conversion optimization and requires precise implementation.

### 10.1 System Overview
**Obiettivo:** Incrementare conversioni in modo controllato, prevenendo abusi e mantenendo margini.

### 10.2 Abilitazione e Modalità

#### 10.2.1 Toggle Principale
- **Default State:** OFF (opt-in required)
- **Location:** "Abilita gestione coupon nel chatbot" in admin settings

#### 10.2.2 Modalità Operative

##### Core: Applicazione Coupon Esistenti
- Il chatbot propone/applica SOLO coupon già presenti in WooCommerce
- Compatibili con le condizioni dell'ordine corrente
- Disponibile in tutti i piani se abilitata

##### Unlimited: Generazione Automatica Coupon
- Disponibile solo se attivata esplicitamente
- Genera coupon one-off con limiti preimpostati
- Triggered by sentiment/context analysis

### 10.3 Regole Configurabili

#### 10.3.1 User Limitations
- **Max 1 coupon per utente** (per periodo configurabile)
- **Solo su primo acquisto** (verifica storico ordini utente/email)
- **Rate-limit** per sessione/IP/utente (es. N tentativi/ora)

#### 10.3.2 Order Constraints
- **Soglia minima ordine** (es. ≥ X €)
- **Categorie/Prodotti esclusi** (blacklist) o inclusi (whitelist)
- **Sconto massimo** (cap % o importo)
- **Non cumulabile** con altre promo/coupon

#### 10.3.3 Temporal & Geographic Rules
- **Validità temporale** (es. 24/72h) e fuso
- **Ambito Paese/Lingua** (se multistore)
- **Limiti per canale** (es. solo da widget chat)

### 10.4 Guardrail Tecnici

#### 10.4.1 Server-Side Validation
- Validazione server-side con regole Woo + nostre policy
- Rifiuta applicazione se non compatibile
- I coupon auto-generati sono **non indovinabili** (tokenizzati), **monouso**, **non cumulabili**, con **hard cap** di sconto

#### 10.4.2 Audit & Control
- **Complete Logging:** Tutte le azioni sono tracciate (utente/sessione, timestamp, ordine, regola che ha concesso il coupon)
- **Kill Switch:** Possibilità di disattivare rapidamente l'auto-coupon
- **Revoke Capability:** Possibilità di revocare coupon emessi
- **Audit log e undo** su carrello

### 10.5 UX Implementation

#### 10.5.1 User Confirmation Flow
- Il chatbot **chiede conferma** prima di applicare un coupon
- Mostra il riepilogo del carrello con il nuovo totale
- Conferma esplicita richiesta dall'utente

#### 10.5.2 Transparency Requirements
- Se mancano policy, il bot **non inventa**: risponde con disclaimer ("Informazioni non definite") + CTA di contatto umano
- Onesto communication quando coupon not available
- Clear explanation of discount applied

### 10.6 Advanced Coupon Features (Unlimited)

#### 10.6.1 Smart Generation Triggers
- **Sentiment Analysis:** Quando sentiment/contesto indica frustrazione/rischio churn
- **Abandonment Detection:** Alta probabilità di abbandono
- **Behavioral Triggers:** Based on user interaction patterns

#### 10.6.2 Dynamic Discount Optimization
- Context-aware discount percentages
- Margin-protection algorithms
- A/B testing for optimal discount rates

---

## 11. API Specifications

### 11.1 REST API Endpoints

#### 11.1.1 Core Namespace
**Base URL:** `/wp-json/woo-ai-assistant/v1/`

#### 11.1.2 Chat Endpoints
```
POST /wp-json/woo-ai-assistant/v1/chat
- Purpose: Handle chat message processing
- Input: message, context (page, user_id, etc.)
- Output: AI response with possible quick actions
- Authentication: WordPress nonces
```

#### 11.1.3 Action Endpoints  
```
POST /wp-json/woo-ai-assistant/v1/action
- Purpose: Execute agentic actions (add to cart, apply coupon)
- Input: action_type, product_id, coupon_code, etc.
- Output: action result and updated state
- Authentication: User capability checks
```

#### 11.1.4 Rating Endpoints
```
POST /wp-json/woo-ai-assistant/v1/rating
- Purpose: Collect conversation ratings
- Input: conversation_id, rating (1-5), optional feedback
- Output: success confirmation
- Authentication: Session validation
```

#### 11.1.5 Configuration Endpoints
```
GET /wp-json/woo-ai-assistant/v1/config
- Purpose: Provide widget configuration
- Output: theme settings, user context, available features
- Authentication: Public endpoint with context awareness
```

### 11.2 Hook System

#### 11.2.1 Action Hooks
```php
// Before KB indexing starts
do_action('woo_ai_assistant_before_index', $content_type);

// After successful chat response  
do_action('woo_ai_assistant_chat_response', $conversation_id, $response);

// Before agentic action execution
do_action('woo_ai_assistant_before_action', $action_type, $data);
```

#### 11.2.2 Filter Hooks
```php
// Modify KB content before indexing
apply_filters('woo_ai_assistant_kb_content', $content, $post_id, $type);

// Customize chat widget settings
apply_filters('woo_ai_assistant_widget_config', $config);

// Add custom coupon rules
apply_filters('woo_ai_assistant_coupon_rules', $rules, $context);
```

### 11.3 External API Integration

#### 11.3.1 Intermediate Server Communication
- **Purpose:** Secure proxy for LLM/embeddings/vector DB and Stripe/licenses
- **Security:** Rate limiting/quote management
- **Location:** EU-based for GDPR compliance
- **Functions:** License validation, usage tracking, AI model access

#### 11.3.2 License Management API
- **Validation:** Real-time license verification
- **Graceful Degradation:** Feature limitation when license invalid
- **Telemetria:** Minimal opt-in data for debugging and improvement

---

## 12. Zero-Config Implementation Details

> **🎯 Core Differentiator:** The "plug-and-play" capability is our primary competitive advantage.

### 12.1 Zero-Config Contract
**Per rendere credibile il claim "plug-and-play",** queste operazioni devono avvenire automaticamente dopo l'installazione:

#### 12.1.1 Immediate Indexing (< 5 minutes)
1. **Product Scanning:** Indicizzazione immediata di prodotti, categorie e pagine chiave via sitemap + Woo settings
2. **Policy Extraction:** Automatic extraction from:
   - Zone di spedizione e costi
   - Metodi di pagamento attivi  
   - Pagina "Resi/Termini" (se assente, fallback onesto)
   - Luoghi di spedizione, dati negozio, etc.

#### 12.1.2 Multilingual Auto-Setup
3. **Language Detection:** Leggi lingua sito e, se WPML/Polylang, routing Q&A corretto
4. **Fallback Strategy:** Se manca la traduzione, proponi fallback trasparente

#### 12.1.3 Smart Triggers (Out-of-the-Box)
5. **Pre-configured Triggers:** PDP e carrello con:
   - Exit-intent detection
   - Inattività 60s
   - 2 scroll profondi  
   - **Nessuna regola da scrivere manualmente**

#### 12.1.4 Safe Actions
6. **Conservative Defaults:** 
   - Applica solo coupon esistenti (se funzione abilitata)
   - "Add-to-cart" richiede sempre conferma utente
   - Suggerimenti instead of automatic applications

#### 12.1.5 Zero-Integration Handoff
7. **Immediate Support Options:**
   - Email a admin WP con transcript + link ordine
   - Mailto/WhatsApp precompilato
   - Live chat dedicata nel backend di WordPress (tempo reale)

#### 12.1.6 Instant Analytics
8. **Ready Metrics:** Mini-dashboard con:
   - Resolution rate
   - Assist-conversion 
   - Tempo medio risposta
   - **Senza setup required**

#### 12.1.7 Performance Perception
9. **Optimized UX:** 
   - Cache FAQ con TTFR < 300ms
   - LLM solo quando serve (altrimenti template)
   - Streaming testo per perceived performance

#### 12.1.8 Event-Driven Updates
10. **Real-time Sync:** 
    - Cambi prezzo/stock/prodotto aggiornano la KB in pochi minuti via hook Woo
    - Non solo cron notturno ma event-driven immediato

#### 12.1.9 Smart Privacy
11. **GDPR Aware:** 
    - Se rilevi Cookies/Compliance plugins, integrazione automatica
    - Altrimenti modalità "minima" finché non c'è consenso

### 12.2 Defensive Measures ("Zero Setup" Risk Mitigation)

#### 12.2.1 Content Gaps
**Risk:** Molte store non hanno "Resi/Spedizioni" complete
**Mitigation:** 
- Health Score KB + suggerimenti automatici
- Template bozza 1-click per pagine mancanti
- Onest fallback communication

#### 12.2.2 AI Hallucinations  
**Risk:** Se mancano dati, AI potrebbe inventare policy
**Mitigation:**
- Disclaimer + CTA umana (email/WhatsApp) invece di inventare
- "Il merchant non ha definito X" responses
- Clear boundaries on what AI can/cannot answer

#### 12.2.3 Coupon Abuse
**Risk:** Automatic coupon generation could be exploited  
**Mitigation:**
- Default = solo applicazione coupon esistenti
- Auto-coupon solo Unlimited, opt-in, con limiti preimpostati e kill-switch
- Comprehensive audit logging

#### 12.2.4 Performance Impact
**Risk:** Heavy indexing could slow down site
**Mitigation:**
- Fallback a risposte template per FAQ ad alta frequenza
- Streaming sposta percezione latenza
- Batch processing with resource limits
- Smart caching strategy

### 12.3 Demonstrable Quality (MVP Acceptance)
- **Automatic Test:** Subito dopo l'install, test automatico di 10 Q&A (spedizioni/tempi/resi/pagamenti + 6 su catalogo) con punteggio ≥ 80%
- **Freshness Test:** Pulsante "Ri-esegui test di freschezza" (mostra in quanti minuti l'ultimo cambio SKU è stato recepito)
- **First Success:** Primo assist-conversion registrato entro 48h su store con > 1k visite/giorno

---

## 13. Glossary & Terminology

### 13.1 Technical Terms

#### 13.1.1 Core Concepts
- **KB (Knowledge Base):** Indicizzata del sito, continuously updated content repository
- **RAG (Retrieval-Augmented Generation):** AI pattern combining knowledge retrieval with generation
- **Server intermediario:** Proxy che gestisce sicurezza/licenze/pagamenti in EU
- **Tenant:** Singolo sito cliente with isolated data
- **TTFR (Time to First Response):** Performance metric for initial bot response
- **Assist-Conversion:** Orders completed after chatbot interaction

#### 13.1.2 AI & Machine Learning
- **Embeddings:** Vector representations of content for semantic search
- **Vector DB:** Pinecone database for similarity search
- **Context Window:** Text provided to AI model for response generation
- **Prompt Injection:** Security attack through malicious user inputs
- **Guardrails:** Safety measures preventing inappropriate AI behavior

#### 13.1.3 eCommerce Specific
- **Agentic Actions:** Bot actions that modify state (add to cart, apply coupon)
- **Handoff:** Transfer of conversation from bot to human agent
- **Proactive Triggers:** Automated chat initiation based on user behavior
- **Zero-Config:** No manual setup required after plugin activation

### 13.2 Business Terms

#### 13.2.1 Metrics & KPIs
- **Resolution Rate:** % of conversations solved without human intervention
- **Human Takeover Rate:** % of conversations requiring human assistance  
- **KB Health Score:** Completeness assessment of knowledge base
- **Freshness Lag:** Time between content update and KB synchronization

#### 13.2.2 Features & Capabilities
- **White-label:** Complete branding customization (Unlimited plan)
- **Chat Recovery:** Email conversation summaries for users
- **Sentiment Analysis:** AI detection of user emotional state
- **Cross-sell/Upsell:** Product recommendation strategies

### 13.3 WordPress & WooCommerce Integration
- **Hook System:** WordPress action/filter integration points
- **PSR-4:** PHP autoloading standard for class organization
- **Nonce:** WordPress security tokens for form validation
- **Capability Checks:** WordPress user permission system
- **i18n (Internationalization):** Multi-language support system

---

## 📚 Related Documentation

- **[CLAUDE.md](../../CLAUDE.md)** - Complete development guidelines, coding standards, and workflow
- **[ARCHITETTURA.md](../../ARCHITETTURA.md)** - Detailed file structure and component architecture  
- **[ROADMAP.md](../../ROADMAP.md)** - Active development roadmap and task tracking
- **[README.md](../../README.md)** - Quick start guide for developers
- **[DEVELOPMENT_CONFIG_README.md](./DEVELOPMENT_CONFIG_README.md)** - Development environment configuration
- **[TESTING_GUIDE.md](./TESTING_GUIDE.md)** - Comprehensive testing guidelines
- **[DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)** - Production deployment checklist

---

**Document Status:** ✅ Complete - Ready for Development  
**Next Action:** Begin Phase 0 implementation following `ROADMAP.md`  
**AI Guidance:** This document provides comprehensive specifications for precise development execution