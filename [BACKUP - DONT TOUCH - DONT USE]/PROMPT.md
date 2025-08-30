# Sviluppo Plugin Woo AI Assistant

Leggi questi documenti nell'ordine:

1. @ROADMAP.md - per identificare il prossimo task TO DO
2. @CLAUDE.md - per il workflow obbligatorio degli agenti
3. @ARCHITETTURA.md - per la struttura dei file

Leggi se necessario anche:

1. @START_HERE.md - Come entry point e overview
2. @DEVELOPMENT_GUIDE.md - Per setup e workflow pratico

Altra documentazione che potrebbe servirti:

1. @PROJECT_SPECIFICATIONS.md
2. @TESTING_STRATEGY.md
3. @PROJECT_STATUS.md

## Istruzioni

1. Identifica autonomamente dal ROADMAP.md quale è il prossimo task con status "TO DO"
2. Verifica che tutte le dipendenze siano completate
3. Implementa SOLO quel task seguendo il workflow obbligatorio
4. FERMATI dopo aver completato il task e mostrami il risultato
5. NON procedere al task successivo senza mia conferma
6. Tieni sempre aggiornata la ROADMAP.md nel caso in cui c'è la necessità di aggiungere informazioni rilevanti

## Workflow Obbligatorio (da CLAUDE.md)

- roadmap-project-manager → marca "in_progress"
- wp-backend-developer O react-frontend-specialist → implementa
- qa-testing-specialist → verifica quality gates
- roadmap-project-manager → marca "completed" SOLO se QA passa

## Output Atteso

Al termine mostrami:

- Task implementato
- File creati/modificati
- Status quality gates
- Prossimo task in coda
