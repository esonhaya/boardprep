<?php

declare(strict_types=1);

namespace App\ViewModels\Developer;

class EntityCardViewModel
{
    public function __construct(

        public readonly array $entity,

        public readonly array $details,

        public readonly array $actions

    ) {
    }
}
