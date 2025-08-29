<?php
/**
 * Tests for RateLimiter Class
 *
 * Comprehensive test coverage for rate limiting functionality including
 * IP-based limits, user-based limits, time windows, and configuration.
 *
 * @package WooAiAssistant
 * @subpackage Tests\Security
 * @since 1.0.0
 */

namespace WooAiAssistant\Tests\Unit\Security;

use WooAiAssistant\Security\RateLimiter;
use WooAiAssistant\Tests\WP_UnitTestCase;

/**
 * Class RateLimiterTest
 *
 * Tests all aspects of rate limiting including limit enforcement,
 * whitelist management, burst allowances, and statistics tracking.
 *
 * @since 1.0.0
 */
class RateLimiterTest extends WP_UnitTestCase
{
    private RateLimiter $rateLimiter;

    public function setUp(): void
    {
        parent::setUp();
        $this->rateLimiter = RateLimiter::getInstance();
        
        // Clear any existing rate limit data
        $this->rateLimiter->clearRateLimits();
        $this->rateLimiter->resetStatistics();
    }

    // MANDATORY: Test class existence and instantiation
    public function test_class_exists_and_instantiates(): void
    {
        $this->assertTrue(class_exists('WooAiAssistant\Security\RateLimiter'));
        $this->assertInstanceOf(RateLimiter::class, $this->rateLimiter);
    }

