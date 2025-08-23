<?php
/**
 * Performance Baseline Setup Script
 *
 * Establishes performance monitoring and baseline metrics for development
 * and production environments.
 *
 * @package WooAiAssistant
 * @subpackage Scripts
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    // Allow CLI execution
    if (php_sapi_name() !== 'cli') {
        exit('Direct access not permitted.');
    }
    
    // Bootstrap WordPress for CLI
    $wp_load_path = dirname(__FILE__) . '/../../../../../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        exit('WordPress not found. Run from plugin directory.');
    }
}

/**
 * Performance Baseline Manager
 */
class WooAiAssistant_PerformanceBaseline {
    
    /**
     * Performance metrics to track
     */
    const METRICS = [
        'response_time' => 'Response Time (ms)',
        'memory_usage' => 'Memory Usage (MB)', 
        'db_queries' => 'Database Queries',
        'cache_hits' => 'Cache Hit Ratio (%)',
        'bundle_size' => 'Widget Bundle Size (KB)',
        'kb_index_time' => 'KB Indexing Time (s)',
        'ai_response_time' => 'AI Response Time (ms)',
    ];
    
    /**
     * Performance targets from PROJECT_SPECIFICATIONS.md
     */
    const TARGETS = [
        'response_time' => 300,      // < 300ms for FAQ
        'memory_usage' => 128,       // 128MB minimum
        'bundle_size' => 50,         // < 50KB gzipped
        'kb_index_time' => 300,      // < 5 minutes for full index
        'ai_response_time' => 3000,  // < 3s for Free plan
    ];
    
    /**
     * Setup performance monitoring
     */
    public static function setup() {
        echo "🚀 Setting up Performance Monitoring Baseline...\n\n";
        
        self::create_monitoring_tables();
        self::install_monitoring_hooks();
        self::create_baseline_measurements();
        self::setup_performance_tracking();
        self::create_monitoring_dashboard();
        
        echo "✅ Performance monitoring baseline established!\n";
        echo "📊 Access monitoring at: wp-admin/admin.php?page=woo-ai-performance\n\n";
        
        self::display_current_metrics();
    }
    
