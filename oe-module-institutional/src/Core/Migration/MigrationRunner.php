<?php

/**
 * src/Core/Migration/MigrationRunner.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Core\Migration;

use OpenEMR\Common\Logging\SystemLogger;

/**
 * MigrationRunner
 *
 * Applies pending versioned SQL migration files from sql/migrations/ in
 * ascending filename order.  Each file is guarded by an INSERT IGNORE into
 * oei_schema_version, so re-running the same migration is always a no-op.
 *
 * Naming convention for migration files:
 *   NNNN_description.sql    (e.g. 0001_initial_schema.sql)
 *
 * Files that do NOT match the pattern are silently skipped (dev seeds, etc.).
 *
 * Usage (called once from Bootstrap::subscribeToEvents):
 *   (new MigrationRunner($moduleRoot))->runPending();
 *
 * runPending() is fast when up-to-date: one SELECT on oei_schema_version
 * plus a filesystem readdir.  It never blocks the request on a fully
 * upgraded install.
 *
 * Ground truth: institutional_all_source.txt
 * Last updated: v0.17.0
 */
final class MigrationRunner
{
    private const MIGRATIONS_DIR = 'sql' . DIRECTORY_SEPARATOR . 'migrations';
    private const VERSION_TABLE  = 'oei_schema_version';

    /** Versions already applied (lazy-loaded). */
    private ?array $applied = null;

    private SystemLogger $logger;

    public function __construct(
        private readonly string $moduleRoot
    ) {
        $this->logger = new SystemLogger();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Run every pending migration in ascending filename order.
     * Safe to call on every bootstrap — returns immediately when fully up to date.
     *
     * @return int Number of migrations applied in this call (0 = already current)
     */
    public function runPending(): int
    {
        if (!$this->dbReady()) {
            return 0;
        }

        $pending = $this->pendingFiles();
        if (empty($pending)) {
            return 0;
        }

        $count = 0;
        foreach ($pending as $version => $path) {
            $this->apply($version, $path);
            $count++;
        }

        return $count;
    }

    /**
     * Return all versions currently in oei_schema_version, newest first.
     * @return string[]
     */
    public function appliedVersions(): array
    {
        return array_keys($this->loadApplied());
    }

    /**
     * Return the highest applied version string, or null if table is empty.
     */
    public function currentVersion(): ?string
    {
        $applied = $this->loadApplied();
        if (empty($applied)) {
            return null;
        }
        return array_key_first($applied);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * @return array<string,string>  version => file path, sorted by filename asc
     */
    private function pendingFiles(): array
    {
        $dir = rtrim($this->moduleRoot, DIRECTORY_SEPARATOR)
             . DIRECTORY_SEPARATOR . self::MIGRATIONS_DIR;

        if (!is_dir($dir)) {
            return [];
        }

        $applied = $this->loadApplied();
        $pending = [];

        $files = scandir($dir);
        if ($files === false) {
            return [];
        }

        foreach ($files as $filename) {
            // Must match NNNN_*.sql
            if (!preg_match('/^\d{4}_.*\.sql$/', $filename)) {
                continue;
            }

            // Extract the version from the INSERT IGNORE inside the file,
            // OR fall back to using the filename prefix as a proxy version
            $version = $this->extractVersion(
                $dir . DIRECTORY_SEPARATOR . $filename,
                $filename
            );

            if (isset($applied[$version])) {
                continue; // already applied
            }

            $pending[$version] = $dir . DIRECTORY_SEPARATOR . $filename;
        }

        // Sort by key (version string); NNNN prefix ensures lexicographic order
        ksort($pending);
        return $pending;
    }

    /**
     * Extract the version string that will be inserted by this migration file.
     * Looks for:  VALUES ('x.y.z', ...
     * Falls back to a sanitised form of the filename (e.g. "0001" → "file-0001").
     */
    private function extractVersion(string $path, string $filename): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return 'file-' . substr($filename, 0, 4);
        }
        if (preg_match("/VALUES\s*\(\s*'([^']+)'\s*,/i", $content, $m)) {
            return $m[1];
        }
        return 'file-' . substr($filename, 0, 4);
    }

    /**
     * Execute one migration file inside a transaction.
     */
    private function apply(string $version, string $path): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }

        $sql = @file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            $this->logger->warning("OEI Migration: empty or unreadable file", [
                'version' => $version,
                'path'    => $path,
            ]);
            return;
        }

        // Strip comment-only lines to avoid empty-statement errors
        $statements = $this->splitStatements($sql);

        try {
            sqlStatement('START TRANSACTION');

            foreach ($statements as $stmt) {
                if (trim($stmt) === '') {
                    continue;
                }
                sqlStatement($stmt);
            }

            sqlStatement('COMMIT');

            $this->logger->info("OEI Migration applied", [
                'version' => $version,
                'file'    => basename($path),
            ]);

            // Refresh local cache
            if ($this->applied !== null) {
                $this->applied[$version] = date('Y-m-d H:i:s');
            }
        } catch (\Throwable $e) {
            sqlStatement('ROLLBACK');
            $this->logger->error("OEI Migration FAILED — transaction rolled back", [
                'version' => $version,
                'file'    => basename($path),
                'error'   => $e->getMessage(),
            ]);
            // Re-throw so Bootstrap can surface the error without silently skipping
            throw $e;
        }
    }

    /**
     * Split a SQL file into individual statements on semicolons,
     * honouring single-quoted strings so embedded semicolons are safe.
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        // Strip -- comments and /* */ blocks for cleaner splitting
        $sql = preg_replace('/--[^\n]*/', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        $statements = [];
        $current    = '';
        $inString   = false;
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            if ($ch === "'" && !$inString) {
                $inString = true;
                $current .= $ch;
                continue;
            }
            if ($ch === "'" && $inString) {
                // Handle escaped quote ''
                if (isset($sql[$i + 1]) && $sql[$i + 1] === "'") {
                    $current .= "''";
                    $i++;
                    continue;
                }
                $inString = false;
                $current .= $ch;
                continue;
            }

            if ($ch === ';' && !$inString) {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        $last = trim($current);
        if ($last !== '') {
            $statements[] = $last;
        }

        return $statements;
    }

    /**
     * Load applied versions from DB, keyed by version, descending by datetime.
     * @return array<string,string>  version => applied_datetime
     */
    private function loadApplied(): array
    {
        if ($this->applied !== null) {
            return $this->applied;
        }

        if (!function_exists('sqlStatement') || !$this->dbReady()) {
            $this->applied = [];
            return $this->applied;
        }

        $this->applied = [];
        try {
            $res = sqlStatement(
                "SELECT version, applied_datetime
                 FROM `" . self::VERSION_TABLE . "`
                 ORDER BY applied_datetime DESC, version DESC"
            );
            while ($row = sqlFetchArray($res)) {
                $this->applied[(string)$row['version']] = (string)$row['applied_datetime'];
            }
        } catch (\Throwable $e) {
            // Table might not exist yet on a brand-new install
            $this->applied = [];
        }

        return $this->applied;
    }

    /**
     * Quick guard: is the DB layer available and is oei_schema_version present?
     */
    private function dbReady(): bool
    {
        if (!function_exists('sqlStatement') || !function_exists('sqlFetchArray')) {
            return false;
        }
        try {
            sqlStatement("SELECT 1 FROM `" . self::VERSION_TABLE . "` LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}






