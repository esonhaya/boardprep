<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\DTO;

class RepositoryContext
{
    public array $questions = [];

    public array $boards = [];

    public array $subjects = [];

    public array $blueprints = [];

    public array $taxonomy = [];
}
