<?php

declare(strict_types=1);

namespace App\Services\Quiz\Result\Attempt;

final class AttemptQuestionSet
{
    public static function fromQuestions(array $questions): array
    {
        $ids = [];

        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }

            $id = AttemptValueReader::text($question["id"] ?? null);
            if ($id !== "" && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return [
            "question_count" => count($questions),
            "question_ids" => $ids,
        ];
    }
}
