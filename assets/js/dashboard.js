/**
 * Dashboard JavaScript
 * Woo AI Assistant Plugin Dashboard Interface Scripts
 * 
 * @package WooAiAssistant
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Dashboard Management Class
     */
    class WooAiDashboard {
        constructor() {
            this.charts = {};
            this.isRefreshing = false;
            this.init();
        }

        /**
         * Initialize dashboard functionality
         */
        init() {
            this.bindEvents();
            this.initializeCharts();
            this.setupAutoRefresh();
            this.setupHealthScoreAnimation();
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Period selector change
            $('#dashboard-period').on('change', (e) => {
                this.onPeriodChange($(e.target).val());
            });

            // Refresh button
            $('#refresh-kpis').on('click', () => {
                this.refreshKpiData();
            });

            // Export button
            $('#export-analytics').on('click', () => {
                this.exportAnalytics();
            });

            // KPI card hover effects
            $('.kpi-card').on('mouseenter', (e) => {
                this.animateKpiCard($(e.currentTarget), 'enter');
            }).on('mouseleave', (e) => {
                this.animateKpiCard($(e.currentTarget), 'leave');
            });
        }

        /**
         * Handle period selector change
         */
        onPeriodChange(period) {
            if (this.isRefreshing) return;

            // Update URL with new period
            const url = new URL(window.location);
            url.searchParams.set('period', period);
            window.history.replaceState({}, '', url);

            // Refresh data
            this.refreshKpiData(period);
        }

        /**
         * Refresh KPI data via AJAX
         */
        refreshKpiData(period = null) {
            if (this.isRefreshing) return;

            this.isRefreshing = true;
            this.showLoadingState();

            const currentPeriod = period || $('#dashboard-period').val();

            $.ajax({
                url: wooAiDashboard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_refresh_kpis',
                    nonce: wooAiDashboard.nonce,
                    period: currentPeriod
                },
                success: (response) => {
                    if (response.success) {
                        this.updateDashboardData(response.data.data);
                        this.showSuccessMessage(response.data.message);
                    } else {
                        this.showErrorMessage(response.data || wooAiDashboard.strings.error);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Dashboard refresh failed:', error);
                    this.showErrorMessage(wooAiDashboard.strings.error);
                },
                complete: () => {
                    this.isRefreshing = false;
                    this.hideLoadingState();
                }
            });
        }

        /**
         * Export analytics data
         */
        exportAnalytics() {
            const period = $('#dashboard-period').val();
            const exportFormat = 'json'; // Could be made configurable

            // Create form for download
            const form = $('<form>', {
                method: 'POST',
                action: wooAiDashboard.ajaxUrl,
                style: 'display: none;'
            });

            form.append($('<input>', { name: 'action', value: 'woo_ai_export_analytics' }));
            form.append($('<input>', { name: 'nonce', value: wooAiDashboard.nonce }));
            form.append($('<input>', { name: 'period', value: period }));
            form.append($('<input>', { name: 'format', value: exportFormat }));

            $('body').append(form);
            form.submit();
            form.remove();

            this.showSuccessMessage(wooAiDashboard.strings.export + ' ' + wooAiDashboard.strings.loading);
        }

        /**
         * Initialize Chart.js charts
         */
        initializeCharts() {
            if (typeof Chart === 'undefined' || !window.wooAiChartData) {
                console.warn('Chart.js or chart data not available');
                return;
            }

            this.initResolutionRateChart();
            this.initConversationsChart();
        }

        /**
         * Initialize resolution rate trend chart
         */
        initResolutionRateChart() {
            const canvas = document.getElementById('resolution-rate-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const data = window.wooAiChartData;

            // Generate sample resolution rate data (in real implementation, this would come from server)
            const resolutionData = data.labels.map(() => 
                Math.floor(Math.random() * 20) + 75 // Random values between 75-95%
            );

            this.charts.resolutionRate = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Resolution Rate (%)',
                        data: resolutionData,
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#2ecc71',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#2ecc71',
                            borderWidth: 1,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return `Resolution Rate: ${context.parsed.y}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 60,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize conversations over time chart
         */
        initConversationsChart() {
            const canvas = document.getElementById('conversations-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const data = window.wooAiChartData;

            // Use actual conversation data if available
            const conversationData = data.conversations || [];
            const chartData = data.labels.map((label, index) => {
                const dayData = conversationData.find(item => {
                    const itemDate = new Date(item.date);
                    const labelDate = new Date();
                    labelDate.setDate(labelDate.getDate() - (data.labels.length - 1 - index));
                    return itemDate.toDateString() === labelDate.toDateString();
                });
                return dayData ? parseInt(dayData.count) : 0;
            });

            this.charts.conversations = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Conversations',
                        data: chartData,
                        backgroundColor: 'rgba(102, 126, 234, 0.6)',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        borderRadius: 4,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#667eea',
                            borderWidth: 1,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed.y;
                                    const label = value === 1 ? 'conversation' : 'conversations';
                                    return `${value} ${label}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    if (Number.isInteger(value)) {
                                        return value;
                                    }
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        /**
         * Update dashboard data after refresh
         */
        updateDashboardData(newData) {
            // Update KPI cards
            this.updateKpiCards(newData);
            
            // Update charts
            this.updateCharts(newData);
            
            // Update analytics sections
            this.updateAnalytics(newData);
        }

        /**
         * Update KPI card values
         */
        updateKpiCards(data) {
            // Resolution Rate
            const resolutionCard = $('#resolution-rate');
            if (resolutionCard.length) {
                resolutionCard.find('.kpi-value').text(data.resolution_rate.percentage + '%');
                resolutionCard.find('.kpi-description').text(
                    `${data.resolution_rate.resolved_count} of ${data.resolution_rate.total_count} conversations resolved`
                );
                this.updateTrend(resolutionCard, data.resolution_rate.trend);
            }

            // Conversion Rate
            const conversionCard = $('#conversion-rate');
            if (conversionCard.length) {
                conversionCard.find('.kpi-value').text(data.assist_conversion_rate.percentage + '%');
                conversionCard.find('.kpi-description').text(
                    `${data.assist_conversion_rate.conversions} conversions from ${data.assist_conversion_rate.total_assists} assists`
                );
            }

            // Total Conversations
            const conversationsCard = $('#total-conversations');
            if (conversationsCard.length) {
                conversationsCard.find('.kpi-value').text(this.formatNumber(data.total_conversations.total));
                this.updateTrend(conversationsCard, data.total_conversations.growth_rate);
            }

            // Average Rating
            const ratingCard = $('#average-rating');
            if (ratingCard.length) {
                ratingCard.find('.kpi-value').text(data.average_rating.average + '/5');
                ratingCard.find('.kpi-description').text(
                    `${data.average_rating.satisfaction_level} (${data.average_rating.total_ratings} ratings)`
                );
            }

            // KB Health Score
            const kbCard = $('#kb-health');
            if (kbCard.length) {
                kbCard.find('.kpi-value').text(data.kb_health_score.score + '%');
                kbCard.find('.kpi-description').text(data.kb_health_score.status);
            }
        }

        /**
         * Update trend indicator for KPI card
         */
        updateTrend(card, trendValue) {
            const trendElement = card.find('.kpi-trend');
            
            if (trendValue === 0 || trendValue === null) {
                trendElement.hide();
                return;
            }

            const isUp = trendValue > 0;
            const trendClass = isUp ? 'trend-up' : 'trend-down';
            const trendIcon = isUp ? '↗' : '↘';

            trendElement
                .removeClass('trend-up trend-down')
                .addClass(trendClass)
                .html(`<span>${trendIcon} ${Math.abs(trendValue)}%</span>`)
                .show();
        }

        /**
         * Update chart data
         */
        updateCharts(data) {
            // This would update chart data with new information
            // For now, we'll just trigger a resize to ensure proper display
            Object.keys(this.charts).forEach(chartKey => {
                if (this.charts[chartKey]) {
                    this.charts[chartKey].resize();
                }
            });
        }

        /**
         * Update analytics sections
         */
        updateAnalytics(data) {
            // Update FAQ analysis
            this.updateFaqAnalysis(data.faq_analysis);
            
            // Update KB health recommendations
            this.updateKbHealthRecommendations(data.kb_health_score);
        }

        /**
         * Update FAQ analysis section
         */
        updateFaqAnalysis(faqData) {
            const faqContainer = $('.faq-list');
            if (!faqContainer.length || !faqData.top_questions.length) return;

            faqContainer.empty();
            faqData.top_questions.forEach((faq, index) => {
                const faqItem = $(`
                    <div class="faq-item">
                        <div class="faq-rank">${index + 1}</div>
                        <div class="faq-content">
                            <p class="faq-question">${this.escapeHtml(faq.question)}</p>
                            <p class="faq-meta">
                                <span class="faq-frequency">Asked ${faq.frequency} times</span>
                                | <span class="faq-category">${this.capitalize(faq.category)}</span>
                            </p>
                        </div>
                    </div>
                `);
                faqContainer.append(faqItem);
            });
        }

        /**
         * Update KB health recommendations
         */
        updateKbHealthRecommendations(kbData) {
            const scoreElement = $('.kb-score-circle .score');
            const recommendationsContainer = $('.kb-recommendations ul');
            
            if (scoreElement.length) {
                scoreElement.text(kbData.score + '%');
                // Update CSS custom property for conic gradient
                $('.kb-score-circle').css('--score', kbData.score);
            }

            if (recommendationsContainer.length && kbData.recommendations) {
                recommendationsContainer.empty();
                kbData.recommendations.forEach(recommendation => {
                    recommendationsContainer.append(`<li>${this.escapeHtml(recommendation)}</li>`);
                });
            }
        }

        /**
         * Setup auto-refresh functionality
         */
        setupAutoRefresh() {
            // Auto-refresh every 5 minutes
            setInterval(() => {
                if (!this.isRefreshing) {
                    this.refreshKpiData();
                }
            }, 300000); // 5 minutes
        }

        /**
         * Setup health score circle animation
         */
        setupHealthScoreAnimation() {
            const scoreCircles = $('.kb-score-circle');
            
            scoreCircles.each(function() {
                const circle = $(this);
                const score = parseInt(circle.find('.score').text());
                
                // Animate the conic gradient
                let currentScore = 0;
                const increment = score / 50; // Animate over ~1 second (50 * 20ms)
                
                const animate = () => {
                    if (currentScore < score) {
                        currentScore += increment;
                        circle.css('--score', Math.min(currentScore, score));
                        setTimeout(animate, 20);
                    }
                };
                
                // Start animation when element comes into view
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            setTimeout(animate, 500); // Small delay for visual effect
                            observer.unobserve(entry.target);
                        }
                    });
                });
                
                observer.observe(circle[0]);
            });
        }

        /**
         * Animate KPI card interactions
         */
        animateKpiCard(card, action) {
            if (action === 'enter') {
                card.css('transform', 'translateY(-4px) scale(1.02)');
            } else {
                card.css('transform', 'translateY(0) scale(1)');
            }
        }

        /**
         * Show loading state
         */
        showLoadingState() {
            $('.kpi-card').addClass('kpi-loading');
            $('#refresh-kpis').prop('disabled', true).find('.dashicons').addClass('spin');
        }

        /**
         * Hide loading state
         */
        hideLoadingState() {
            $('.kpi-card').removeClass('kpi-loading');
            $('#refresh-kpis').prop('disabled', false).find('.dashicons').removeClass('spin');
        }

        /**
         * Show success message
         */
        showSuccessMessage(message) {
            this.showNotice(message, 'success');
        }

        /**
         * Show error message
         */
        showErrorMessage(message) {
            this.showNotice(message, 'error');
        }

        /**
         * Show notification
         */
        showNotice(message, type = 'info') {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible woo-ai-dashboard-notice">
                    <p>${this.escapeHtml(message)}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            // Remove existing notices
            $('.woo-ai-dashboard-notice').remove();

            // Add new notice
            $('.woo-ai-dashboard-header').after(notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(300, () => notice.remove());
            }, 5000);

            // Handle manual dismiss
            notice.on('click', '.notice-dismiss', () => {
                notice.fadeOut(300, () => notice.remove());
            });
        }

        /**
         * Format number with thousand separators
         */
        formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }

        /**
         * Capitalize first letter
         */
        capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Add CSS animation for spinning refresh icon
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .dashicons.spin {
                animation: spin 1s linear infinite;
            }
        `)
        .appendTo('head');

    // Initialize dashboard when DOM is ready
    $(document).ready(function() {
        if ($('.woo-ai-dashboard-content').length) {
            window.wooAiDashboardInstance = new WooAiDashboard();
        }
    });

    // Make dashboard instance available globally for debugging
    window.WooAiDashboard = WooAiDashboard;

})(jQuery);