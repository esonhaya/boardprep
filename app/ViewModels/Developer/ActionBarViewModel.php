<?php

declare(strict_types=1);

namespace App\ViewModels\Developer;

class ActionBarViewModel
{
    public function __construct(

        public readonly array $actions

    ) {
    }
}
