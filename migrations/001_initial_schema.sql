-- ====================================================================
-- Woo AI Assistant - Initial Database Schema
-- Version: 1.0.0
-- Migration: 001
-- Description: Creates the 6 core tables for the Woo AI Assistant plugin
-- ====================================================================

-- Enable foreign key checks for this migration
SET foreign_key_checks = 1;

-- ====================================================================
-- Table: woo_ai_conversations
-- Purpose: Tracks user conversations with the AI assistant
-- ====================================================================
CREATE TABLE IF NOT EXISTS `{prefix}woo_ai_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `session_id` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `rating` tinyint(1) unsigned DEFAULT NULL,
  `context_data` longtext DEFAULT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `total_messages` int(10) unsigned NOT NULL DEFAULT 0,
  `handoff_requested` tinyint(1) NOT NULL DEFAULT 0,
  `handoff_email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_rating` (`rating`),
  KEY `idx_handoff` (`handoff_requested`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- Table: woo_ai_messages
-- Purpose: Stores individual messages within conversations
-- ====================================================================
CREATE TABLE IF NOT EXISTS `{prefix}woo_ai_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `role` enum('user', 'assistant', 'system') NOT NULL,
  `content` longtext NOT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tokens_used` int(10) unsigned DEFAULT NULL,
  `processing_time_ms` int(10) unsigned DEFAULT NULL,
  `model_used` varchar(50) DEFAULT NULL,
  `temperature` decimal(3,2) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_role` (`role`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tokens_used` (`tokens_used`),
  FOREIGN KEY (`conversation_id`) REFERENCES `{prefix}woo_ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- Table: woo_ai_knowledge_base
-- Purpose: Stores indexed content chunks with embeddings for RAG
-- ====================================================================
CREATE TABLE IF NOT EXISTS `{prefix}woo_ai_knowledge_base` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content_type` varchar(50) NOT NULL,
  `content_id` bigint(20) unsigned DEFAULT NULL,
  `chunk_text` longtext NOT NULL,
  `embedding` longtext DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `chunk_hash` varchar(64) NOT NULL,
  `chunk_index` int(10) unsigned NOT NULL DEFAULT 0,
  `total_chunks` int(10) unsigned NOT NULL DEFAULT 1,
  `word_count` int(10) unsigned DEFAULT NULL,
  `embedding_model` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chunk_hash` (`chunk_hash`),
  KEY `idx_content_type` (`content_type`),
  KEY `idx_content_id` (`content_id`),
  KEY `idx_updated_at` (`updated_at`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_content_lookup` (`content_type`, `content_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- Table: woo_ai_settings
-- Purpose: Stores plugin configuration and settings
-- ====================================================================
CREATE TABLE IF NOT EXISTS `{prefix}woo_ai_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `autoload` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `setting_group` varchar(50) DEFAULT 'general',
  `is_sensitive` tinyint(1) NOT NULL DEFAULT 0,
  `validation_rule` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_autoload` (`autoload`),
  KEY `idx_setting_group` (`setting_group`),
  KEY `idx_is_sensitive` (`is_sensitive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- Table: woo_ai_analytics
-- Purpose: Tracks performance metrics and usage statistics
-- ====================================================================
CREATE TABLE IF NOT EXISTS `{prefix}woo_ai_analytics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_type` varchar(50) NOT NULL,
  `metric_value` decimal(15,4) NOT NULL,
  `context` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `additional_data` longtext DEFAULT NULL,
  `source` varchar(50) DEFAULT 'plugin',
  PRIMARY KEY (`id`),
  KEY `idx_metric_type` (`metric_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_metrics_lookup` (`metric_type`, `created_at`),
  FOREIGN KEY (`conversation_id`) REFERENCES `{prefix}woo_ai_conversations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- Table: woo_ai_action_logs
-- Purpose: Audit trail for all actions performed by the assistant
-- ====================================================================
CREATE TABLE IF NOT EXISTS `{prefix}woo_ai_action_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action_type` varchar(50) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `execution_time_ms` int(10) unsigned DEFAULT NULL,
  `severity` enum('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
  PRIMARY KEY (`id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_success` (`success`),
  KEY `idx_severity` (`severity`),
  KEY `idx_audit_lookup` (`action_type`, `created_at`, `success`),
  FOREIGN KEY (`conversation_id`) REFERENCES `{prefix}woo_ai_conversations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- Insert default settings
-- ====================================================================
INSERT IGNORE INTO `{prefix}woo_ai_settings` (`setting_key`, `setting_value`, `setting_group`, `is_sensitive`) VALUES
('plugin_version', '1.0.0', 'system', 0),
('installation_date', NOW(), 'system', 0),
('widget_enabled', '1', 'general', 0),
('widget_position', 'bottom-right', 'general', 0),
('widget_color_primary', '#007cba', 'appearance', 0),
('widget_color_secondary', '#ffffff', 'appearance', 0),
('max_conversations_per_session', '10', 'limits', 0),
('conversation_timeout_minutes', '30', 'limits', 0),
('enable_analytics', '1', 'privacy', 0),
('enable_logging', '1', 'system', 0),
('kb_auto_sync_enabled', '1', 'knowledge_base', 0),
('kb_sync_interval_hours', '24', 'knowledge_base', 0),
('ai_temperature', '0.7', 'ai', 0),
('ai_max_tokens', '1000', 'ai', 0),
('proactive_triggers_enabled', '1', 'features', 0),
('coupon_generation_enabled', '1', 'features', 0);

-- ====================================================================
-- Create database views for common queries
-- ====================================================================

-- View: Recent conversations with message counts
CREATE OR REPLACE VIEW `{prefix}woo_ai_conversation_summary` AS
SELECT 
    c.id,
    c.user_id,
    c.session_id,
    c.created_at,
    c.updated_at,
    c.status,
    c.rating,
    c.total_messages,
    c.handoff_requested,
    COALESCE(u.display_name, 'Guest') as user_name,
    (SELECT content FROM `{prefix}woo_ai_messages` WHERE conversation_id = c.id AND role = 'user' ORDER BY created_at ASC LIMIT 1) as first_message,
    (SELECT created_at FROM `{prefix}woo_ai_messages` WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_activity
FROM `{prefix}woo_ai_conversations` c
LEFT JOIN `{wpdb_users}` u ON c.user_id = u.ID;

-- View: Knowledge base content summary
CREATE OR REPLACE VIEW `{prefix}woo_ai_kb_summary` AS
SELECT 
    content_type,
    COUNT(*) as total_chunks,
    SUM(word_count) as total_words,
    MAX(updated_at) as last_updated,
    COUNT(DISTINCT content_id) as unique_content_items
FROM `{prefix}woo_ai_knowledge_base` 
WHERE is_active = 1
GROUP BY content_type;

-- ====================================================================
-- Migration completion marker
-- ====================================================================
INSERT INTO `{prefix}woo_ai_settings` (`setting_key`, `setting_value`, `setting_group`, `is_sensitive`) VALUES
('migration_001_completed', NOW(), 'system', 0)
ON DUPLICATE KEY UPDATE `setting_value` = NOW();