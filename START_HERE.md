# 🚀 Woo AI Assistant - Start Here

**Welcome to the Woo AI Assistant project!** This is your entry point to understanding and contributing to the AI-powered WooCommerce chatbot plugin.

## 📋 Quick Navigation

### 🎯 I'm New to This Project
👉 **Start here:** [DEVELOPMENT_GUIDE.md](./DEVELOPMENT_GUIDE.md)
- Complete setup instructions for MAMP environment
- Step-by-step development workflow
- Code examples and best practices
- Common issues and solutions

### 🤖 I Need to Understand the Specialized Agents
👉 **Essential reading:** [CLAUDE.md](./CLAUDE.md)
- **CRITICAL:** Specialized AI agents workflow (wp-backend-developer, react-frontend-specialist, qa-testing-specialist, roadmap-project-manager)
- Coding standards and conventions
- Quality gates and testing requirements
- Environment configuration

### 📅 I Want to Start Working on Tasks
👉 **Check roadmap:** [ROADMAP.md](./ROADMAP.md)
- Current project status and next tasks
- Task dependencies and progress tracking
- File coverage checklist
- Bug tracker and notes

### 🏗️ I Need to Understand the Architecture
👉 **Technical details:** [ARCHITETTURA.md](./ARCHITETTURA.md)
- Complete file structure and component relationships
- Directory organization and naming conventions
- Technology stack and dependencies

### 📋 I Want Business Context and Requirements
👉 **Full specifications:** [PROJECT_SPECIFICATIONS.md](./PROJECT_SPECIFICATIONS.md)
- Business goals and user stories
- Feature requirements and pricing plans
- API specifications and security requirements
- Zero-config implementation details

### 🧪 I Need Testing Information
👉 **Progressive testing:** [TESTING_STRATEGY.md](./TESTING_STRATEGY.md)
- Phase-by-phase testing approach
- Test execution commands
- Coverage requirements by development phase

### 📊 I Want Current Project Status
👉 **Status overview:** [PROJECT_STATUS.md](./PROJECT_STATUS.md)
- Real-time development progress
- Active/completed milestones
- Team assignments and blockers

---

## ⚡ Quick Start (30 seconds)

```bash
# 1. Clone and setup
cd /Applications/MAMP/htdocs/wp/wp-content/plugins/
git clone [repository-url] woo-ai-assistant
cd woo-ai-assistant

# 2. Configure environment
cp .env.example .env
# Edit .env with your API keys

# 3. Install dependencies
composer install && npm install

# 4. Check roadmap for next task
open ROADMAP.md
```

## 🎯 Critical Success Rules

### 1. 🤖 Always Use Specialized Agents
- **wp-backend-developer** for PHP/WordPress tasks
- **react-frontend-specialist** for React/frontend tasks  
- **qa-testing-specialist** for ALL task completions
- **roadmap-project-manager** for status updates

### 2. 📋 Follow the Mandatory Workflow
```
1. Check ROADMAP.md for next task
2. Mark task as "in_progress"
3. Implement following CLAUDE.md standards
4. Run quality gates (MANDATORY)
5. Mark as "completed" only after QA passes
```

### 3. ✅ Never Skip Quality Gates
```bash
# MUST run before marking any task complete
composer run quality-gates-enforce
# Check status file shows: "QUALITY_GATES_STATUS=PASSED"
cat .quality-gates-status
```

---

## 📚 Document Hierarchy

```
START_HERE.md          ← YOU ARE HERE (entry point)
├── DEVELOPMENT_GUIDE.md    ← Setup and workflow
├── CLAUDE.md               ← Agents and standards  
├── ROADMAP.md             ← Task tracking
├── ARCHITETTURA.md        ← Technical architecture
├── PROJECT_SPECIFICATIONS.md ← Business requirements
├── TESTING_STRATEGY.md    ← Progressive testing
└── PROJECT_STATUS.md      ← Current status
```

---

## 🆘 Need Help?

1. **Setup Issues?** → Check [DEVELOPMENT_GUIDE.md](./DEVELOPMENT_GUIDE.md) "Common Issues & Solutions"
2. **Coding Standards?** → See [CLAUDE.md](./CLAUDE.md) "Coding Standards" section
3. **Agent Confusion?** → Review [CLAUDE.md](./CLAUDE.md) "Specialized Agents Workflow"
4. **Task Questions?** → Check [ROADMAP.md](./ROADMAP.md) task specifications
5. **Architecture Questions?** → Reference [ARCHITETTURA.md](./ARCHITETTURA.md)

---

## 🎯 Ready to Start?

**Next Action:** Open [DEVELOPMENT_GUIDE.md](./DEVELOPMENT_GUIDE.md) and begin with the environment setup, then check [ROADMAP.md](./ROADMAP.md) for your first task!

---

*Generated with Claude Code - Last Updated: 2025-08-30*