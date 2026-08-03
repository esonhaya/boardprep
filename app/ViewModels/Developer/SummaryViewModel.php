<?php

declare(strict_types=1);

namespace App\ViewModels\Developer;

class SummaryViewModel
{
    public function __construct(

        public readonly array $items

    ) {
    }
}
