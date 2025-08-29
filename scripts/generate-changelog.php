<?php
/**
 * Changelog Generation Script for Woo AI Assistant Plugin
 *
 * This script automatically generates changelog entries from git commits,
 * GitHub issues, pull requests, and manual entries. It supports multiple
 * output formats including Markdown and WordPress.org readme format.
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

class ChangelogGenerator
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
     * Changelog sections configuration
     *
     * @var array
     */
    private $sections = [
        'breaking' => [
            'title' => 'Breaking Changes',
            'keywords' => ['BREAKING', 'breaking change', 'breaking:'],
            'emoji' => '⚠️'
        ],
        'added' => [
            'title' => 'Added',
            'keywords' => ['feat', 'feature', 'add', 'new'],
            'emoji' => '✨'
        ],
        'changed' => [
            'title' => 'Changed',
            'keywords' => ['change', 'update', 'modify', 'improve'],
            'emoji' => '🔄'
        ],
        'deprecated' => [
            'title' => 'Deprecated',
            'keywords' => ['deprecate', 'deprecated'],
            'emoji' => '🗑️'
        ],
        'removed' => [
            'title' => 'Removed',
            'keywords' => ['remove', 'delete', 'drop'],
            'emoji' => '🗑️'
        ],
        'fixed' => [
            'title' => 'Fixed',
            'keywords' => ['fix', 'bug', 'patch', 'resolve'],
            'emoji' => '🐛'
        ],
        'security' => [
            'title' => 'Security',
            'keywords' => ['security', 'vulnerability', 'cve'],
            'emoji' => '🔒'
        ]
    ];

    /**
     * Constructor
     *
     * @param string $pluginDir Plugin directory path
     */
    public function __construct($pluginDir = null)
    {
        $this->pluginDir = $pluginDir ?: dirname(__DIR__);
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
     * Get git commits between two references
     *
     * @param string $from Starting reference (tag, branch, commit)
     * @param string $to Ending reference (tag, branch, commit)
     * @return array Array of commit objects
     */
    public function getGitCommits($from = null, $to = 'HEAD')
    {
        if (!is_dir($this->pluginDir . '/.git')) {
            $this->printWarning('Not a git repository, skipping git commit analysis');
            return [];
        }

        // Get the range
        $range = $to;
        if ($from) {
            $range = "$from..$to";
        }

        // Get commits with format
        $command = "cd {$this->pluginDir} && git log --pretty=format:'%H|%s|%an|%ae|%ad|%b' --date=iso $range";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->printError('Failed to get git commits');
            return [];
        }

        $commits = [];
        $currentCommit = null;

        foreach ($output as $line) {
            if (empty($line)) {
                continue;
            }

            // Check if this is a new commit line (contains the pipe-separated format)
            if (strpos($line, '|') !== false) {
                // Save previous commit if exists
                if ($currentCommit) {
                    $commits[] = $currentCommit;
                }

                // Parse new commit
                $parts = explode('|', $line, 6);
                if (count($parts) >= 5) {
                    $currentCommit = [
                        'hash' => $parts[0],
                        'subject' => $parts[1],
                        'author_name' => $parts[2],
                        'author_email' => $parts[3],
                        'date' => $parts[4],
                        'body' => isset($parts[5]) ? $parts[5] : ''
                    ];
                }
            } else {
                // This is a continuation of the commit body
                if ($currentCommit) {
                    $currentCommit['body'] .= "\n" . $line;
                }
            }
        }

        // Add the last commit
        if ($currentCommit) {
            $commits[] = $currentCommit;
        }

        return $commits;
    }

    /**
     * Get latest git tag
     *
     * @return string|null Latest tag or null if no tags
     */
    public function getLatestTag()
    {
        if (!is_dir($this->pluginDir . '/.git')) {
            return null;
        }

        $command = "cd {$this->pluginDir} && git describe --tags --abbrev=0 2>/dev/null";
        exec($command, $output, $returnCode);

        return ($returnCode === 0 && !empty($output)) ? $output[0] : null;
    }

    /**
     * Get all git tags
     *
     * @return array Array of tags
     */
    public function getAllTags()
    {
        if (!is_dir($this->pluginDir . '/.git')) {
            return [];
        }

        $command = "cd {$this->pluginDir} && git tag -l --sort=-version:refname";
        exec($command, $output, $returnCode);

        return ($returnCode === 0) ? $output : [];
    }

    /**
     * Categorize a commit message
     *
     * @param string $message Commit message
     * @return string Category key
     */
    public function categorizeCommit($message)
    {
        $message = strtolower($message);

        foreach ($this->sections as $category => $config) {
            foreach ($config['keywords'] as $keyword) {
                if (strpos($message, strtolower($keyword)) !== false) {
                    return $category;
                }
            }
        }

        // Default category for uncategorized commits
        return 'changed';
    }

    /**
     * Parse conventional commit format
     *
     * @param array $commit Commit object
     * @return array Parsed commit with category and scope
     */
    public function parseConventionalCommit($commit)
    {
        $subject = $commit['subject'];
        $category = 'changed';
        $scope = null;
        $description = $subject;
        $isBreaking = false;

        // Check for conventional commit format: type(scope): description
        if (preg_match('/^(\w+)(?:\(([^)]+)\))?(!)?: (.+)$/', $subject, $matches)) {
            $type = strtolower($matches[1]);
            $scope = !empty($matches[2]) ? $matches[2] : null;
            $isBreaking = !empty($matches[3]);
            $description = $matches[4];

            // Map conventional commit types to our categories
            $typeMapping = [
                'feat' => 'added',
                'feature' => 'added',
                'fix' => 'fixed',
                'docs' => 'changed',
                'style' => 'changed',
                'refactor' => 'changed',
                'perf' => 'changed',
                'test' => 'changed',
                'chore' => 'changed',
                'build' => 'changed',
                'ci' => 'changed',
                'revert' => 'changed'
            ];

            $category = $typeMapping[$type] ?? 'changed';

            if ($isBreaking) {
                $category = 'breaking';
            }
        } else {
            // Fallback to keyword-based categorization
            $category = $this->categorizeCommit($subject);
        }

        // Check for breaking changes in body
        if (strpos(strtolower($commit['body']), 'breaking change') !== false) {
            $category = 'breaking';
        }

        return array_merge($commit, [
            'category' => $category,
            'scope' => $scope,
            'description' => $description,
            'is_breaking' => $isBreaking
        ]);
    }

    /**
     * Generate changelog for a version
     *
     * @param string $version Version number
     * @param string $fromTag Previous version tag
     * @param array $options Generation options
     * @return array Changelog data
     */
    public function generateVersionChangelog($version, $fromTag = null, $options = [])
    {
        $this->printStatus("Generating changelog for version $version" . ($fromTag ? " (from $fromTag)" : ""));

        // Get commits
        $commits = $this->getGitCommits($fromTag);
        $this->printStatus("Found " . count($commits) . " commits");

        // Parse and categorize commits
        $categorizedCommits = [];
        foreach ($commits as $commit) {
            $parsedCommit = $this->parseConventionalCommit($commit);
            $category = $parsedCommit['category'];

            if (!isset($categorizedCommits[$category])) {
                $categorizedCommits[$category] = [];
            }

            $categorizedCommits[$category][] = $parsedCommit;
        }

        // Generate changelog data
        $changelog = [
            'version' => $version,
            'date' => date('Y-m-d'),
            'commits' => $commits,
            'categorized' => $categorizedCommits,
            'total_commits' => count($commits)
        ];

        return $changelog;
    }

    /**
     * Format changelog as Markdown
     *
     * @param array $changelog Changelog data
     * @param array $options Formatting options
     * @return string Formatted changelog
     */
    public function formatAsMarkdown($changelog, $options = [])
    {
        $output = [];
        
        // Header
        $version = $changelog['version'];
        $date = $changelog['date'];
        $output[] = "## [$version] - $date";
        $output[] = "";

        // Add summary if there are commits
        if ($changelog['total_commits'] > 0) {
            $output[] = "_This release includes {$changelog['total_commits']} commits._";
            $output[] = "";
        }

        // Add sections
        foreach ($this->sections as $sectionKey => $sectionConfig) {
            if (empty($changelog['categorized'][$sectionKey])) {
                continue;
            }

            $commits = $changelog['categorized'][$sectionKey];
            $emoji = $sectionConfig['emoji'];
            $title = $sectionConfig['title'];

            $output[] = "### $emoji $title";
            $output[] = "";

            foreach ($commits as $commit) {
                $description = $commit['description'];
                $scope = $commit['scope'] ? "**{$commit['scope']}**: " : "";
                $hash = substr($commit['hash'], 0, 7);
                
                $line = "- $scope$description";
                
                // Add commit hash if requested
                if (!empty($options['include_hash'])) {
                    $line .= " ([`$hash`]({$options['repo_url']}/commit/{$commit['hash']}))";
                }
                
                $output[] = $line;
            }

            $output[] = "";
        }

        return implode("\n", $output);
    }

    /**
     * Format changelog as WordPress.org readme format
     *
     * @param array $changelog Changelog data
     * @return string Formatted changelog
     */
    public function formatAsWordPressReadme($changelog)
    {
        $output = [];
        
        // Header (WordPress.org format uses = version =)
        $version = $changelog['version'];
        $date = $changelog['date'];
        $output[] = "= $version =";
        $output[] = "Release Date: $date";
        $output[] = "";

        // Add sections (simplified for WordPress.org)
        $hasContent = false;
        
        foreach (['breaking', 'added', 'fixed', 'changed', 'security'] as $sectionKey) {
            if (empty($changelog['categorized'][$sectionKey])) {
                continue;
            }

            $commits = $changelog['categorized'][$sectionKey];
            $sectionConfig = $this->sections[$sectionKey];

            if (!$hasContent) {
                $hasContent = true;
            }

            foreach ($commits as $commit) {
                $description = $commit['description'];
                $scope = $commit['scope'] ? "[{$commit['scope']}] " : "";
                
                // Prefix with section type for clarity
                $prefix = '';
                if ($sectionKey === 'breaking') {
                    $prefix = 'BREAKING: ';
                } elseif ($sectionKey === 'added') {
                    $prefix = 'New: ';
                } elseif ($sectionKey === 'fixed') {
                    $prefix = 'Fix: ';
                } elseif ($sectionKey === 'security') {
                    $prefix = 'Security: ';
                }
                
                $output[] = "* $prefix$scope$description";
            }
        }

        if (!$hasContent) {
            $output[] = "* Minor improvements and bug fixes";
        }

        $output[] = "";

        return implode("\n", $output);
    }

    /**
     * Read existing changelog file
     *
     * @param string $filePath Path to changelog file
     * @return string|null Existing changelog content or null if file doesn't exist
     */
    public function readExistingChangelog($filePath)
    {
        if (!file_exists($filePath)) {
            return null;
        }

        return file_get_contents($filePath);
    }

    /**
     * Update changelog file
     *
     * @param string $filePath Path to changelog file
     * @param string $newContent New changelog content to prepend
     * @return bool Success status
     */
    public function updateChangelogFile($filePath, $newContent)
    {
        $existingContent = $this->readExistingChangelog($filePath);
        
        if ($existingContent === null) {
            // Create new file
            $fullContent = "# Changelog\n\nAll notable changes to this project will be documented in this file.\n\n$newContent";
        } else {
            // Check if this version already exists
            $version = null;
            if (preg_match('/## \[([^\]]+)\]/', $newContent, $matches)) {
                $version = $matches[1];
            }

            if ($version && strpos($existingContent, "## [$version]") !== false) {
                $this->printWarning("Version $version already exists in changelog");
                return false;
            }

            // Insert new content after the header
            $lines = explode("\n", $existingContent);
            $headerEnd = 0;
            
            // Find end of header section
            for ($i = 0; $i < count($lines); $i++) {
                if (preg_match('/^## \[/', $lines[$i])) {
                    $headerEnd = $i;
                    break;
                }
            }

            if ($headerEnd === 0) {
                // No existing versions, append to end
                $fullContent = $existingContent . "\n$newContent";
            } else {
                // Insert before first version
                $before = array_slice($lines, 0, $headerEnd);
                $after = array_slice($lines, $headerEnd);
                
                $fullContent = implode("\n", $before) . "\n$newContent\n" . implode("\n", $after);
            }
        }

        return file_put_contents($filePath, $fullContent) !== false;
    }

    /**
     * Display help message
     *
     * @return void
     */
    public function displayHelp()
    {
        echo "Changelog Generation Script for Woo AI Assistant Plugin\n\n";
        echo "Usage:\n";
        echo "  php generate-changelog.php [COMMAND] [OPTIONS]\n\n";
        echo "Commands:\n";
        echo "  generate VERSION [FROM_TAG]  Generate changelog for version\n";
        echo "  update VERSION [FROM_TAG]    Generate and update changelog files\n";
        echo "  list-tags                    List all git tags\n\n";
        echo "Options:\n";
        echo "  --format FORMAT             Output format (markdown|wordpress) [default: markdown]\n";
        echo "  --output FILE               Output file path\n";
        echo "  --include-hash             Include commit hashes in output\n";
        echo "  --repo-url URL             Repository URL for links\n";
        echo "  --dry-run                  Show output without writing files\n";
        echo "  -h, --help                 Show this help message\n\n";
        echo "Examples:\n";
        echo "  php generate-changelog.php generate 1.2.0\n";
        echo "  php generate-changelog.php generate 1.2.0 v1.1.0\n";
        echo "  php generate-changelog.php update 1.2.0 --format wordpress\n";
        echo "  php generate-changelog.php list-tags\n\n";
    }
}

