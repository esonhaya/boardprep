<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Constants\Status;

abstract class StatusRepository extends BaseRepository
{
    protected string $statusField = 'status';

    public function active(): array
    {
        return $this->where([
            $this->statusField => Status::ACTIVE,
        ]);
    }

    public function archived(): array
    {
        return $this->where([
            $this->statusField => Status::ARCHIVED,
        ]);
    }

    public function setStatus(
        string $id,
        string $status
    ): ?array {

        return $this->update(
            $id,
            [
                $this->statusField => $status,
            ]
        );

    }

    public function activate(
        string $id
    ): ?array {

        return $this->setStatus(
            $id,
            Status::ACTIVE
        );

    }

    public function archive(
        string $id
    ): ?array {

        return $this->setStatus(
            $id,
            Status::ARCHIVED
        );

    }
}
