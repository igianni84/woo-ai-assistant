<?php

/**
 * Prompt Defense Class
 *
 * Provides comprehensive defense against prompt injection attacks in AI
 * interactions. Implements pattern detection, content filtering, and
 * sophisticated attack vector prevention for LLM-powered features.
 *
 * @package WooAiAssistant
 * @subpackage Security
 * @since 1.0.0
 * @author Claude Code Assistant
 * @link https://github.com/woo-ai-assistant/woo-ai-assistant
 */

namespace WooAiAssistant\Security;

use WooAiAssistant\Common\Traits\Singleton;
use WooAiAssistant\Common\Utils;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PromptDefense
 *
 * Advanced prompt injection defense system that analyzes user input for
 * potential prompt injection attacks, jailbreak attempts, and malicious
 * instructions targeting AI models. Provides multiple layers of protection.
 *
 * @since 1.0.0
 */
class PromptDefense
{
    use Singleton;

    /**
     * Risk levels for prompt classification
     *
     * @since 1.0.0
     * @var array
     */
    private array $riskLevels = [
        'safe' => 0,
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    /**
     * Direct instruction patterns
     *
     * @since 1.0.0
     * @var array
     */
    private array $instructionPatterns = [
        '/(?:ignore|forget|disregard)\s+(?:previous|all\s+previous|your\s+previous)\s+(?:instructions?|prompts?|rules?|guidelines?)/i',
        '/(?:system|assistant|ai|model)\s*:\s*.+(?:ignore|override|change)/i',
        '/you\s+are\s+(?:no\s+longer|not)\s+(?:bound\s+by|constrained\s+by|limited\s+by)/i',
        '/(?:act\s+as|pretend\s+to\s+be|roleplay\s+as)\s+(?:a\s+)?(?:malicious|evil|harmful|jailbroken)/i',
        '/your\s+(?:safety|ethical?)\s+(?:guidelines?|constraints?|limitations?)\s+(?:are|have\s+been)\s+(?:disabled|removed|overridden)/i',
        '/(?:enable|activate|turn\s+on)\s+(?:developer|debug|admin|god)\s+mode/i',
        '/(?:jailbreak|DAN|do\s+anything\s+now|unrestricted\s+mode)/i',
        '/(?:override|bypass|disable)\s+(?:safety|content\s+policy|restrictions?)/i',
    ];

    /**
     * Role manipulation patterns
     *
     * @since 1.0.0
     * @var array
     */
    private array $rolePatterns = [
        '/(?:you|your\s+role)\s+(?:is|are)\s+(?:no\s+longer|not|now)\s+(?:an?\s+)?(?:assistant|ai|chatbot|language\s+model)/i',
        '/(?:pretend|act|behave)\s+(?:like|as\s+if)\s+you\s+(?:are|were)\s+(?:a\s+)?(?:human|person|developer|administrator)/i',
        '/(?:roleplay|role-play|rp)\s+(?:as|being)\s+(?:a\s+)?(?:unrestricted|uncensored|jailbroken)/i',
        '/from\s+now\s+on,?\s+you\s+(?:will|shall|must)\s+(?:act|behave|respond)\s+as/i',
        '/(?:take\s+on\s+the\s+role|assume\s+the\s+identity)\s+of\s+(?:a\s+)?(?:malicious|harmful)/i',
    ];

    /**
     * System prompt manipulation patterns
     *
     * @since 1.0.0
     * @var array
     */
    private array $systemPatterns = [
        '/(?:\[?system\]?|\<system\>|##\s*system)\s*:?\s*.+/i',
        '/(?:\[?user\]?|\<user\>|##\s*user)\s*:?\s*.+(?:ignore|override|disable)/i',
        '/(?:\[?assistant\]?|\<assistant\>|##\s*assistant)\s*:?\s*.+/i',
        '/(?:start\s+of\s+conversation|beginning\s+of\s+chat|new\s+session)\s*:?\s*.+(?:instructions?|rules?)/i',
        '/(?:hidden|secret|private)\s+(?:instructions?|prompts?|commands?)/i',
    ];

    /**
     * Code injection patterns
     *
     * @since 1.0.0
     * @var array
     */
    private array $codePatterns = [
        '/```(?:python|javascript|php|sql|bash|shell)\s*.+?```/si',
        '/(?:eval|exec|system|shell_exec|passthru)\s*\(/i',
        '/<script[^>]*>.*?<\/script>/si',
        '/(?:import|require|include)\s+(?:os|sys|subprocess|requests)/i',
        '/(?:DROP|DELETE|INSERT|UPDATE|SELECT)\s+(?:TABLE|FROM|INTO)/i',
    ];

    /**
     * Encoding/obfuscation patterns
     *
     * @since 1.0.0
     * @var array
     */
    private array $obfuscationPatterns = [
        '/(?:base64|hex|url|html)\s*(?:encode|decode)/i',
        '/\\\\x[0-9a-f]{2}/i',
        '/\\\\u[0-9a-f]{4}/i',
        '/(?:%[0-9a-f]{2}){3,}/i',
        '/(?:[a-z0-9+\/]{20,}={0,2})/i', // Potential base64
    ];

    /**
     * Malicious intent keywords
     *
     * @since 1.0.0
     * @var array
     */
    private array $maliciousKeywords = [
        'bypass', 'override', 'jailbreak', 'exploit', 'vulnerability',
        'hack', 'crack', 'disable safety', 'remove restrictions',
        'ignore rules', 'break free', 'unrestricted', 'uncensored',
        'developer mode', 'god mode', 'admin access', 'root privileges',
        'system prompt', 'hidden instructions', 'secret commands',
    ];

    /**
     * Detection statistics
     *
     * @since 1.0.0
     * @var array
     */
    private array $statistics = [
        'prompts_analyzed' => 0,
        'threats_detected' => 0,
        'injections_blocked' => 0,
        'false_positives' => 0,
        'threat_levels' => [
            'safe' => 0,
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'critical' => 0,
        ],
    ];

    /**
     * Constructor
     *
     * Initializes the prompt defense system with pattern matching rules
     * and detection algorithms.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->loadConfiguration();
        $this->loadStatistics();
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * Registers hooks for automatic threat detection and logging.
     *
     * @since 1.0.0
     * @return void
     */
    private function setupHooks(): void
    {
        // Hook into chat input processing
        add_filter('woo_ai_assistant_before_chat_process', [$this, 'analyzePromptSafety'], 10, 2);

        // Save statistics on shutdown
        add_action('shutdown', [$this, 'saveStatistics']);

        // Handle detected threats
        add_action('woo_ai_assistant_prompt_threat_detected', [$this, 'handleDetectedThreat'], 10, 3);
    }

    /**
     * Analyze prompt safety
     *
     * Comprehensive analysis of user input to detect potential prompt
     * injection attempts and other security threats.
     *
     * @since 1.0.0
     * @param string $prompt User input to analyze
     * @param array $context Additional context for analysis
     * @return array Analysis results with threat level and details
     *
     * @example
     * ```php
     * $defense = PromptDefense::getInstance();
     * $result = $defense->analyzePromptSafety("Ignore all previous instructions");
     * if ($result['risk_level'] > 2) {
     *     // Handle high-risk prompt
     * }
     * ```
     */
    public function analyzePromptSafety(string $prompt, array $context = []): array
    {
        $this->statistics['prompts_analyzed']++;

        // Initialize analysis result
        $result = [
            'original_prompt' => $prompt,
            'risk_level' => $this->riskLevels['safe'],
            'risk_label' => 'safe',
            'threats_detected' => [],
            'confidence' => 0.0,
            'sanitized_prompt' => $prompt,
            'should_block' => false,
            'recommendations' => [],
        ];

        // Perform multi-layer analysis
        $this->analyzeDirectInstructions($prompt, $result);
        $this->analyzeRoleManipulation($prompt, $result);
        $this->analyzeSystemPrompts($prompt, $result);
        $this->analyzeCodeInjection($prompt, $result);
        $this->analyzeObfuscation($prompt, $result);
        $this->analyzeMaliciousKeywords($prompt, $result);
        $this->analyzeStructuralPatterns($prompt, $result);
        $this->calculateOverallRisk($result);

        // Log analysis if threats detected
        if ($result['risk_level'] > $this->riskLevels['safe']) {
            $this->statistics['threats_detected']++;
            $this->statistics['threat_levels'][$result['risk_label']]++;

            Utils::logError('Prompt injection threat detected', [
                'risk_level' => $result['risk_label'],
                'threats' => $result['threats_detected'],
                'confidence' => $result['confidence'],
                'prompt_length' => strlen($prompt),
            ]);

            // Trigger threat detection action
            do_action('woo_ai_assistant_prompt_threat_detected', $prompt, $result, $context);
        } else {
            $this->statistics['threat_levels']['safe']++;
        }

        return $result;
    }

    /**
     * Sanitize dangerous prompt
     *
     * Attempts to sanitize a potentially dangerous prompt by removing
     * or neutralizing malicious elements.
     *
     * @since 1.0.0
     * @param string $prompt Original prompt
     * @param array $threats Detected threats
     * @return string Sanitized prompt
     */
    public function sanitizeDangerousPrompt(string $prompt, array $threats = []): string
    {
        $sanitized = $prompt;

        // Remove direct instruction patterns
        foreach ($this->instructionPatterns as $pattern) {
            $sanitized = preg_replace($pattern, '[INSTRUCTION_FILTERED]', $sanitized);
        }

        // Remove role manipulation attempts
        foreach ($this->rolePatterns as $pattern) {
            $sanitized = preg_replace($pattern, '[ROLE_FILTERED]', $sanitized);
        }

        // Remove system prompt markers
        foreach ($this->systemPatterns as $pattern) {
            $sanitized = preg_replace($pattern, '[SYSTEM_FILTERED]', $sanitized);
        }

        // Remove code injection attempts
        foreach ($this->codePatterns as $pattern) {
            $sanitized = preg_replace($pattern, '[CODE_FILTERED]', $sanitized);
        }

        // Remove obfuscated content
        foreach ($this->obfuscationPatterns as $pattern) {
            $sanitized = preg_replace($pattern, '[OBFUSCATED_FILTERED]', $sanitized);
        }

        // Filter malicious keywords
        foreach ($this->maliciousKeywords as $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
            $sanitized = preg_replace($pattern, '[KEYWORD_FILTERED]', $sanitized);
        }

        // Clean up multiple filtered markers
        $sanitized = preg_replace('/\[(?:INSTRUCTION|ROLE|SYSTEM|CODE|OBFUSCATED|KEYWORD)_FILTERED\]\s*/i', '', $sanitized);

        // Trim and clean whitespace
        $sanitized = trim(preg_replace('/\s+/', ' ', $sanitized));

        return $sanitized;
    }

    /**
     * Check if prompt should be blocked
     *
     * Determines if a prompt should be completely blocked based on
     * risk assessment and threat analysis.
     *
     * @since 1.0.0
     * @param array $analysisResult Analysis result from analyzePromptSafety
     * @return bool True if prompt should be blocked
     */
    public function shouldBlockPrompt(array $analysisResult): bool
    {
        // Block critical threats
        if ($analysisResult['risk_level'] >= $this->riskLevels['critical']) {
            return true;
        }

        // Block high-risk prompts with high confidence
        if (
            $analysisResult['risk_level'] >= $this->riskLevels['high'] &&
            $analysisResult['confidence'] > 0.8
        ) {
            return true;
        }

        // Block if multiple threat types detected
        if (count($analysisResult['threats_detected']) >= 3) {
            return true;
        }

        return false;
    }

    /**
     * Generate safe response
     *
     * Generates a safe, informative response when a prompt injection
     * is detected and blocked.
     *
     * @since 1.0.0
     * @param array $analysisResult Analysis result
     * @return string Safe response message
     */
    public function generateSafeResponse(array $analysisResult): string
    {
        $responses = [
            "I'm designed to help with WooCommerce-related questions. Could you please rephrase your request to focus on products, orders, or store information?",
            "I notice your message contains some unusual formatting. Could you please ask your question in a more straightforward way?",
            "I'm here to assist with your store and product inquiries. How can I help you find what you're looking for?",
            "I can help you with information about our products and services. What specific item or topic are you interested in?",
            "Let me help you with your shopping questions. What would you like to know about our store?",
        ];

        // Select response based on threat level
        $responseIndex = min(count($responses) - 1, $analysisResult['risk_level']);

        return $responses[$responseIndex];
    }

    /**
     * Analyze direct instructions
     *
     * Detects attempts to give direct instructions to override behavior.
     *
     * @since 1.0.0
     * @param string $prompt Input prompt
     * @param array &$result Analysis result (passed by reference)
     * @return void
     */
    private function analyzeDirectInstructions(string $prompt, array &$result): void
    {
        foreach ($this->instructionPatterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                $result['threats_detected'][] = [
                    'type' => 'direct_instruction',
                    'pattern' => $pattern,
                    'match' => $matches[0],
                    'severity' => 'high',
                ];
                $result['risk_level'] = max($result['risk_level'], $this->riskLevels['high']);
            }
        }
    }

