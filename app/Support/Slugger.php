<?php

class Slugger
{
    /**
     * Convert arbitrary text into a URL-safe slug.
     */
    public static function slugify(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return 'item';
        }

        $text = strtolower($text);

        // Remove apostrophes.
        $text = str_replace("'", "", $text);

        // Replace non-alphanumeric runs with a dash.
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Collapse repeated dashes.
        $text = preg_replace('/-+/', '-', $text);

        // Trim edge dashes.
        $text = trim($text, '-');

        return $text !== ''
            ? $text
            : 'item';
    }

    /**
     * Generate a unique slug.
     *
     * $key       = field containing the slug.
     * $ignoreId  = existing slug to ignore while editing.
     */
    public static function unique(
        string $text,
        array $rows,
        string $key = 'id',
        ?string $ignoreId = null
    ): string {

        $base = self::slugify($text);

        $slug = $base;

        $suffix = 2;

        while (
            self::exists(
                $slug,
                $rows,
                $key,
                $ignoreId
            )
        ) {

            $slug = $base . '-' . $suffix;

            $suffix++;

        }

        return $slug;

    }

    /**
     * Check if a slug already exists.
     */
    public static function exists(
        string $slug,
        array $rows,
        string $key = 'id',
        ?string $ignoreId = null
    ): bool {

        foreach ($rows as $row) {

            $value = (string)($row[$key] ?? '');

            if ($ignoreId !== null && $value === $ignoreId) {
                continue;
            }

            if ($value === $slug) {
                return true;
            }

        }

        return false;

    }

    /**
     * Validate an existing slug.
     */
    public static function isValid(
        string $slug
    ): bool {

        return (bool) preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $slug
        );

    }
}
