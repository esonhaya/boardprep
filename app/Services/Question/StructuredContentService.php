<?php

declare(strict_types=1);

namespace App\Services\Question;

final class StructuredContentService
{
    private const FIGURE_EXTENSIONS = ['svg', 'png', 'jpg', 'jpeg', 'webp'];

    /**
     * Canonical production shapes. Optional fields are explicit so producer,
     * validator, renderer, and flashcard consumers share one contract.
     *
     * @return array<string, array{required: string[], optional: string[]}>
     */
    public static function schema(): array
    {
        return [
            'text' => ['required' => ['value'], 'optional' => ['fallback', 'label']],
            'equation' => ['required' => ['value'], 'optional' => ['fallback']],
            'table' => ['required' => ['columns', 'rows'], 'optional' => ['title', 'value']],
            'chart' => ['required' => ['columns', 'rows'], 'optional' => ['title', 'value']],
            'figure' => ['required' => ['asset', 'alt'], 'optional' => ['caption']],
        ];
    }

    /** Keep the established string API for existing application callers. */
    public static function validate(array $question): array
    {
        return array_map(
            static fn(array $issue): string => $issue['message'],
            self::validateDetailed($question)
        );
    }

    /**
     * Machine-readable structured-content contract failures.
     *
     * @return array<int, array{code: string, message: string, path: string}>
     */
    public static function validateDetailed(array $question): array
    {
        $errors = [];
        $blocks = $question['content_blocks'] ?? [];
        if ($blocks === []) {
            return $errors;
        }
        if (!is_array($blocks) || array_is_list($blocks) === false) {
            return [self::issue('INVALID_NESTING', 'Invalid structured content blocks', 'content_blocks')];
        }

        foreach ($blocks as $index => $block) {
            $path = 'content_blocks.' . $index;
            if (!is_array($block)) {
                $errors[] = self::issue('MALFORMED_BLOCK', "Malformed structured block {$index}", $path);
                continue;
            }

            $type = $block['type'] ?? null;
            if (!is_string($type) || !array_key_exists($type, self::schema())) {
                $errors[] = self::issue('UNSUPPORTED_BLOCK_TYPE', "Invalid structured block {$index}", $path . '.type');
                continue;
            }

            if ($type === 'text' || $type === 'equation') {
                self::validateScalarField($block, 'value', $type . ' block', $path, $errors);
                self::validateOptionalScalarField($block, 'fallback', $path, $errors);
                if ($type === 'text') {
                    self::validateOptionalScalarField($block, 'label', $path, $errors);
                }
                continue;
            }

            if ($type === 'table' || $type === 'chart') {
                self::validateTable($block, $index, $errors);
                self::validateOptionalScalarField($block, 'title', $path, $errors);
                self::validateOptionalScalarField($block, 'value', $path, $errors);
                continue;
            }

            self::validateFigure($block, $index, $errors);
        }

        return $errors;
    }

