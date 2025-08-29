<?php
/**
 * Version Bump Script for Woo AI Assistant Plugin
 *
 * This script handles automatic version management including:
 * - Bumping version numbers in plugin files
 * - Creating git tags
 * - Updating version constants
 * - Validating version formats
 *
 * @package WooAiAssistant
 * @subpackage Scripts
 * @since 1.0.0
 * @author Claude Code Assistant
 */

// Exit if accessed directly
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

class VersionManager
{
    /**
     * Plugin directory path
     *
     * @var string
     */
    private $pluginDir;

    /**
     * Plugin slug
     *
     * @var string
     */
    private $pluginSlug = 'woo-ai-assistant';

    /**
     * Main plugin file path
     *
     * @var string
     */
    private $mainPluginFile;

    /**
     * Supported version types for bumping
     *
     * @var array
     */
    private $supportedBumpTypes = ['major', 'minor', 'patch', 'rc', 'beta', 'alpha'];

    /**
     * Color codes for output
     *
     * @var array
     */
    private $colors = [
        'red' => "\033[0;31m",
        'green' => "\033[0;32m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m"
    ];

    /**
     * Constructor
     *
     * @param string $pluginDir Plugin directory path
     */
    public function __construct($pluginDir = null)
    {
        $this->pluginDir = $pluginDir ?: dirname(__DIR__);
        $this->mainPluginFile = $this->pluginDir . '/' . $this->pluginSlug . '.php';
    }

    /**
     * Print colored output
     *
     * @param string $message Message to print
     * @param string $color Color name
     * @return void
     */
    private function printMessage($message, $color = 'reset')
    {
        $colorCode = $this->colors[$color] ?? $this->colors['reset'];
        echo $colorCode . $message . $this->colors['reset'] . PHP_EOL;
    }

    /**
     * Print status message
     *
     * @param string $message
     * @return void
     */
    private function printStatus($message)
    {
        $this->printMessage('[INFO] ' . $message, 'blue');
    }

    /**
     * Print success message
     *
     * @param string $message
     * @return void
     */
    private function printSuccess($message)
    {
        $this->printMessage('[SUCCESS] ' . $message, 'green');
    }

    /**
     * Print warning message
     *
     * @param string $message
     * @return void
     */
    private function printWarning($message)
    {
        $this->printMessage('[WARNING] ' . $message, 'yellow');
    }

    /**
     * Print error message
     *
     * @param string $message
     * @return void
     */
    private function printError($message)
    {
        $this->printMessage('[ERROR] ' . $message, 'red');
    }

