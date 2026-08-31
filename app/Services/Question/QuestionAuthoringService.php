<?php

declare(strict_types=1);

namespace App\Services\Question;

use Throwable;
use App\Services\Question\Authoring\QuestionAuthoringDecision;
use App\Services\Question\Authoring\QuestionAuthoringPersistence;

final class QuestionAuthoringService
{
    public static function prepare(int|string $id, array $input): array
    {
        $question = QuestionService::build($id, $input);
        $validation = QuestionService::validateForSave($question);

        return [
            'question' => $question,
            'valid' => $validation['valid'] ?? false,
            'errors' => $validation['errors'] ?? [],
            'duplicates' => $validation['duplicates'] ?? [],
        ];
    }

    public static function canSave(int|string $id, array $input): bool
    {
        return QuestionAuthoringDecision::allows(
            self::prepare($id, $input)
        );
    }

    public static function submit(int|string $id, array $input): array
    {
        $result = self::prepare($id, $input);
        $result['saved'] = false;
        $result['persisted'] = null;

        if (!QuestionAuthoringDecision::allows($result)) {
            return $result;
        }

        try {
            $result['persisted'] = QuestionAuthoringPersistence::persist(
                $id,
                $result['question']
            );
        } catch (Throwable $exception) {
            $result['errors'][] = $exception->getMessage();
            return $result;
        }
        $result['saved'] = $result['persisted'] !== null;

        return $result;
    }

    public static function save(int|string $id, array $input): ?array
    {
        return self::submit($id, $input)['persisted'];
    }
}
