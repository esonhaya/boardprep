<?php

declare(strict_types=1);

/** Copy static repository JSON for tests without copying learner/review state. */
final class StaticStorageFixture
{
    public static function create(string $repositoryRoot, string $label): string
    {
        $sourceRoot = rtrim($repositoryRoot, DIRECTORY_SEPARATOR) . '/storage';
        $fixtureRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . '/boardprep-' . preg_replace('/[^a-z0-9-]/i', '-', $label)
            . '-' . bin2hex(random_bytes(6));
        if (!mkdir($fixtureRoot, 0700, true)) {
            throw new RuntimeException('Unable to create isolated static-storage fixture.');
        }

        $mutableCollections = array_fill_keys([
            'academic-review-decisions', 'academic-source-reviews', 'anonymous-identities',
            'attempts', 'flashcard-state', 'flashcard_state', 'history', 'learner-progress',
            'learner-state', 'privacy', 'privacy-records', 'progress', 'question-history',
            'question-statistics', 'sessions', 'weakness',
        ], true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($sourceRoot) + 1);
            $target = $fixtureRoot . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0700, true)) {
                    throw new RuntimeException('Unable to create isolated fixture directory.');
                }
                continue;
            }
            $collection = pathinfo($item->getFilename(), PATHINFO_FILENAME);
            if (isset($mutableCollections[$collection])) continue;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) {
                throw new RuntimeException('Unable to create isolated fixture collection directory.');
            }
            if (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('Unable to copy static repository collection ' . $relative . '.');
            }
        }
        return $fixtureRoot;
    }
}
