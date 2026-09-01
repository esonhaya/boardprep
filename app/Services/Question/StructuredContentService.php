<?php

declare(strict_types=1);

namespace App\Services\Question;

final class StructuredContentService
{
    private const TYPES = ['text', 'equation', 'table', 'figure', 'chart'];

    public static function validate(array $question): array
    {
        $errors = [];
        $blocks = $question['content_blocks'] ?? [];
        if ($blocks === []) {
            return $errors;
        }
        if (!is_array($blocks) || array_is_list($blocks) === false) {
            return ['Invalid structured content blocks'];
        }
        foreach ($blocks as $index => $block) {
            if (!is_array($block) || !in_array($block['type'] ?? '', self::TYPES, true)) {
                $errors[] = "Invalid structured block {$index}";
                continue;
            }
            $type = $block['type'];
            if ($type === 'text' || $type === 'equation') {
                if (trim((string) ($block['value'] ?? '')) === '') {
                    $errors[] = "Empty {$type} block {$index}";
                }
            } elseif ($type === 'table' || $type === 'chart') {
                self::validateTable($block, $index, $errors);
            } elseif ($type === 'figure') {
                if (trim((string) ($block['asset'] ?? '')) === '') {
                    $errors[] = "Missing figure asset {$index}";
                }
                if (trim((string) ($block['alt'] ?? '')) === '') {
                    $errors[] = "Missing figure alt text {$index}";
                }
                if (str_contains((string) ($block['asset'] ?? ''), '<') || str_contains((string) ($block['asset'] ?? ''), '://') || str_contains((string) ($block['asset'] ?? ''), '..')) {
                    $errors[] = "Unsafe figure asset {$index}";
                }
            }
        }
        return $errors;
    }

    private static function validateTable(array $block, int|string $index, array &$errors): void
    {
        $columns = $block['columns'] ?? [];
        $rows = $block['rows'] ?? [];
        if (!is_array($columns) || $columns === [] || !is_array($rows)) {
            $errors[] = "Malformed table block {$index}";
            return;
        }
        foreach ($columns as $column) {
            if (!is_string($column) || trim($column) === '') {
                $errors[] = "Invalid table header {$index}";
            }
        }
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== count($columns)) {
                $errors[] = "Invalid table row {$index}";
            }
        }
    }

    public static function render(array $question): string
    {
        $html = '';
        foreach (($question['content_blocks'] ?? []) as $block) {
            if (!is_array($block)) continue;
            $type = $block['type'] ?? '';
            if ($type === 'equation') {
                $value = htmlspecialchars((string) ($block['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                $fallback = htmlspecialchars((string) ($block['fallback'] ?? $block['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                $html .= '<div class="question-equation" role="img" aria-label="' . $fallback . '"><code>' . $value . '</code></div>';
            } elseif ($type === 'table' || $type === 'chart') {
                $html .= '<div class="question-table-wrap"><table class="question-data"><thead><tr>';
                foreach (($block['columns'] ?? []) as $column) $html .= '<th scope="col">' . htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8') . '</th>';
                $html .= '</tr></thead><tbody>';
                foreach (($block['rows'] ?? []) as $row) { $html .= '<tr>'; foreach ($row as $cell) $html .= '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>'; $html .= '</tr>'; }
                $html .= '</tbody></table></div>';
            } elseif ($type === 'figure') {
                $asset = htmlspecialchars((string) ($block['asset'] ?? ''), ENT_QUOTES, 'UTF-8');
                $alt = htmlspecialchars((string) ($block['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
                $assetPath = dirname(__DIR__, 3) . '/public/' . ltrim((string) ($block['asset'] ?? ''), '/');
                $html .= '<figure class="question-figure">' . (is_file($assetPath) ? '<img src="/' . ltrim($asset, '/') . '" alt="' . $alt . '">' : '<p role="status">Figure unavailable: ' . $alt . '</p>');
                if (trim((string) ($block['caption'] ?? '')) !== '') $html .= '<figcaption>' . htmlspecialchars((string) $block['caption'], ENT_QUOTES, 'UTF-8') . '</figcaption>';
                $html .= '</figure>';
            }
        }
        return $html;
    }
}
