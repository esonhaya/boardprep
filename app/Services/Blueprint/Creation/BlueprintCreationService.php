<?php

declare(strict_types=1);

namespace App\Services\Blueprint\Creation;

use App\Repositories\BlueprintRepository;
use App\Services\Shared\BlueprintValidator;

final class BlueprintCreationService
{
    public static function create(
        BlueprintRepository $repository,
        array $data
    ): array {
        $input = BlueprintCreationInput::from($data);
        $candidate = BlueprintRecordFactory::build($input, 1);
        $validation = BlueprintValidator::validate($candidate);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        $version = BlueprintVersionResolver::next(
            $repository,
            $input->boardId,
            $input->subjectId
        );
        $blueprint = BlueprintRecordFactory::build($input, $version);

        $repository->create($blueprint);

        return [
            'success' => true,
            'blueprint' => $blueprint,
        ];
    }
}
