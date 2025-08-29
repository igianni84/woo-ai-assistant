# ROADMAP-2: Emergency Debugging & System Recovery

**Project:** Woo AI Assistant - Crisis Resolution  
**Created:** 2025-08-27  
**Priority:** CRITICAL - System Non-Functional  
**Status:** Emergency Recovery Mode

---

## 🚨 SITUAZIONE ATTUALE

### Problemi Identificati dall'Audit:
1. **❌ Sito localhost:8888/wp non si apre** - Errore critico PHP 
2. **❌ Pagina Settings del plugin in errore** - Classe/file mancante
3. **❌ Knowledge Base non si crea** - Hook system non funzionante  
4. **❌ Debug.log verboso** - Errori continui senza API keys
5. **❌ Test non passano** - 15/30 ConversationHandler tests failing
6. **❌ API Keys non configurate** - Nessun sistema di configurazione

### Gap Architetturale dal ROADMAP.md:
- **Task 9.4: Final Architecture Verification** ⬜ NON ESEGUITO
- **Acceptance Criteria Checklist:** TUTTE le checkbox vuote
- **File mancanti:** KnowledgeBase/Hooks.php, Admin/Assets.php
- **Integration testing:** Mai eseguito end-to-end

---

## 🎯 PIANO DI RECUPERO - FASI PRIORITARIE

### **FASE 1: DIAGNOSTICA E STABILIZZAZIONE** ⚠️
*Stima: 2-4 ore*
*Obiettivo: Far funzionare il sito WordPress di base*

#### Task 1.1: Sistema Base Recovery 🔄 IN_PROGRESS
**Priorità: CRITICA**
*Status: IN_PROGRESS*
*Started: 2025-08-27*
*Assigned to: Emergency Recovery Team*
- [ ] **Verifica errori fatali PHP**
  ```bash
  # Controlliamo il log degli errori
  tail -f /Applications/MAMP/logs/php_error.log
  ```
- [ ] **Test plugin activation/deactivation**
  - Disattiva il plugin
  - Verifica che il sito torni online
  - Identifica errori fatali in fase di attivazione
- [ ] **Controllo autoloader PSR-4**
  - Verifica composer autoload funzionante
  - Controlla namespace e class loading
- [ ] **Database connectivity test**
  - Verifica connessione database
  - Controlla creazione tabelle

#### Task 1.2: File Architecture Verification ⬜  
**Priorità: CRITICA**
- [ ] **Eseguire Task 9.4 mai completato**
  ```bash
  # Script di verifica file da eseguire
  php scripts/verify-architecture.php
  ```
- [ ] **Verificare tutti i file da ROADMAP.md File Coverage Checklist**
  - 54/69 files completed - identificare i 15 mancanti
  - Focus sui file critici: KnowledgeBase/Hooks.php, Admin/Assets.php
- [ ] **Test class loading**
  - Verifica autoload di tutte le classi in src/
  - Controlla use statements e namespace

#### Task 1.3: Errori Debug Log Analysis ⬜
**Priorità: ALTA**  
- [ ] **Analizza WordPress debug.log**
  ```bash
  tail -100 /Applications/MAMP/htdocs/wp/wp-content/debug.log
  ```
- [ ] **Identifica pattern di errori**
- [ ] **Traccia errori API mancanti**
- [ ] **Documenta tutti gli errori trovati**

---

### **FASE 2: RIPRISTINO FUNZIONALITA' CORE** 🔧
*Stima: 4-6 ore*
*Obiettivo: Plugin attivabile senza errori*

#### Task 2.1: File Mancanti Implementation ⬜
**Priorità: CRITICA**
- [ ] **Creare KnowledgeBase/Hooks.php**
  - Implementare hook WooCommerce per aggiornamento KB
  - Hook per save_post, product_update, etc.
  - Sistema event-driven mancante
- [ ] **Creare Admin/Assets.php** 
  - Asset enqueueing per admin pages
  - CSS/JS loading corretto
