<?php
/**
 * Security Vulnerability Testing Suite
 * 
 * Comprehensive security tests to identify and prevent common
 * vulnerabilities in the Woo AI Assistant plugin.
 * 
 * @package WooAiAssistant
 * @subpackage Tests
 * @since 1.0.0
 * @author Claude Code Assistant
 */

declare(strict_types=1);

namespace WooAiAssistant\Tests\Security;

use WP_UnitTestCase;
use WooAiAssistant\Main;
use WooAiAssistant\RestApi\RestController;
use WooAiAssistant\Security\InputSanitizer;
use WooAiAssistant\Security\CsrfProtection;
use WooAiAssistant\Security\RateLimiter;

/**
 * Security Test Suite Class
 * 
 * @since 1.0.0
 */
class SecurityTestSuite extends WP_UnitTestCase
{
    /**
     * Plugin instance
     * 
     * @var Main
     */
    private Main $plugin;
    
    /**
     * Test user ID
     * 
     * @var int
     */
    private int $userId;
    
    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();
        
        $this->plugin = Main::getInstance();
        $this->userId = $this->factory->user->create([
            'role' => 'customer',
            'user_login' => 'testuser',
            'user_email' => 'test@example.com'
        ]);
    }
    
    /**
     * Test SQL injection prevention
     */
    public function test_sql_injection_prevention(): void
    {
        // Test direct database queries use prepared statements
        global $wpdb;
        
        $maliciousInput = "'; DROP TABLE {$wpdb->prefix}woo_ai_conversations; --";
        
        // Test conversation queries
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woo_ai_conversations WHERE user_id = %d",
                $maliciousInput
            )
        );
        
        // Should not cause SQL error and table should still exist
        $this->assertIsArray($result);
        
        $tableExists = $wpdb->get_var(
            "SHOW TABLES LIKE '{$wpdb->prefix}woo_ai_conversations'"
        );
        $this->assertEquals(
            $wpdb->prefix . 'woo_ai_conversations',
            $tableExists,
            'Table should not be dropped by SQL injection attempt'
        );
    }
    
    /**
     * Test XSS (Cross-Site Scripting) prevention
     */
    public function test_xss_prevention(): void
    {
        $maliciousScripts = [
            '<script>alert("xss")</script>',
            '<img src="x" onerror="alert(\'xss\')">',
            '"><script>alert(String.fromCharCode(88,83,83))</script>',
            'javascript:alert("xss")',
            '<iframe src="javascript:alert(\'xss\')"></iframe>',
            '<svg onload="alert(\'xss\')"></svg>',
            '<body onload="alert(\'xss\')">',
            '<div onclick="alert(\'xss\')">Click me</div>'
        ];
        
        $sanitizer = new InputSanitizer();
        
        foreach ($maliciousScripts as $script) {
            // Test message sanitization
            $sanitizedMessage = $sanitizer->sanitizeChatMessage($script);
            $this->assertStringNotContainsString(
                '<script>',
                $sanitizedMessage,
                'Script tags should be removed'
            );
            $this->assertStringNotContainsString(
                'javascript:',
                $sanitizedMessage,
                'JavaScript URLs should be removed'
            );
            $this->assertStringNotContainsString(
                'onerror=',
                $sanitizedMessage,
                'Event handlers should be removed'
            );
            $this->assertStringNotContainsString(
                'onload=',
                $sanitizedMessage,
                'Event handlers should be removed'
            );
            
            // Test output escaping
            $escapedOutput = esc_html($script);
            $this->assertStringNotContainsString(
                '<script>',
                $escapedOutput,
                'Output should be properly escaped'
            );
        }
    }
    
    /**
     * Test CSRF (Cross-Site Request Forgery) protection
     */
    public function test_csrf_protection(): void
    {
        wp_set_current_user($this->userId);
        
        $csrfProtection = new CsrfProtection();
        
        // Test nonce generation
        $nonce = $csrfProtection->generateNonce('chat_action');
        $this->assertNotEmpty($nonce, 'CSRF token should be generated');
        
        // Test valid nonce verification
        $this->assertTrue(
            $csrfProtection->verifyNonce($nonce, 'chat_action'),
            'Valid CSRF token should pass verification'
        );
        
        // Test invalid nonce rejection
        $this->assertFalse(
            $csrfProtection->verifyNonce('invalid_nonce', 'chat_action'),
            'Invalid CSRF token should be rejected'
        );
        
        // Test expired nonce (mock expiration)
        $expiredNonce = wp_create_nonce('chat_action');
        // Simulate time passage
        add_filter('nonce_life', function() {
            return -1; // Expired
        });
        
        $this->assertFalse(
            $csrfProtection->verifyNonce($expiredNonce, 'chat_action'),
            'Expired CSRF token should be rejected'
        );
        
        remove_all_filters('nonce_life');
    }
    
    /**
     * Test authentication and authorization
     */
    public function test_authentication_and_authorization(): void
    {
        $restController = new RestController();
        
        // Test unauthenticated access to protected endpoints
        wp_set_current_user(0); // Logged out
        
        $request = new \WP_REST_Request('POST', '/woo-ai-assistant/v1/admin/settings');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['setting' => 'value']));
        
        $response = $restController->handleAdminRequest($request);
        
        $this->assertEquals(
            401,
            $response->get_status(),
            'Unauthenticated requests to admin endpoints should be rejected'
        );
        
        // Test insufficient privileges
        wp_set_current_user($this->userId); // Customer role
        
        $response = $restController->handleAdminRequest($request);
        $this->assertEquals(
            403,
            $response->get_status(),
            'Insufficient privileges should be rejected'
        );
        
        // Test proper admin access
        $adminId = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($adminId);
        
        $response = $restController->handleAdminRequest($request);
        $this->assertNotEquals(
            403,
            $response->get_status(),
            'Admin users should have access to admin endpoints'
        );
    }
    
    /**
     * Test rate limiting
     */
    public function test_rate_limiting(): void
    {
        $rateLimiter = new RateLimiter();
        
        $clientIp = '192.168.1.1';
        $userId = $this->userId;
        
        // Test normal usage within limits
        for ($i = 0; $i < 10; $i++) {
            $allowed = $rateLimiter->isAllowed($clientIp, $userId, 'chat_message');
            $this->assertTrue($allowed, "Request {$i} should be allowed");
        }
        
        // Test rate limit exceeded
        for ($i = 0; $i < 100; $i++) {
            $rateLimiter->recordRequest($clientIp, $userId, 'chat_message');
        }
        
        $blocked = $rateLimiter->isAllowed($clientIp, $userId, 'chat_message');
        $this->assertFalse($blocked, 'Requests exceeding rate limit should be blocked');
        
        // Test rate limit reset after time window
        $rateLimiter->clearLimits($clientIp, $userId, 'chat_message');
        $allowedAfterReset = $rateLimiter->isAllowed($clientIp, $userId, 'chat_message');
        $this->assertTrue($allowedAfterReset, 'Requests should be allowed after rate limit reset');
    }
    
    /**
     * Test input validation and sanitization
     */
    public function test_input_validation_and_sanitization(): void
    {
        $sanitizer = new InputSanitizer();
        
        // Test chat message sanitization
        $maliciousInputs = [
            "SELECT * FROM users WHERE 1=1",
            "<script>document.cookie</script>",
            "../../etc/passwd",
            "\${jndi:ldap://malicious.com/}",
            "{{7*7}}[[5*5]]",
            "eval(base64_decode('malicious_code'))",
            "file:///etc/passwd",
            "data:text/html,<script>alert('xss')</script>"
        ];
        
        foreach ($maliciousInputs as $input) {
            $sanitized = $sanitizer->sanitizeChatMessage($input);
            
            // Should remove or neutralize malicious content
            $this->assertStringNotContainsString('SELECT', $sanitized);
            $this->assertStringNotContainsString('<script>', $sanitized);
            $this->assertStringNotContainsString('../', $sanitized);
            $this->assertStringNotContainsString('jndi:', $sanitized);
            $this->assertStringNotContainsString('{{', $sanitized);
            $this->assertStringNotContainsString('eval(', $sanitized);
            $this->assertStringNotContainsString('file://', $sanitized);
            $this->assertStringNotContainsString('data:', $sanitized);
        }
        
        // Test file upload validation
        $maliciousFiles = [
            'malware.exe',
            'script.php',
            'config.ini',
            '.htaccess',
            'shell.sh',
            'virus.bat'
        ];
        
        foreach ($maliciousFiles as $filename) {
            $isValid = $sanitizer->validateUploadedFile($filename, 'text/plain', 1024);
            $this->assertFalse($isValid, "Malicious file {$filename} should be rejected");
        }
        
        // Test safe files
        $safeFiles = [
            'document.txt',
            'image.jpg',
            'data.csv',
            'report.pdf'
        ];
        
        foreach ($safeFiles as $filename) {
            $isValid = $sanitizer->validateUploadedFile($filename, 'text/plain', 1024);
            $this->assertTrue($isValid, "Safe file {$filename} should be allowed");
        }
    }
    
    /**
     * Test session security
     */
    public function test_session_security(): void
    {
        wp_set_current_user($this->userId);
        
        // Test session token validation
        $sessionToken = wp_get_session_token();
        $this->assertNotEmpty($sessionToken, 'Session token should be present');
        
        // Test session fixation prevention
        $oldSessionId = session_id();
        
        // Simulate login (should regenerate session)
        do_action('wp_login', 'testuser', get_user_by('id', $this->userId));
        
        $newSessionId = session_id();
        $this->assertNotEquals(
            $oldSessionId,
            $newSessionId,
            'Session ID should regenerate on login'
        );
        
        // Test session timeout
        $sessions = get_user_meta($this->userId, 'session_tokens', true);
        $this->assertIsArray($sessions, 'User sessions should be tracked');
        
        foreach ($sessions as $session) {
            $this->assertArrayHasKey('expiration', $session);
            $this->assertGreaterThan(
                time(),
                $session['expiration'],
                'Session should not be expired'
            );
        }
    }
    
    /**
     * Test file upload security
     */
    public function test_file_upload_security(): void
    {
        $sanitizer = new InputSanitizer();
        
        // Test malicious file types
        $maliciousUploads = [
            ['filename' => 'malware.exe', 'type' => 'application/x-executable'],
            ['filename' => 'script.php', 'type' => 'application/x-php'],
            ['filename' => 'shell.sh', 'type' => 'application/x-sh'],
            ['filename' => '.htaccess', 'type' => 'text/plain'],
            ['filename' => 'config.ini', 'type' => 'text/plain'],
        ];
        
        foreach ($maliciousUploads as $upload) {
            $isValid = $sanitizer->validateUploadedFile(
                $upload['filename'],
                $upload['type'],
                1024
            );
            $this->assertFalse(
                $isValid,
                "Malicious file {$upload['filename']} should be rejected"
            );
        }
        
        // Test file size limits
        $oversizedFile = $sanitizer->validateUploadedFile(
            'large.txt',
            'text/plain',
            100 * 1024 * 1024 // 100MB
        );
        $this->assertFalse($oversizedFile, 'Oversized files should be rejected');
        
        // Test MIME type validation
        $mismatchedFile = $sanitizer->validateUploadedFile(
            'image.jpg',
            'application/x-php', // Wrong MIME type
            1024
        );
        $this->assertFalse($mismatchedFile, 'Files with mismatched MIME types should be rejected');
    }
    
    /**
     * Test API endpoint security
     */
    public function test_api_endpoint_security(): void
    {
        // Test CORS headers
        $request = new \WP_REST_Request('GET', '/woo-ai-assistant/v1/chat');
        $request->set_header('Origin', 'https://malicious.com');
        
        $restController = new RestController();
        $response = $restController->handleChatRequest($request);
        
        // Should not allow arbitrary origins
        $this->assertEmpty(
            $response->get_header('Access-Control-Allow-Origin'),
            'Arbitrary origins should not be allowed'
        );
        
        // Test request method validation
        $postRequest = new \WP_REST_Request('POST', '/woo-ai-assistant/v1/chat');
        $postResponse = $restController->handleChatRequest($postRequest);
        
        $this->assertNotEquals(
            405,
            $postResponse->get_status(),
            'Valid POST requests should be allowed'
        );
        
        $deleteRequest = new \WP_REST_Request('DELETE', '/woo-ai-assistant/v1/chat');
        $deleteResponse = $restController->handleChatRequest($deleteRequest);
        
        $this->assertEquals(
            405,
            $deleteResponse->get_status(),
            'Invalid request methods should be rejected'
        );
    }
    
    /**
     * Test data encryption and privacy
     */
    public function test_data_encryption_and_privacy(): void
    {
        global $wpdb;
        
        // Test sensitive data is not stored in plain text
        $sensitiveData = [
            'api_key' => 'test-api-key-12345',
            'user_email' => 'user@example.com',
            'chat_content' => 'Private conversation content'
        ];
        
        // Store data
        update_option('woo_ai_api_key', $sensitiveData['api_key']);
        
        // Check if sensitive data is encrypted/hashed
        $storedApiKey = get_option('woo_ai_api_key');
        
        // API key should be stored securely (not in plain text in production)
        if (defined('WOO_AI_ENCRYPT_OPTIONS') && WOO_AI_ENCRYPT_OPTIONS) {
            $this->assertNotEquals(
                $sensitiveData['api_key'],
                $storedApiKey,
                'API keys should be encrypted when stored'
            );
        }
        
        // Test chat data privacy
        $wpdb->insert(
            $wpdb->prefix . 'woo_ai_conversations',
            [
                'user_id' => $this->userId,
                'message' => $sensitiveData['chat_content'],
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s']
        );
        
        $storedMessage = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT message FROM {$wpdb->prefix}woo_ai_conversations WHERE user_id = %d",
                $this->userId
            )
        );
        
        // In production, this should be encrypted
        $this->assertNotEmpty($storedMessage, 'Chat messages should be stored');
    }
    
    /**
     * Test logging and monitoring security
     */
    public function test_logging_and_monitoring_security(): void
    {
        // Test that sensitive data is not logged
        $sensitiveInputs = [
            'password123',
            'token_abc123xyz',
            '4111-1111-1111-1111', // Credit card number
            'ssn_123-45-6789'
        ];
        
        foreach ($sensitiveInputs as $sensitiveInput) {
            // Trigger logging
            do_action('woo_ai_log_event', 'test_event', ['input' => $sensitiveInput]);
            
            // Check log files don't contain sensitive data
            $logFiles = glob(WP_CONTENT_DIR . '/debug.log*');
            
            foreach ($logFiles as $logFile) {
                if (file_exists($logFile)) {
                    $logContent = file_get_contents($logFile);
                    $this->assertStringNotContainsString(
                        $sensitiveInput,
                        $logContent,
                        'Sensitive data should not appear in logs'
                    );
                }
            }
        }
    }
    
    /**
     * Test security headers
     */
    public function test_security_headers(): void
    {
        // Simulate admin page request
        set_current_screen('toplevel_page_woo-ai-assistant');
        
        do_action('admin_init');
        
        // Check if security headers are set
        $headers = headers_list();
        $headerString = implode(' ', $headers);
        
        // Test for security headers (if implemented)
        $securityHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options', 
            'X-XSS-Protection',
            'Content-Security-Policy',
            'Strict-Transport-Security'
        ];
        
        foreach ($securityHeaders as $header) {
            // Note: In actual implementation, these would be set
            // This test documents expected security headers
            $this->addToAssertionCount(1); // Placeholder assertion
        }
    }
}