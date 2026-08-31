<?php

declare(strict_types=1);

use App\Services\Question\QuestionService;

final class QuestionImportProcessor
{
    public function process(
        array $questions,
        QuestionImportReport $report
    ): void {
        $accepted = [];
        $seenIds = [];
        $seenTexts = [];

        foreach ($questions as $question) {
            $result = QuestionService::validateForCreate($question);
            $id = self::text($question['id'] ?? null);
            $text = self::normalizedText($question['question'] ?? null);

            if ($id !== '' && isset($seenIds[$id])) {
                $result['valid'] = false;
                $result['errors'][] = 'Duplicate ID in import payload';
            }
            if ($text !== '' && isset($seenTexts[$text])) {
                $result['valid'] = false;
                $result['errors'][] = 'Duplicate question in import payload';
            }

            if (($result['valid'] ?? false) !== true || !empty($result['errors'])) {
                $report->fail($question, implode(' ', $result['errors']));
                continue;
            }
            if (!empty($result['duplicates'])) {
                $report->skip($question, 'Duplicate question.');
                continue;
            }

            $seenIds[$id] = true;
            $seenTexts[$text] = true;
            $accepted[] = $question;
        }

        if ($report->failed !== []) {
            $report->error('Import aborted; no questions were saved.');
            return;
        }

        foreach ($accepted as $question) {
            try {
                $eligibility = is_array($question['_eligibility'] ?? null) ? $question['_eligibility'] : [];
                unset($question['_eligibility']);
                $persisted = QuestionService::save($question);
                \App\Services\Question\QuestionEligibilityService::persist($persisted, $eligibility);
                $report->success($persisted);
            } catch (\Throwable $exception) {
                $report->fail($question, $exception->getMessage());
            }
        }
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function normalizedText(mixed $value): string
    {
        $text = self::text($value);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return strtolower($text);
    }
}
