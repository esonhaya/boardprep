<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Shared\QuestionValidationService;

class QuestionService
{
    public static function build(int $id, array $input): array
    {
        $existing = $id > 0
            ? self::repository()->find((string) $id)
            : null;

        return QuestionBuilderService::build($id, $input, $existing);
    }

    public static function validate(array $question): array
    {
        return QuestionValidationService::validate($question);
    }

    public static function validateForSave(array $question): array
    {
        $validation = self::validate($question);

        return [
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
            'duplicates' => self::findDuplicates($question),
        ];
    }

    public static function save(array $question): array
    {
        return self::repository()->create($question);
    }

    public static function update(int|string $id, array $question): ?array
    {
        $question['updatedAt'] = date(DATE_ATOM);

        return self::repository()->update((string) $id, $question);
    }

    private static function findDuplicates(array $question): array
    {
        return QuestionDuplicateService::find($question);
    }

    private static function repository(): QuestionRepository
    {
        return App::container()->get(QuestionRepository::class);
    }
}
