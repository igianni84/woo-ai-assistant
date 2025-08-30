<?php

/**
 * verify-standards.php - Progressive PHP standards verification
 * Usage: php scripts/verify-standards.php [phase]
 */

$phase = isset($argv[1]) ? (int)$argv[1] : 0;
$errors = 0;

echo "🔍 Verifying PHP standards for Phase $phase...\n";
echo "==========================================\n\n";

// Only check files that should exist in current phase
$filesToCheck = getFilesForPhase($phase);

foreach ($filesToCheck as $file) {
    if (!file_exists($file)) {
        echo "⚠️  Skipping $file (not created yet)\n";
        continue;
    }

    echo "Checking $file...\n";

    $content = file_get_contents($file);
    $fileErrors = 0;

    // Check class naming (PascalCase)
    if (preg_match_all('/class\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches)) {
        foreach ($matches[1] as $className) {
            if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $className)) {
                echo "  ❌ Class '$className' does not follow PascalCase\n";
                $fileErrors++;
            }
        }
    }

    // Check method naming (camelCase)
    if (preg_match_all('/(?:public|private|protected)\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches)) {
        foreach ($matches[1] as $methodName) {
            // Skip magic methods
            if (strpos($methodName, '__') === 0) {
                continue;
            }

            if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $methodName)) {
                echo "  ❌ Method '$methodName' does not follow camelCase\n";
                $fileErrors++;
            }
        }
    }

    // Check for proper namespace
    if (!preg_match('/namespace\s+WooAiAssistant/', $content)) {
        echo "  ⚠️  Missing or incorrect namespace\n";
        $fileErrors++;
    }

    // Check for exit if accessed directly
    if (!preg_match('/if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)/', $content)) {
        echo "  ⚠️  Missing ABSPATH check\n";
        $fileErrors++;
    }

    if ($fileErrors === 0) {
        echo "  ✅ All standards checks passed\n";
    } else {
        $errors += $fileErrors;
    }

    echo "\n";
}

echo "==========================================\n";

if ($errors === 0) {
    echo "✅ All PHP standards verified successfully for Phase $phase!\n";
    exit(0);
} else {
    echo "❌ Found $errors standard violations for Phase $phase\n";
    echo "   Fix the issues and run verification again.\n";
    exit(1);
}

/**
 * Get files to check based on current phase
 */
function getFilesForPhase($phase)
{
    $files = [];

    // Phase 0 - Foundation
    if ($phase >= 0) {
        $files[] = 'woo-ai-assistant.php';
    }

    // Phase 1 - Core Infrastructure
    if ($phase >= 1) {
        $files[] = 'src/Main.php';
        $files[] = 'src/Setup/Activator.php';
        $files[] = 'src/Setup/Deactivator.php';
        $files[] = 'src/Admin/AdminMenu.php';
        $files[] = 'src/Common/Traits/Singleton.php';
    }

    // Phase 2 - Knowledge Base
    if ($phase >= 2) {
        $files[] = 'src/KnowledgeBase/Scanner.php';
        $files[] = 'src/KnowledgeBase/Indexer.php';
        $files[] = 'src/KnowledgeBase/ChunkingStrategy.php';
        $files[] = 'src/KnowledgeBase/VectorManager.php';
    }

    // Phase 3 - Server Integration
    if ($phase >= 3) {
        $files[] = 'src/Api/IntermediateServerClient.php';
        $files[] = 'src/Api/LicenseManager.php';
        $files[] = 'src/Config/DevelopmentConfig.php';
    }

    // Phase 5+ - Chat Logic
    if ($phase >= 5) {
        $files[] = 'src/Chatbot/ConversationHandler.php';
        $files[] = 'src/RestApi/Endpoints/ChatEndpoint.php';
    }

    return $files;
}
