/**
 * Knowledge Base Status Page JavaScript
 * 
 * Handles AJAX interactions for the Knowledge Base Status page
 * including indexing operations, status refresh, and clear operations.
 * 
 * @package WooAiAssistant
 * @subpackage Assets
 * @since 1.0.0
 * @author Claude Code Assistant
 */

(function($) {
    'use strict';

    /**
     * Knowledge Base Status functionality
     */
    const WooAiKbStatus = {
        
        /**
         * Initialize the functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeStatus();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Start indexing button
            $('#start-indexing').on('click', this.handleStartIndexing.bind(this));
            
            // Refresh status button
            $('#refresh-status').on('click', this.handleRefreshStatus.bind(this));
            
            // Clear index button
            $('#clear-index').on('click', this.handleClearIndex.bind(this));
        },

        /**
         * Initialize status on page load
         */
        initializeStatus: function() {
            // Only check status if explicitly requested, no auto-refresh
            // User can manually click refresh if needed
        },

        /**
         * Handle start indexing button click
         */
        handleStartIndexing: function(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const originalText = button.html();
            
            // Disable button and show loading state
            button.prop('disabled', true)
                  .html('<span class="dashicons dashicons-update spin"></span> ' + wooAiKbStatus.strings.indexingInProgress);
            
            // Show progress container
            $('#indexing-progress').show();
            this.updateProgress(0, 'Starting indexing process...');
            
            // Clear any existing messages
            $('#operation-messages').empty();
            
            // Make AJAX request
            $.ajax({
                url: wooAiKbStatus.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_kb_start_indexing',
                    nonce: wooAiKbStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        this.startProgressMonitoring();
                    } else {
                        this.showMessage(response.data || 'Indexing failed to start', 'error');
                        this.resetButton(button, originalText);
                        $('#indexing-progress').hide();
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showMessage('AJAX error: ' + error, 'error');
                    this.resetButton(button, originalText);
                    $('#indexing-progress').hide();
                }.bind(this)
            });
        },

        /**
         * Handle refresh status button click
         */
        handleRefreshStatus: function(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const originalText = button.html();
            
            // Disable button and show loading state
            button.prop('disabled', true)
                  .html('<span class="dashicons dashicons-update spin"></span> ' + wooAiKbStatus.strings.refreshing);
            
            // Make AJAX request
            $.ajax({
                url: wooAiKbStatus.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_kb_refresh_status',
                    nonce: wooAiKbStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateStatusDisplay(response.data);
                        this.showMessage('Status refreshed successfully', 'success');
                    } else {
                        this.showMessage(response.data || 'Failed to refresh status', 'error');
                    }
                    this.resetButton(button, originalText);
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showMessage('AJAX error: ' + error, 'error');
                    this.resetButton(button, originalText);
                }.bind(this)
            });
        },

        /**
         * Handle clear index button click
         */
        handleClearIndex: function(e) {
            e.preventDefault();
            
            // Confirm action
            if (!confirm(wooAiKbStatus.strings.confirmClear)) {
                return;
            }
            
            const button = $(e.target);
            const originalText = button.html();
            
            // Disable button and show loading state
            button.prop('disabled', true)
                  .html('<span class="dashicons dashicons-update spin"></span> Clearing...');
            
            // Make AJAX request
            $.ajax({
                url: wooAiKbStatus.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_kb_clear_index',
                    nonce: wooAiKbStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        // Refresh the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showMessage(response.data || 'Failed to clear index', 'error');
                        this.resetButton(button, originalText);
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showMessage('AJAX error: ' + error, 'error');
                    this.resetButton(button, originalText);
                }.bind(this)
            });
        },

        /**
         * Start progress monitoring for indexing
         */
        startProgressMonitoring: function() {
            // Check indexing status periodically
            this.progressInterval = setInterval(function() {
                this.checkIndexingCompletion();
            }.bind(this), 3000); // Check every 3 seconds
        },

        /**
         * Check if indexing is complete
         */
        checkIndexingCompletion: function() {
            // Poll the server to check real indexing status
            $.ajax({
                url: wooAiKbStatus.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_kb_check_indexing_status',
                    nonce: wooAiKbStatus.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        if (response.data.status === 'completed') {
                            this.completeIndexing();
                        } else if (response.data.status === 'failed') {
                            this.handleIndexingError(response.data.message || 'Indexing failed');
                        } else if (response.data.progress) {
                            this.updateProgress(response.data.progress, response.data.message || 'Indexing...');
                        }
                    }
                }.bind(this),
                error: function() {
                    // Continue checking even if there's an error
                }.bind(this)
            });
        },

        /**
         * Complete indexing process
         */
        completeIndexing: function() {
            // Clear progress interval
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }
            
            // Update progress to 100%
            this.updateProgress(100, 'Indexing completed!');
            
            // Hide progress after 3 seconds
            setTimeout(function() {
                $('#indexing-progress').hide();
                this.resetButton($('#start-indexing'), '<span class="dashicons dashicons-update"></span> Start Full Index');
                
                // Refresh status display without triggering click
                this.refreshStatusDisplay();
            }.bind(this), 3000);
        },

        /**
         * Check indexing status periodically
         */
        checkIndexingStatus: function() {
            // This could check if indexing is currently running
            // and update the UI accordingly
        },

        /**
         * Handle indexing error
         */
        handleIndexingError: function(message) {
            // Clear progress interval
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }
            
            // Show error message
            this.showMessage(message, 'error');
            
            // Hide progress and reset button
            $('#indexing-progress').hide();
            this.resetButton($('#start-indexing'), '<span class="dashicons dashicons-update"></span> Start Full Index');
        },

        /**
         * Refresh status display without triggering events
         */
        refreshStatusDisplay: function() {
            $.ajax({
                url: wooAiKbStatus.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_kb_refresh_status',
                    nonce: wooAiKbStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateStatusDisplay(response.data);
                    }
                }.bind(this),
                error: function() {
                    // Silent failure for background refresh
                }.bind(this)
            });
        },

        /**
         * Update progress display
         */
        updateProgress: function(percent, message) {
            $('#indexing-progress .progress-fill').css('width', percent + '%');
            $('#indexing-progress .progress-text').text(message + ' (' + Math.round(percent) + '%)');
        },

        /**
         * Update status display with new data
         */
        updateStatusDisplay: function(data) {
            // Update status cards
            if (data.health_score !== undefined) {
                $('.status-card.health-score .card-value').text(data.health_score + '%');
            }
            
            if (data.total_documents !== undefined) {
                $('.status-card .card-value').first().next().next().text(this.formatNumber(data.total_documents));
            }
            
            // Update coverage bars
            const contentTypes = ['products', 'pages', 'posts'];
            contentTypes.forEach(function(type) {
                if (data[type + '_total'] !== undefined && data[type + '_indexed'] !== undefined) {
                    const total = data[type + '_total'];
                    const indexed = data[type + '_indexed'];
                    const coverage = total > 0 ? Math.round((indexed / total) * 100) : 0;
                    
                    const row = $('td:contains("' + type.charAt(0).toUpperCase() + type.slice(1) + '")').closest('tr');
                    if (row.length) {
                        row.find('td').eq(1).text(this.formatNumber(total));
                        row.find('td').eq(2).text(this.formatNumber(indexed));
                        row.find('.coverage-fill').css('width', coverage + '%');
                        row.find('.coverage-text').text(coverage + '%');
                    }
                }
            }.bind(this));
        },

        /**
         * Show operation message
         */
        showMessage: function(message, type) {
            const alertClass = type === 'success' ? 'notice-success' : 'notice-error';
            const messageHtml = '<div class="notice ' + alertClass + ' is-dismissible"><p>' + message + '</p></div>';
            
            $('#operation-messages').html(messageHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('#operation-messages .notice').fadeOut();
            }, 5000);
        },

        /**
         * Reset button to original state
         */
        resetButton: function(button, originalText) {
            button.prop('disabled', false).html(originalText);
        },

        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * Add spinning animation to dashicons
         */
        addSpinAnimation: function() {
            if (!$('#kb-status-spin-css').length) {
                $('<style id="kb-status-spin-css">')
                    .prop('type', 'text/css')
                    .html('@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } .spin { animation: spin 1s linear infinite; }')
                    .appendTo('head');
            }
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        WooAiKbStatus.init();
        WooAiKbStatus.addSpinAnimation();
    });

})(jQuery);