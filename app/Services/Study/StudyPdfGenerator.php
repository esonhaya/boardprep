<?php

declare(strict_types=1);

namespace App\Services\Study;

use RuntimeException;

/** Builds small, searchable PDFs from reviewed BoardPrep lesson records. */
final class StudyPdfGenerator
{
    private const VERSION = 1;
    private const OUTPUT_DIR = '/public/study-resources';
    private const MANIFEST = 'manifest.json';

    public static function build(string $root, bool $checkOnly = false): array
    {
        $lessons = self::readJson($root . '/storage/study-lessons.json');
        $sources = self::readJson($root . '/storage/sources.json');
        $sourceById = [];
        foreach ($sources as $source) {
            if (is_array($source) && is_scalar($source['id'] ?? null)) {
                $sourceById[(string) $source['id']] ??= $source;
            }
        }

        $directory = $root . self::OUTPUT_DIR;
        $manifestPath = $directory . '/' . self::MANIFEST;
        $previous = is_file($manifestPath) ? self::readJson($manifestPath) : [];
        $previousByPath = [];
        foreach (($previous['files'] ?? []) as $entry) {
            if (is_array($entry) && is_string($entry['path'] ?? null)) {
                $previousByPath[$entry['path']] = $entry;
            }
        }

        $entries = [];
        $outputs = [];
        foreach ($lessons as $lesson) {
            if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') {
                continue;
            }
            $id = self::requiredText($lesson['id'] ?? null, 'lesson id');
            $filename = self::requiredText($lesson['pdf_file'] ?? null, 'PDF filename');
            if (preg_match('/\A[a-z0-9][a-z0-9-]*\.pdf\z/', $filename) !== 1) {
                throw new RuntimeException("Unsafe PDF filename for lesson {$id}.");
            }
            $sourceRows = [];
            foreach (array_merge((array) ($lesson['official_scope_source_ids'] ?? []), (array) ($lesson['instructional_source_ids'] ?? [])) as $sourceId) {
                $sourceId = is_scalar($sourceId) ? (string) $sourceId : '';
                if ($sourceId === '' || !isset($sourceById[$sourceId])) {
                    throw new RuntimeException("Lesson {$id} has an unregistered source: {$sourceId}.");
                }
                $sourceRows[$sourceId] = $sourceById[$sourceId];
            }
            $pdf = self::render($lesson, $sourceRows);
            self::validatePdf($pdf);
            $path = $directory . '/' . $filename;
            $relative = 'public/study-resources/' . $filename;
            $oldEntry = $previousByPath[$relative] ?? null;
            if (is_file($path)) {
                $currentHash = hash_file('sha256', $path);
                if (!is_array($oldEntry) || !hash_equals((string) ($oldEntry['sha256'] ?? ''), (string) $currentHash)) {
                    throw new RuntimeException("Refusing to overwrite an untracked or modified PDF: {$relative}.");
                }
                if ($checkOnly && !hash_equals((string) $currentHash, hash('sha256', $pdf))) {
                    throw new RuntimeException("Generated PDF is stale: {$relative}.");
                }
            } elseif ($checkOnly) {
                throw new RuntimeException("Generated PDF is missing: {$relative}.");
            } elseif (is_array($oldEntry)) {
                throw new RuntimeException("Manifest records a missing generated PDF: {$relative}.");
            }

            $entry = [
                'lesson_id' => $id,
                'path' => $relative,
                'lesson_sha256' => hash('sha256', self::canonicalJson($lesson)),
                'sha256' => hash('sha256', $pdf),
                'pages' => self::pageCount($pdf),
                'searchable_text' => true,
            ];
            $entries[] = $entry;
            $outputs[$path] = $pdf;
        }

