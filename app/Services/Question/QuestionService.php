<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Exceptions\StorageException;
use App\Exceptions\ValidationException;
use App\Repositories\QuestionRepository;
use App\Services\Shared\QuestionValidationService;

class QuestionService
{
    public static function build(int|string $id, array $input): array
    {
        $existing = trim((string) $id) !== '' && trim((string) $id) !== '0'
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

    public static function validateForCreate(array $question): array
    {
        $validation = self::validateForSave($question);
        $id = $question['id'] ?? null;

        if (is_scalar($id) && trim((string) $id) !== ''
            && self::repository()->find((string) $id) !== null) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Duplicate ID';
        }

        return $validation;
    }

    public static function save(array $question): array
    {
        self::assertValid(self::validateForCreate($question));
        $reuse = self::takeReuseMetadata($question);
        $repository = self::repository();
        $created = $repository->create($question);
        if ($reuse !== null) $repository->persistReuseMetadata($created, $reuse);
        return $created;
    }

    public static function update(int|string $id, array $question): ?array
    {
        $existing = self::repository()->find((string) $id);
        if ($existing === null) {
            throw new StorageException("Question '{$id}' does not exist.");
        }

        $question['id'] = $existing['id'];
        $question['updatedAt'] = date(DATE_ATOM);
        self::assertValid(self::validateForSave($question));
        $reuse = self::takeReuseMetadata($question);
        $repository = self::repository();
        $updated = $repository->update((string) $id, $question);
        if (is_array($updated) && $reuse !== null) $repository->persistReuseMetadata($updated, $reuse);
        return $updated;
    }

    private static function assertValid(array $validation): void
    {
        if (($validation['valid'] ?? false) === true
            && empty($validation['duplicates'] ?? [])) {
            return;
        }

        $errors = $validation['errors'] ?? [];
        if (!empty($validation['duplicates'] ?? [])) {
            $errors[] = 'Duplicate question';
        }

        throw new ValidationException(implode(' ', $errors));
    }

    private static function findDuplicates(array $question): array
    {
        return QuestionDuplicateService::find($question);
    }

    private static function repository(): QuestionRepository
    {
        return App::container()->get(QuestionRepository::class);
    }

    private static function takeReuseMetadata(array &$question): ?array
    {
        $reuse = $question['reuse'] ?? null;
        unset($question['reuse']);
        return is_array($reuse)
            && QuestionReuseMetadataService::validate($reuse) === []
            ? $reuse
            : null;
    }

}