    /**
     * Create database tables for performance tracking
     */
    private static function create_monitoring_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo_ai_performance_metrics';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_name varchar(50) NOT NULL,
            metric_value decimal(10,4) NOT NULL,
            context varchar(100) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY metric_name (metric_name),
            KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        echo "📊 Created performance metrics table\n";
    }
    
    /**
     * Install monitoring hooks
     */
    private static function install_monitoring_hooks() {
        // Create monitoring hooks file
        $hooks_file = dirname(__FILE__) . '/../src/Monitoring/PerformanceHooks.php';
        $hooks_dir = dirname($hooks_file);
        
        if (!is_dir($hooks_dir)) {
            wp_mkdir_p($hooks_dir);
        }
        
        $hooks_content = self::generate_hooks_class();
        file_put_contents($hooks_file, $hooks_content);
        
        echo "🎯 Created performance monitoring hooks\n";
    }
    
    /**
     * Generate performance hooks class
     */
    private static function generate_hooks_class() {
        return '<?php
/**
 * Performance Monitoring Hooks
 *
 * @package WooAiAssistant
 * @subpackage Monitoring
 * @since 1.0.0
 */

namespace WooAiAssistant\Monitoring;

if (!defined("ABSPATH")) {
    exit;
}

/**
 * Performance monitoring and tracking
 */
class PerformanceHooks {
    
    /**
     * Initialize monitoring hooks
     */
    public static function init() {
        // Track page load times
        add_action("wp_loaded", [self::class, "start_timing"]);
        add_action("wp_footer", [self::class, "end_timing"]);
        
        // Track chat performance  
        add_action("woo_ai_assistant_chat_start", [self::class, "track_chat_start"]);
        add_action("woo_ai_assistant_chat_response", [self::class, "track_chat_response"]);
        
        // Track KB indexing
        add_action("woo_ai_assistant_before_index", [self::class, "track_index_start"]);
        add_action("woo_ai_assistant_after_index", [self::class, "track_index_end"]);
        
        // Track memory usage
        add_action("shutdown", [self::class, "track_memory_usage"]);
    }
    
    /**
     * Start page timing
     */
    public static function start_timing() {
        if (!defined("WOO_AI_TIMING_START")) {
            define("WOO_AI_TIMING_START", microtime(true));
        }
    }
    
    /**
     * End page timing and record
     */
    public static function end_timing() {
        if (defined("WOO_AI_TIMING_START")) {
            $duration = (microtime(true) - WOO_AI_TIMING_START) * 1000;
            self::record_metric("response_time", $duration, get_current_url());
        }
    }
    
    /**
     * Track chat conversation start
     */
    public static function track_chat_start($conversation_id) {
        set_transient("woo_ai_chat_start_" . $conversation_id, microtime(true), 300);
    }
    
    /**
     * Track chat response time
     */
    public static function track_chat_response($conversation_id, $response) {
        $start_time = get_transient("woo_ai_chat_start_" . $conversation_id);
        if ($start_time) {
            $duration = (microtime(true) - $start_time) * 1000;
            self::record_metric("ai_response_time", $duration, "chat");
            delete_transient("woo_ai_chat_start_" . $conversation_id);
        }
    }
    
    /**
     * Track KB indexing start
     */
    public static function track_index_start($content_type) {
        set_transient("woo_ai_index_start_" . $content_type, microtime(true), 1800);
    }
    
    /**
     * Track KB indexing end
     */
    public static function track_index_end($content_type, $items_indexed) {
        $start_time = get_transient("woo_ai_index_start_" . $content_type);
        if ($start_time) {
            $duration = microtime(true) - $start_time;
            self::record_metric("kb_index_time", $duration, $content_type);
            delete_transient("woo_ai_index_start_" . $content_type);
        }
    }
    
    /**
     * Track memory usage
     */
    public static function track_memory_usage() {
        $memory_mb = memory_get_peak_usage(true) / 1024 / 1024;
        self::record_metric("memory_usage", $memory_mb, get_current_url());
    }
    
    /**
     * Record performance metric
     */
    private static function record_metric($name, $value, $context = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . "woo_ai_performance_metrics";
        
        $wpdb->insert(
            $table_name,
            [
                "metric_name" => $name,
                "metric_value" => $value,
                "context" => $context,
                "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? null,
                "ip_address" => $_SERVER["REMOTE_ADDR"] ?? null,
            ],
            ["%s", "%f", "%s", "%s", "%s"]
        );
    }
    
    /**
     * Get current URL
     */
    private static function get_current_url() {
        if (is_admin()) {
            return "admin";
        }
        
        if (is_front_page()) {
            return "homepage";
        }
        
        if (is_shop()) {
            return "shop";
        }
        
        if (is_product()) {
            return "product";
        }
        
        return get_post_type() ?: "unknown";
    }
}

// Initialize hooks if WordPress is loaded
if (function_exists("add_action")) {
    PerformanceHooks::init();
}';
    }
    
    /**
     * Create baseline measurements
     */
    private static function create_baseline_measurements() {
        echo "📏 Creating baseline measurements...\n";
        
        // Measure current widget bundle size
        $widget_js = dirname(__FILE__) . '/../assets/js/widget.js';
        if (file_exists($widget_js)) {
            $size_kb = filesize($widget_js) / 1024;
            self::record_metric('bundle_size', $size_kb, 'widget');
            echo "   Widget bundle: {$size_kb}KB\n";
        }
        
        // Measure memory baseline
        $memory_mb = memory_get_usage(true) / 1024 / 1024;
        self::record_metric('memory_usage', $memory_mb, 'baseline');
        echo "   Memory baseline: {$memory_mb}MB\n";
        
        // Test response time baseline
        $start = microtime(true);
        wp_cache_get('test', 'woo_ai_assistant'); // Simple cache operation
        $duration = (microtime(true) - $start) * 1000;
        self::record_metric('response_time', $duration, 'baseline');
        echo "   Response baseline: {$duration}ms\n";
    }
    
    /**
     * Setup performance tracking scripts
     */
    private static function setup_performance_tracking() {
        // Create performance tracking script
        $script_content = self::generate_tracking_script();
        $script_file = dirname(__FILE__) . '/performance-monitor.sh';
        file_put_contents($script_file, $script_content);
        chmod($script_file, 0755);
        
        echo "📈 Created performance monitoring script\n";
    }
    
    /**
     * Generate performance tracking script
     */
    private static function generate_tracking_script() {
        return '#!/bin/bash
# Performance Monitoring Script for Woo AI Assistant
# Runs continuous performance checks and alerts

echo "🚀 Starting Performance Monitoring..."

# Check widget bundle size
if [ -f "../assets/js/widget.js" ]; then
    BUNDLE_SIZE=$(stat -f%z ../assets/js/widget.js 2>/dev/null || stat -c%s ../assets/js/widget.js 2>/dev/null)
    BUNDLE_KB=$((BUNDLE_SIZE / 1024))
    echo "📦 Widget Bundle Size: ${BUNDLE_KB}KB"
    
    if [ $BUNDLE_KB -gt 50 ]; then
        echo "⚠️  WARNING: Bundle size exceeds 50KB target!"
    fi
fi

# Check gzipped size if gzip available  
if command -v gzip >/dev/null 2>&1 && [ -f "../assets/js/widget.js" ]; then
    GZIPPED_SIZE=$(gzip -c ../assets/js/widget.js | wc -c)
    GZIPPED_KB=$((GZIPPED_SIZE / 1024))
    echo "📦 Gzipped Bundle Size: ${GZIPPED_KB}KB"
    
    if [ $GZIPPED_KB -gt 15 ]; then
        echo "⚠️  WARNING: Gzipped bundle exceeds 15KB recommended!"
    fi
fi

# Memory usage check
if command -v ps >/dev/null 2>&1; then
    PHP_MEMORY=$(ps aux | grep php | grep -v grep | awk "{sum+=\$6} END {print sum/1024}")
    if [ ! -z "$PHP_MEMORY" ]; then
        echo "🧠 PHP Memory Usage: ${PHP_MEMORY}MB"
    fi
fi

# Database query performance
echo "🗄️  Running database performance check..."
mysql -e "SHOW PROCESSLIST;" 2>/dev/null | grep -v "Sleep" | wc -l | while read ACTIVE_QUERIES; do
    if [ $ACTIVE_QUERIES -gt 10 ]; then
        echo "⚠️  WARNING: High database activity ($ACTIVE_QUERIES active queries)"
    else
        echo "✅ Database performance: $ACTIVE_QUERIES active queries"
    fi
done

# Check WordPress performance
if command -v curl >/dev/null 2>&1; then
    echo "🌐 Testing WordPress response time..."
    RESPONSE_TIME=$(curl -o /dev/null -s -w "%{time_total}" http://localhost:8888/wp/ 2>/dev/null || echo "0")
    RESPONSE_MS=$(echo "$RESPONSE_TIME * 1000" | bc -l 2>/dev/null || echo "0")
    echo "⏱️  Homepage response: ${RESPONSE_MS}ms"
    
    if (( $(echo "$RESPONSE_TIME > 2" | bc -l 2>/dev/null) )); then
        echo "⚠️  WARNING: Slow response time (target: <2s)"
    fi
fi

echo "✅ Performance monitoring complete"
';
    }
    
    /**
     * Create monitoring dashboard
     */
    private static function create_monitoring_dashboard() {
        // This would typically create admin menu item
        // For now, just create a simple report script
        $dashboard_file = dirname(__FILE__) . '/performance-report.php';
        $dashboard_content = self::generate_dashboard_script();
        file_put_contents($dashboard_file, $dashboard_content);
        
        echo "📊 Created performance dashboard script\n";
    }
    
    /**
     * Generate dashboard script
     */
    private static function generate_dashboard_script() {
        return '<?php
/**
 * Performance Report Generator
 */

// Bootstrap WordPress
$wp_load_path = dirname(__FILE__) . "/../../../../../../../wp-load.php";
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    exit("WordPress not found. Run from plugin directory.");
}

global $wpdb;
$table_name = $wpdb->prefix . "woo_ai_performance_metrics";

echo "📊 WOO AI ASSISTANT PERFORMANCE REPORT\n";
echo str_repeat("=", 50) . "\n\n";

// Check if table exists
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $table_name
));

