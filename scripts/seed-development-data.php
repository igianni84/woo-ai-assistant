<?php
/**
 * Development Seed Data Script for Woo AI Assistant
 *
 * This script creates sample data for development and testing purposes including:
 * - Sample conversations with various statuses
 * - Knowledge base entries for different content types
 * - Usage statistics for dashboard testing
 * - Sample products with detailed descriptions
 * - FAQ posts and pages for knowledge base testing
 *
 * @package WooAiAssistant
 * @subpackage Scripts
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Security check
if (!defined('WP_CLI') && (!defined('ABSPATH') || !current_user_can('manage_options'))) {
    die('Access denied. This script should only be run via WP-CLI or by administrators.');
}

/**
 * Main seed data class
 */
class WooAiAssistantSeedData {
    
    /**
     * Database instance
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Number of conversations to create
     * @var int
     */
    private $conversation_count = 50;
    
    /**
     * Number of products to create
     * @var int
     */
    private $product_count = 20;
    
    /**
     * Number of FAQ posts to create
     * @var int
     */
    private $faq_count = 15;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Main method to create all seed data
     */
    public function createAll() {
        $this->log('🌱 Starting Woo AI Assistant seed data creation...');
        
        try {
            // Check if WooCommerce is active
            if (!$this->isWooCommerceActive()) {
                throw new Exception('WooCommerce is not active. Please activate WooCommerce first.');
            }
            
            // Create sample data
            $this->createSampleProducts();
            $this->createSamplePages();
            $this->createSampleFAQs();
            $this->createSampleConversations();
            $this->createSampleKnowledgeBase();
            $this->createSampleUsageStats();
            $this->createSampleSettings();
            
            $this->log('✅ Seed data creation completed successfully!');
            $this->printSummary();
            
        } catch (Exception $e) {
            $this->log('❌ Error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Create sample products for testing
     */
    private function createSampleProducts() {
        $this->log('📦 Creating sample products...');
        
        $products = [
            [
                'name' => 'Premium Wireless Headphones',
                'price' => 299.99,
                'description' => 'Experience exceptional sound quality with our premium wireless headphones featuring active noise cancellation, 30-hour battery life, and premium leather comfort padding.',
                'short_description' => 'Premium wireless headphones with noise cancellation',
                'category' => 'Electronics',
                'attributes' => ['Color' => ['Black', 'White', 'Red'], 'Connectivity' => ['Bluetooth 5.0', 'USB-C']],
            ],
            [
                'name' => 'Organic Cotton T-Shirt',
                'price' => 29.99,
                'description' => 'Soft, comfortable, and environmentally friendly organic cotton t-shirt. Available in multiple colors and sizes. Perfect for casual wear and layering.',
                'short_description' => 'Comfortable organic cotton t-shirt',
                'category' => 'Clothing',
                'attributes' => ['Size' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'Color' => ['White', 'Black', 'Gray', 'Navy']],
            ],
            [
                'name' => 'Smart Home Security Camera',
                'price' => 159.99,
                'description' => 'Keep your home secure with our smart Wi-Fi security camera featuring 1080p HD video, night vision, motion detection, and mobile app control.',
                'short_description' => '1080p Wi-Fi security camera with mobile app',
                'category' => 'Smart Home',
                'attributes' => ['Resolution' => ['1080p'], 'Connectivity' => ['Wi-Fi', 'Ethernet']],
            ],
            [
                'name' => 'Artisan Coffee Blend',
                'price' => 24.99,
                'description' => 'Hand-selected premium coffee beans roasted to perfection. Our signature blend offers rich flavor notes of chocolate and caramel with a smooth finish.',
                'short_description' => 'Premium artisan coffee blend',
                'category' => 'Food & Beverages',
                'attributes' => ['Roast Level' => ['Medium', 'Dark'], 'Grind' => ['Whole Bean', 'Ground']],
            ],
            [
                'name' => 'Yoga Mat Pro',
                'price' => 79.99,
                'description' => 'Professional-grade yoga mat with superior grip and cushioning. Made from eco-friendly materials with alignment guides for perfect poses.',
                'short_description' => 'Professional eco-friendly yoga mat',
                'category' => 'Fitness',
                'attributes' => ['Color' => ['Purple', 'Green', 'Black', 'Pink'], 'Thickness' => ['6mm']],
            ],
        ];
        
        $created_count = 0;
        
        foreach ($products as $product_data) {
            // Check if product already exists
            $existing = get_posts([
                'post_type' => 'product',
                'title' => $product_data['name'],
                'numberposts' => 1
            ]);
            
            if (!empty($existing)) {
                continue;
            }
            
            // Create product
            $product_id = wp_insert_post([
                'post_title' => $product_data['name'],
                'post_content' => $product_data['description'],
                'post_excerpt' => $product_data['short_description'],
                'post_status' => 'publish',
                'post_type' => 'product',
                'post_author' => 1,
            ]);
            
            if ($product_id) {
                // Set product meta
                update_post_meta($product_id, '_price', $product_data['price']);
                update_post_meta($product_id, '_regular_price', $product_data['price']);
                update_post_meta($product_id, '_manage_stock', 'yes');
                update_post_meta($product_id, '_stock', rand(10, 100));
                update_post_meta($product_id, '_stock_status', 'instock');
                update_post_meta($product_id, '_visibility', 'visible');
                update_post_meta($product_id, '_featured', rand(0, 1) ? 'yes' : 'no');
                
                // Set product category
                $term = get_term_by('name', $product_data['category'], 'product_cat');
                if (!$term) {
                    $term_result = wp_insert_term($product_data['category'], 'product_cat');
                    if (!is_wp_error($term_result)) {
                        $term_id = $term_result['term_id'];
                    }
                } else {
                    $term_id = $term->term_id;
                }
                
                if (isset($term_id)) {
                    wp_set_object_terms($product_id, $term_id, 'product_cat');
                }
                
                $created_count++;
            }
        }
        
        $this->log("   Created {$created_count} sample products");
    }
    
    /**
     * Create sample pages for knowledge base
     */
    private function createSamplePages() {
        $this->log('📄 Creating sample pages...');
        
        $pages = [
            [
                'title' => 'Shipping & Delivery Information',
                'content' => 'We offer fast and reliable shipping worldwide. Free shipping on orders over $50. Standard delivery takes 3-5 business days, while express delivery takes 1-2 business days.',
            ],
            [
                'title' => 'Return & Refund Policy',
                'content' => 'We accept returns within 30 days of purchase. Items must be in original condition. Refunds are processed within 3-5 business days after we receive your return.',
            ],
            [
                'title' => 'Size Guide',
                'content' => 'Find your perfect fit with our comprehensive size guide. Measurements are provided in both US and international sizes for all clothing categories.',
            ],
            [
                'title' => 'Care Instructions',
                'content' => 'Proper care extends the life of your products. Follow these guidelines for cleaning and maintaining your purchases to ensure lasting quality.',
            ],
        ];
        
        $created_count = 0;
        
        foreach ($pages as $page_data) {
            $existing = get_page_by_title($page_data['title']);
            if ($existing) {
                continue;
            }
            
            $page_id = wp_insert_post([
                'post_title' => $page_data['title'],
                'post_content' => $page_data['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
            ]);
            
            if ($page_id) {
                $created_count++;
            }
        }
        
        $this->log("   Created {$created_count} sample pages");
    }
    
    /**
     * Create sample FAQ posts
     */
    private function createSampleFAQs() {
        $this->log('❓ Creating sample FAQ posts...');
        
        $faqs = [
            ['q' => 'How do I track my order?', 'a' => 'You can track your order using the tracking number provided in your confirmation email. Visit our order tracking page and enter your order number.'],
            ['q' => 'What payment methods do you accept?', 'a' => 'We accept all major credit cards, PayPal, Apple Pay, Google Pay, and bank transfers for wholesale orders.'],
            ['q' => 'Can I change my order after placing it?', 'a' => 'Orders can be modified within 1 hour of placement. After this time, please contact customer service for assistance.'],
            ['q' => 'Do you offer international shipping?', 'a' => 'Yes, we ship to over 50 countries worldwide. Shipping costs and delivery times vary by destination.'],
            ['q' => 'How do I create an account?', 'a' => 'Click the "Register" button on our homepage and fill out the required information. Account creation is free and gives you access to exclusive member benefits.'],
            ['q' => 'What is your warranty policy?', 'a' => 'All products come with a manufacturer warranty. Electronics have a 1-year warranty, clothing has a 6-month warranty for defects.'],
            ['q' => 'How do I apply a discount code?', 'a' => 'Enter your discount code in the "Coupon Code" field during checkout before completing your purchase.'],
            ['q' => 'Can I cancel my order?', 'a' => 'Orders can be cancelled within 24 hours of placement if they have not yet been processed for shipping.'],
        ];
        
        $created_count = 0;
        
        foreach ($faqs as $faq_data) {
            $title = $faq_data['q'];
            $existing = get_posts([
                'post_type' => 'post',
                'title' => $title,
                'numberposts' => 1
            ]);
            
            if (!empty($existing)) {
                continue;
            }
            
            $post_id = wp_insert_post([
                'post_title' => $title,
                'post_content' => $faq_data['a'],
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_author' => 1,
                'post_category' => [get_cat_ID('FAQ') ?: wp_create_category('FAQ')],
            ]);
            
            if ($post_id) {
                $created_count++;
            }
        }
        
        $this->log("   Created {$created_count} FAQ posts");
    }
    
    /**
     * Create sample conversations for testing
     */
    private function createSampleConversations() {
        $this->log('💬 Creating sample conversations...');
        
        $conversation_table = $this->wpdb->prefix . 'woo_ai_conversations';
        $message_table = $this->wpdb->prefix . 'woo_ai_messages';
        
        // Check if tables exist
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$conversation_table}'") !== $conversation_table) {
            $this->log('   Conversation tables not found. Run plugin activation first.');
            return;
        }
        
        $sample_conversations = [
            [
                'messages' => [
                    ['sender' => 'user', 'text' => 'Hi, I need help finding a good pair of headphones'],
                    ['sender' => 'assistant', 'text' => 'I\'d be happy to help you find the perfect headphones! What will you primarily use them for - music, gaming, or work calls?'],
                    ['sender' => 'user', 'text' => 'Mainly for music, I like bass-heavy genres'],
                    ['sender' => 'assistant', 'text' => 'Great! Our Premium Wireless Headphones would be perfect for you. They feature enhanced bass response and active noise cancellation. Would you like me to show you the details?'],
                ],
                'status' => 'completed',
                'rating' => 5,
            ],
            [
                'messages' => [
                    ['sender' => 'user', 'text' => 'What\'s your return policy?'],
                    ['sender' => 'assistant', 'text' => 'We accept returns within 30 days of purchase. Items must be in original condition with tags attached. Would you like more details about the return process?'],
                ],
                'status' => 'completed',
                'rating' => 4,
            ],
            [
                'messages' => [
                    ['sender' => 'user', 'text' => 'I\'m looking for organic clothing options'],
                    ['sender' => 'assistant', 'text' => 'We have several organic options! Our Organic Cotton T-Shirt is very popular - it\'s made from 100% certified organic cotton. What size are you looking for?'],
                    ['sender' => 'user', 'text' => 'Medium would be great. What colors do you have?'],
                    ['sender' => 'assistant', 'text' => 'In medium, we have White, Black, Gray, and Navy available. All are currently in stock. Would you like me to add one to your cart?'],
                ],
                'status' => 'active',
                'rating' => null,
            ],
        ];
        
        $created_count = 0;
        
        foreach ($sample_conversations as $conv_data) {
            $conversation_id = 'conv_' . time() . '_' . rand(1000, 9999);
            
            // Insert conversation
            $conversation_result = $this->wpdb->insert(
                $conversation_table,
                [
                    'conversation_id' => $conversation_id,
                    'user_id' => rand(0, 1) ? rand(1, 5) : null,
                    'session_id' => 'session_' . rand(10000, 99999),
                    'status' => $conv_data['status'],
                    'started_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
                    'total_messages' => count($conv_data['messages']),
                    'user_rating' => $conv_data['rating'],
                    'context' => json_encode(['page_type' => 'shop', 'user_agent' => 'Development Seed Data']),
                ]
            );
            
            if ($conversation_result) {
                // Insert messages
                foreach ($conv_data['messages'] as $index => $message) {
                    $this->wpdb->insert(
                        $message_table,
                        [
                            'conversation_id' => $conversation_id,
                            'message_type' => $message['sender'],
                            'message_content' => $message['text'],
                            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days') + ($index * 60)),
                            'tokens_used' => $message['sender'] === 'assistant' ? rand(50, 200) : null,
                            'model_used' => $message['sender'] === 'assistant' ? 'gemini-2.5-flash' : null,
                            'confidence_score' => $message['sender'] === 'assistant' ? rand(85, 98) / 100 : null,
                        ]
                    );
                }
                $created_count++;
            }
        }
        
        // Create additional random conversations
        for ($i = 0; $i < 10; $i++) {
            $conversation_id = 'conv_' . time() . '_' . rand(1000, 9999) . '_' . $i;
            
            $this->wpdb->insert(
                $conversation_table,
                [
                    'conversation_id' => $conversation_id,
                    'user_id' => rand(0, 1) ? rand(1, 5) : null,
                    'session_id' => 'session_' . rand(10000, 99999),
                    'status' => ['active', 'completed', 'abandoned'][rand(0, 2)],
                    'started_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days')),
                    'total_messages' => rand(2, 8),
                    'user_rating' => rand(0, 1) ? rand(3, 5) : null,
                    'context' => json_encode(['page_type' => ['home', 'product', 'shop', 'cart'][rand(0, 3)]]),
                ]
            );
            $created_count++;
        }
        
        $this->log("   Created {$created_count} sample conversations");
    }
    