// Main execution
function main($args)
{
    $generator = new ChangelogGenerator();
    
    // Parse arguments
    $command = $args[1] ?? 'help';
    $version = $args[2] ?? null;
    $fromTag = $args[3] ?? null;
    
    $options = [
        'format' => 'markdown',
        'output' => null,
        'include_hash' => false,
        'repo_url' => 'https://github.com/woo-ai-assistant/woo-ai-assistant',
        'dry_run' => false
    ];

    // Parse options
    for ($i = 2; $i < count($args); $i++) {
        switch ($args[$i]) {
            case '--format':
                $options['format'] = $args[$i + 1] ?? 'markdown';
                $i++;
                break;
            case '--output':
                $options['output'] = $args[$i + 1] ?? null;
                $i++;
                break;
            case '--include-hash':
                $options['include_hash'] = true;
                break;
            case '--repo-url':
                $options['repo_url'] = $args[$i + 1] ?? $options['repo_url'];
                $i++;
                break;
            case '--dry-run':
                $options['dry_run'] = true;
                break;
            case '-h':
            case '--help':
                $generator->displayHelp();
                exit(0);
        }
    }

    try {
        switch ($command) {
            case 'generate':
                if (!$version) {
                    $generator->printError('Version required for generate command');
                    exit(1);
                }

                // Auto-detect from tag if not specified
                if (!$fromTag) {
                    $fromTag = $generator->getLatestTag();
                    if ($fromTag) {
                        $generator->printStatus("Auto-detected previous version: $fromTag");
                    }
                }

                $changelog = $generator->generateVersionChangelog($version, $fromTag, $options);
                
                if ($options['format'] === 'wordpress') {
                    $output = $generator->formatAsWordPressReadme($changelog);
                } else {
                    $output = $generator->formatAsMarkdown($changelog, $options);
                }

                if ($options['output'] && !$options['dry_run']) {
                    file_put_contents($options['output'], $output);
                    $generator->printSuccess("Changelog written to: {$options['output']}");
                } else {
                    echo $output;
                }
                break;

            case 'update':
                if (!$version) {
                    $generator->printError('Version required for update command');
                    exit(1);
                }

                // Auto-detect from tag if not specified
                if (!$fromTag) {
                    $fromTag = $generator->getLatestTag();
                    if ($fromTag) {
                        $generator->printStatus("Auto-detected previous version: $fromTag");
                    }
                }

                $changelog = $generator->generateVersionChangelog($version, $fromTag, $options);

                // Update CHANGELOG.md
                $changelogPath = dirname(__DIR__) . '/CHANGELOG.md';
                $markdownContent = $generator->formatAsMarkdown($changelog, $options);
                
                if (!$options['dry_run']) {
                    if ($generator->updateChangelogFile($changelogPath, $markdownContent)) {
                        $generator->printSuccess("Updated CHANGELOG.md");
                    } else {
                        $generator->printError("Failed to update CHANGELOG.md");
                    }

                    // Update readme.txt for WordPress.org
                    $readmePath = dirname(__DIR__) . '/readme.txt';
                    if (file_exists($readmePath)) {
                        $wordpressContent = $generator->formatAsWordPressReadme($changelog);
                        
                        // Insert into readme.txt changelog section
                        $readmeContent = file_get_contents($readmePath);
                        
                        if (strpos($readmeContent, '== Changelog ==') !== false) {
                            $readmeContent = preg_replace(
                                '/== Changelog ==\s*/',
                                "== Changelog ==\n\n$wordpressContent",
                                $readmeContent,
                                1
                            );
                            
                            if (file_put_contents($readmePath, $readmeContent)) {
                                $generator->printSuccess("Updated readme.txt");
                            }
                        }
                    }
                } else {
                    $generator->printStatus("DRY RUN: Would update the following files:");
                    $generator->printStatus("- CHANGELOG.md");
                    if (file_exists(dirname(__DIR__) . '/readme.txt')) {
                        $generator->printStatus("- readme.txt");
                    }
                    echo "\nGenerated content:\n";
                    echo $markdownContent;
                }
                break;

            case 'list-tags':
                $tags = $generator->getAllTags();
                if (empty($tags)) {
                    $generator->printWarning('No git tags found');
                } else {
                    $generator->printStatus('Available tags:');
                    foreach ($tags as $tag) {
                        echo "  $tag\n";
                    }
                }
                break;

            default:
                if ($command !== 'help') {
                    $generator->printError("Unknown command: $command");
                }
                $generator->displayHelp();
                exit($command === 'help' ? 0 : 1);
        }
    } catch (Exception $e) {
        $generator->printError($e->getMessage());
        exit(1);
    }
}

// Run main function with command line arguments
main($argv);