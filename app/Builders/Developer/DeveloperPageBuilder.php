<?php

declare(strict_types=1);

namespace App\Builders\Developer;

use App\Services\Developer\DeveloperViewService;

class DeveloperPageBuilder
{
    private array $data = [];

    public static function make(): self
    {
        return new self();
    }

    public function title(
        string $title,
        string $description = ""
    ): self {

        $this->data["pageHeader"] =
            DeveloperViewService::pageHeader(
                $title,
                $description
            );

        return $this;
    }

    public function summary(
        array $items
    ): self {

        $this->data["summary"] =
            DeveloperViewService::summary(
                $items
            );

        return $this;
    }

    public function actions(
        array $actions
    ): self {

        $this->data["actionBar"] =
            DeveloperViewService::actionBar(
                $actions
            );

        return $this;
    }

    public function entities(
        array $entities
    ): self {

        $this->data["entities"] =
            $entities;

        return $this;
    }

    public function build(): array
    {
        return $this->data;
    }
}
