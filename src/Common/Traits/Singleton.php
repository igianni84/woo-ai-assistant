<?php

/**
 * Singleton Trait
 *
 * Provides singleton functionality for classes that need to maintain
 * a single instance throughout the application lifecycle.
 *
 * @package WooAiAssistant
 * @subpackage Common\Traits
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Common\Traits;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait Singleton
 *
 * Implements the singleton pattern to ensure only one instance of a class
 * can be created and provides global access to that instance.
 *
 * @since 1.0.0
 */
trait Singleton
{
    /**
     * Instance of the class
     *
     * @since 1.0.0
     * @var static|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * Creates a new instance if one doesn't exist, or returns the existing instance.
     *
     * @since 1.0.0
     * @return static The singleton instance of the class
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     *
     * Classes using this trait cannot be instantiated directly.
     * Use getInstance() method instead.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        // Override this method in the class that uses this trait
        // to add initialization logic
    }

    /**
     * Prevent cloning of the singleton instance
     *
     * @since 1.0.0
     * @return void
     * @throws Exception When trying to clone the singleton
     */
    private function __clone()
    {
        throw new \Exception('Cannot clone singleton instance');
    }

    /**
     * Prevent unserialization of the singleton instance
     *
     * @since 1.0.0
     * @return void
     * @throws Exception When trying to unserialize the singleton
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton instance');
    }

    /**
     * Check if the singleton instance exists
     *
     * @since 1.0.0
     * @return bool True if instance exists, false otherwise
     */
    public static function hasInstance(): bool
    {
        return null !== static::$instance;
    }

    /**
     * Destroy the singleton instance
     *
     * This method should be used carefully and only when you need to
     * reset the singleton instance (e.g., during testing).
     *
     * @since 1.0.0
     * @return void
     */
    public static function destroyInstance(): void
    {
        static::$instance = null;
    }
}