        $manifest = [
            'generator' => 'BoardPrep original lesson PDF generator',
            'generator_version' => self::VERSION,
            'content_policy' => 'Original BoardPrep instructional text with source attribution; external source documents are not reproduced.',
            'files' => $entries,
        ];
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if ($checkOnly) {
            if (!is_file($manifestPath) || file_get_contents($manifestPath) !== $manifestJson) {
                throw new RuntimeException('Study PDF manifest is missing or stale.');
            }
            return ['status' => 'PASS', 'files' => $entries, 'mode' => 'check'];
        }

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the study PDF output directory.');
        }
        foreach ($outputs as $path => $pdf) {
            if (!is_file($path) && file_put_contents($path, $pdf, LOCK_EX) !== strlen($pdf)) {
                throw new RuntimeException('Unable to write generated PDF: ' . basename($path));
            }
        }
        self::atomicWrite($manifestPath, $manifestJson);
        return ['status' => 'GENERATED', 'files' => $entries, 'mode' => 'write'];
    }

    public static function render(array $lesson, array $sourcesById): string
    {
        $blocks = [];
        $blocks[] = ['title', self::requiredText($lesson['title'] ?? null, 'lesson title')];
        $blocks[] = ['meta', strtoupper(self::text($lesson['board_id'] ?? '')) . ' · ' . self::text($lesson['subject_id'] ?? '') . ' · BoardPrep lesson v' . (int) ($lesson['version'] ?? 1)];
        $blocks[] = ['heading', 'Official examination scope'];
        $blocks[] = ['body', self::text($lesson['scope_note'] ?? 'Check the current official examination notice for scope and requirements.')];
        $blocks[] = ['heading', 'Learning objectives'];
        foreach ((array) ($lesson['learning_objectives'] ?? []) as $objective) {
            if (is_scalar($objective) && trim((string) $objective) !== '') $blocks[] = ['bullet', (string) $objective];
        }
        foreach ((array) ($lesson['sections'] ?? []) as $section) {
            if (!is_array($section)) continue;
            if (($heading = self::text($section['heading'] ?? '')) !== '') $blocks[] = ['heading', $heading];
            if (($body = self::text($section['body'] ?? '')) !== '') $blocks[] = ['body', $body];
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (is_scalar($item) && trim((string) $item) !== '') $blocks[] = ['bullet', (string) $item];
            }
        }
        foreach ((array) ($lesson['worked_examples'] ?? []) as $example) {
            if (!is_array($example)) continue;
            $blocks[] = ['heading', self::text($example['heading'] ?? 'Worked example')];
            if (($prompt = self::text($example['prompt'] ?? '')) !== '') $blocks[] = ['body', 'Question: ' . $prompt];
            foreach ((array) ($example['steps'] ?? []) as $step => $text) {
                if (is_scalar($text) && trim((string) $text) !== '') $blocks[] = ['body', ($step + 1) . '. ' . (string) $text];
            }
            if (($answer = self::text($example['answer'] ?? '')) !== '') $blocks[] = ['body', 'Answer: ' . $answer];
            if (($check = self::text($example['check'] ?? '')) !== '') $blocks[] = ['body', 'Check: ' . $check];
        }
        foreach ([['common_misconceptions', 'Common misconceptions'], ['question_patterns', 'Practice patterns']] as [$key, $heading]) {
            $items = (array) ($lesson[$key] ?? []);
            if ($items === []) continue;
            $blocks[] = ['heading', $heading];
            foreach ($items as $item) if (is_scalar($item) && trim((string) $item) !== '') $blocks[] = ['bullet', (string) $item];
        }
        $blocks[] = ['heading', 'Official scope references'];
        foreach ((array) ($lesson['official_scope_source_ids'] ?? []) as $id) {
            $source = $sourcesById[(string) $id] ?? [];
            $blocks[] = ['source', self::sourceText($source, 'Official scope')];
        }
        $blocks[] = ['heading', 'Instructional references'];
        foreach ((array) ($lesson['instructional_source_ids'] ?? []) as $id) {
            $source = $sourcesById[(string) $id] ?? [];
            $blocks[] = ['source', self::sourceText($source, 'Instructional reference')];
        }
        $blocks[] = ['meta', 'BoardPrep lesson material is original instruction. It is not an official examination document. No claim about question frequency or examination weighting is made.'];

        $pages = [[]];
        $y = 790;
        foreach ($blocks as [$style, $text]) {
            $size = match ($style) { 'title' => 19, 'heading' => 13, 'meta' => 8, default => 10 };
            $leading = match ($style) { 'title' => 25, 'heading' => 18, 'meta' => 12, default => 14 };
            $max = $style === 'title' ? 50 : ($style === 'heading' ? 72 : 94);
            $lines = self::wrap(self::pdfText($text), $max);
            if ($y - ($leading * count($lines)) < 55) { $pages[] = []; $y = 790; }
            if ($style === 'heading' && $y < 750) $y -= 5;
            foreach ($lines as $line) {
                $x = $style === 'bullet' ? 58 : 50;
                $prefix = $style === 'bullet' ? '- ' : '';
                $bold = in_array($style, ['title', 'heading'], true) ? ' /F2' : ' /F1';
                $pages[count($pages) - 1][] = sprintf("BT%s %.1f Tf %.1f %.1f Td (%s) Tj ET", $bold, (float) $size, (float) $x, (float) $y, self::escape($prefix . $line));
                $y -= $leading;
            }
            if ($style === 'heading') $y -= 3;
        }
        $total = count($pages);
        foreach ($pages as $index => &$page) {
            $page[] = sprintf('BT /F1 8 Tf 500 28 Td (BoardPrep | %d / %d) Tj ET', $index + 1, $total);
        }
        unset($page);
        return self::assemble($pages);
    }

    private static function assemble(array $pages): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        foreach ($pages as $index => $_) $kids[] = (5 + $index * 2) . ' 0 R';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($pages as $index => $commands) {
            $pageId = 5 + $index * 2;
            $streamId = $pageId + 1;
            $content = implode("\n", $commands) . "\n";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $streamId . ' 0 R >>';
            $objects[$streamId] = "<< /Length " . strlen($content) . ">>\nstream\n" . $content . "endstream";
        }
        ksort($objects);
        $pdf = "%PDF-1.4\n%BoardPrep\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        return $pdf;
    }

    private static function validatePdf(string $pdf): void
    {
        if (!str_starts_with($pdf, '%PDF-1.4') || preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $match) !== 1) {
            throw new RuntimeException('Generated study PDF has an invalid header or trailer.');
        }
        $offset = (int) $match[1];
        if (substr($pdf, $offset, 5) !== "xref\n") throw new RuntimeException('Generated study PDF xref offset is invalid.');
        $size = self::pageCount($pdf);
        if ($size < 1) throw new RuntimeException('Generated study PDF has no pages.');
    }

    private static function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type \/Page\b/', $pdf, $matches);
    }

    private static function sourceText(array $source, string $kind): string
    {
        $title = self::text($source['title'] ?? 'Registered source');
        $authority = self::text($source['authority'] ?? '');
        $url = self::text($source['url'] ?? '');
        return $kind . ': ' . $title . ($authority !== '' ? ' — ' . $authority : '') . ($url !== '' ? ' — ' . $url : '');
    }

    private static function readJson(string $path): array
    {
        if (!is_file($path)) throw new RuntimeException('Missing source content: ' . $path);
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Expected a JSON list: ' . $path);
        return $data;
    }

    private static function atomicWrite(string $path, string $contents): void
    {
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to safely update study PDF manifest.');
        }
    }

    private static function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function requiredText(mixed $value, string $label): string
    {
        $text = self::text($value);
        if ($text === '') throw new RuntimeException('Missing ' . $label . ' in study lesson.');
        return $text;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function pdfText(string $text): string
    {
        $normalized = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) : $text;
        return $normalized === false ? $text : $normalized;
    }

    private static function escape(string $text): string
    {
        return str_replace(["\\", '(', ')', "\r", "\n"], ["\\\\", '\\(', '\\)', '', ''], $text);
    }

    private static function wrap(string $text, int $width): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($line !== '' && strlen($line . ' ' . $word) > $width) { $lines[] = $line; $line = ''; }
            while (strlen($word) > $width) { $lines[] = substr($word, 0, $width); $word = substr($word, $width); }
            $line = $line === '' ? $word : $line . ' ' . $word;
        }
        if ($line !== '' || $lines === []) $lines[] = $line;
        return $lines;
    }
}
