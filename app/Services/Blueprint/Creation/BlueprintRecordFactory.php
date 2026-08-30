<?php

declare(strict_types=1);

namespace App\Services\Blueprint\Creation;

use App\Constants\Status;

final class BlueprintRecordFactory
{
    public static function build(
        BlueprintCreationInput $input,
        int $version
    ): array {
        return [
            'id' => BlueprintIdGenerator::generate(
                $input->boardId,
                $input->subjectId,
                $version
            ),
            'scope' => 'subject',
            'board_id' => $input->boardId,
            'subject_id' => $input->subjectId,
            'board' => $input->boardId,
            'subject' => $input->subjectId,
            'name' => $input->name,
            'version' => $version,
            'status' => Status::ACTIVE,
            'questionCount' => $input->questionCount,
            'difficulty' => [
                'easy' => $input->easy,
                'medium' => $input->medium,
                'hard' => $input->hard,
            ],
            'topicWeights' => [],
            'conceptWeights' => [],
        ];
    }
}
