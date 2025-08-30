/**
 * Admin Entry Point
 *
 * Entry point for React components used in the WordPress admin interface.
 * This file handles admin-specific functionality and components.
 *
 * @package WooAiAssistant
 * @subpackage Admin
 * @since 1.0.0
 */

import { createRoot } from 'react-dom/client';
import './styles/admin.scss';

// Initialize admin components when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  initializeAdminComponents();
});

/**
 * Initialize React components in admin interface
 */
function initializeAdminComponents() {
  // Initialize Knowledge Base Status component
  initializeKnowledgeBaseStatus();

  // Initialize Settings components
  initializeSettingsComponents();

  // Initialize Dashboard widgets
  initializeDashboardWidgets();

  // Admin components initialization complete
}

/**
 * Initialize Knowledge Base Status component
 */
function initializeKnowledgeBaseStatus() {
  const container = document.getElementById('woo-ai-kb-status');
  if (container) {
    const root = createRoot(container);
    root.render(<KnowledgeBaseStatus />);
  }
}

/**
 * Initialize Settings page components
 */
function initializeSettingsComponents() {
  const container = document.getElementById('woo-ai-settings-react');
  if (container) {
    const root = createRoot(container);
    root.render(<SettingsManager />);
  }
}

/**
 * Initialize Dashboard widgets
 */
function initializeDashboardWidgets() {
  const container = document.getElementById('woo-ai-dashboard-stats');
  if (container) {
    const root = createRoot(container);
    root.render(<DashboardStats />);
  }
}

/**
 * Knowledge Base Status Component
 */
const KnowledgeBaseStatus = () => {
  return (
    <div className='woo-ai-kb-status'>
      <div className='status-indicator'>
        <div className='status-dot active'></div>
        <span>Knowledge Base: Ready</span>
      </div>
      <div className='kb-stats'>
        <div className='stat'>
          <strong>Products:</strong> 0
        </div>
        <div className='stat'>
          <strong>Pages:</strong> 0
        </div>
        <div className='stat'>
          <strong>Last Updated:</strong> Never
        </div>
      </div>
    </div>
  );
};

/**
 * Settings Manager Component
 */
const SettingsManager = () => {
  return (
    <div className='woo-ai-settings-manager'>
      <div className='settings-section'>
        <h3>API Configuration</h3>
        <p>Configure your AI service connections here.</p>
        <div className='connection-status'>
          <span className='status-indicator inactive'>Not Connected</span>
        </div>
      </div>
    </div>
  );
};

/**
 * Dashboard Stats Component
 */
const DashboardStats = () => {
  return (
    <div className='woo-ai-dashboard-stats'>
      <div className='stats-grid'>
        <div className='stat-item'>
          <div className='stat-number'>0</div>
          <div className='stat-label'>Conversations</div>
        </div>
        <div className='stat-item'>
          <div className='stat-number'>0</div>
          <div className='stat-label'>Messages</div>
        </div>
        <div className='stat-item'>
          <div className='stat-number'>0</div>
          <div className='stat-label'>Products Indexed</div>
        </div>
        <div className='stat-item'>
          <div className='stat-number'>N/A</div>
          <div className='stat-label'>Satisfaction</div>
        </div>
      </div>
    </div>
  );
};

// Export for testing
export {
  initializeAdminComponents,
  initializeKnowledgeBaseStatus,
  initializeSettingsComponents,
  initializeDashboardWidgets,
  KnowledgeBaseStatus,
  SettingsManager,
  DashboardStats
};
