<?php

/**
 * Admin Dashboard Page Class
 *
 * Handles the main dashboard page rendering and KPI data display for the
 * Woo AI Assistant plugin admin interface. Provides comprehensive analytics,
 * performance metrics, and business intelligence for AI chatbot operations.
 *
 * @package WooAiAssistant
 * @subpackage Admin\Pages
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Admin\Pages;

use WooAiAssistant\Common\Utils;
use WooAiAssistant\Common\Traits\Singleton;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DashboardPage
 *
 * Manages the admin dashboard page with comprehensive KPI widgets, analytics,
 * and performance monitoring for the AI chatbot system.
 *
 * @since 1.0.0
 */
class DashboardPage
{
    use Singleton;

    /**
     * Required capability for dashboard access
     *
     * @since 1.0.0
     * @var string
     */
    private const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /**
     * Cache duration for KPI data (in seconds)
     *
     * @since 1.0.0
     * @var int
     */
    private const CACHE_DURATION = 300; // 5 minutes

    /**
     * Default time period for analytics
     *
     * @since 1.0.0
     * @var int
     */
    private const DEFAULT_PERIOD_DAYS = 7;

    /**
     * Constructor
     *
     * Initializes the dashboard page and sets up hooks.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_woo_ai_refresh_kpis', [$this, 'handleKpiRefresh']);
        add_action('wp_ajax_woo_ai_export_analytics', [$this, 'handleAnalyticsExport']);
    }

    /**
     * Render the complete dashboard page
     *
     * Main entry point for rendering the dashboard with all KPI widgets,
     * charts, and analytics data.
     *
     * @since 1.0.0
     * @param array $args Optional. Additional arguments for rendering
     * @return void
     *
     * @example
     * ```php
     * $dashboard = DashboardPage::getInstance();
     * $dashboard->renderDashboard(['period' => 30]);
     * ```
     */
    public function renderDashboard(array $args = []): void
    {
        // Security check
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-ai-assistant'));
        }