    /**
     * Create sample knowledge base entries
     */
    private function createSampleKnowledgeBase() {
        $this->log('🧠 Creating sample knowledge base entries...');
        
        $kb_table = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$kb_table}'") !== $kb_table) {
            $this->log('   Knowledge base table not found. Run plugin activation first.');
            return;
        }
        
        $kb_entries = [
            [
                'source_type' => 'product',
                'title' => 'Premium Wireless Headphones - Features',
                'content' => 'Premium wireless headphones with active noise cancellation, 30-hour battery life, premium leather comfort padding, Bluetooth 5.0 connectivity.',
                'chunk_content' => 'Active noise cancellation blocks external sounds for immersive listening experience.',
            ],
            [
                'source_type' => 'page',
                'title' => 'Shipping Information',
                'content' => 'Free shipping on orders over $50. Standard delivery 3-5 business days, express delivery 1-2 business days.',
                'chunk_content' => 'Free shipping available for orders over fifty dollars.',
            ],
            [
                'source_type' => 'faq',
                'title' => 'Payment Methods',
                'content' => 'We accept major credit cards, PayPal, Apple Pay, Google Pay, and bank transfers for wholesale orders.',
                'chunk_content' => 'Multiple payment options including credit cards and digital wallets.',
            ],
        ];
        
        $created_count = 0;
        
        foreach ($kb_entries as $entry) {
            $result = $this->wpdb->insert(
                $kb_table,
                [
                    'source_type' => $entry['source_type'],
                    'source_id' => rand(1, 100),
                    'title' => $entry['title'],
                    'content' => $entry['content'],
                    'chunk_content' => $entry['chunk_content'],
                    'chunk_index' => 0,
                    'embedding' => json_encode(array_fill(0, 384, rand(-100, 100) / 1000)), // Dummy embedding
                    'metadata' => json_encode(['chunk_size' => strlen($entry['chunk_content'])]),
                    'hash' => md5($entry['content']),
                    'indexed_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 7) . ' days')),
                ]
            );
            
            if ($result) {
                $created_count++;
            }
        }
        
        $this->log("   Created {$created_count} knowledge base entries");
    }
    
    /**
     * Create sample usage statistics
     */
    private function createSampleUsageStats() {
        $this->log('📊 Creating sample usage statistics...');
        
        $stats_table = $this->wpdb->prefix . 'woo_ai_usage_stats';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") !== $stats_table) {
            $this->log('   Usage stats table not found. Run plugin activation first.');
            return;
        }
        
        $created_count = 0;
        
        // Create stats for the last 30 days
        for ($i = 30; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            
            $stats = [
                'conversations_started' => rand(5, 25),
                'conversations_completed' => rand(3, 20),
                'messages_sent' => rand(50, 200),
                'ai_responses' => rand(40, 180),
                'user_satisfaction' => rand(70, 95),
                'kb_searches' => rand(20, 80),
            ];
            
            foreach ($stats as $stat_type => $value) {
                $result = $this->wpdb->replace(
                    $stats_table,
                    [
                        'date' => $date,
                        'stat_type' => $stat_type,
                        'stat_value' => $value,
                        'metadata' => json_encode(['generated' => true]),
                    ]
                );
                
                if ($result) {
                    $created_count++;
                }
            }
        }
        
        $this->log("   Created {$created_count} usage statistics entries");
    }
    
    /**
     * Create sample settings
     */
    private function createSampleSettings() {
        $this->log('⚙️  Setting up sample configuration...');
        
        $sample_settings = [
            'woo_ai_assistant_sample_data_created' => date('Y-m-d H:i:s'),
            'woo_ai_assistant_development_mode' => 'yes',
            'woo_ai_assistant_kb_last_indexed' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'woo_ai_assistant_total_conversations' => rand(100, 500),
        ];
        
        foreach ($sample_settings as $option_name => $value) {
            update_option($option_name, $value);
        }
        
        $this->log('   Sample settings configured');
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function isWooCommerceActive() {
        return class_exists('WooCommerce') || function_exists('WC');
    }
    
    /**
     * Print summary of created data
     */
    private function printSummary() {
        $this->log('');
        $this->log('📋 SEED DATA SUMMARY');
        $this->log('===================');
        
        // Count products
        $product_count = wp_count_posts('product');
        $this->log('Products: ' . ($product_count->publish ?? 0) . ' published');
        
        // Count pages
        $page_count = wp_count_posts('page');
        $this->log('Pages: ' . ($page_count->publish ?? 0) . ' published');
        
        // Count posts
        $post_count = wp_count_posts('post');
        $this->log('Posts: ' . ($post_count->publish ?? 0) . ' published');
        
        // Count conversations
        $conv_table = $this->wpdb->prefix . 'woo_ai_conversations';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$conv_table}'") === $conv_table) {
            $conv_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$conv_table}");
            $this->log('Conversations: ' . $conv_count);
        }
        
        // Count KB entries
        $kb_table = $this->wpdb->prefix . 'woo_ai_knowledge_base';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$kb_table}'") === $kb_table) {
            $kb_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$kb_table}");
            $this->log('Knowledge Base Entries: ' . $kb_count);
        }
        
        $this->log('');
        $this->log('🎉 Development environment is ready for testing!');
        $this->log('');
        $this->log('Next steps:');
        $this->log('1. Visit wp-admin to see the AI Assistant menu');
        $this->log('2. Check the dashboard for sample analytics');
        $this->log('3. Test the chat widget on the frontend');
        $this->log('4. Review conversation logs and knowledge base');
    }
    
    /**
     * Log message to console and error log
     */
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[{$timestamp}] {$message}";
        
        if (defined('WP_CLI')) {
            WP_CLI::log($message);
        } else {
            echo $formatted . PHP_EOL;
        }
        
        if ($level === 'error') {
            error_log("Woo AI Assistant Seed Data Error: {$message}");
        } else {
            error_log("Woo AI Assistant Seed Data: {$message}");
        }
    }
    
    /**
     * Clean up all seed data (for development)
     */
    public function cleanup() {
        $this->log('🧹 Cleaning up seed data...');
        
        try {
            // Delete sample posts and pages
            $posts_to_delete = get_posts([
                'post_type' => ['post', 'page', 'product'],
                'numberposts' => -1,
                'meta_query' => [
                    [
                        'key' => '_woo_ai_seed_data',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            
            foreach ($posts_to_delete as $post) {
                wp_delete_post($post->ID, true);
            }
            
            // Clear database tables
            $tables = [
                $this->wpdb->prefix . 'woo_ai_conversations',
                $this->wpdb->prefix . 'woo_ai_messages',
                $this->wpdb->prefix . 'woo_ai_knowledge_base',
                $this->wpdb->prefix . 'woo_ai_usage_stats',
            ];
            
            foreach ($tables as $table) {
                if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                    $this->wpdb->query("TRUNCATE TABLE {$table}");
                }
            }
            
            // Remove sample options
            delete_option('woo_ai_assistant_sample_data_created');
            delete_option('woo_ai_assistant_development_mode');
            
            $this->log('✅ Seed data cleanup completed');
            
        } catch (Exception $e) {
            $this->log('❌ Cleanup error: ' . $e->getMessage(), 'error');
        }
    }
}

// Execute if run directly
if (php_sapi_name() === 'cli' || (defined('WP_CLI') && WP_CLI)) {
    // WP-CLI command
    if (defined('WP_CLI') && class_exists('WP_CLI')) {
        WP_CLI::add_command('woo-ai seed-data', function($args, $assoc_args) {
            $seeder = new WooAiAssistantSeedData();
            
            if (isset($assoc_args['cleanup'])) {
                $seeder->cleanup();
            } else {
                $seeder->createAll();
            }
        });
    }
} elseif (defined('ABSPATH') && is_admin()) {
    // WordPress admin execution
    if (isset($_GET['woo_ai_seed_data']) && current_user_can('manage_options')) {
        $seeder = new WooAiAssistantSeedData();
        
        if ($_GET['woo_ai_seed_data'] === 'cleanup') {
            $seeder->cleanup();
        } else {
            $seeder->createAll();
        }
        
        wp_redirect(admin_url('admin.php?page=woo-ai-assistant'));
        exit;
    }
}