    /**
     * Get current version from main plugin file
     *
     * @return string|null Current version or null if not found
     */
    public function getCurrentVersion()
    {
        if (!file_exists($this->mainPluginFile)) {
            return null;
        }

        $content = file_get_contents($this->mainPluginFile);
        if (preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:-(?:alpha|beta|rc)[0-9]*)?)/i', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate version format
     *
     * @param string $version Version string to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidVersion($version)
    {
        return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-(?:alpha|beta|rc)[0-9]*)?$/', $version);
    }

    /**
     * Compare two version strings
     *
     * @param string $version1 First version
     * @param string $version2 Second version
     * @return int -1 if version1 < version2, 0 if equal, 1 if version1 > version2
     */
    public function compareVersions($version1, $version2)
    {
        return version_compare($version1, $version2);
    }

    /**
     * Bump version based on type
     *
     * @param string $currentVersion Current version
     * @param string $bumpType Type of bump (major, minor, patch, rc, beta, alpha)
     * @return string New version
     */
    public function bumpVersion($currentVersion, $bumpType)
    {
        // Parse current version
        if (!preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)(?:-(alpha|beta|rc)([0-9]*))?$/', $currentVersion, $matches)) {
            throw new InvalidArgumentException("Invalid version format: $currentVersion");
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];
        $patch = (int) $matches[3];
        $prerelease = $matches[4] ?? null;
        $prereleaseNum = isset($matches[5]) && $matches[5] !== '' ? (int) $matches[5] : 1;

        switch ($bumpType) {
            case 'major':
                return ($major + 1) . '.0.0';

            case 'minor':
                return $major . '.' . ($minor + 1) . '.0';

            case 'patch':
                return $major . '.' . $minor . '.' . ($patch + 1);

            case 'alpha':
                if ($prerelease === 'alpha') {
                    return "$major.$minor.$patch-alpha" . ($prereleaseNum + 1);
                }
                return "$major.$minor." . ($patch + 1) . "-alpha1";

            case 'beta':
                if ($prerelease === 'beta') {
                    return "$major.$minor.$patch-beta" . ($prereleaseNum + 1);
                } elseif ($prerelease === 'alpha') {
                    return "$major.$minor.$patch-beta1";
                }
                return "$major.$minor." . ($patch + 1) . "-beta1";

            case 'rc':
                if ($prerelease === 'rc') {
                    return "$major.$minor.$patch-rc" . ($prereleaseNum + 1);
                } elseif (in_array($prerelease, ['alpha', 'beta'])) {
                    return "$major.$minor.$patch-rc1";
                }
                return "$major.$minor." . ($patch + 1) . "-rc1";

            default:
                throw new InvalidArgumentException("Unsupported bump type: $bumpType");
        }
    }

    /**
     * Update version in main plugin file
     *
     * @param string $newVersion New version to set
     * @return bool True on success, false on failure
     */
    public function updatePluginFileVersion($newVersion)
    {
        if (!file_exists($this->mainPluginFile)) {
            $this->printError("Main plugin file not found: " . $this->mainPluginFile);
            return false;
        }

        $content = file_get_contents($this->mainPluginFile);
        
        // Update plugin header version
        $content = preg_replace(
            '/^(\s*\*\s*Version:\s*)[0-9]+\.[0-9]+\.[0-9]+(?:-(?:alpha|beta|rc)[0-9]*)?/m',
            '$1' . $newVersion,
            $content
        );

        // Update version constant
        $content = preg_replace(
            '/define\(\s*[\'"]WOO_AI_ASSISTANT_VERSION[\'"]\s*,\s*[\'"][0-9]+\.[0-9]+\.[0-9]+(?:-(?:alpha|beta|rc)[0-9]*)?[\'"]\s*\)/',
            "define('WOO_AI_ASSISTANT_VERSION', '$newVersion')",
            $content
        );

        return file_put_contents($this->mainPluginFile, $content) !== false;
    }

    /**
     * Update version in package.json if it exists
     *
     * @param string $newVersion New version to set
     * @return bool True on success or if file doesn't exist, false on failure
     */
    public function updatePackageJsonVersion($newVersion)
    {
        $packageJsonPath = $this->pluginDir . '/package.json';
        
        if (!file_exists($packageJsonPath)) {
            return true; // Not an error if file doesn't exist
        }

        $packageData = json_decode(file_get_contents($packageJsonPath), true);
        if ($packageData === null) {
            $this->printError("Invalid JSON in package.json");
            return false;
        }

        $packageData['version'] = $newVersion;
        
        return file_put_contents(
            $packageJsonPath,
            json_encode($packageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        ) !== false;
    }

    /**
     * Update version in composer.json if it exists
     *
     * @param string $newVersion New version to set
     * @return bool True on success or if file doesn't exist, false on failure
     */
    public function updateComposerJsonVersion($newVersion)
    {
        $composerJsonPath = $this->pluginDir . '/composer.json';
        
        if (!file_exists($composerJsonPath)) {
            return true; // Not an error if file doesn't exist
        }

        $composerData = json_decode(file_get_contents($composerJsonPath), true);
        if ($composerData === null) {
            $this->printError("Invalid JSON in composer.json");
            return false;
        }

        $composerData['version'] = $newVersion;
        
        return file_put_contents(
            $composerJsonPath,
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        ) !== false;
    }

    /**
     * Create git tag for version
     *
     * @param string $version Version to tag
     * @param bool $push Whether to push tag to remote
     * @return bool True on success, false on failure
     */
    public function createGitTag($version, $push = false)
    {
        if (!is_dir($this->pluginDir . '/.git')) {
            $this->printWarning("Not a git repository, skipping tag creation");
            return true;
        }

        $tagName = "v$version";
        
        // Check if tag already exists
        exec("cd {$this->pluginDir} && git tag -l $tagName", $output, $return);
        if (!empty($output)) {
            $this->printError("Tag $tagName already exists");
            return false;
        }

        // Create annotated tag
        $message = "Release version $version";
        exec("cd {$this->pluginDir} && git tag -a $tagName -m '$message'", $output, $return);
        
        if ($return !== 0) {
            $this->printError("Failed to create git tag");
            return false;
        }

        $this->printSuccess("Git tag created: $tagName");

        // Push tag if requested
        if ($push) {
            exec("cd {$this->pluginDir} && git push origin $tagName", $output, $return);
            if ($return === 0) {
                $this->printSuccess("Git tag pushed to remote");
            } else {
                $this->printWarning("Failed to push git tag to remote");
            }
        }

        return true;
    }

    /**
     * Update all version references
     *
     * @param string $newVersion New version to set
     * @param bool $createTag Whether to create git tag
     * @param bool $pushTag Whether to push git tag
     * @return bool True on success, false on failure
     */
    public function updateVersion($newVersion, $createTag = true, $pushTag = false)
    {
        $this->printStatus("Updating version to: $newVersion");

        // Update main plugin file
        if (!$this->updatePluginFileVersion($newVersion)) {
            $this->printError("Failed to update main plugin file");
            return false;
        }
        $this->printSuccess("Updated main plugin file");

        // Update package.json
        if (!$this->updatePackageJsonVersion($newVersion)) {
            $this->printError("Failed to update package.json");
            return false;
        }
        if (file_exists($this->pluginDir . '/package.json')) {
            $this->printSuccess("Updated package.json");
        }

        // Update composer.json
        if (!$this->updateComposerJsonVersion($newVersion)) {
            $this->printError("Failed to update composer.json");
            return false;
        }
        if (file_exists($this->pluginDir . '/composer.json')) {
            $this->printSuccess("Updated composer.json");
        }

        // Create git tag
        if ($createTag && !$this->createGitTag($newVersion, $pushTag)) {
            $this->printWarning("Failed to create git tag, but version update was successful");
        }

        return true;
    }

    /**
     * Display help message
     *
     * @return void
     */
    public function displayHelp()
    {
        echo "Version Bump Script for Woo AI Assistant Plugin\n\n";
        echo "Usage:\n";
        echo "  php version-bump.php [COMMAND] [OPTIONS]\n\n";
        echo "Commands:\n";
        echo "  current                 Show current version\n";
        echo "  set VERSION            Set specific version\n";
        echo "  bump TYPE              Bump version by type (major|minor|patch|alpha|beta|rc)\n\n";
        echo "Options:\n";
        echo "  --no-tag               Don't create git tag\n";
        echo "  --push-tag             Push git tag to remote\n";
        echo "  --dry-run              Show what would be done without making changes\n";
        echo "  -h, --help             Show this help message\n\n";
        echo "Examples:\n";
        echo "  php version-bump.php current\n";
        echo "  php version-bump.php set 1.2.0\n";
        echo "  php version-bump.php bump minor\n";
        echo "  php version-bump.php bump patch --push-tag\n\n";
    }
}