        // Verify nonce if this is a POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_dashboard_nonce')) {
                wp_die(__('Security check failed.', 'woo-ai-assistant'));
            }
        }

        // Get time period from request or use default
        $period = $this->sanitizePeriod($_GET['period'] ?? self::DEFAULT_PERIOD_DAYS);

        try {
            // Get all KPI data
            $kpiData = $this->getKpiData($period);

            // Render dashboard structure
            $this->renderDashboardHeader();
            $this->renderDashboardContent($kpiData, $period);
        } catch (\Exception $e) {
            Utils::logError('Dashboard rendering failed: ' . $e->getMessage());
            $this->renderErrorMessage($e->getMessage());
        }
    }

    /**
     * Enqueue dashboard assets
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueueAssets(string $hook): void
    {
        // Only enqueue on our dashboard page
        if ($hook !== 'toplevel_page_woo-ai-assistant') {
            return;
        }

        $assetVersion = Utils::getPluginVersion();

        // Enqueue Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
            [],
            '4.4.0',
            true
        );

        // Enqueue dashboard-specific CSS
        wp_enqueue_style(
            'woo-ai-dashboard',
            Utils::getAssetsUrl('css/dashboard.css'),
            ['wp-admin'],
            $assetVersion
        );

        // Enqueue dashboard-specific JavaScript
        wp_enqueue_script(
            'woo-ai-dashboard',
            Utils::getAssetsUrl('js/dashboard.js'),
            ['jquery', 'chartjs', 'wp-api-fetch'],
            $assetVersion,
            true
        );

        // Localize script with dashboard data
        wp_localize_script('woo-ai-dashboard', 'wooAiDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_ai_dashboard_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'woo-ai-assistant'),
                'error' => __('Error loading data', 'woo-ai-assistant'),
                'refresh' => __('Refresh Data', 'woo-ai-assistant'),
                'export' => __('Export Analytics', 'woo-ai-assistant'),
            ],
        ]);
    }

    /**
     * Get comprehensive KPI data for dashboard
     *
     * Retrieves and calculates all key performance indicators for the
     * specified time period with proper caching.
     *
     * @since 1.0.0
     * @param int $periodDays Number of days to analyze
     * @return array Comprehensive KPI data array
     * @throws \Exception When database query fails
     */
    public function getKpiData(int $periodDays = self::DEFAULT_PERIOD_DAYS): array
    {
        $cacheKey = "woo_ai_kpi_data_{$periodDays}";
        $cachedData = wp_cache_get($cacheKey, 'woo_ai_assistant');

        if (false !== $cachedData) {
            return $cachedData;
        }

        try {
            global $wpdb;

            $dateThreshold = gmdate('Y-m-d H:i:s', strtotime("-{$periodDays} days"));

            $kpiData = [
                'resolution_rate' => $this->calculateResolutionRate($dateThreshold),
                'assist_conversion_rate' => $this->calculateAssistConversionRate($dateThreshold),
                'total_conversations' => $this->getTotalConversations($dateThreshold),
                'average_rating' => $this->calculateAverageRating($dateThreshold),
                'faq_analysis' => $this->getFaqAnalysis($dateThreshold),
                'kb_health_score' => $this->calculateKbHealthScore(),
                'period_days' => $periodDays,
                'date_range' => [
                    'start' => $dateThreshold,
                    'end' => current_time('mysql')
                ]
            ];

            // Cache the results
            wp_cache_set($cacheKey, $kpiData, 'woo_ai_assistant', self::CACHE_DURATION);

            Utils::logDebug('KPI data retrieved successfully', ['period' => $periodDays]);

            return $kpiData;
        } catch (\Exception $e) {
            Utils::logError('Failed to retrieve KPI data: ' . $e->getMessage());
            throw new \Exception('Unable to load dashboard data. Please try again.');
        }
    }

    /**
     * Calculate resolution rate percentage
     *
     * Calculates the percentage of conversations that were resolved without
     * requiring human handoff based on conversation status and ratings.
     *
     * @since 1.0.0
     * @param string $dateThreshold Start date for calculation
     * @return array Resolution rate data with percentage and trend
     */
    private function calculateResolutionRate(string $dateThreshold): array
    {
        global $wpdb;

        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';

        // Get total conversations in period
        $totalConversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversationsTable} 
             WHERE started_at >= %s AND status != 'active'",
            $dateThreshold
        ));

        if (!$totalConversations) {
            return [
                'percentage' => 0,
                'resolved_count' => 0,
                'total_count' => 0,
                'trend' => 0
            ];
        }

        // Count resolved conversations (status = 'completed' and rating >= 3, or no handoff)
        $resolvedConversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversationsTable} 
             WHERE started_at >= %s 
             AND (status = 'completed' OR status = 'resolved')
             AND (user_rating IS NULL OR user_rating >= 3)
             AND (context IS NULL OR context NOT LIKE '%handoff_requested%')",
            $dateThreshold
        ));

        $percentage = round(($resolvedConversations / $totalConversations) * 100, 1);

        // Calculate trend (compare with previous period)
        $periodDays = (strtotime('now') - strtotime($dateThreshold)) / (60 * 60 * 24);
        $previousPeriodStart = gmdate('Y-m-d H:i:s', strtotime("-" . ($periodDays * 2) . " days"));
        $previousPeriodEnd = $dateThreshold;

        $previousTotal = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversationsTable} 
             WHERE started_at >= %s AND started_at < %s AND status != 'active'",
            $previousPeriodStart,
            $previousPeriodEnd
        ));

        $trend = 0;
        if ($previousTotal > 0) {
            $previousResolved = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$conversationsTable} 
                 WHERE started_at >= %s AND started_at < %s
                 AND (status = 'completed' OR status = 'resolved')
                 AND (user_rating IS NULL OR user_rating >= 3)
                 AND (context IS NULL OR context NOT LIKE '%handoff_requested%')",
                $previousPeriodStart,
                $previousPeriodEnd
            ));

            $previousPercentage = ($previousResolved / $previousTotal) * 100;
            $trend = round($percentage - $previousPercentage, 1);
        }

        return [
            'percentage' => $percentage,
            'resolved_count' => (int) $resolvedConversations,
            'total_count' => (int) $totalConversations,
            'trend' => $trend
        ];
    }

    /**
     * Calculate assist-to-conversion rate percentage
     *
     * Calculates the percentage of chat interactions that led to completed
     * WooCommerce orders within a reasonable timeframe.
     *
     * @since 1.0.0
     * @param string $dateThreshold Start date for calculation
     * @return array Conversion rate data with percentage and metrics
     */
    private function calculateAssistConversionRate(string $dateThreshold): array
    {
        global $wpdb;

        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';
        $agentActionsTable = $wpdb->prefix . 'woo_ai_agent_actions';

        // Get conversations with product interactions
        $conversationsWithProducts = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT c.conversation_id, c.user_id, c.session_id, c.started_at
             FROM {$conversationsTable} c
             LEFT JOIN {$agentActionsTable} a ON c.conversation_id = a.conversation_id
             WHERE c.started_at >= %s 
             AND c.user_id IS NOT NULL
             AND (a.action_type IN ('product_recommendation', 'add_to_cart', 'coupon_applied') 
                  OR c.context LIKE '%product%')",
            $dateThreshold
        ));

        if (empty($conversationsWithProducts)) {
            return [
                'percentage' => 0,
                'conversions' => 0,
                'total_assists' => 0,
                'revenue_generated' => 0
            ];
        }

        $totalAssists = count($conversationsWithProducts);
        $conversions = 0;
        $revenueGenerated = 0;

        // Check for orders within 24 hours of conversation for each user
        foreach ($conversationsWithProducts as $conversation) {
            $orderExists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}posts p
                 LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'shop_order'
                 AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')
                 AND pm.meta_key = '_customer_user'
                 AND pm.meta_value = %d
                 AND p.post_date >= %s
                 AND p.post_date <= %s",
                $conversation->user_id,
                $conversation->started_at,
                gmdate('Y-m-d H:i:s', strtotime($conversation->started_at . ' +24 hours'))
            ));

            if ($orderExists > 0) {
                $conversions++;

                // Get order value for revenue calculation
                $orderValue = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(pm.meta_value) FROM {$wpdb->prefix}posts p
                     LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
                     LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id
                     WHERE p.post_type = 'shop_order'
                     AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')
                     AND pm.meta_key = '_order_total'
                     AND pm2.meta_key = '_customer_user'
                     AND pm2.meta_value = %d
                     AND p.post_date >= %s
                     AND p.post_date <= %s",
                    $conversation->user_id,
                    $conversation->started_at,
                    gmdate('Y-m-d H:i:s', strtotime($conversation->started_at . ' +24 hours'))
                ));

                $revenueGenerated += (float) $orderValue;
            }
        }

        $percentage = $totalAssists > 0 ? round(($conversions / $totalAssists) * 100, 1) : 0;

        return [
            'percentage' => $percentage,
            'conversions' => $conversions,
            'total_assists' => $totalAssists,
            'revenue_generated' => $revenueGenerated
        ];
    }

    /**
     * Get total conversations count with breakdown
     *
     * @since 1.0.0
     * @param string $dateThreshold Start date for calculation
     * @return array Conversations data with counts and breakdown
     */
    private function getTotalConversations(string $dateThreshold): array
    {
        global $wpdb;

        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversationsTable} WHERE started_at >= %s",
            $dateThreshold
        ));

        // Get status breakdown
        $statusBreakdown = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM {$conversationsTable} 
             WHERE started_at >= %s GROUP BY status",
            $dateThreshold
        ), OBJECT_K);

        // Get daily conversation counts for chart data
        $dailyCounts = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(started_at) as date, COUNT(*) as count 
             FROM {$conversationsTable} 
             WHERE started_at >= %s 
             GROUP BY DATE(started_at) 
             ORDER BY date ASC",
            $dateThreshold
        ));

        return [
            'total' => (int) $total,
            'status_breakdown' => $statusBreakdown,
            'daily_counts' => $dailyCounts,
            'growth_rate' => $this->calculateConversationGrowthRate($dateThreshold)
        ];
    }

    /**
     * Calculate average user rating
     *
     * @since 1.0.0
     * @param string $dateThreshold Start date for calculation
     * @return array Rating data with average and distribution
     */
    private function calculateAverageRating(string $dateThreshold): array
    {
        global $wpdb;

        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';

        $ratingData = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(user_rating) as average_rating,
                COUNT(user_rating) as total_ratings
             FROM {$conversationsTable} 
             WHERE started_at >= %s AND user_rating IS NOT NULL",
            $dateThreshold
        ));

        if (!$ratingData || $ratingData->total_ratings == 0) {
            return [
                'average' => 0,
                'total_ratings' => 0,
                'distribution' => [],
                'satisfaction_level' => 'No ratings'
            ];
        }

        // Get rating distribution
        $distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT user_rating, COUNT(*) as count 
             FROM {$conversationsTable} 
             WHERE started_at >= %s AND user_rating IS NOT NULL 
             GROUP BY user_rating 
             ORDER BY user_rating ASC",
            $dateThreshold
        ), OBJECT_K);

        $average = round((float) $ratingData->average_rating, 1);
        $satisfactionLevel = $this->getSatisfactionLevel($average);

        return [
            'average' => $average,
            'total_ratings' => (int) $ratingData->total_ratings,
            'distribution' => $distribution,
            'satisfaction_level' => $satisfactionLevel
        ];
    }

    /**
     * Get FAQ analysis - most asked questions
     *
     * Analyzes conversation messages to identify the top 5 most frequently
     * asked questions or topics.
     *
     * @since 1.0.0
     * @param string $dateThreshold Start date for analysis
     * @return array FAQ analysis data with top questions and categories
     */
    private function getFaqAnalysis(string $dateThreshold): array
    {
        global $wpdb;

        $messagesTable = $wpdb->prefix . 'woo_ai_messages';
        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';

        // Get user messages from the period
        $userMessages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.message_content, COUNT(*) as frequency
             FROM {$messagesTable} m
             JOIN {$conversationsTable} c ON m.conversation_id = c.conversation_id
             WHERE c.started_at >= %s 
             AND m.message_type = 'user'
             AND LENGTH(m.message_content) > 10
             GROUP BY LOWER(SUBSTRING(m.message_content, 1, 100))
             HAVING frequency > 1
             ORDER BY frequency DESC
             LIMIT 10",
            $dateThreshold
        ));

        // Common question patterns to categorize inquiries
        $questionCategories = [
            'shipping' => ['ship', 'delivery', 'when will', 'how long', 'tracking'],
            'pricing' => ['price', 'cost', 'discount', 'coupon', 'sale'],
            'products' => ['product', 'item', 'available', 'stock', 'size'],
            'returns' => ['return', 'refund', 'exchange', 'warranty'],
            'support' => ['help', 'support', 'problem', 'issue', 'trouble']
        ];

        $categorizedQuestions = [];
        $topQuestions = [];

        foreach ($userMessages as $message) {
            $content = strtolower($message->message_content);
            $category = 'general';

            // Categorize the question
            foreach ($questionCategories as $cat => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($content, $keyword) !== false) {
                        $category = $cat;
                        break 2;
                    }
                }
            }

            if (!isset($categorizedQuestions[$category])) {
                $categorizedQuestions[$category] = 0;
            }
            $categorizedQuestions[$category] += (int) $message->frequency;

            // Add to top questions (limit to readable length)
            if (count($topQuestions) < 5) {
                $topQuestions[] = [
                    'question' => substr($message->message_content, 0, 100) . (strlen($message->message_content) > 100 ? '...' : ''),
                    'frequency' => (int) $message->frequency,
                    'category' => $category
                ];
            }
        }

        return [
            'top_questions' => $topQuestions,
            'categories' => $categorizedQuestions,
            'total_analyzed' => array_sum(array_column($userMessages, 'frequency'))
        ];
    }

    /**
     * Calculate Knowledge Base health score
     *
     * Evaluates the completeness and quality of the indexed knowledge base
     * content to provide a health score percentage.
     *
     * @since 1.0.0
     * @return array KB health score with breakdown and recommendations
     */
    private function calculateKbHealthScore(): array
    {
        global $wpdb;

        $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

        try {
            // Get KB content statistics
            $kbStats = $wpdb->get_row(
                "SELECT 
                    COUNT(*) as total_entries,
                    COUNT(CASE WHEN source_type = 'product' THEN 1 END) as product_entries,
                    COUNT(CASE WHEN source_type = 'page' THEN 1 END) as page_entries,
                    COUNT(CASE WHEN source_type = 'category' THEN 1 END) as category_entries,
                    COUNT(CASE WHEN embedding IS NOT NULL THEN 1 END) as embedded_entries,
                    AVG(LENGTH(content)) as avg_content_length
                 FROM {$kbTable}"
            );

            if (!$kbStats || $kbStats->total_entries == 0) {
                return [
                    'score' => 0,
                    'status' => 'empty',
                    'recommendations' => [__('Run initial knowledge base indexing', 'woo-ai-assistant')],
                    'metrics' => []
                ];
            }

            // Calculate individual health metrics
            $metrics = [
                'content_coverage' => $this->calculateContentCoverage($kbStats),
                'embedding_completeness' => $kbStats->embedded_entries / $kbStats->total_entries * 100,
                'content_quality' => $this->calculateContentQuality($kbStats),
                'freshness' => $this->calculateContentFreshness(),
            ];

            // Calculate overall health score (weighted average)
            $weights = [
                'content_coverage' => 0.3,
                'embedding_completeness' => 0.25,
                'content_quality' => 0.25,
                'freshness' => 0.2
            ];

            $score = 0;
            foreach ($metrics as $metric => $value) {
                $score += $value * $weights[$metric];
            }

            $score = round($score, 1);
            $status = $this->getHealthStatus($score);
            $recommendations = $this->getHealthRecommendations($score, $metrics);

            return [
                'score' => $score,
                'status' => $status,
                'metrics' => $metrics,
                'recommendations' => $recommendations,
                'total_entries' => (int) $kbStats->total_entries,
                'last_updated' => $this->getLastKbUpdate()
            ];
        } catch (\Exception $e) {
            Utils::logError('KB health score calculation failed: ' . $e->getMessage());

            return [
                'score' => 0,
                'status' => 'error',
                'recommendations' => [__('Unable to calculate KB health. Check system logs.', 'woo-ai-assistant')],
                'metrics' => []
            ];
        }
    }

    /**
     * Handle AJAX request to refresh KPI data
     *
     * @since 1.0.0
     * @return void
     */
    public function handleKpiRefresh(): void
    {
        // Security checks
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('Insufficient permissions.', 'woo-ai-assistant'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_dashboard_nonce')) {
            wp_die(__('Security check failed.', 'woo-ai-assistant'));
        }

        $period = $this->sanitizePeriod($_POST['period'] ?? self::DEFAULT_PERIOD_DAYS);

        try {
            // Clear cache and get fresh data
            wp_cache_delete("woo_ai_kpi_data_{$period}", 'woo_ai_assistant');
            $kpiData = $this->getKpiData($period);

            wp_send_json_success([
                'data' => $kpiData,
                'message' => __('KPI data refreshed successfully.', 'woo-ai-assistant')
            ]);
        } catch (\Exception $e) {
            Utils::logError('KPI refresh failed: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Failed to refresh data.', 'woo-ai-assistant')]);
        }
    }

    /**
     * Handle analytics export request
     *
     * @since 1.0.0
     * @return void
     */
    public function handleAnalyticsExport(): void
    {
        // Security checks
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('Insufficient permissions.', 'woo-ai-assistant'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo_ai_dashboard_nonce')) {
            wp_die(__('Security check failed.', 'woo-ai-assistant'));
        }

        $period = $this->sanitizePeriod($_POST['period'] ?? 30);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');

        try {
            $exportData = $this->prepareExportData($period);
            $this->downloadExport($exportData, $format, $period);
        } catch (\Exception $e) {
            Utils::logError('Analytics export failed: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Export failed.', 'woo-ai-assistant')]);
        }
    }

    /**
     * Render dashboard header with period selector and actions
     *
     * @since 1.0.0
     * @return void
     */
    private function renderDashboardHeader(): void
    {
        echo '<div class="woo-ai-dashboard-header">';
        echo '<div class="header-content">';

        echo '<div class="header-title">';
        echo '<h1>' . esc_html__('AI Assistant Dashboard', 'woo-ai-assistant') . '</h1>';
        echo '<p>' . esc_html__('Monitor your chatbot performance and customer engagement', 'woo-ai-assistant') . '</p>';
        echo '</div>';

        echo '<div class="header-controls">';
        $this->renderPeriodSelector();
        $this->renderDashboardActions();
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render main dashboard content
     *
     * @since 1.0.0
     * @param array $kpiData KPI data array
     * @param int $period Period in days
     * @return void
     */
    private function renderDashboardContent(array $kpiData, int $period): void
    {
        echo '<div class="woo-ai-dashboard-content">';

        // KPI Cards Grid
        echo '<div class="kpi-grid">';
        $this->renderKpiCards($kpiData);
        echo '</div>';

        // Charts Section
        echo '<div class="charts-section">';
        $this->renderChartsSection($kpiData);
        echo '</div>';

        // Detailed Analytics
        echo '<div class="analytics-section">';
        $this->renderAnalyticsSection($kpiData);
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render KPI cards with data and trends
     *
     * @since 1.0.0
     * @param array $kpiData KPI data array
     * @return void
     */
    private function renderKpiCards(array $kpiData): void
    {
        $cards = [
            [
                'id' => 'resolution-rate',
                'title' => __('Resolution Rate', 'woo-ai-assistant'),
                'value' => $kpiData['resolution_rate']['percentage'] . '%',
                'trend' => $kpiData['resolution_rate']['trend'],
                'icon' => 'dashicons-yes-alt',
                'color' => '#2ecc71',
                'description' => sprintf(
                    __('%d of %d conversations resolved', 'woo-ai-assistant'),
                    $kpiData['resolution_rate']['resolved_count'],
                    $kpiData['resolution_rate']['total_count']
                )
            ],
            [
                'id' => 'conversion-rate',
                'title' => __('Assist-Conversion Rate', 'woo-ai-assistant'),
                'value' => $kpiData['assist_conversion_rate']['percentage'] . '%',
                'trend' => 0, // Would need historical data for trend
                'icon' => 'dashicons-cart',
                'color' => '#3498db',
                'description' => sprintf(
                    __('%d conversions from %d assists', 'woo-ai-assistant'),
                    $kpiData['assist_conversion_rate']['conversions'],
                    $kpiData['assist_conversion_rate']['total_assists']
                )
            ],
            [
                'id' => 'total-conversations',
                'title' => __('Total Conversations', 'woo-ai-assistant'),
                'value' => number_format($kpiData['total_conversations']['total']),
                'trend' => $kpiData['total_conversations']['growth_rate'],
                'icon' => 'dashicons-format-chat',
                'color' => '#9b59b6',
                'description' => __('Customer interactions', 'woo-ai-assistant')
            ],
            [
                'id' => 'average-rating',
                'title' => __('Average Rating', 'woo-ai-assistant'),
                'value' => $kpiData['average_rating']['average'] . '/5',
                'trend' => 0, // Would need historical data for trend
                'icon' => 'dashicons-star-filled',
                'color' => '#f39c12',
                'description' => sprintf(
                    __('%s (%d ratings)', 'woo-ai-assistant'),
                    $kpiData['average_rating']['satisfaction_level'],
                    $kpiData['average_rating']['total_ratings']
                )
            ],
            [
                'id' => 'kb-health',
                'title' => __('KB Health Score', 'woo-ai-assistant'),
                'value' => $kpiData['kb_health_score']['score'] . '%',
                'trend' => 0,
                'icon' => 'dashicons-book-alt',
                'color' => '#e74c3c',
                'description' => $kpiData['kb_health_score']['status']
            ]
        ];

        foreach ($cards as $card) {
            echo '<div class="kpi-card" id="' . esc_attr($card['id']) . '">';

            echo '<div class="kpi-header">';
            echo '<div class="kpi-icon" style="color: ' . esc_attr($card['color']) . ';">';
            echo '<span class="dashicons ' . esc_attr($card['icon']) . '"></span>';
            echo '</div>';

            if ($card['trend'] != 0) {
                $trendClass = $card['trend'] > 0 ? 'trend-up' : 'trend-down';
                $trendIcon = $card['trend'] > 0 ? '↗' : '↘';
                echo '<div class="kpi-trend ' . $trendClass . '">';
                echo '<span>' . $trendIcon . ' ' . abs($card['trend']) . '%</span>';
                echo '</div>';
            }
            echo '</div>';

            echo '<div class="kpi-content">';
            echo '<h3 class="kpi-value">' . esc_html($card['value']) . '</h3>';
            echo '<p class="kpi-title">' . esc_html($card['title']) . '</p>';
            echo '<p class="kpi-description">' . esc_html($card['description']) . '</p>';
            echo '</div>';

            echo '</div>';
        }
    }

    /**
     * Render charts section with resolution rate chart
     *
     * @since 1.0.0
     * @param array $kpiData KPI data array
     * @return void
     */
    private function renderChartsSection(array $kpiData): void
    {
        echo '<div class="charts-grid">';

        // Resolution Rate Chart
        echo '<div class="chart-container">';
        echo '<div class="chart-header">';
        echo '<h3>' . esc_html__('Resolution Rate Trend', 'woo-ai-assistant') . '</h3>';
        echo '</div>';
        echo '<canvas id="resolution-rate-chart"></canvas>';
        echo '</div>';

        // Conversations Over Time Chart
        echo '<div class="chart-container">';
        echo '<div class="chart-header">';
        echo '<h3>' . esc_html__('Conversations Over Time', 'woo-ai-assistant') . '</h3>';
        echo '</div>';
        echo '<canvas id="conversations-chart"></canvas>';
        echo '</div>';

        echo '</div>';

        // Add chart data to JavaScript
        $this->renderChartData($kpiData);
    }

    /**
     * Render analytics section with detailed breakdowns
     *
     * @since 1.0.0
     * @param array $kpiData KPI data array
     * @return void
     */
    private function renderAnalyticsSection(array $kpiData): void
    {
        echo '<div class="analytics-grid">';

        // FAQ Analysis
        echo '<div class="analytics-card">';
        echo '<h3>' . esc_html__('Top Customer Questions', 'woo-ai-assistant') . '</h3>';

        if (!empty($kpiData['faq_analysis']['top_questions'])) {
            echo '<div class="faq-list">';
            foreach ($kpiData['faq_analysis']['top_questions'] as $index => $faq) {
                echo '<div class="faq-item">';
                echo '<div class="faq-rank">' . ($index + 1) . '</div>';
                echo '<div class="faq-content">';
                echo '<p class="faq-question">' . esc_html($faq['question']) . '</p>';
                echo '<p class="faq-meta">';
                echo '<span class="faq-frequency">' . sprintf(__('Asked %d times', 'woo-ai-assistant'), $faq['frequency']) . '</span>';
                echo ' | <span class="faq-category">' . esc_html(ucfirst($faq['category'])) . '</span>';
                echo '</p>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="no-data">' . esc_html__('No frequent questions identified yet.', 'woo-ai-assistant') . '</p>';
        }
        echo '</div>';

        // KB Health Details
        echo '<div class="analytics-card">';
        echo '<h3>' . esc_html__('Knowledge Base Health', 'woo-ai-assistant') . '</h3>';

        $kbHealth = $kpiData['kb_health_score'];
        echo '<div class="kb-health-details">';
        echo '<div class="kb-score-circle">';
        echo '<span class="score">' . esc_html($kbHealth['score']) . '%</span>';
        echo '</div>';

        if (!empty($kbHealth['recommendations'])) {
            echo '<div class="kb-recommendations">';
            echo '<h4>' . esc_html__('Recommendations:', 'woo-ai-assistant') . '</h4>';
            echo '<ul>';
            foreach ($kbHealth['recommendations'] as $recommendation) {
                echo '<li>' . esc_html($recommendation) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    // Private helper methods...

    /**
     * Sanitize period input
     *
     * @since 1.0.0
     * @param mixed $period Period value to sanitize
     * @return int Sanitized period in days
     */
    private function sanitizePeriod($period): int
    {
        $period = absint($period);

        // Allow only specific periods for security
        $allowedPeriods = [1, 7, 14, 30, 90, 365];

        if (!in_array($period, $allowedPeriods)) {
            return self::DEFAULT_PERIOD_DAYS;
        }

        return $period;
    }

    /**
     * Calculate conversation growth rate
     *
     * @since 1.0.0
     * @param string $dateThreshold Start date
     * @return float Growth rate percentage
     */
    private function calculateConversationGrowthRate(string $dateThreshold): float
    {
        global $wpdb;

        $conversationsTable = $wpdb->prefix . 'woo_ai_conversations';
        $periodDays = (strtotime('now') - strtotime($dateThreshold)) / (60 * 60 * 24);

        $previousPeriodStart = gmdate('Y-m-d H:i:s', strtotime("-" . ($periodDays * 2) . " days"));
        $previousPeriodEnd = $dateThreshold;

        $currentPeriod = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversationsTable} WHERE started_at >= %s",
            $dateThreshold
        ));

        $previousPeriod = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$conversationsTable} 
             WHERE started_at >= %s AND started_at < %s",
            $previousPeriodStart,
            $previousPeriodEnd
        ));

        if ($previousPeriod == 0) {
            return $currentPeriod > 0 ? 100.0 : 0.0;
        }

        return round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 1);
    }

    /**
     * Get satisfaction level description from average rating
     *
     * @since 1.0.0
     * @param float $average Average rating
     * @return string Satisfaction level description
     */
    private function getSatisfactionLevel(float $average): string
    {
        if ($average >= 4.5) {
            return __('Excellent', 'woo-ai-assistant');
        }
        if ($average >= 4.0) {
            return __('Very Good', 'woo-ai-assistant');
        }
        if ($average >= 3.5) {
            return __('Good', 'woo-ai-assistant');
        }
        if ($average >= 3.0) {
            return __('Fair', 'woo-ai-assistant');
        }
        if ($average >= 2.0) {
            return __('Poor', 'woo-ai-assistant');
        }
        return __('Very Poor', 'woo-ai-assistant');
    }

    /**
     * Calculate content coverage score
     *
     * @since 1.0.0
     * @param object $kbStats KB statistics object
     * @return float Coverage score percentage
     */
    private function calculateContentCoverage($kbStats): float
    {
        // Get total available content to index
        $totalProducts = wp_count_posts('product')->publish ?? 0;
        $totalPages = wp_count_posts('page')->publish ?? 0;

        $totalAvailable = $totalProducts + $totalPages;

        if ($totalAvailable == 0) {
            return 100; // No content to index
        }

        $totalIndexed = $kbStats->product_entries + $kbStats->page_entries;
        return min(100, ($totalIndexed / $totalAvailable) * 100);
    }

    /**
     * Calculate content quality score
     *
     * @since 1.0.0
     * @param object $kbStats KB statistics object
     * @return float Quality score percentage
     */
    private function calculateContentQuality($kbStats): float
    {
        // Base quality on average content length and completeness
        $avgLength = $kbStats->avg_content_length ?? 0;

        // Ideal content length range (200-2000 characters)
        if ($avgLength >= 200 && $avgLength <= 2000) {
            return 100;
        } elseif ($avgLength < 200) {
            return max(50, ($avgLength / 200) * 100);
        } else {
            // Penalize very long content
            return max(75, 100 - (($avgLength - 2000) / 1000) * 10);
        }
    }

    /**
     * Calculate content freshness score
     *
     * @since 1.0.0
     * @return float Freshness score percentage
     */
    private function calculateContentFreshness(): float
    {
        global $wpdb;

        $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

        $recentUpdate = $wpdb->get_var(
            "SELECT MAX(updated_at) FROM {$kbTable}"
        );

        if (!$recentUpdate) {
            return 0;
        }

        $daysSinceUpdate = (strtotime('now') - strtotime($recentUpdate)) / (60 * 60 * 24);

        // Fresh if updated within last 7 days
        if ($daysSinceUpdate <= 7) {
            return 100;
        } elseif ($daysSinceUpdate <= 30) {
            return max(70, 100 - (($daysSinceUpdate - 7) / 23) * 30);
        } else {
            return max(30, 70 - (($daysSinceUpdate - 30) / 60) * 40);
        }
    }

    /**
     * Get health status description
     *
     * @since 1.0.0
     * @param float $score Health score
     * @return string Status description
     */
    private function getHealthStatus(float $score): string
    {
        if ($score >= 90) {
            return __('Excellent', 'woo-ai-assistant');
        }
        if ($score >= 80) {
            return __('Very Good', 'woo-ai-assistant');
        }
        if ($score >= 70) {
            return __('Good', 'woo-ai-assistant');
        }
        if ($score >= 60) {
            return __('Fair', 'woo-ai-assistant');
        }
        if ($score >= 40) {
            return __('Poor', 'woo-ai-assistant');
        }
        return __('Critical', 'woo-ai-assistant');
    }

    /**
     * Get health recommendations based on score and metrics
     *
     * @since 1.0.0
     * @param float $score Overall health score
     * @param array $metrics Individual metric scores
     * @return array Array of recommendations
     */
    private function getHealthRecommendations(float $score, array $metrics): array
    {
        $recommendations = [];

        if ($metrics['content_coverage'] < 80) {
            $recommendations[] = __('Index more products and pages to improve coverage', 'woo-ai-assistant');
        }

        if ($metrics['embedding_completeness'] < 90) {
            $recommendations[] = __('Ensure all content has embeddings generated', 'woo-ai-assistant');
        }

        if ($metrics['content_quality'] < 70) {
            $recommendations[] = __('Review and improve product descriptions', 'woo-ai-assistant');
        }

        if ($metrics['freshness'] < 80) {
            $recommendations[] = __('Update knowledge base with recent content changes', 'woo-ai-assistant');
        }

        if (empty($recommendations)) {
            $recommendations[] = __('Knowledge base is performing well!', 'woo-ai-assistant');
        }

        return $recommendations;
    }

    /**
     * Get last KB update timestamp
     *
     * @since 1.0.0
     * @return string|null Last update timestamp
     */
    private function getLastKbUpdate(): ?string
    {
        global $wpdb;

        $kbTable = $wpdb->prefix . 'woo_ai_knowledge_base';

        return $wpdb->get_var(
            "SELECT MAX(updated_at) FROM {$kbTable}"
        );
    }

    /**
     * Render period selector dropdown
     *
     * @since 1.0.0
     * @return void
     */
    private function renderPeriodSelector(): void
    {
        $currentPeriod = $this->sanitizePeriod($_GET['period'] ?? self::DEFAULT_PERIOD_DAYS);

        echo '<div class="period-selector">';
        echo '<label for="dashboard-period">' . esc_html__('Time Period:', 'woo-ai-assistant') . '</label>';
        echo '<select id="dashboard-period" name="period">';

        $periods = [
            1 => __('Last 24 hours', 'woo-ai-assistant'),
            7 => __('Last 7 days', 'woo-ai-assistant'),
            14 => __('Last 14 days', 'woo-ai-assistant'),
            30 => __('Last 30 days', 'woo-ai-assistant'),
            90 => __('Last 90 days', 'woo-ai-assistant'),
            365 => __('Last year', 'woo-ai-assistant')
        ];

        foreach ($periods as $days => $label) {
            $selected = $days === $currentPeriod ? 'selected' : '';
            echo '<option value="' . esc_attr($days) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '</div>';
    }

    /**
     * Render dashboard action buttons
     *
     * @since 1.0.0
     * @return void
     */
    private function renderDashboardActions(): void
    {
        echo '<div class="dashboard-actions">';

        echo '<button type="button" id="refresh-kpis" class="button button-secondary">';
        echo '<span class="dashicons dashicons-update"></span> ';
        echo esc_html__('Refresh', 'woo-ai-assistant');
        echo '</button>';

        echo '<button type="button" id="export-analytics" class="button button-secondary">';
        echo '<span class="dashicons dashicons-download"></span> ';
        echo esc_html__('Export', 'woo-ai-assistant');
        echo '</button>';

        echo '</div>';
    }

    /**
     * Render chart data as JavaScript
     *
     * @since 1.0.0
     * @param array $kpiData KPI data array
     * @return void
     */
    private function renderChartData(array $kpiData): void
    {
        $chartData = [
            'conversations' => $kpiData['total_conversations']['daily_counts'],
            'resolution_rate' => $kpiData['resolution_rate'],
            'labels' => []
        ];

        // Generate date labels
        $period = $kpiData['period_days'];
        for ($i = $period - 1; $i >= 0; $i--) {
            $chartData['labels'][] = gmdate('M j', strtotime("-{$i} days"));
        }

        echo '<script type="text/javascript">';
        echo 'window.wooAiChartData = ' . wp_json_encode($chartData) . ';';
        echo '</script>';
    }

    /**
     * Render error message
     *
     * @since 1.0.0
     * @param string $message Error message
     * @return void
     */
    private function renderErrorMessage(string $message): void
    {
        echo '<div class="woo-ai-dashboard-error">';
        echo '<div class="error-content">';
        echo '<h2>' . esc_html__('Dashboard Error', 'woo-ai-assistant') . '</h2>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=woo-ai-assistant')) . '" class="button button-primary">';
        echo esc_html__('Try Again', 'woo-ai-assistant') . '</a></p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Prepare data for analytics export
     *
     * @since 1.0.0
     * @param int $period Period in days
     * @return array Export data array
     */
    private function prepareExportData(int $period): array
    {
        $kpiData = $this->getKpiData($period);

        return [
            'summary' => [
                'period' => $period,
                'generated_at' => current_time('mysql'),
                'total_conversations' => $kpiData['total_conversations']['total'],
                'resolution_rate' => $kpiData['resolution_rate']['percentage'],
                'conversion_rate' => $kpiData['assist_conversion_rate']['percentage'],
                'average_rating' => $kpiData['average_rating']['average'],
                'kb_health_score' => $kpiData['kb_health_score']['score']
            ],
            'conversations' => $kpiData['total_conversations'],
            'faq_analysis' => $kpiData['faq_analysis'],
            'kb_health' => $kpiData['kb_health_score']
        ];
    }

    /**
     * Download analytics export file
     *
     * @since 1.0.0
     * @param array $data Export data
     * @param string $format Export format (csv, json)
     * @param int $period Period in days
     * @return void
     */
    private function downloadExport(array $data, string $format, int $period): void
    {
        $filename = 'woo-ai-analytics-' . gmdate('Y-m-d') . '-' . $period . 'days.' . $format;

        switch ($format) {
            case 'csv':
                $this->exportToCsv($data, $filename);
                break;
            case 'json':
            default:
                $this->exportToJson($data, $filename);
                break;
        }
    }

    /**
     * Export data to CSV format
     *
     * @since 1.0.0
     * @param array $data Data to export
     * @param string $filename Output filename
     * @return void
     */
    private function exportToCsv(array $data, string $filename): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Summary section
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Period (days)', $data['summary']['period']]);
        fputcsv($output, ['Total Conversations', $data['summary']['total_conversations']]);
        fputcsv($output, ['Resolution Rate (%)', $data['summary']['resolution_rate']]);
        fputcsv($output, ['Conversion Rate (%)', $data['summary']['conversion_rate']]);
        fputcsv($output, ['Average Rating', $data['summary']['average_rating']]);
        fputcsv($output, ['KB Health Score (%)', $data['summary']['kb_health_score']]);

        fclose($output);
        exit;
    }

    /**
     * Export data to JSON format
     *
     * @since 1.0.0
     * @param array $data Data to export
     * @param string $filename Output filename
     * @return void
     */
    private function exportToJson(array $data, string $filename): void
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo wp_json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