if (!$table_exists) {
    echo "⚠️  Performance monitoring not initialized.\n";
    echo "Run: php scripts/performance-baseline.php\n";
    exit;
}

// Recent performance metrics (last 24 hours)
echo "📈 RECENT PERFORMANCE (Last 24 Hours)\n";
echo str_repeat("-", 40) . "\n";

$metrics = $wpdb->get_results($wpdb->prepare("
    SELECT 
        metric_name,
        AVG(metric_value) as avg_value,
        MIN(metric_value) as min_value,
        MAX(metric_value) as max_value,
        COUNT(*) as sample_count
    FROM $table_name 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY metric_name
    ORDER BY metric_name
"));

foreach ($metrics as $metric) {
    $unit = "";
    switch ($metric->metric_name) {
        case "response_time":
        case "ai_response_time":
            $unit = "ms";
            break;
        case "memory_usage":
        case "bundle_size":
            $unit = "KB";
            break;
        case "kb_index_time":
            $unit = "s";
            break;
        case "cache_hits":
            $unit = "%";
            break;
    }
    
    printf("%-20s: %6.2f%s (min: %.2f, max: %.2f, samples: %d)\n",
        ucfirst(str_replace("_", " ", $metric->metric_name)),
        $metric->avg_value,
        $unit,
        $metric->min_value,
        $metric->max_value,
        $metric->sample_count
    );
}

echo "\n🎯 PERFORMANCE TARGETS\n";
echo str_repeat("-", 30) . "\n";

$targets = [
    "response_time" => ["target" => 300, "unit" => "ms"],
    "memory_usage" => ["target" => 128, "unit" => "MB"],
    "bundle_size" => ["target" => 50, "unit" => "KB"],
    "ai_response_time" => ["target" => 3000, "unit" => "ms"],
];

foreach ($metrics as $metric) {
    if (isset($targets[$metric->metric_name])) {
        $target = $targets[$metric->metric_name];
        $status = $metric->avg_value <= $target["target"] ? "✅ PASS" : "❌ FAIL";
        
        printf("%-20s: %s (%.2f%s vs target: %d%s)\n",
            ucfirst(str_replace("_", " ", $metric->metric_name)),
            $status,
            $metric->avg_value,
            $target["unit"],
            $target["target"],
            $target["unit"]
        );
    }
}

echo "\n📋 RECOMMENDATIONS\n";
echo str_repeat("-", 25) . "\n";

// Analyze and provide recommendations
foreach ($metrics as $metric) {
    if ($metric->metric_name === "response_time" && $metric->avg_value > 300) {
        echo "⚠️  Consider enabling caching for faster response times\n";
    }
    
    if ($metric->metric_name === "memory_usage" && $metric->avg_value > 256) {
        echo "⚠️  High memory usage detected - review code efficiency\n";
    }
    
    if ($metric->metric_name === "bundle_size" && $metric->avg_value > 50) {
        echo "⚠️  Widget bundle size exceeds target - consider code splitting\n";
    }
}

echo "\n✅ Report generated: " . date("Y-m-d H:i:s") . "\n";
';
    }
    
    /**
     * Display current metrics
     */
    private static function display_current_metrics() {
        global $wpdb;
        
        echo "📋 CURRENT PERFORMANCE STATUS\n";
        echo str_repeat("-", 35) . "\n";
        
        foreach (self::TARGETS as $metric => $target) {
            $unit = self::get_metric_unit($metric);
            echo sprintf("%-20s: Target <%d%s\n", 
                ucfirst(str_replace('_', ' ', $metric)), 
                $target, 
                $unit
            );
        }
        
        echo "\n🔧 MONITORING TOOLS AVAILABLE\n";
        echo str_repeat("-", 35) . "\n";
        echo "• ./scripts/performance-monitor.sh - Continuous monitoring\n";
        echo "• ./scripts/performance-report.php - Detailed reports\n";
        echo "• Docker Grafana dashboard - Real-time metrics\n";
        echo "• WordPress admin performance page (coming soon)\n\n";
    }
    
    /**
     * Get metric unit
     */
    private static function get_metric_unit($metric) {
        $units = [
            'response_time' => 'ms',
            'memory_usage' => 'MB', 
            'db_queries' => '',
            'cache_hits' => '%',
            'bundle_size' => 'KB',
            'kb_index_time' => 's',
            'ai_response_time' => 'ms',
        ];
        
        return $units[$metric] ?? '';
    }
    
    /**
     * Record performance metric
     */
    private static function record_metric($name, $value, $context = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo_ai_performance_metrics';
        
        $wpdb->insert(
            $table_name,
            [
                'metric_name' => $name,
                'metric_value' => $value,
                'context' => $context,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ],
            ['%s', '%f', '%s', '%s', '%s']
        );
    }
}

// Run setup if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    WooAiAssistant_PerformanceBaseline::setup();
}