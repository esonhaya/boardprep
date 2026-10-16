<?php

declare(strict_types=1);

final class SelectionSession
{
    private array $selectedIds = [];
    private array $selectedContent = [];

    public function available(
        array $questions,
        bool $includeContentDuplicates = false
    ): array {

        return array_values(

            array_filter(

                $questions,

                function (
                    mixed $question
                ) use ($includeContentDuplicates): bool {

                    if (!is_array($question)) {
                        return false;
                    }

                    $id =
                        $question["id"] ?? null;

                    if ($id === null || !is_scalar($id) || trim((string) $id) === '') {
                        return true;
                    }

                    if (isset($this->selectedIds[(string) $id])) {
                        return false;
                    }

                    if (!$includeContentDuplicates) {
                        $contentKey = self::contentKey($question);
                        if ($contentKey !== null && isset($this->selectedContent[$contentKey])) {
                            return false;
                        }
                    }

                    return true;

                }

            )

        );

    }

    public function reserve(
        array $questions
    ): void {

        foreach ($questions as $question) {

            if (is_array($question) && is_scalar($question["id"] ?? null) && trim((string) $question["id"]) !== '') {

                $this->selectedIds[
                    (string) $question["id"]
                ] = true;

            }

            if (is_array($question)) {
                $contentKey = self::contentKey($question);
                if ($contentKey !== null) {
                    $this->selectedContent[$contentKey] = true;
                }
            }

        }

    }

    public function selectedIds(): array
    {

        return array_keys(
            $this->selectedIds
        );

    }

    /**
     * Identify the learner-facing task independently of record ID, taxonomy,
     * rationale, or choice order. A different set of choices remains a
     * distinct task even when the stem is reused.
     */
    private static function contentKey(array $question): ?string
    {
        $stem = $question['question'] ?? null;
        $choices = $question['choices'] ?? null;
        if (!is_scalar($stem) || !is_array($choices) || count($choices) < 2) {
            return null;
        }

        $normalize = static function (string $value): string {
            $value = preg_replace('/\\s+/u', ' ', trim($value)) ?? trim($value);
            return strtolower($value);
        };
        $normalizedChoices = [];
        foreach ($choices as $choice) {
            if (!is_scalar($choice)) {
                return null;
            }
            $normalizedChoices[] = $normalize((string) $choice);
        }
        sort($normalizedChoices, SORT_STRING);

        $identity = json_encode([
            $normalize((string) $stem),
            $normalizedChoices,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($identity) ? hash('sha256', $identity) : null;
    }
}
