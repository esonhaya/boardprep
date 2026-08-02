<?php

declare(strict_types=1);

namespace App\Repositories;

class BoardRepository extends StatusRepository
{
    protected string $collection = 'boards';
}
