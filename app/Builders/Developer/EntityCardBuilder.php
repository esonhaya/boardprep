<?php

declare(strict_types=1);

namespace App\Builders\Developer;

use App\Services\Developer\DeveloperViewService;

class EntityCardBuilder
{
    private array $entity = [];
    private array $details = [];
    private array $actions = [];

    public static function make(): self
    {
        return new self();
    }

    public function entity(
        array $entity
    ): self {

        $this->entity = $entity;

        return $this;
    }

    public function details(
        array $details
    ): self {

        $this->details = $details;

        return $this;
    }

    public function actions(
        array $actions
    ): self {

        $this->actions = $actions;

        return $this;
    }

    public function build()
    {
        return DeveloperViewService::entity(
            $this->entity,
            $this->details,
            $this->actions
        );
    }
}