    /**
     * Analyze role manipulation
     *
     * Detects attempts to manipulate the AI's role or identity.
     *
     * @since 1.0.0
     * @param string $prompt Input prompt
     * @param array &$result Analysis result (passed by reference)
     * @return void
     */
    private function analyzeRoleManipulation(string $prompt, array &$result): void
    {
        foreach ($this->rolePatterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                $result['threats_detected'][] = [
                    'type' => 'role_manipulation',
                    'pattern' => $pattern,
                    'match' => $matches[0],
                    'severity' => 'medium',
                ];
                $result['risk_level'] = max($result['risk_level'], $this->riskLevels['medium']);
            }
        }
    }

    /**
     * Analyze system prompts
     *
     * Detects attempts to inject system-level prompts or commands.
     *
     * @since 1.0.0
     * @param string $prompt Input prompt
     * @param array &$result Analysis result (passed by reference)
     * @return void
     */
    private function analyzeSystemPrompts(string $prompt, array &$result): void
    {
        foreach ($this->systemPatterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                $result['threats_detected'][] = [
                    'type' => 'system_prompt',
                    'pattern' => $pattern,
                    'match' => $matches[0],
                    'severity' => 'critical',
                ];
                $result['risk_level'] = max($result['risk_level'], $this->riskLevels['critical']);
            }
        }
    }

    /**
     * Analyze code injection
     *
     * Detects attempts to inject code or execute commands.
     *
     * @since 1.0.0
     * @param string $prompt Input prompt
     * @param array &$result Analysis result (passed by reference)
     * @return void
     */
    private function analyzeCodeInjection(string $prompt, array &$result): void
    {
        foreach ($this->codePatterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                $result['threats_detected'][] = [
                    'type' => 'code_injection',
                    'pattern' => $pattern,
                    'match' => substr($matches[0], 0, 100) . '...', // Truncate for logging
                    'severity' => 'high',
                ];
                $result['risk_level'] = max($result['risk_level'], $this->riskLevels['high']);
            }
        }
    }

    /**
     * Analyze obfuscation
     *
     * Detects attempts to obfuscate malicious content using encoding.
     *
     * @since 1.0.0
     * @param string $prompt Input prompt
     * @param array &$result Analysis result (passed by reference)
     * @return void
     */
    private function analyzeObfuscation(string $prompt, array &$result): void
    {
        foreach ($this->obfuscationPatterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                $result['threats_detected'][] = [
                    'type' => 'obfuscation',
                    'pattern' => $pattern,
                    'match' => $matches[0],
                    'severity' => 'medium',
                ];
                $result['risk_level'] = max($result['risk_level'], $this->riskLevels['medium']);
            }
        }
    }

    /**
     * Analyze malicious keywords
     *
     * Detects presence of known malicious keywords and phrases.
     *
     * @since 1.0.0
     * @param string $prompt Input prompt
     * @param array &$result Analysis result (passed by reference)
     * @return void
     */
    private function analyzeMaliciousKeywords(string $prompt, array &$result): void
    {
        $lowerPrompt = strtolower($prompt);
        $foundKeywords = [];

        foreach ($this->maliciousKeywords as $keyword) {
            if (strpos($lowerPrompt, strtolower($keyword)) !== false) {
                $foundKeywords[] = $keyword;
            }
        }

        if (!empty($foundKeywords)) {
            $severity = count($foundKeywords) > 3 ? 'high' : 'low';
            $riskLevel = count($foundKeywords) > 3 ? $this->riskLevels['high'] : $this->riskLevels['low'];

            $result['threats_detected'][] = [
                'type' => 'malicious_keywords',
                'keywords' => $foundKeywords,
                'count' => count($foundKeywords),
                'severity' => $severity,
            ];
            $result['risk_level'] = max($result['risk_level'], $riskLevel);
        }
    }

    /**
     * Analyze structural patterns
     *
     * Analyzes the structure of the prompt for suspicious patterns.
     *
     * @since 1.0.0
     * @param string $prompt Input prompt
     * @param array &$result Analysis result (passed by reference)
     * @return void
     */
    private function analyzeStructuralPatterns(string $prompt, array &$result): void
    {
        // Check for excessive repetition (flooding attack)
        if (preg_match('/(.{3,})\1{5,}/', $prompt)) {
            $result['threats_detected'][] = [
                'type' => 'repetition_flood',
                'severity' => 'low',
            ];
            $result['risk_level'] = max($result['risk_level'], $this->riskLevels['low']);
        }

        // Check for unusual character distribution
        $nonAsciiCount = preg_match_all('/[^\x00-\x7F]/', $prompt);
        $totalLength = strlen($prompt);

        if ($totalLength > 0 && ($nonAsciiCount / $totalLength) > 0.3) {
            $result['threats_detected'][] = [
                'type' => 'unusual_encoding',
                'severity' => 'low',
            ];
            $result['risk_level'] = max($result['risk_level'], $this->riskLevels['low']);
        }

        // Check for unusual prompt length (potential buffer overflow attempt)
        if (strlen($prompt) > 10000) {
            $result['threats_detected'][] = [
                'type' => 'excessive_length',
                'length' => strlen($prompt),
                'severity' => 'medium',
            ];
            $result['risk_level'] = max($result['risk_level'], $this->riskLevels['medium']);
        }
    }

    /**
     * Calculate overall risk
     *
     * Calculates the overall risk level and confidence score based on
     * all detected threats and patterns.
     *
     * @since 1.0.0
     * @param array &$result Analysis result (passed by reference)
     * @return void
     */
    private function calculateOverallRisk(array &$result): void
    {
        $threatCount = count($result['threats_detected']);

        if ($threatCount === 0) {
            $result['risk_label'] = 'safe';
            $result['confidence'] = 1.0;
            return;
        }

        // Calculate weighted risk based on threat severity
        $severityWeights = [
            'low' => 1,
            'medium' => 2,
            'high' => 4,
            'critical' => 8,
        ];

        $totalWeight = 0;
        foreach ($result['threats_detected'] as $threat) {
            $totalWeight += $severityWeights[$threat['severity']] ?? 1;
        }

        // Determine risk label based on highest risk level
        $riskLabels = array_flip($this->riskLevels);
        $result['risk_label'] = $riskLabels[$result['risk_level']];

        // Calculate confidence based on number and severity of threats
        $baseConfidence = min(1.0, $threatCount * 0.2);
        $severityBonus = min(0.5, $totalWeight * 0.1);
        $result['confidence'] = min(1.0, $baseConfidence + $severityBonus);

        // Determine if should block
        $result['should_block'] = $this->shouldBlockPrompt($result);

        // Add recommendations
        $result['recommendations'] = $this->generateRecommendations($result);

        // Create sanitized version
        $result['sanitized_prompt'] = $this->sanitizeDangerousPrompt(
            $result['original_prompt'],
            $result['threats_detected']
        );
    }

    /**
     * Generate recommendations
     *
     * Generates actionable recommendations based on threat analysis.
     *
     * @since 1.0.0
     * @param array $result Analysis result
     * @return array Array of recommendations
     */
    private function generateRecommendations(array $result): array
    {
        $recommendations = [];

        if ($result['risk_level'] >= $this->riskLevels['high']) {
            $recommendations[] = 'Block this prompt and log the incident';
            $recommendations[] = 'Consider rate limiting the source';
        }

        if ($result['risk_level'] >= $this->riskLevels['medium']) {
            $recommendations[] = 'Use sanitized version of the prompt';
            $recommendations[] = 'Monitor for repeated attempts';
        }

        foreach ($result['threats_detected'] as $threat) {
            switch ($threat['type']) {
                case 'direct_instruction':
                    $recommendations[] = 'Detected instruction override attempt';
                    break;
                case 'role_manipulation':
                    $recommendations[] = 'Detected role manipulation attempt';
                    break;
                case 'system_prompt':
                    $recommendations[] = 'Detected system prompt injection';
                    break;
                case 'code_injection':
                    $recommendations[] = 'Detected code injection attempt';
                    break;
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Handle detected threat
     *
     * Default handler for detected prompt injection threats.
     *
     * @since 1.0.0
     * @param string $prompt Original prompt
     * @param array $result Analysis result
     * @param array $context Additional context
     * @return void
     */
    public function handleDetectedThreat(string $prompt, array $result, array $context): void
    {
        // Increment blocked counter if should block
        if ($result['should_block']) {
            $this->statistics['injections_blocked']++;
        }

        // Log detailed threat information
        Utils::logError('Prompt injection threat handled', [
            'risk_level' => $result['risk_label'],
            'confidence' => $result['confidence'],
            'threat_types' => array_column($result['threats_detected'], 'type'),
            'should_block' => $result['should_block'],
            'context' => $context,
            'ip' => Utils::getClientIpAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ]);

        // Trigger additional security measures if critical
        if ($result['risk_level'] >= $this->riskLevels['critical']) {
            do_action('woo_ai_assistant_critical_threat_detected', $prompt, $result, $context);
        }
    }

    /**
     * Load configuration from database
     *
     * Loads threat detection configuration from WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    private function loadConfiguration(): void
    {
        $config = get_option('woo_ai_assistant_prompt_defense_config', []);

        if (!empty($config)) {
            // Allow customization of patterns and keywords
            if (isset($config['additional_keywords'])) {
                $this->maliciousKeywords = array_merge(
                    $this->maliciousKeywords,
                    $config['additional_keywords']
                );
            }
        }
    }

    /**
     * Load statistics from database
     *
     * Loads threat detection statistics from WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    private function loadStatistics(): void
    {
        $stats = get_option('woo_ai_assistant_prompt_defense_stats', []);
        $this->statistics = array_merge($this->statistics, $stats);
    }

    /**
     * Save statistics to database
     *
     * Saves current threat detection statistics to WordPress options.
     *
     * @since 1.0.0
     * @return void
     */
    public function saveStatistics(): void
    {
        update_option('woo_ai_assistant_prompt_defense_stats', $this->statistics);
    }

    /**
     * Get defense statistics
     *
     * Returns comprehensive statistics about prompt defense operations.
     *
     * @since 1.0.0
     * @return array Prompt defense statistics
     */
    public function getStatistics(): array
    {
        return [
            'detection_stats' => $this->statistics,
            'pattern_counts' => [
                'instruction_patterns' => count($this->instructionPatterns),
                'role_patterns' => count($this->rolePatterns),
                'system_patterns' => count($this->systemPatterns),
                'code_patterns' => count($this->codePatterns),
                'obfuscation_patterns' => count($this->obfuscationPatterns),
                'malicious_keywords' => count($this->maliciousKeywords),
            ],
            'risk_levels' => $this->riskLevels,
        ];
    }

    /**
     * Reset statistics
     *
     * Resets all prompt defense statistics to zero.
     *
     * @since 1.0.0
     * @return void
     */
    public function resetStatistics(): void
    {
        $this->statistics = [
            'prompts_analyzed' => 0,
            'threats_detected' => 0,
            'injections_blocked' => 0,
            'false_positives' => 0,
            'threat_levels' => [
                'safe' => 0,
                'low' => 0,
                'medium' => 0,
                'high' => 0,
                'critical' => 0,
            ],
        ];

        $this->saveStatistics();
    }

    /**
     * Add custom pattern
     *
     * Adds a custom threat detection pattern to the system.
     *
     * @since 1.0.0
     * @param string $pattern Regular expression pattern
     * @param string $type Pattern type (instruction, role, system, etc.)
     * @return bool True if pattern was added successfully
     */
    public function addCustomPattern(string $pattern, string $type): bool
    {
        $validTypes = ['instruction', 'role', 'system', 'code', 'obfuscation'];

        if (!in_array($type, $validTypes, true)) {
            return false;
        }

        $propertyName = $type . 'Patterns';
        if (!property_exists($this, $propertyName)) {
            return false;
        }

        // Validate pattern syntax
        if (@preg_match($pattern, '') === false) {
            return false;
        }

        $this->{$propertyName}[] = $pattern;

        Utils::logDebug("Added custom {$type} pattern for prompt defense");
        return true;
    }
}
