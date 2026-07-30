<?php

declare(strict_types=1);

namespace App\Repositories;

class BlueprintRepository extends BaseRepository
{
    protected string $collection = 'blueprints';

    public function board(
        string $boardId
    ): ?array {
        $results = $this->where([
            'scope' => 'board',
            'board_id' => $boardId,
            'status' => 'active',
        ]);

        return $results[0] ?? null;
    }

    public function subject(
        string $boardId,
        string $subjectId
    ): ?array {
        $results = $this->where([
            'scope' => 'subject',
            'board_id' => $boardId,
            'subject_id' => $subjectId,
            'status' => 'active',
        ]);

        return $results[0] ?? null;
    }

public function versions(
    string $boardId,
    ?string $subjectId = null
): array {

    $criteria = [
        'board_id' => $boardId,
    ];

    if ($subjectId === null) {
        $criteria['scope'] = 'board';
    } else {
        $criteria['scope'] = 'subject';
        $criteria['subject_id'] = $subjectId;
    }

    $versions = $this->where($criteria);

    usort(
        $versions,
        static function (array $a, array $b): int {

            return version_compare(
                (string) ($b['version'] ?? '0'),
                (string) ($a['version'] ?? '0')
            );

        }
    );

    return $versions;

}


    public function activate(
        string $id
    ): ?array {

        $blueprint = $this->find($id);

        if ($blueprint === null) {
            return null;
        }

        foreach (
            $this->versions(
                $blueprint['board_id'],
                $blueprint['subject_id'] ?? null
            ) as $version
        ) {

            if (
                ($version['id'] ?? null) !== $id &&
                ($version['status'] ?? null) === 'active'
            ) {

                $this->update(
                    $version['id'],
                    [
                        'status' => 'archived',
                    ]
                );

            }

        }

        return $this->update(
            $id,
            [
                'status' => 'active',
            ]
        );
    }

    public function archive(
        string $id
    ): ?array {
        return $this->update(
            $id,
            [
                'status' => 'archived',
            ]
        );
    }
}
