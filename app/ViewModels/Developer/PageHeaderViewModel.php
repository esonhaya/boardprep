<?php

declare(strict_types=1);

namespace App\ViewModels\Developer;

class PageHeaderViewModel
{
    public function __construct(

        public readonly string $title,

        public readonly string $description = ""

    ) {
    }
}
