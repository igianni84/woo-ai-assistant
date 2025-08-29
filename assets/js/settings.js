/**
 * Woo AI Assistant Settings Page JavaScript
 *
 * Handles all JavaScript functionality for the plugin settings page including
 * tab navigation, AJAX form submission, API testing, color picker initialization,
 * and dynamic UI interactions.
 *
 * @package WooAiAssistant
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Settings Manager Object
     *
     * Manages all settings page functionality
     */
    const WooAiSettingsManager = {
        
        /**
         * Initialize settings manager
         */
        init: function() {
            this.bindEvents();
            this.initColorPickers();
            this.initMediaUploader();
            this.loadKnowledgeBaseStats();
            this.initTooltips();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            // Tab navigation
            $('.nav-tab').on('click', this.handleTabClick);
            
            // Form submission
            $('#woo-ai-settings-form').on('submit', this.handleFormSubmit);
            
            // Reset button
            $('#reset-settings').on('click', this.handleReset);
            
            // API testing
            $('#test-connection').on('click', this.testApiConnection);
            $('#verify-license').on('click', this.verifyLicense);
            
            // Knowledge Base actions
            $('#index-now').on('click', this.triggerIndexing);
            
            // Toggle visibility buttons
            $('.toggle-visibility').on('click', this.togglePasswordVisibility);
            
            // Dependent field visibility
            this.setupDependentFields();
            
            // Live preview updates
            this.setupLivePreview();
        },

        /**
         * Handle tab click navigation
         *
         * @param {Event} e Click event
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const tabId = $tab.data('tab');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show corresponding panel
            $('.tab-panel').removeClass('active');
            $('#' + tabId).addClass('active');
            
            // Update URL hash
            window.location.hash = tabId;
        },

        /**
         * Handle form submission via AJAX
         *
         * @param {Event} e Submit event
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitButton = $form.find('.button-primary');
            const originalText = $submitButton.val();
            
            // Show loading state
            $submitButton.val(wooAiSettings.strings.saving || 'Saving...').prop('disabled', true);
            
            // Prepare form data
            const formData = new FormData($form[0]);
            formData.append('action', 'woo_ai_save_settings');
            formData.append('nonce', wooAiSettings.nonce);
            
            // Send AJAX request
            $.ajax({
                url: wooAiSettings.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        WooAiSettingsManager.showNotice('success', response.data.message || wooAiSettings.strings.save_success);
                    } else {
                        WooAiSettingsManager.showNotice('error', response.data.message || wooAiSettings.strings.save_error);
                    }
                },
                error: function() {
                    WooAiSettingsManager.showNotice('error', wooAiSettings.strings.save_error);
                },
                complete: function() {
                    $submitButton.val(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle settings reset
         *
         * @param {Event} e Click event
         */
        handleReset: function(e) {
            e.preventDefault();
            
            if (!confirm(wooAiSettings.strings.reset_confirm)) {
                return;
            }
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.text('Resetting...').prop('disabled', true);
            
            $.post(wooAiSettings.ajax_url, {
                action: 'woo_ai_reset_settings',
                nonce: wooAiSettings.nonce
            }, function(response) {
                if (response.success) {
                    WooAiSettingsManager.showNotice('success', response.data.message || wooAiSettings.strings.reset_success);
                    // Reload page to show default values
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    WooAiSettingsManager.showNotice('error', response.data.message || 'Reset failed');
                }
            }).always(function() {
                $button.text(originalText).prop('disabled', false);
            });
        },

        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            $('.color-picker').wpColorPicker({
                change: function(event, ui) {
                    // Trigger live preview update
                    $(event.target).trigger('colorchange', [ui.color.toString()]);
                }
            });
        },

        /**
         * Initialize media uploader for avatar
         */
        initMediaUploader: function() {
            $('#upload-avatar').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $input = $('#avatar_url');
                
                // Create media frame
                const frame = wp.media({
                    title: 'Select Avatar Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });
                
                // Handle selection
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.url).trigger('change');
                });
                
                // Open frame
                frame.open();
            });
        },

        /**
         * Test API connection
         *
         * @param {Event} e Click event
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $input = $('#intermediate_server_url');
            const apiUrl = $input.val();
            
            if (!apiUrl) {
                WooAiSettingsManager.showNotice('error', 'Please enter an API URL');
                return;
            }
            
            const originalText = $button.text();
            $button.text('Testing...').prop('disabled', true);
            
            $.post(wooAiSettings.ajax_url, {
                action: 'woo_ai_test_api_connection',
                nonce: wooAiSettings.nonce,
                api_url: apiUrl
            }, function(response) {
                if (response.success) {
                    WooAiSettingsManager.showNotice('success', response.data.message || wooAiSettings.strings.connection_success);
                } else {
                    WooAiSettingsManager.showNotice('error', response.data.message || wooAiSettings.strings.connection_failed);
                }
            }).always(function() {
                $button.text(originalText).prop('disabled', false);
            });
        },

        /**
         * Verify license key
         *
         * @param {Event} e Click event
         */
        verifyLicense: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $input = $('#license_key');
            const licenseKey = $input.val();
            const $status = $('#license-status');
            
            if (!licenseKey) {
                WooAiSettingsManager.showNotice('error', 'Please enter a license key');
                return;
            }
            
            const originalText = $button.text();
            $button.text('Verifying...').prop('disabled', true);
            $status.html('<span class="spinner is-active"></span>');
            
            $.post(wooAiSettings.ajax_url, {
                action: 'woo_ai_verify_license',
                nonce: wooAiSettings.nonce,
                license_key: licenseKey
            }, function(response) {
                if (response.success) {
                    $status.html('<span class="license-valid">' + (response.data.message || wooAiSettings.strings.license_valid) + '</span>');
                    
                    // Update plan information if provided
                    if (response.data.plan) {
                        $status.append('<div class="license-plan">Plan: ' + response.data.plan + '</div>');
                    }
                } else {
                    $status.html('<span class="license-invalid">' + (response.data.message || wooAiSettings.strings.license_invalid) + '</span>');
                }
            }).fail(function() {
                $status.html('<span class="license-error">Verification failed. Please try again.</span>');
            }).always(function() {
                $button.text(originalText).prop('disabled', false);
            });
        },

        /**
         * Trigger Knowledge Base indexing
         *
         * @param {Event} e Click event
         */
        triggerIndexing: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            if (!confirm('This will re-index all content. Continue?')) {
                return;
            }
            
            $button.text('Indexing...').prop('disabled', true);
            
            // Show progress indicator
            const $progress = $('<div class="indexing-progress"><div class="progress-bar"><div class="progress-fill"></div></div><div class="progress-text">Starting...</div></div>');
            $button.after($progress);
            
            $.post(wooAiSettings.ajax_url, {
                action: 'woo_ai_trigger_indexing',
                nonce: wooAiSettings.nonce
            }, function(response) {
                if (response.success) {
                    WooAiSettingsManager.showNotice('success', response.data.message || wooAiSettings.strings.index_complete);
                    
                    // Refresh KB stats
                    WooAiSettingsManager.loadKnowledgeBaseStats();
                } else {
                    WooAiSettingsManager.showNotice('error', response.data.message || 'Indexing failed');
                }
            }).always(function() {
                $button.text(originalText).prop('disabled', false);
                $progress.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Toggle password field visibility
         *
         * @param {Event} e Click event
         */
        togglePasswordVisibility: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const targetId = $button.data('target');
            const $input = $('#' + targetId);
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $button.text('Hide');
            } else {
                $input.attr('type', 'password');
                $button.text('Show');
            }
        },

        /**
         * Setup dependent fields visibility
         */
        setupDependentFields: function() {
            // Show/hide coupon fields based on auto-generation checkbox
            $('#allow_auto_generation').on('change', function() {
                const $couponFields = $(this).closest('table').find('tr').not(':first');
                if ($(this).is(':checked')) {
                    $couponFields.slideDown();
                } else {
                    $couponFields.slideUp();
                }
            }).trigger('change');
            
            // Show/hide trigger message fields based on enabled state
            $('.trigger-section input[type="checkbox"][id$="_enabled"]').on('change', function() {
                const $section = $(this).closest('.trigger-section');
                const $fields = $section.find('tr').not(':first');
                
                if ($(this).is(':checked')) {
                    $fields.slideDown();
                } else {
                    $fields.slideUp();
                }
            }).trigger('change');
            
            // Show/hide consent text based on require consent
            $('#require_consent').on('change', function() {
                const $consentRow = $('#consent_text').closest('tr');
                if ($(this).is(':checked')) {
                    $consentRow.slideDown();
                } else {
                    $consentRow.slideUp();
                }
            }).trigger('change');
        },

        /**
         * Setup live preview for appearance settings
         */
        setupLivePreview: function() {
            // Create preview widget if on appearance tab
            if (window.location.hash === '#appearance') {
                this.createPreviewWidget();
            }
            
            // Update preview on color changes
            $('#primary_color').on('colorchange', function(e, color) {
                $('.preview-widget-header').css('background-color', color);
            });
            
            $('#text_color').on('colorchange', function(e, color) {
                $('.preview-widget-body').css('color', color);
            });
            
            $('#background_color').on('colorchange', function(e, color) {
                $('.preview-widget-body').css('background-color', color);
            });
            
            // Update preview on text changes
            $('#header_text').on('input', function() {
                $('.preview-widget-header-text').text($(this).val());
            });
            
            $('#placeholder_text').on('input', function() {
                $('.preview-widget-input').attr('placeholder', $(this).val());
            });
            
            // Update widget size
            $('#widget_size').on('change', function() {
                const size = $(this).val();
                $('.preview-widget')
                    .removeClass('size-small size-medium size-large')
                    .addClass('size-' + size);
            });
        },

        /**
         * Create preview widget for appearance settings
         */
        createPreviewWidget: function() {
            const previewHtml = `
                <div class="preview-container">
                    <h3>Live Preview</h3>
                    <div class="preview-widget size-medium">
                        <div class="preview-widget-header">
                            <span class="preview-widget-header-text">Hi! How can I help you today?</span>
                            <button class="preview-widget-close">×</button>
                        </div>
                        <div class="preview-widget-body">
                            <div class="preview-widget-messages">
                                <div class="message bot">Hello! I'm your AI shopping assistant.</div>
                                <div class="message user">I need help finding a product</div>
                                <div class="message bot typing">
                                    <span></span><span></span><span></span>
                                </div>
                            </div>
                            <div class="preview-widget-footer">
                                <input type="text" class="preview-widget-input" placeholder="Type your message...">
                                <button class="preview-widget-send">Send</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#appearance .form-table').after(previewHtml);
        },

        /**
         * Load Knowledge Base statistics
         */
        loadKnowledgeBaseStats: function() {
            const $statsContainer = $('#kb-stats');
            
            if ($statsContainer.length === 0) {
                return;
            }
            
            $.post(wooAiSettings.ajax_url, {
                action: 'woo_ai_get_kb_stats',
                nonce: wooAiSettings.nonce
            }, function(response) {
                if (response.success && response.data) {
                    $('#kb-total-docs').text(response.data.total_documents || '0');
                    $('#kb-total-chunks').text(response.data.total_chunks || '0');
                    $('#kb-last-index').text(response.data.last_index || 'Never');
                    
                    // Format health score with color
                    const healthScore = response.data.health_score || 0;
                    let scoreClass = 'score-low';
                    if (healthScore >= 80) {
                        scoreClass = 'score-high';
                    } else if (healthScore >= 60) {
                        scoreClass = 'score-medium';
                    }
                    
                    $('#kb-health-score')
                        .text(healthScore + '%')
                        .removeClass('score-low score-medium score-high')
                        .addClass(scoreClass);
                }
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to help icons
            $('.help-tip').tooltip({
                position: {
                    my: 'center bottom',
                    at: 'center top-10'
                },
                tooltipClass: 'woo-ai-tooltip'
            });
        },

        /**
         * Show admin notice
         *
         * @param {string} type Notice type (success, error, warning, info)
         * @param {string} message Notice message
         */
        showNotice: function(type, message) {
            // Remove existing notices
            $('.woo-ai-notice').remove();
            
            // Create notice HTML
            const noticeHtml = `
                <div class="notice notice-${type} woo-ai-notice is-dismissible">
                    <p>${message}</p>
                </div>
            `;
            
            // Insert after page title
            $('.wrap h1').after(noticeHtml);
            
            // Initialize dismissible notices
            $('.woo-ai-notice').on('click', '.notice-dismiss', function() {
                $(this).parent().fadeOut(function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    $('.woo-ai-notice').fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Initialize settings manager
        WooAiSettingsManager.init();
        
        // Handle initial hash
        if (window.location.hash) {
            const hash = window.location.hash.substring(1);
            $('.nav-tab[data-tab="' + hash + '"]').trigger('click');
        }
    });

})(jQuery);