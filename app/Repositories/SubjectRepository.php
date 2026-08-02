<?php

declare(strict_types=1);

namespace App\Repositories;

class SubjectRepository extends StatusRepository
{
    protected string $collection = 'subjects';

    public function existsByName(
        string $name,
        ?string $ignoreId = null
    ): bool {

        foreach ($this->all() as $subject) {

            if (
                strcasecmp(
                    (string) ($subject['name'] ?? ''),
                    $name
                ) !== 0
            ) {
                continue;
            }

            if (
                $ignoreId !== null &&
                ($subject['id'] ?? '') === $ignoreId
            ) {
                continue;
            }

            return true;

        }

        return false;

    }
}