    private static function validateTable(array $block, int|string $index, array &$errors): void
    {
        $path = 'content_blocks.' . $index;
        $columns = $block['columns'] ?? null;
        $rows = $block['rows'] ?? null;
        if (!is_array($columns) || $columns === [] || !is_array($rows)) {
            $errors[] = self::issue('MALFORMED_BLOCK', "Malformed table block {$index}", $path);
            return;
        }
        foreach ($columns as $column) {
            if (!is_string($column) || trim($column) === '') {
                $errors[] = self::issue('INVALID_FIELD_TYPE', "Invalid table header {$index}", $path . '.columns');
            }
        }
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== count($columns)) {
                $errors[] = self::issue('INVALID_NESTING', "Invalid table row {$index}", $path . '.rows');
                continue;
            }
            foreach ($row as $cell) {
                if (!is_scalar($cell)) {
                    $errors[] = self::issue('INVALID_FIELD_TYPE', "Invalid table cell {$index}", $path . '.rows');
                }
            }
        }
    }

    private static function validateFigure(array $block, int|string $index, array &$errors): void
    {
        $path = 'content_blocks.' . $index;
        self::validateScalarField($block, 'asset', 'figure asset', $path, $errors);
        self::validateScalarField($block, 'alt', 'figure alt text', $path, $errors);
        self::validateOptionalScalarField($block, 'caption', $path, $errors);

        $asset = is_scalar($block['asset'] ?? null) ? trim((string) $block['asset']) : '';
        if ($asset !== '' && self::unsafeFigureAsset($asset)) {
            $errors[] = self::issue('INVALID_REFERENCE', "Unsafe figure asset {$index}", $path . '.asset');
        } elseif ($asset !== '' && !is_file(self::assetPath($asset))) {
            $errors[] = self::issue('INVALID_REFERENCE', "Missing figure file {$index}", $path . '.asset');
        }

        $extension = strtolower((string) pathinfo($asset, PATHINFO_EXTENSION));
        if ($asset !== '' && !in_array($extension, self::FIGURE_EXTENSIONS, true)) {
            $errors[] = self::issue('UNSUPPORTED_SHAPE', "Unsupported figure type {$index}", $path . '.asset');
        }
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            self::validateRasterProvenance($block, $index, $errors);
        }
    }

    private static function validateScalarField(array $block, string $field, string $label, string $path, array &$errors): void
    {
        if (!array_key_exists($field, $block)) {
            $errors[] = self::issue('MISSING_REQUIRED_FIELD', "Missing {$field} in {$label}", $path . '.' . $field);
            return;
        }
        if (!is_scalar($block[$field])) {
            $errors[] = self::issue('INVALID_FIELD_TYPE', "Invalid {$field} field in {$label}", $path . '.' . $field);
            return;
        }
        if (trim((string) $block[$field]) === '') {
            $errors[] = self::issue('EMPTY_REQUIRED_VALUE', "Empty {$label}", $path . '.' . $field);
        }
    }

    private static function validateOptionalScalarField(array $block, string $field, string $path, array &$errors): void
    {
        if (array_key_exists($field, $block) && !is_scalar($block[$field])) {
            $errors[] = self::issue('INVALID_FIELD_TYPE', "Invalid {$field} field", $path . '.' . $field);
        }
    }

    /** @return array{code: string, message: string, path: string} */
    private static function issue(string $code, string $message, string $path): array
    {
        return ['code' => $code, 'message' => $message, 'path' => $path];
    }

    public static function render(array $question): string
    {
        $html = '';
        foreach (($question['content_blocks'] ?? []) as $block) {
            if (!is_array($block)) continue;
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $value = htmlspecialchars((string) ($block['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                $html .= '<p class="question-text-block">' . $value . '</p>';
            } elseif ($type === 'equation') {
                $value = htmlspecialchars((string) ($block['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                $fallback = htmlspecialchars((string) ($block['fallback'] ?? $block['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                $html .= '<div class="question-equation" role="img" aria-label="' . $fallback . '"><code>' . $value . '</code></div>';
            } elseif ($type === 'table' || $type === 'chart') {
                $html .= '<div class="question-table-wrap" role="region" aria-label="Scrollable question data" tabindex="0"><table class="question-data"><thead><tr>';
                foreach (($block['columns'] ?? []) as $column) $html .= '<th scope="col">' . htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8') . '</th>';
                $html .= '</tr></thead><tbody>';
                foreach (($block['rows'] ?? []) as $row) { $html .= '<tr>'; foreach ($row as $cell) $html .= '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>'; $html .= '</tr>'; }
                $html .= '</tbody></table></div>';
            } elseif ($type === 'figure') {
                $asset = htmlspecialchars((string) ($block['asset'] ?? ''), ENT_QUOTES, 'UTF-8');
                $alt = htmlspecialchars((string) ($block['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
                $assetPath = self::assetPath((string) ($block['asset'] ?? ''));
                $html .= '<figure class="question-figure">' . (is_file($assetPath) ? '<img src="/' . ltrim($asset, '/') . '" alt="' . $alt . '">' : '<p role="status">Figure unavailable: ' . $alt . '</p>');
                if (trim((string) ($block['caption'] ?? '')) !== '') $html .= '<figcaption>' . htmlspecialchars((string) $block['caption'], ENT_QUOTES, 'UTF-8') . '</figcaption>';
                $html .= '</figure>';
            }
        }
        return $html;
    }

    private static function assetPath(string $asset): string
    {
        return dirname(__DIR__, 3) . '/public/' . ltrim($asset, '/');
    }

    private static function unsafeFigureAsset(string $asset): bool
    {
        return $asset === ''
            || str_starts_with($asset, '/')
            || str_contains($asset, '\\')
            || str_contains($asset, '<')
            || str_contains($asset, '://')
            || str_contains($asset, '..')
            || preg_match('/[\x00-\x1F\x7F]/', $asset) === 1;
    }

    private static function validateRasterProvenance(array $block, int|string $index, array &$errors): void
    {
        $provenance = $block['provenance'] ?? null;
        if (!is_array($provenance)) {
            $errors[] = self::issue('MISSING_REQUIRED_FIELD', "Missing raster provenance {$index}", 'content_blocks.' . $index . '.provenance');
            return;
        }

        $status = strtoupper(trim((string) ($provenance['status'] ?? '')));
        $allowedStatuses = ['VERIFIED', 'INTERNAL', 'PUBLIC_DOMAIN', 'LICENSED'];
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = self::issue('INVALID_FIELD_TYPE', "Invalid raster provenance status {$index}", 'content_blocks.' . $index . '.provenance.status');
        }
        if (trim((string) ($provenance['creator'] ?? '')) === '') {
            $errors[] = self::issue('MISSING_REQUIRED_FIELD', "Missing raster creator {$index}", 'content_blocks.' . $index . '.provenance.creator');
        }
        if ($status !== 'INTERNAL' && trim((string) ($provenance['source_url'] ?? '')) === '') {
            $errors[] = self::issue('MISSING_REQUIRED_FIELD', "Missing raster source URL {$index}", 'content_blocks.' . $index . '.provenance.source_url');
        }
        if ($status !== 'INTERNAL' && trim((string) ($provenance['license'] ?? '')) === '') {
            $errors[] = self::issue('MISSING_REQUIRED_FIELD', "Missing raster license {$index}", 'content_blocks.' . $index . '.provenance.license');
        }
        if ($status === 'LICENSED' && trim((string) ($provenance['attribution'] ?? '')) === '') {
            $errors[] = self::issue('MISSING_REQUIRED_FIELD', "Missing raster attribution {$index}", 'content_blocks.' . $index . '.provenance.attribution');
        }
    }
}
