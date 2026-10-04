<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;

final class ReleaseIdentity
{
    /** @return array{product:string,product_version:string,release_tag:string,build_commit:string,baseline_commit:string} */
    public static function current(?string $root = null): array
    {
        $root ??= dirname(__DIR__, 2);
        $config = is_file($root . '/config/release.php')
            ? (array) require $root . '/config/release.php'
            : [];

        $product = (string) ($config['product'] ?? 'BoardPrep');
        $version = (string) ($config['product_version'] ?? '1.0.0');
        $tag = (string) ($config['release_tag'] ?? 'v1.0.0');
        $baseline = (string) ($config['baseline_commit'] ?? '');

        $manifest = self::readManifest($root);
        if ($manifest !== []) {
            $product = (string) ($manifest['product'] ?? $product);
            $version = (string) ($manifest['product_version'] ?? $version);
            $tag = (string) ($manifest['release_tag'] ?? $tag);
        }

        $buildCommit = self::environmentCommit();
        if ($buildCommit === '') {
            $buildCommit = self::gitCommit($root);
        }
        if ($buildCommit === '' && $manifest !== []) {
            $buildCommit = self::validCommit((string) ($manifest['build_commit'] ?? $manifest['release_commit'] ?? ''));
        }
        if ($buildCommit === '') {
            $buildCommit = self::validCommit($baseline);
        }

        return [
            'product' => $product,
            'product_version' => $version,
            'release_tag' => $tag,
            'build_commit' => $buildCommit,
            'baseline_commit' => $baseline,
        ];
    }

    /** @return array<string,mixed> */
    private static function readManifest(string $root): array
    {
        foreach ([$root . '/RELEASE_MANIFEST.json', $root . '/storage/release-candidate-v1.json'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            try {
                $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
                // A malformed optional manifest must not prevent the app from booting.
            }
        }
        return [];
    }

    private static function environmentCommit(): string
    {
        return self::validCommit((string) (Environment::get('BUILD_COMMIT', '') ?? ''));
    }

    private static function gitCommit(string $root): string
    {
        if (!is_dir($root . '/.git') && !is_file($root . '/.git')) {
            return '';
        }
        $commit = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null'));
        return self::validCommit($commit);
    }

    private static function validCommit(string $commit): string
    {
        return preg_match('/^[0-9a-f]{40}$/i', $commit) === 1 ? strtolower($commit) : '';
    }
}