- [ ] **Altri file dalla checklist mancanti**
  - docs/user-guide.md 
  - docs/developer-guide.md
  - docs/api-reference.md (può essere posticipato)

#### Task 2.2: Settings Page Repair ⬜
**Priorità: ALTA**
- [ ] **Debug Admin/Pages/SettingsPage.php**
  - Verifica rendering senza errori  
  - Check form handling
  - Verifica security nonces
- [ ] **Test admin menu rendering**
  - Verifica AdminMenu.php funzionante
  - Test navigation tra le pagine
- [ ] **Admin assets loading**
  - CSS admin.css caricato correttamente
  - JS admin.js funzionante

#### Task 2.3: Database & Installation Fix ⬜
**Priorità: ALTA**
- [ ] **Verifica Setup/Activator.php**
  - Test creazione tabelle database
  - Verifica schema corretto
  - Check default options creation
- [ ] **Test Setup/AutoIndexer.php**
  - Verifica prima indicizzazione funzionante
  - Debug knowledge base creation
- [ ] **Fix Knowledge Base Hooks**
  - Implementare hook per "Reindex Knowledge Base" button
  - Connettere frontend action a backend processing

---

### **FASE 3: SISTEMA DI CONFIGURAZIONE API** ⚙️
*Stima: 3-4 ore*
*Obiettivo: Sistema per inserire API keys*

#### Task 3.1: API Configuration System ⬜
**Priorità: CRITICA**
- [ ] **Creare sezione Settings per API Keys**
  ```php
  // In SettingsPage.php
  - Sezione "API Configuration"  
  - Campo "OpenRouter API Key"
  - Campo "OpenAI Embedding API Key"
  - Campo "Pinecone API Key"
  - Campo "Server Intermediario URL"
  ```
- [ ] **Secure storage delle API keys**
  - Utilizzo WordPress options con encryption
  - Mascheramento nei form (password fields)
- [ ] **Validation delle API keys**
  - Test connectivity con servizi esterni
  - Feedback visual sullo stato delle keys

#### Task 3.2: Environment Configuration ⬜
**Priorità: ALTA**
- [ ] **Creare sistema .env per development**
  ```bash
  # File .env template per development
  OPENROUTER_API_KEY=your_key_here
  OPENAI_EMBEDDING_KEY=your_key_here  
  PINECONE_API_KEY=your_key_here
  WOO_AI_DEBUG=true
  ```
- [ ] **Environment detection**
  - Development vs Production mode
  - Debug verbosity levels
  - Graceful degradation senza API keys

#### Task 3.3: API Integration Testing ⬜
**Priorità: MEDIA**
- [ ] **Test connectivity con OpenRouter**
- [ ] **Test embedding generation con OpenAI** 
- [ ] **Test Pinecone vector storage**
- [ ] **Mock services per development senza keys**

---

### **FASE 4: TESTING & QUALITY GATES** ✅
*Stima: 2-3 ore*
*Obiettivo: System working end-to-end*

#### Task 4.1: Unit Tests Fixing ⬜
**Priorità: MEDIA**
- [ ] **Fix ConversationHandler tests (15/30 failing)**
  - Debug mock database interactions
  - Fix conversation ID generation tests
  - Adjust test expectations
- [ ] **Run complete test suite**
  ```bash
  composer run test
  npm run test
  ```
- [ ] **Achieve >90% test coverage target**

#### Task 4.2: Integration Testing ⬜
**Priorità: ALTA**
- [ ] **End-to-end user flow testing**
  - Plugin activation → KB indexing → Chat response
  - Complete user journey validation  
- [ ] **Cross-browser widget testing**
- [ ] **WordPress/WooCommerce compatibility**
- [ ] **Performance benchmarking**

