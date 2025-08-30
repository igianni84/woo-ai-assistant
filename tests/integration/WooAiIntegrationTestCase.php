<?php
/**
 * Base Integration Test Case for Woo AI Assistant Plugin
 *
 * Provides common functionality for integration tests that verify
 * the plugin's interaction with WordPress, WooCommerce, and external systems.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Integration
 * @since 1.0.0
 * @author Claude Code Assistant
 */

namespace WooAiAssistant\Tests\Integration;

use WP_UnitTestCase;
use WooAiAssistant\Main;

/**
 * Class WooAiIntegrationTestCase
 *
 * Base test case for integration tests.
 * 
 * @since 1.0.0
 */
abstract class WooAiIntegrationTestCase extends WP_UnitTestCase
{
    /**
     * Plugin main instance
     *
     * @var Main|null
     */
    protected $plugin;

    /**
     * Test data storage
     *
     * @var array
     */
    protected $testData = [];

    /**
     * Set up integration test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Ensure plugin is loaded
        $this->plugin = Main::getInstance();

        // Set up integration test data
        $this->setUpIntegrationData();
    }

    /**
     * Tear down integration test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->cleanUpIntegrationData();
        parent::tearDown();
    }

    /**
     * Set up integration test data
     *
     * Override in child classes.
     *
     * @return void
     */
    protected function setUpIntegrationData(): void
    {
        // Base implementation
    }

    /**
     * Clean up integration test data
     *
     * @return void
     */
    protected function cleanUpIntegrationData(): void
    {
        // Clean up test data
        foreach ($this->testData as $type => $items) {
            switch ($type) {
                case 'posts':
                    foreach ($items as $post_id) {
                        wp_delete_post($post_id, true);
                    }
                    break;
                case 'users':
                    foreach ($items as $user_id) {
                        wp_delete_user($user_id);
                    }
                    break;
                case 'options':
                    foreach ($items as $option_name) {
                        delete_option($option_name);
                    }
                    break;
            }
        }

        $this->testData = [];
    }
}