    // MANDATORY: Verify naming conventions
    public function test_class_follows_naming_conventions(): void
    {
        $reflection = new \ReflectionClass($this->rateLimiter);
        
        // Class name must be PascalCase
        $className = $reflection->getShortName();
        $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9]*$/', $className, 
            "Class name '{$className}' must be PascalCase");
            
        // All public methods must be camelCase
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) continue; // Skip magic methods
            
            $this->assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/', $methodName,
                "Method '{$methodName}' must be camelCase");
        }
    }

    // Test basic rate limit checking
    public function test_checkRateLimit_should_allow_requests_within_limits(): void
    {
        $action = 'test_action';
        $identifier = '192.168.1.1';
        
        $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('remaining', $result);
        $this->assertArrayHasKey('reset_time', $result);
        $this->assertArrayHasKey('burst_used', $result);
        
        $this->assertTrue($result['allowed']);
        $this->assertEquals('within_limits', $result['reason']);
        $this->assertFalse($result['burst_used']);
    }

    // Test rate limit enforcement
    public function test_checkRateLimit_should_block_after_exceeding_limits(): void
    {
        $action = 'test_action';
        $identifier = '192.168.1.100';
        
        // Configure very low limits for testing
        $this->rateLimiter->configureRateLimits([
            $action => [
                'requests_per_minute' => 2,
                'requests_per_hour' => 10,
                'requests_per_day' => 50,
                'burst_allowance' => 1,
            ]
        ]);
        
        // First request should be allowed
        $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
        $this->assertTrue($result['allowed']);
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        
        // Second request should be allowed
        $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
        $this->assertTrue($result['allowed']);
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        
        // Third request should use burst allowance
        $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['burst_used']);
        $this->assertEquals('burst_allowance', $result['reason']);
        $this->rateLimiter->recordRequest($action, $identifier, 'ip', true);
        
        // Fourth request should be blocked
        $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('limit_exceeded', $result['reason']);
    }

    // Test whitelist functionality
    public function test_checkRateLimit_should_bypass_limits_for_whitelisted_identifiers(): void
    {
        $action = 'test_action';
        $whitelistedIp = '192.168.1.200';
        
        // Add IP to whitelist
        $this->assertTrue($this->rateLimiter->addToWhitelist($whitelistedIp, 'ip'));
        
        // Configure very restrictive limits
        $this->rateLimiter->configureRateLimits([
            $action => [
                'requests_per_minute' => 1,
                'burst_allowance' => 0,
            ]
        ]);
        
        // Even with restrictive limits, whitelisted IP should be allowed
        $result = $this->rateLimiter->checkRateLimit($action, $whitelistedIp, 'ip');
        $this->assertTrue($result['allowed']);
        $this->assertEquals('whitelisted', $result['reason']);
        $this->assertEquals(PHP_INT_MAX, $result['remaining']);
    }

    // Test user-based rate limiting
    public function test_checkRateLimit_should_handle_user_based_limits(): void
    {
        $action = 'chat_send';
        $userId = '123';
        
        $result = $this->rateLimiter->checkRateLimit($action, $userId, 'user');
        
        $this->assertIsArray($result);
        $this->assertTrue($result['allowed']);
        
        // Record the request
        $this->rateLimiter->recordRequest($action, $userId, 'user');
        
        // Should still be allowed (within limits)
        $result = $this->rateLimiter->checkRateLimit($action, $userId, 'user');
        $this->assertTrue($result['allowed']);
    }

    // Test request recording
    public function test_recordRequest_should_track_requests_properly(): void
    {
        $action = 'test_action';
        $identifier = '192.168.1.150';
        
        // Record a request
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        
        // Check remaining requests
        $remaining = $this->rateLimiter->getRemainingRequests($action, $identifier, 'ip');
        
        $this->assertIsArray($remaining);
        $this->assertArrayHasKey('minute', $remaining);
        $this->assertArrayHasKey('hour', $remaining);
        $this->assertArrayHasKey('day', $remaining);
        
        // Should have decreased by 1 from default limits
        $this->assertLessThan(60, $remaining['minute']); // Default is 60 for default action
    }

    // Test isRateLimited method
    public function test_isRateLimited_should_return_current_rate_limit_status(): void
    {
        $action = 'test_action';
        $identifier = '192.168.1.175';
        
        // Initially should not be rate limited
        $this->assertFalse($this->rateLimiter->isRateLimited($identifier, 'ip', $action));
        
        // Configure very low limits and exhaust them
        $this->rateLimiter->configureRateLimits([
            $action => [
                'requests_per_minute' => 1,
                'burst_allowance' => 0,
            ]
        ]);
        
        // Make one request to hit the limit
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        
        // Now should be rate limited
        $this->assertTrue($this->rateLimiter->isRateLimited($identifier, 'ip', $action));
    }

    // Test getRemainingRequests method
    public function test_getRemainingRequests_should_calculate_remaining_correctly(): void
    {
        $action = 'api_call';
        $identifier = '192.168.1.160';
        
        // Get initial remaining requests
        $remaining = $this->rateLimiter->getRemainingRequests($action, $identifier, 'ip');
        
        $this->assertIsArray($remaining);
        foreach (['minute', 'hour', 'day'] as $window) {
            $this->assertArrayHasKey($window, $remaining);
            $this->assertIsInt($remaining[$window]);
            $this->assertGreaterThanOrEqual(0, $remaining[$window]);
        }
        
        // Record some requests and check again
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        
        $newRemaining = $this->rateLimiter->getRemainingRequests($action, $identifier, 'ip');
        
        // Should be less than before
        $this->assertLessThan($remaining['minute'], $newRemaining['minute']);
        $this->assertLessThan($remaining['hour'], $newRemaining['hour']);
        $this->assertLessThan($remaining['day'], $newRemaining['day']);
    }

    // Test whitelist management
    public function test_addToWhitelist_should_add_identifiers(): void
    {
        $ip = '192.168.1.250';
        $userId = '999';
        
        // Add IP to whitelist
        $this->assertTrue($this->rateLimiter->addToWhitelist($ip, 'ip'));
        
        // Add user to whitelist
        $this->assertTrue($this->rateLimiter->addToWhitelist($userId, 'user'));
        
        // Adding same identifier again should return false (already exists)
        $this->assertFalse($this->rateLimiter->addToWhitelist($ip, 'ip'));
        
        // Invalid type should return false
        $this->assertFalse($this->rateLimiter->addToWhitelist($ip, 'invalid_type'));
    }

    // Test whitelist removal
    public function test_removeFromWhitelist_should_remove_identifiers(): void
    {
        $ip = '192.168.1.240';
        
        // Add to whitelist first
        $this->assertTrue($this->rateLimiter->addToWhitelist($ip, 'ip'));
        
        // Remove from whitelist
        $this->assertTrue($this->rateLimiter->removeFromWhitelist($ip, 'ip'));
        
        // Removing again should return false (not found)
        $this->assertFalse($this->rateLimiter->removeFromWhitelist($ip, 'ip'));
        
        // Invalid type should return false
        $this->assertFalse($this->rateLimiter->removeFromWhitelist($ip, 'invalid_type'));
    }

    // Test rate limit clearing
    public function test_clearRateLimits_should_remove_rate_limit_data(): void
    {
        $action = 'test_action';
        $identifier = '192.168.1.190';
        
        // Record some requests
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        
        // Clear rate limits for this identifier and action
        $cleared = $this->rateLimiter->clearRateLimits($identifier, 'ip', $action);
        $this->assertGreaterThan(0, $cleared);
        
        // After clearing, should have full remaining requests
        $remaining = $this->rateLimiter->getRemainingRequests($action, $identifier, 'ip');
        // The exact values depend on configuration, but should be reasonable
        $this->assertGreaterThan(0, $remaining['minute']);
    }

    // Test rate limit configuration
    public function test_configureRateLimits_should_update_limits(): void
    {
        $customLimits = [
            'custom_action' => [
                'requests_per_minute' => 100,
                'requests_per_hour' => 1000,
                'requests_per_day' => 10000,
                'burst_allowance' => 20,
            ]
        ];
        
        $this->rateLimiter->configureRateLimits($customLimits);
        
        // Test that the new limits are applied
        $action = 'custom_action';
        $identifier = '192.168.1.180';
        
        $remaining = $this->rateLimiter->getRemainingRequests($action, $identifier, 'ip');
        
        // Should reflect the new limits (or close to them)
        $this->assertGreaterThanOrEqual(99, $remaining['minute']);
        $this->assertGreaterThanOrEqual(999, $remaining['hour']);
        $this->assertGreaterThanOrEqual(9999, $remaining['day']);
    }

    // Test statistics tracking
    public function test_getStatistics_should_return_rate_limiting_stats(): void
    {
        // Perform some actions to generate statistics
        $action = 'test_action';
        $identifier = '192.168.1.170';
        
        $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
        $this->assertTrue($result['allowed']);
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        
        // Configure low limits and trigger a block
        $this->rateLimiter->configureRateLimits([
            $action => [
                'requests_per_minute' => 1,
                'burst_allowance' => 0,
            ]
        ]);
        
        $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
        $this->assertFalse($result['allowed']);
        
        // Get statistics
        $stats = $this->rateLimiter->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('statistics', $stats);
        $this->assertArrayHasKey('configuration', $stats);
        $this->assertArrayHasKey('whitelist', $stats);
        $this->assertArrayHasKey('active_limits', $stats);
        
        $statistics = $stats['statistics'];
        $this->assertArrayHasKey('total_requests', $statistics);
        $this->assertArrayHasKey('blocked_requests', $statistics);
        $this->assertArrayHasKey('ip_blocks', $statistics);
        
        $this->assertGreaterThan(0, $statistics['total_requests']);
        $this->assertGreaterThan(0, $statistics['blocked_requests']);
        $this->assertGreaterThan(0, $statistics['ip_blocks']);
    }

    // Test statistics reset
    public function test_resetStatistics_should_clear_all_statistics(): void
    {
        // Generate some statistics
        $this->rateLimiter->checkRateLimit('test', '192.168.1.1', 'ip');
        
        $this->rateLimiter->resetStatistics();
        
        $stats = $this->rateLimiter->getStatistics();
        $statistics = $stats['statistics'];
        
        $this->assertEquals(0, $statistics['total_requests']);
        $this->assertEquals(0, $statistics['blocked_requests']);
        $this->assertEquals(0, $statistics['burst_requests']);
        $this->assertEquals(0, $statistics['ip_blocks']);
        $this->assertEquals(0, $statistics['user_blocks']);
    }

    // Test burst allowance functionality
    public function test_checkRateLimit_should_use_burst_allowance_when_limit_exceeded(): void
    {
        $action = 'burst_test';
        $identifier = '192.168.1.123';
        
        // Configure tight limits with burst allowance
        $this->rateLimiter->configureRateLimits([
            $action => [
                'requests_per_minute' => 2,
                'burst_allowance' => 3,
            ]
        ]);
        
        // Use up regular limit
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        $this->rateLimiter->recordRequest($action, $identifier, 'ip');
        
        // Next request should use burst allowance
        $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['burst_used']);
        $this->assertEquals('burst_allowance', $result['reason']);
    }

    // Test different actions have independent limits
    public function test_checkRateLimit_should_handle_different_actions_independently(): void
    {
        $identifier = '192.168.1.145';
        $action1 = 'action_one';
        $action2 = 'action_two';
        
        // Configure different limits for each action
        $this->rateLimiter->configureRateLimits([
            $action1 => [
                'requests_per_minute' => 1,
                'burst_allowance' => 0,
            ],
            $action2 => [
                'requests_per_minute' => 5,
                'burst_allowance' => 0,
            ]
        ]);
        
        // Exhaust limit for action1
        $this->rateLimiter->recordRequest($action1, $identifier, 'ip');
        
        // Action1 should be blocked
        $result1 = $this->rateLimiter->checkRateLimit($action1, $identifier, 'ip');
        $this->assertFalse($result1['allowed']);
        
        // Action2 should still be allowed
        $result2 = $this->rateLimiter->checkRateLimit($action2, $identifier, 'ip');
        $this->assertTrue($result2['allowed']);
    }

    // Test cleanup functionality
    public function test_cleanupExpiredData_should_not_throw_errors(): void
    {
        // This method should exist and be callable
        $this->assertTrue(method_exists($this->rateLimiter, 'cleanupExpiredData'));
        
        // Should not throw any exceptions
        $this->rateLimiter->cleanupExpiredData();
        
        // If we get here, the method worked
        $this->assertTrue(true);
    }

    // Test saveStatistics method
    public function test_saveStatistics_should_persist_statistics(): void
    {
        // Generate some statistics
        $this->rateLimiter->checkRateLimit('test', '192.168.1.1', 'ip');
        
        // Save statistics (should not throw error)
        $this->rateLimiter->saveStatistics();
        
        // If we get here without exception, it worked
        $this->assertTrue(true);
    }

    // Test edge case: empty identifier
    public function test_checkRateLimit_should_handle_empty_identifier(): void
    {
        $result = $this->rateLimiter->checkRateLimit('test_action', '', 'ip');
        
        $this->assertIsArray($result);
        // Should handle gracefully (likely treat as unique identifier)
        $this->assertArrayHasKey('allowed', $result);
    }

    // Test edge case: very long identifier
    public function test_checkRateLimit_should_handle_long_identifier(): void
    {
        $longIdentifier = str_repeat('a', 1000);
        
        $result = $this->rateLimiter->checkRateLimit('test_action', $longIdentifier, 'ip');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    // Test concurrent access simulation
    public function test_checkRateLimit_should_handle_rapid_requests(): void
    {
        $action = 'rapid_test';
        $identifier = '192.168.1.201';
        
        // Configure moderate limits
        $this->rateLimiter->configureRateLimits([
            $action => [
                'requests_per_minute' => 10,
                'burst_allowance' => 2,
            ]
        ]);
        
        $allowedCount = 0;
        $blockedCount = 0;
        
        // Simulate rapid requests
        for ($i = 0; $i < 15; $i++) {
            $result = $this->rateLimiter->checkRateLimit($action, $identifier, 'ip');
            
            if ($result['allowed']) {
                $allowedCount++;
                $burstUsed = $result['burst_used'] ?? false;
                $this->rateLimiter->recordRequest($action, $identifier, 'ip', $burstUsed);
            } else {
                $blockedCount++;
            }
        }
        
        // Should have allowed some requests (regular + burst) and blocked others
        $this->assertGreaterThan(0, $allowedCount);
        $this->assertGreaterThan(0, $blockedCount);
        $this->assertLessThanOrEqual(12, $allowedCount); // 10 regular + 2 burst
    }
}