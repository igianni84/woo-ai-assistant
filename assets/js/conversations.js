/**
 * Conversations Log Page JavaScript
 * 
 * Handles all interactive functionality for the conversations log page including
 * viewing details, exporting data, search, filtering, and bulk actions.
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        const ConversationsManager = {
            
            // Configuration
            config: {
                ajaxUrl: wooAiConversations.ajaxUrl,
                nonce: wooAiConversations.nonce,
                strings: wooAiConversations.strings,
                confidenceThresholds: wooAiConversations.confidenceThresholds,
                maxExportLimit: wooAiConversations.maxExportLimit
            },

            // Current state
            state: {
                currentConversation: null,
                isLoading: false,
                selectedConversations: [],
                currentFilter: {}
            },

            /**
             * Initialize the conversations manager
             */
            init: function() {
                this.bindEvents();
                this.initializeSearch();
                this.initializeModals();
                this.initializeBulkActions();
            },

            /**
             * Bind all event handlers
             */
            bindEvents: function() {
                // View conversation details
                $(document).on('click', '.view-conversation-details', this.handleViewDetails.bind(this));
                
                // Export conversations
                $('#export-conversations').on('click', this.handleExportClick.bind(this));
                $('#export-form').on('submit', this.handleExportSubmit.bind(this));
                
                // End conversation
                $(document).on('click', '.end-conversation', this.handleEndConversation.bind(this));
                
                // Delete conversation
                $(document).on('click', '.delete-conversation', this.handleDeleteConversation.bind(this));
                
                // Modal close buttons
                $('.modal-close').on('click', this.closeModal.bind(this));
                
                // Close modal on outside click
                $('.conversation-modal').on('click', function(e) {
                    if ($(e.target).hasClass('conversation-modal')) {
                        ConversationsManager.closeModal();
                    }
                });
                
                // ESC key to close modal
                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape') {
                        ConversationsManager.closeModal();
                    }
                });
                
                // Filter form submission
                $('#conversations-filter-form').on('submit', this.handleFilterSubmit.bind(this));
                
                // Real-time search
                $('#search-conversations').on('input', this.debounce(this.handleSearchInput.bind(this), 500));
                
                // Select all checkbox
                $('#cb-select-all-1, #cb-select-all-2').on('change', this.handleSelectAll.bind(this));
                
                // Individual checkbox
                $(document).on('change', 'input[name="conversation[]"]', this.updateSelectedConversations.bind(this));
            },

            /**
             * Initialize search functionality
             */
            initializeSearch: function() {
                // Add search suggestions dropdown
                const $searchInput = $('#search-conversations');
                if ($searchInput.length) {
                    $searchInput.wrap('<div class="search-wrapper"></div>');
                    $searchInput.after('<div class="search-suggestions" style="display: none;"></div>');
                }
            },

            /**
             * Initialize modal dialogs
             */
            initializeModals: function() {
                // Ensure modals are appended to body for proper z-index
                $('.conversation-modal').appendTo('body');
            },

            /**
             * Initialize bulk actions
             */
            initializeBulkActions: function() {
                // Override default bulk action behavior
                $('#doaction, #doaction2').on('click', function(e) {
                    const action = $(this).prev('select').val();
                    
                    if (action === 'export' || action === 'delete' || action === 'mark_resolved') {
                        e.preventDefault();
                        ConversationsManager.handleBulkAction(action);
                    }
                });
            },

            /**
             * Handle viewing conversation details
             */
            handleViewDetails: function(e) {
                e.preventDefault();
                
                const conversationId = $(e.currentTarget).data('conversation-id');
                
                if (!conversationId) {
                    this.showNotification('error', 'Invalid conversation ID');
                    return;
                }
                
                this.loadConversationDetails(conversationId);
            },

            /**
             * Load conversation details via AJAX
             */
            loadConversationDetails: function(conversationId) {
                const self = this;
                
                // Show modal with loading state
                $('#conversation-details-modal').addClass('active');
                $('#conversation-details-content').html('<div class="loading">' + this.config.strings.loading + '</div>');
                
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'woo_ai_conversation_details',
                        conversation_id: conversationId,
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            self.renderConversationDetails(response.data.data);
                        } else {
                            self.renderDetailsError(response.data?.message || self.config.strings.error);
                        }
                    },
                    error: function() {
                        self.renderDetailsError(self.config.strings.error);
                    }
                });
            },

            /**
             * Render conversation details in modal
             */
            renderConversationDetails: function(data) {
                const conversation = data.conversation;
                const messages = data.messages || [];
                const metrics = data.metrics || {};
                const kbSnippets = data.kb_snippets || [];
                
                let html = '';
                
                // Conversation info section
                html += '<div class="conversation-info">';
                html += '<h3>Conversation Information</h3>';
                html += '<div class="info-grid">';
                html += this.renderInfoItem('Conversation ID', conversation.conversation_id);
                html += this.renderInfoItem('User', conversation.user_name || 'Guest');
                html += this.renderInfoItem('Status', this.renderStatusBadge(conversation.status));
                html += this.renderInfoItem('Started', this.formatDate(conversation.started_at));
                html += this.renderInfoItem('Ended', conversation.ended_at ? this.formatDate(conversation.ended_at) : 'Active');
                html += this.renderInfoItem('Duration', this.formatDuration(metrics.avg_response_time));
                html += this.renderInfoItem('Rating', this.renderRating(conversation.user_rating));
                html += this.renderInfoItem('Total Messages', conversation.total_messages);
                html += '</div>';
                html += '</div>';
                
                // Metrics section
                if (Object.keys(metrics).length > 0) {
                    html += '<div class="metrics-section">';
                    html += '<h3>Conversation Metrics</h3>';
                    html += '<div class="metrics-grid">';
                    html += this.renderMetricCard(metrics.user_messages || 0, 'User Messages');
                    html += this.renderMetricCard(metrics.assistant_messages || 0, 'AI Responses');
                    html += this.renderMetricCard(this.formatNumber(metrics.total_tokens || 0), 'Tokens Used');
                    html += this.renderMetricCard((metrics.avg_confidence * 100).toFixed(0) + '%', 'Avg Confidence');
                    html += '</div>';
                    html += '</div>';
                }
                
                // Messages section
                if (messages.length > 0) {
                    html += '<div class="messages-section">';
                    html += '<h3>Conversation Messages (' + messages.length + ')</h3>';
                    html += '<div class="messages-list">';
                    
                    messages.forEach(function(message) {
                        html += ConversationsManager.renderMessage(message);
                    });
                    
                    html += '</div>';
                    html += '</div>';
                }
                
                // KB Snippets section
                if (kbSnippets.length > 0) {
                    html += '<div class="kb-snippets-section">';
                    html += '<h3>Knowledge Base Snippets Used</h3>';
                    
                    kbSnippets.forEach(function(snippet) {
                        html += '<div class="kb-snippet-item">';
                        html += '<div class="kb-snippet-title">' + ConversationsManager.escapeHtml(snippet.title) + '</div>';
                        html += '<div class="kb-snippet-excerpt">' + ConversationsManager.escapeHtml(snippet.content_excerpt) + '</div>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                }
                
                $('#conversation-details-content').html(html);
            },

            /**
             * Render a single message
             */
            renderMessage: function(message) {
                const messageClass = message.type === 'user' ? 'user-message' : 'assistant-message';
                const senderName = message.type === 'user' ? 'User' : 'AI Assistant';
                
                let html = '<div class="message-item ' + messageClass + '">';
                html += '<div class="message-header">';
                html += '<span class="message-sender">' + senderName + '</span>';
                html += '<span class="message-time">' + this.formatDate(message.created_at) + '</span>';
                html += '</div>';
                html += '<div class="message-content">' + this.escapeHtml(message.content) + '</div>';
                
                // Add metadata if available
                if (message.confidence_score || message.tokens_used || message.model_used) {
                    html += '<div class="message-metadata">';
                    
                    if (message.confidence_score) {
                        html += '<span class="metadata-item">Confidence: ' + (message.confidence_score * 100).toFixed(0) + '%</span>';
                    }
                    
                    if (message.tokens_used) {
                        html += '<span class="metadata-item">Tokens: ' + message.tokens_used + '</span>';
                    }
                    
                    if (message.model_used) {
                        html += '<span class="metadata-item">Model: ' + message.model_used + '</span>';
                    }
                    
                    html += '</div>';
                }
                
                html += '</div>';
                
                return html;
            },

            /**
             * Render error in details modal
             */
            renderDetailsError: function(message) {
                const html = '<div class="error-content"><p>' + this.escapeHtml(message) + '</p></div>';
                $('#conversation-details-content').html(html);
            },

            /**
             * Handle export button click
             */
            handleExportClick: function(e) {
                e.preventDefault();
                $('#export-modal').addClass('active');
            },

            /**
             * Handle export form submission
             */
            handleExportSubmit: function(e) {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const exportData = {
                    action: 'woo_ai_export_conversations',
                    format: formData.get('format'),
                    limit: formData.get('limit'),
                    include_messages: formData.get('include_messages') ? 1 : 0,
                    include_kb_snippets: formData.get('include_kb_snippets') ? 1 : 0,
                    nonce: this.config.nonce
                };
                
                // Add current filters
                const currentFilters = this.getCurrentFilters();
                Object.assign(exportData, currentFilters);
                
                this.performExport(exportData);
            },

            /**
             * Perform the export via AJAX
             */
            performExport: function(exportData) {
                const self = this;
                
                // Create a form and submit it to trigger download
                const form = $('<form>', {
                    method: 'POST',
                    action: this.config.ajaxUrl
                });
                
                $.each(exportData, function(key, value) {
                    form.append($('<input>', {
                        type: 'hidden',
                        name: key,
                        value: value
                    }));
                });
                
                form.appendTo('body').submit().remove();
                
                this.closeModal();
                this.showNotification('success', this.config.strings.exportSuccess);
            },

            /**
             * Handle end conversation
             */
            handleEndConversation: function(e) {
                e.preventDefault();
                
                const conversationId = $(e.currentTarget).data('conversation-id');
                
                if (!confirm('Are you sure you want to end this conversation?')) {
                    return;
                }
                
                this.updateConversationStatus(conversationId, 'ended');
            },

            /**
             * Handle delete conversation
             */
            handleDeleteConversation: function(e) {
                e.preventDefault();
                
                const conversationId = $(e.currentTarget).data('conversation-id');
                
                if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
                    return;
                }
                
                this.deleteConversation(conversationId);
            },

            /**
             * Delete a conversation
             */
            deleteConversation: function(conversationId) {
                const self = this;
                
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'woo_ai_bulk_conversation_actions',
                        bulk_action: 'delete',
                        conversation_ids: [conversationId],
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showNotification('success', 'Conversation deleted successfully');
                            location.reload();
                        } else {
                            self.showNotification('error', response.data?.message || 'Failed to delete conversation');
                        }
                    },
                    error: function() {
                        self.showNotification('error', 'An error occurred while deleting the conversation');
                    }
                });
            },

            /**
             * Update conversation status
             */
            updateConversationStatus: function(conversationId, status) {
                const self = this;
                
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'woo_ai_bulk_conversation_actions',
                        bulk_action: 'mark_' + status,
                        conversation_ids: [conversationId],
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showNotification('success', 'Conversation status updated');
                            location.reload();
                        } else {
                            self.showNotification('error', response.data?.message || 'Failed to update status');
                        }
                    },
                    error: function() {
                        self.showNotification('error', 'An error occurred while updating the conversation');
                    }
                });
            },

            /**
             * Handle bulk actions
             */
            handleBulkAction: function(action) {
                const selectedIds = [];
                
                $('input[name="conversation[]"]:checked').each(function() {
                    selectedIds.push($(this).val());
                });
                
                if (selectedIds.length === 0) {
                    this.showNotification('warning', 'Please select at least one conversation');
                    return;
                }
                
                let confirmMessage = '';
                
                switch(action) {
                    case 'delete':
                        confirmMessage = 'Are you sure you want to delete ' + selectedIds.length + ' conversation(s)?';
                        break;
                    case 'mark_resolved':
                        confirmMessage = 'Mark ' + selectedIds.length + ' conversation(s) as resolved?';
                        break;
                    case 'export':
                        this.exportSelectedConversations(selectedIds);
                        return;
                }
                
                if (confirmMessage && !confirm(confirmMessage)) {
                    return;
                }
                
                this.performBulkAction(action, selectedIds);
            },

            /**
             * Perform bulk action via AJAX
             */
            performBulkAction: function(action, conversationIds) {
                const self = this;
                
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'woo_ai_bulk_conversation_actions',
                        bulk_action: action,
                        conversation_ids: conversationIds,
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showNotification('success', response.data?.message || 'Action completed successfully');
                            location.reload();
                        } else {
                            self.showNotification('error', response.data?.message || 'Action failed');
                        }
                    },
                    error: function() {
                        self.showNotification('error', 'An error occurred while performing the action');
                    }
                });
            },

            /**
             * Export selected conversations
             */
            exportSelectedConversations: function(conversationIds) {
                // Show export modal with selected conversations
                $('#export-modal').addClass('active');
                $('#export-modal').data('selected-conversations', conversationIds);
            },

            /**
             * Handle filter form submission
             */
            handleFilterSubmit: function(e) {
                // Form will submit normally for server-side filtering
                // We can add AJAX filtering here if needed
            },

            /**
             * Handle search input
             */
            handleSearchInput: function(e) {
                const searchTerm = $(e.target).val();
                
                if (searchTerm.length < 2) {
                    $('.search-suggestions').hide();
                    return;
                }
                
                // Could implement search suggestions here
            },

            /**
             * Handle select all checkbox
             */
            handleSelectAll: function(e) {
                const isChecked = $(e.target).prop('checked');
                $('input[name="conversation[]"]').prop('checked', isChecked);
                this.updateSelectedConversations();
            },

            /**
             * Update selected conversations state
             */
            updateSelectedConversations: function() {
                this.state.selectedConversations = [];
                
                $('input[name="conversation[]"]:checked').each((index, element) => {
                    this.state.selectedConversations.push($(element).val());
                });
                
                // Update bulk action buttons state
                const hasSelection = this.state.selectedConversations.length > 0;
                $('#doaction, #doaction2').prop('disabled', !hasSelection);
            },

            /**
             * Get current filters
             */
            getCurrentFilters: function() {
                const filters = {};
                const params = new URLSearchParams(window.location.search);
                
                ['search', 'status', 'rating', 'confidence', 'date_from', 'date_to', 'user_id'].forEach(function(param) {
                    if (params.has(param)) {
                        filters[param] = params.get(param);
                    }
                });
                
                return filters;
            },

            /**
             * Close all modals
             */
            closeModal: function() {
                $('.conversation-modal').removeClass('active');
            },

            /**
             * Show notification
             */
            showNotification: function(type, message) {
                // Remove any existing notifications
                $('.woo-ai-notification').remove();
                
                const notification = $('<div>', {
                    class: 'notice notice-' + type + ' is-dismissible woo-ai-notification',
                    html: '<p>' + this.escapeHtml(message) + '</p>'
                });
                
                $('.wp-header-end').after(notification);
                
                // Auto dismiss after 5 seconds
                setTimeout(function() {
                    notification.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
                
                // Make WordPress dismissible button work
                $(document).trigger('wp-updates-notice-added');
            },

            /**
             * Helper: Render info item
             */
            renderInfoItem: function(label, value) {
                return '<div class="info-item">' +
                       '<div class="info-label">' + label + '</div>' +
                       '<div class="info-value">' + value + '</div>' +
                       '</div>';
            },

            /**
             * Helper: Render metric card
             */
            renderMetricCard: function(value, label) {
                return '<div class="metric-card">' +
                       '<div class="metric-value">' + value + '</div>' +
                       '<div class="metric-label">' + label + '</div>' +
                       '</div>';
            },

            /**
             * Helper: Render status badge
             */
            renderStatusBadge: function(status) {
                return '<span class="status-badge status-' + status + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
            },

            /**
             * Helper: Render rating stars
             */
            renderRating: function(rating) {
                if (!rating) return 'Not rated';
                
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    stars += i <= rating ? '★' : '☆';
                }
                
                return '<span class="rating-stars">' + stars + '</span>';
            },

            /**
             * Helper: Format date
             */
            formatDate: function(dateString) {
                if (!dateString) return '';
                
                const date = new Date(dateString);
                const options = {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                
                return date.toLocaleDateString('en-US', options);
            },

            /**
             * Helper: Format duration
             */
            formatDuration: function(seconds) {
                if (!seconds || seconds === 0) return 'N/A';
                
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = Math.floor(seconds % 60);
                
                if (hours > 0) {
                    return hours + 'h ' + minutes + 'm';
                } else if (minutes > 0) {
                    return minutes + 'm ' + secs + 's';
                } else {
                    return secs + 's';
                }
            },

            /**
             * Helper: Format number
             */
            formatNumber: function(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            },

            /**
             * Helper: Escape HTML
             */
            escapeHtml: function(text) {
                if (!text) return '';
                
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            },

            /**
             * Helper: Debounce function
             */
            debounce: function(func, wait) {
                let timeout;
                
                return function executedFunction() {
                    const later = () => {
                        clearTimeout(timeout);
                        func.apply(this, arguments);
                    };
                    
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        };

        // Initialize the conversations manager
        ConversationsManager.init();
        
        // Make it globally accessible for debugging
        window.WooAiConversationsManager = ConversationsManager;
    });

})(jQuery);