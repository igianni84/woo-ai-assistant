<?php

/**
 * Singleton Trait
 *
 * Reusable trait for implementing the Singleton design pattern.
 * Provides a getInstance() method and prevents direct instantiation.
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
 * Provides singleton functionality for classes.
 *
 * @since 1.0.0
 */
trait Singleton
{
    /**
     * Instance storage
     *
     * @var static[]
     */
    private static array $instances = [];

    /**
     * Get singleton instance
     *
     * @return static
     */
    public static function getInstance(): static
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    /**
     * Prevent direct instantiation
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
        // Empty
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Initialize the instance
     *
     * Override this method in classes that use this trait
     * to perform initialization tasks.
     *
     * @return void
     */
    protected function init(): void
    {
        // Override in implementing classes
    }
}
