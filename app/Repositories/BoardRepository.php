<?php

declare(strict_types=1);

namespace App\Repositories;

class BoardRepository extends BaseRepository
{
    protected string $collection = 'boards';

    public function active(): array
    {
        return $this->where([
            'status' => 'active',
        ]);
    }

    public function archived(): array
    {
        return $this->where([
            'status' => 'archived',
        ]);
    }

    public function setStatus(
        string $id,
        string $status
    ): ?array {
        return $this->update(
            $id,
            [
                'status' => $status,
            ]
        );
    }

    public function activate(
        string $id
    ): ?array {
        return $this->setStatus(
            $id,
            'active'
        );
    }

    public function archive(
        string $id
    ): ?array {
        return $this->setStatus(
            $id,
            'archived'
        );
    }
}
