/**
 * Woo AI Assistant - Admin JavaScript
 *
 * JavaScript for WordPress admin interface including dashboard interactions,
 * settings management, AJAX requests, and table management.
 *
 * @package WooAiAssistant
 * @subpackage Assets
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    // Global admin object
    window.WooAIAdmin = {
        init: function() {
            this.bindEvents();
            this.initializeTabs();
            this.initializeCharts();
            this.initializeDataTables();
            this.setupAjaxDefaults();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $(document).on('click', '.woo-ai-assistant-nav-tab', this.handleTabClick);
            $(document).on('submit', '.woo-ai-assistant-form', this.handleFormSubmit);
            $(document).on('click', '.woo-ai-reindex-btn', this.handleReindex);
            $(document).on('click', '.woo-ai-test-connection-btn', this.handleTestConnection);
            $(document).on('click', '.woo-ai-export-btn', this.handleExport);
            $(document).on('change', '.woo-ai-bulk-action-select', this.handleBulkActionChange);
            $(document).on('click', '.woo-ai-clear-logs-btn', this.handleClearLogs);
        },

        /**
         * Initialize tab navigation
         */
        initializeTabs: function() {
            const hash = window.location.hash;
            if (hash) {
                const tab = $(`.woo-ai-assistant-nav-tab[href="${hash}"]`);
                if (tab.length) {
                    this.activateTab(hash.substring(1));
                }
            }
        },

        /**
         * Handle tab clicks
         */
        handleTabClick: function(e) {
            e.preventDefault();
            const tabId = $(this).attr('href').substring(1);
            WooAIAdmin.activateTab(tabId);
            window.history.pushState(null, null, '#' + tabId);
        },

        /**
         * Activate a specific tab
         */
        activateTab: function(tabId) {
            $('.woo-ai-assistant-nav-tab').removeClass('nav-tab-active');
            $(`.woo-ai-assistant-nav-tab[href="#${tabId}"]`).addClass('nav-tab-active');
            
            $('.woo-ai-tab-content').hide();
            $(`#${tabId}`).show();
        },

        /**
         * Handle form submissions
         */
        handleFormSubmit: function(e) {
            const form = $(this);
            const submitBtn = form.find('input[type="submit"]');
            const originalText = submitBtn.val();

            // Show loading state
            submitBtn.val(wooAiAdmin.i18n.saving + '...');
            submitBtn.prop('disabled', true);

            // Add spinner
            if (!form.find('.spinner').length) {
                form.append('<span class="spinner is-active" style="float: none; margin: 0 10px;"></span>');
            }

            // If this is an AJAX form, handle it
            if (form.hasClass('ajax-form')) {
                e.preventDefault();
                WooAIAdmin.submitAjaxForm(form, submitBtn, originalText);
            }
        },

        /**
         * Submit form via AJAX
         */
        submitAjaxForm: function(form, submitBtn, originalText) {
            const formData = new FormData(form[0]);
            formData.append('action', 'woo_ai_save_settings');
            formData.append('nonce', wooAiAdmin.nonce);

            $.ajax({
                url: wooAiAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        WooAIAdmin.showNotice(response.data.message, 'success');
                    } else {
                        WooAIAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WooAIAdmin.showNotice(wooAiAdmin.i18n.error, 'error');
                },
                complete: function() {
                    submitBtn.val(originalText);
                    submitBtn.prop('disabled', false);
                    form.find('.spinner').remove();
                }
            });
        },

        /**
         * Handle knowledge base reindexing
         */
        handleReindex: function(e) {
            e.preventDefault();
            
            if (!confirm(wooAiAdmin.i18n.confirmReindex)) {
                return;
            }

            const btn = $(this);
            const originalText = btn.text();
            btn.text(wooAiAdmin.i18n.reindexing + '...');
            btn.prop('disabled', true);

            $.ajax({
                url: wooAiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_reindex_kb',
                    nonce: wooAiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WooAIAdmin.showNotice(response.data.message, 'success');
                        // Refresh the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        WooAIAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WooAIAdmin.showNotice(wooAiAdmin.i18n.error, 'error');
                },
                complete: function() {
                    btn.text(originalText);
                    btn.prop('disabled', false);
                }
            });
        },

        /**
         * Handle connection testing
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const originalText = btn.text();
            btn.text(wooAiAdmin.i18n.testing + '...');
            btn.prop('disabled', true);

            $.ajax({
                url: wooAiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_test_connection',
                    nonce: wooAiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WooAIAdmin.showNotice(response.data.message, 'success');
                    } else {
                        WooAIAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WooAIAdmin.showNotice(wooAiAdmin.i18n.connectionError, 'error');
                },
                complete: function() {
                    btn.text(originalText);
                    btn.prop('disabled', false);
                }
            });
        },

        /**
         * Handle data export
         */
        handleExport: function(e) {
            e.preventDefault();
            
            const exportType = $(this).data('export-type');
            const btn = $(this);
            const originalText = btn.text();
            
            btn.text(wooAiAdmin.i18n.exporting + '...');
            btn.prop('disabled', true);

            // Create a temporary form for file download
            const form = $('<form></form>');
            form.attr('method', 'POST');
            form.attr('action', wooAiAdmin.ajaxUrl);
            form.append($('<input type="hidden" name="action" value="woo_ai_export_data">'));
            form.append($('<input type="hidden" name="export_type" value="' + exportType + '">'));
            form.append($('<input type="hidden" name="nonce" value="' + wooAiAdmin.nonce + '">'));
            
            $('body').append(form);
            form.submit();
            form.remove();

            // Reset button after a delay
            setTimeout(function() {
                btn.text(originalText);
                btn.prop('disabled', false);
            }, 2000);
        },

        /**
         * Handle bulk action changes
         */
        handleBulkActionChange: function() {
            const action = $(this).val();
            const selectedItems = $('.item-checkbox:checked').length;
            
            if (action && selectedItems > 0) {
                const confirmMessage = wooAiAdmin.i18n.bulkActionConfirm
                    .replace('%d', selectedItems)
                    .replace('%s', action);
                
                if (confirm(confirmMessage)) {
                    WooAIAdmin.performBulkAction(action);
                }
            }
            
            $(this).val('');
        },

        /**
         * Perform bulk action
         */
        performBulkAction: function(action) {
            const selectedIds = [];
            $('.item-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });

            $.ajax({
                url: wooAiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_bulk_action',
                    bulk_action: action,
                    selected_ids: selectedIds,
                    nonce: wooAiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WooAIAdmin.showNotice(response.data.message, 'success');
                        window.location.reload();
                    } else {
                        WooAIAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WooAIAdmin.showNotice(wooAiAdmin.i18n.error, 'error');
                }
            });
        },

        /**
         * Handle log clearing
         */
        handleClearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm(wooAiAdmin.i18n.confirmClearLogs)) {
                return;
            }

            const btn = $(this);
            const originalText = btn.text();
            btn.text(wooAiAdmin.i18n.clearing + '...');
            btn.prop('disabled', true);

            $.ajax({
                url: wooAiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_clear_logs',
                    nonce: wooAiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WooAIAdmin.showNotice(response.data.message, 'success');
                        $('.log-entries').empty();
                    } else {
                        WooAIAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WooAIAdmin.showNotice(wooAiAdmin.i18n.error, 'error');
                },
                complete: function() {
                    btn.text(originalText);
                    btn.prop('disabled', false);
                }
            });
        },

        /**
         * Initialize data tables
         */
        initializeDataTables: function() {
            if ($.fn.DataTable && $('.woo-ai-data-table').length) {
                $('.woo-ai-data-table').DataTable({
                    pageLength: 25,
                    responsive: true,
                    order: [[0, 'desc']],
                    language: {
                        search: wooAiAdmin.i18n.search + ':',
                        lengthMenu: wooAiAdmin.i18n.show + ' _MENU_ ' + wooAiAdmin.i18n.entries,
                        info: wooAiAdmin.i18n.showing + ' _START_ ' + wooAiAdmin.i18n.to + ' _END_ ' + wooAiAdmin.i18n.of + ' _TOTAL_ ' + wooAiAdmin.i18n.entries,
                        paginate: {
                            first: wooAiAdmin.i18n.first,
                            last: wooAiAdmin.i18n.last,
                            next: wooAiAdmin.i18n.next,
                            previous: wooAiAdmin.i18n.previous
                        }
                    }
                });
            }
        },

        /**
         * Initialize charts
         */
        initializeCharts: function() {
            if (typeof Chart !== 'undefined') {
                this.initConversationChart();
                this.initRatingChart();
                this.initUsageChart();
            }
        },

        /**
         * Initialize conversation analytics chart
         */
        initConversationChart: function() {
            const canvas = document.getElementById('conversationChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: wooAiAdmin.chartData.conversations.labels,
                    datasets: [{
                        label: wooAiAdmin.i18n.conversations,
                        data: wooAiAdmin.chartData.conversations.data,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Initialize rating chart
         */
        initRatingChart: function() {
            const canvas = document.getElementById('ratingChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
                    datasets: [{
                        data: wooAiAdmin.chartData.ratings.data,
                        backgroundColor: [
                            '#00a32a',
                            '#dba617',
                            '#0073aa',
                            '#ff8c00',
                            '#d63638'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    legend: {
                        position: 'bottom'
                    }
                }
            });
        },

        /**
         * Initialize usage chart
         */
        initUsageChart: function() {
            const canvas = document.getElementById('usageChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: wooAiAdmin.chartData.usage.labels,
                    datasets: [{
                        label: wooAiAdmin.i18n.usage,
                        data: wooAiAdmin.chartData.usage.data,
                        backgroundColor: '#0073aa',
                        borderColor: '#005a87',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Setup AJAX defaults
         */
        setupAjaxDefaults: function() {
            // Add loading indicator for AJAX requests
            $(document).ajaxStart(function() {
                $('.ajax-loading').show();
            }).ajaxStop(function() {
                $('.ajax-loading').hide();
            });

            // Handle AJAX errors globally
            $(document).ajaxError(function(event, xhr, settings, error) {
                if (xhr.status === 403) {
                    WooAIAdmin.showNotice(wooAiAdmin.i18n.permissionError, 'error');
                } else if (xhr.status === 500) {
                    WooAIAdmin.showNotice(wooAiAdmin.i18n.serverError, 'error');
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut();
            }, 5000);
        },

        /**
         * Update dashboard stats in real-time
         */
        updateDashboardStats: function() {
            $.ajax({
                url: wooAiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_get_dashboard_stats',
                    nonce: wooAiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const stats = response.data;
                        $('.total-conversations .stat-value').text(stats.totalConversations);
                        $('.resolution-rate .stat-value').text(stats.resolutionRate + '%');
                        $('.average-rating .stat-value').text(stats.averageRating);
                        $('.kb-items .stat-value').text(stats.kbItems);
                    }
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WooAIAdmin.init();
        
        // Update dashboard stats every 30 seconds
        if ($('.woo-ai-assistant-dashboard').length) {
            setInterval(WooAIAdmin.updateDashboardStats, 30000);
        }
    });

})(jQuery);