#### Task 4.3: Acceptance Criteria Validation ⬜
**Priorità: CRITICA**
- [ ] **MVP Acceptance Criteria (currently ALL unchecked)**
  ```
  ✅ Install and activate in <5 minutes
  ✅ Auto-indexing of at least 30 products  
  ✅ Basic chat functionality working
  ✅ 10 Q&A test passes with >80% accuracy
  ✅ Dashboard shows basic KPIs
  ```
- [ ] **Final Task 9.4: Final Architecture Verification**
- [ ] **Complete ROADMAP.md checklist**

---

### **FASE 5: DOCUMENTATION & DEPLOYMENT** 📚
*Stima: 4-6 ore*
*Obiettivo: Production ready release*

#### Task 5.1: User Documentation ⬜
**Priorità: BASSA** (post-bugfix)
- [ ] **Installation Guide**
- [ ] **API Configuration Guide** 
- [ ] **Troubleshooting Guide**
- [ ] **FAQ Common Issues**

#### Task 5.2: Final Release Preparation ⬜
**Priorità: BASSA** (post-bugfix)
- [ ] **Complete Task 10.2: Distribution & Updates**
- [ ] **Production deployment checklist**
- [ ] **Backup/rollback procedures**

---

## 🔄 EXECUTION WORKFLOW

### Sequenza Obbligatoria:
```
1. 🚨 FASE 1 → Stabilizza il sistema (sito funzionante)
2. 🔧 FASE 2 → Ripristina funzionalità core (plugin attivabile)  
3. ⚙️ FASE 3 → Sistema API configuration (funzionalità operative)
4. ✅ FASE 4 → Testing & validation (quality assurance)
5. 📚 FASE 5 → Documentation (opzionale per ora)
```

### Daily Checkpoints:
- [ ] **Checkpoint 1**: Sito WordPress accessibile
- [ ] **Checkpoint 2**: Plugin attivabile senza errori
- [ ] **Checkpoint 3**: Settings page funzionante  
- [ ] **Checkpoint 4**: Knowledge Base si crea correttamente
- [ ] **Checkpoint 5**: Chat widget appare e risponde
- [ ] **Checkpoint 6**: Test API integrations funzionanti

---

## ⚠️ RISK MITIGATION

### Fallback Strategy:
1. **Se errori fatali persistono**: Rollback a versione funzionante minima
2. **Se API integration fallisce**: Implement dummy/mock responses  
3. **Se tests non passano**: Focus su core functionality prima
4. **Se performance issues**: Optimize dopo stabilizzazione

### Emergency Contacts:
- **Priority**: System stability over feature completeness
- **Documentation**: Tutti i fix devono essere documentati
- **Testing**: Ogni fix deve essere testato prima di procedere

---

## 🎯 SUCCESS CRITERIA

### Minimum Viable Recovery:
- [ ] ✅ Sito WordPress accessibile su localhost:8888/wp
- [ ] ✅ Plugin attivabile/disattivabile senza errori
- [ ] ✅ Settings page apre senza crash  
- [ ] ✅ API keys configurabili tramite admin
- [ ] ✅ Knowledge Base reindexing funzionante
- [ ] ✅ Debug.log pulito (no error spam)

### Full System Recovery:
- [ ] ✅ Chat widget visible and responsive
- [ ] ✅ Basic AI responses working (with API keys)
- [ ] ✅ End-to-end user journey completed
- [ ] ✅ All Acceptance Criteria MVP checked ✅
- [ ] ✅ Task 9.4 Final Architecture Verification ✅

---

**🚀 READY TO BEGIN EMERGENCY RECOVERY**

**Next Action:** Iniziare con FASE 1, Task 1.1 - Sistema Base Recovery

**Metodologia:** Utilizzare gli agent specializzati:
- **qa-testing-specialist** per diagnostica e testing
- **wp-backend-developer** per fix PHP/WordPress  
- **roadmap-project-manager** per tracking progress

**Note:** Focus su stabilità prima di funzionalità. Un plugin funzionante al 60% è meglio di un plugin "completo" che crasha il sito.