// Main execution
function main($args)
{
    $versionManager = new VersionManager();
    
    // Parse arguments
    $command = $args[1] ?? 'help';
    $createTag = true;
    $pushTag = false;
    $dryRun = false;

    // Parse options
    for ($i = 2; $i < count($args); $i++) {
        switch ($args[$i]) {
            case '--no-tag':
                $createTag = false;
                break;
            case '--push-tag':
                $pushTag = true;
                break;
            case '--dry-run':
                $dryRun = true;
                break;
            case '-h':
            case '--help':
                $versionManager->displayHelp();
                exit(0);
        }
    }

    try {
        switch ($command) {
            case 'current':
                $currentVersion = $versionManager->getCurrentVersion();
                if ($currentVersion) {
                    echo "[SUCCESS] Current version: $currentVersion\n";
                } else {
                    echo "[ERROR] Could not determine current version\n";
                    exit(1);
                }
                break;

            case 'set':
                if (!isset($args[2])) {
                    echo "[ERROR] Version number required\n";
                    $versionManager->displayHelp();
                    exit(1);
                }

                $newVersion = $args[2];
                if (!$versionManager->isValidVersion($newVersion)) {
                    echo "[ERROR] Invalid version format: $newVersion\n";
                    exit(1);
                }

                $currentVersion = $versionManager->getCurrentVersion();
                if ($currentVersion && $versionManager->compareVersions($newVersion, $currentVersion) <= 0) {
                    echo "[WARNING] New version ($newVersion) is not greater than current version ($currentVersion)\n";
                }

                if ($dryRun) {
                    echo "[INFO] DRY RUN: Would set version to $newVersion\n";
                } else {
                    if ($versionManager->updateVersion($newVersion, $createTag, $pushTag)) {
                        echo "[SUCCESS] Version updated to: $newVersion\n";
                    } else {
                        echo "[ERROR] Failed to update version\n";
                        exit(1);
                    }
                }
                break;

            case 'bump':
                if (!isset($args[2])) {
                    echo "[ERROR] Bump type required (major|minor|patch|alpha|beta|rc)\n";
                    exit(1);
                }

                $bumpType = $args[2];
                $supportedTypes = ['major', 'minor', 'patch', 'alpha', 'beta', 'rc'];
                if (!in_array($bumpType, $supportedTypes)) {
                    echo "[ERROR] Unsupported bump type: $bumpType\n";
                    echo "[ERROR] Supported types: " . implode(', ', $supportedTypes) . "\n";
                    exit(1);
                }

                $currentVersion = $versionManager->getCurrentVersion();
                if (!$currentVersion) {
                    echo "[ERROR] Could not determine current version\n";
                    exit(1);
                }

                $newVersion = $versionManager->bumpVersion($currentVersion, $bumpType);

                if ($dryRun) {
                    echo "[INFO] DRY RUN: Would bump version from $currentVersion to $newVersion\n";
                } else {
                    if ($versionManager->updateVersion($newVersion, $createTag, $pushTag)) {
                        echo "[SUCCESS] Version bumped from $currentVersion to $newVersion\n";
                    } else {
                        echo "[ERROR] Failed to update version\n";
                        exit(1);
                    }
                }
                break;

            default:
                if ($command !== 'help') {
                    echo "[ERROR] Unknown command: $command\n";
                }
                $versionManager->displayHelp();
                exit($command === 'help' ? 0 : 1);
        }
    } catch (Exception $e) {
        echo "[ERROR] " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Run main function with command line arguments
main($argv);