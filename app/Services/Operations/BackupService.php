<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Core\Environment;
use App\Core\ReleaseIdentity;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Backs up mutable runtime state only. Canonical educational content remains
 * in the release package and is never overwritten by restore operations.
 */
final class BackupService
{
    /** @return array<string,mixed> */
    public static function create(string $root, string $output, bool $dryRun = false): array
    {
        Environment::load($root . '/.env');
        $output = self::absolute($output, $root);
        if (is_dir($output) || is_file($output)) {
            throw new RuntimeException('Backup destination already exists; refusing to overwrite it.');
        }

        $driver = strtolower((string) (Environment::get('DB_DRIVER', 'json') ?? 'json'));
        $files = [];
        $storagePath = self::storagePath($root);
        if ($driver === 'json') {
            $lockPath = $storagePath . '/.storage.lock';
            $lock = fopen($lockPath, 'c');
            if ($lock === false || !flock($lock, LOCK_SH)) {
                throw new RuntimeException('Unable to acquire a consistent JSON storage snapshot lock.');
            }
            try {
                foreach (['attempts.json', 'weakness.json', 'question-statistics.json', 'flashcard_state.json', 'blueprints.json'] as $name) {
                    $source = $storagePath . '/' . $name;
                    if (!is_file($source)) continue;
                    $files[] = ['source_path' => $source, 'source' => self::label($source, $root), 'relative' => 'json/' . $name, 'bytes' => filesize($source) ?: 0, 'sha256' => hash_file('sha256', $source) ?: ''];
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } elseif ($driver === 'sqlite') {
            $source = self::sqlitePath($root);
            if (!is_file($source)) throw new RuntimeException('SQLite database does not exist; initialize it before backup.');
            if (!$dryRun) {
                if (!mkdir($output . '/sqlite', 0770, true) && !is_dir($output . '/sqlite')) throw new RuntimeException('Unable to create SQLite backup directory.');
                $destination = $output . '/sqlite/boardprep.sqlite';
                try {
                    $pdo = new PDO('sqlite:' . $source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->exec('PRAGMA wal_checkpoint(FULL)');
                    $quoted = str_replace("'", "''", $destination);
                    $pdo->exec("VACUUM INTO '{$quoted}'");
                } catch (Throwable $exception) {
                    throw new RuntimeException('Unable to create a consistent SQLite backup.', 0, $exception);
                }
                $files[] = ['source_path' => $destination, 'source' => 'sqlite/boardprep.sqlite', 'relative' => 'sqlite/boardprep.sqlite', 'bytes' => filesize($destination) ?: 0, 'sha256' => hash_file('sha256', $destination) ?: ''];
            } else {
                $files[] = ['source_path' => $source, 'source' => self::label($source, $root), 'relative' => 'sqlite/boardprep.sqlite', 'bytes' => filesize($source) ?: 0, 'sha256' => hash_file('sha256', $source) ?: ''];
            }
        } else {
            throw new RuntimeException('Backup helper supports JSON and SQLite modes; MySQL requires a database-native backup.');
        }

        if ($files === []) throw new RuntimeException('No mutable persistent state was found to back up.');
        $manifestFiles = array_map(static function (array $file): array {
            unset($file['source_path']);
            return $file;
        }, $files);
        $manifest = [
            'product' => ReleaseIdentity::current($root)['product'],
            'product_version' => ReleaseIdentity::current($root)['product_version'],
            'build_commit' => ReleaseIdentity::current($root)['build_commit'],
            'created_at' => gmdate('c'),
            'storage_mode' => $driver,
            'storage_version' => $driver === 'sqlite' ? 'database/migrations' : 'json-collections-v1',
            'canonical_content_excluded' => true,
            'files' => $manifestFiles,
        ];
        if ($dryRun) return ['status' => 'PASS', 'dry_run' => true, 'destination' => self::label($output, $root), 'manifest' => $manifest];

        if (!is_dir($output) && !mkdir($output, 0770, true) && !is_dir($output)) throw new RuntimeException('Unable to create backup destination.');
        foreach ($files as $file) {
            $destination = $output . '/' . $file['relative'];
            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('Unable to create backup directory.');
            if ((string) $file['source_path'] !== $destination && !copy((string) $file['source_path'], $destination)) {
                throw new RuntimeException('Unable to copy persistent state into backup.');
            }
        }
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (file_put_contents($output . '/backup-manifest.json', $json, LOCK_EX) === false) throw new RuntimeException('Unable to write backup manifest.');
        return ['status' => 'PASS', 'dry_run' => false, 'destination' => self::label($output, $root), 'manifest' => $manifest];
    }

    /** @return array<string,mixed> */
    public static function verify(string $root, string $backup): array
    {
        $backup = self::absolute($backup, $root);
        $manifestPath = $backup . '/backup-manifest.json';
        if (!is_file($manifestPath)) throw new RuntimeException('Backup manifest is missing.');
        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Backup manifest is malformed.', 0, $exception);
        }
        if (!is_array($manifest)) throw new RuntimeException('Backup manifest must be an object.');
        $errors = [];
        foreach ((array) ($manifest['files'] ?? []) as $file) {
            if (!is_array($file)) continue;
            $path = $backup . '/' . ltrim((string) ($file['relative'] ?? ''), '/');
            if (!is_file($path)) { $errors[] = (string) ($file['relative'] ?? ''); continue; }
            if (hash_file('sha256', $path) !== (string) ($file['sha256'] ?? '')) $errors[] = (string) ($file['relative'] ?? '');
        }
        return ['status' => $errors === [] ? 'PASS' : 'FAIL', 'manifest' => $manifest, 'errors' => $errors];
    }

    /** @return array<string,mixed> */
    public static function restore(string $root, string $backup, string $destination, bool $dryRun = false, bool $confirmLive = false): array
    {
        $verification = self::verify($root, $backup);
        if ($verification['status'] !== 'PASS') throw new RuntimeException('Backup verification failed; restore refused.');
        $backup = self::absolute($backup, $root);
        $destination = self::absolute($destination, $root);
        $live = rtrim($destination, '/') === rtrim(self::storagePath($root), '/') || rtrim($destination, '/') === rtrim(dirname(self::sqlitePath($root)), '/');
        if ($live && !$confirmLive) throw new RuntimeException('Live restore requires explicit confirmation.');
        $manifest = (array) ($verification['manifest'] ?? []);
        $plan = [];
        foreach ((array) ($manifest['files'] ?? []) as $file) {
            if (!is_array($file)) continue;
            $relative = (string) ($file['relative'] ?? '');
            $targetRelative = str_starts_with($relative, 'json/') ? substr($relative, 5) : (str_starts_with($relative, 'sqlite/') ? 'boardprep.sqlite' : basename($relative));
            $plan[] = ['source' => $relative, 'target' => $targetRelative];
        }
        if ($dryRun) return ['status' => 'PASS', 'dry_run' => true, 'destination' => self::label($destination, $root), 'plan' => $plan];
        foreach ($plan as $item) {
            $source = $backup . '/' . $item['source'];
            $target = $destination . '/' . $item['target'];
            if (is_file($target) && !$confirmLive) throw new RuntimeException('Restore target exists; use a fresh destination or explicit confirmation.');
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0770, true) && !is_dir(dirname($target))) throw new RuntimeException('Unable to create restore directory.');
            if (!copy($source, $target)) throw new RuntimeException('Unable to restore persistent state.');
        }
        return ['status' => 'PASS', 'dry_run' => false, 'destination' => self::label($destination, $root), 'plan' => $plan];
    }

    private static function storagePath(string $root): string
    {
        $path = trim((string) (Environment::get('APP_STORAGE_PATH', '') ?? ''));
        return self::absolute($path !== '' ? $path : 'storage', $root);
    }

    private static function sqlitePath(string $root): string
    {
        $path = trim((string) (Environment::get('DB_SQLITE_PATH', '') ?? ''));
        return self::absolute($path !== '' ? $path : 'storage/runtime/boardprep.sqlite', $root);
    }

    private static function absolute(string $path, string $root): string
    {
        if ($path === '' || $path[0] === '/') return $path;
        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }

    private static function label(string $path, string $root): string
    {
        $prefix = rtrim($root, '/') . '/';
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : '[external]';
    }

    private static function sourcePath(string $label, string $root): string
    {
        return str_starts_with($label, '/') ? $label : $root . '/' . ltrim($label, '/');
    }
}
