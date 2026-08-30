<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\DTO;

class RepositoryStatistics
{
    public int $totalQuestions = 0;

    public int $totalIssues = 0;

    public int $errors = 0;

    public int $warnings = 0;

    public int $infos = 0;

    public array $issuesByCategory = [];

    public array $issuesByValidator = [];

    public array $questionsPerDifficulty = [];

    public array $questionsPerStatus = [];

    public array $questionsPerBoard = [];

    public array $questionsPerSubject = [];

    public array $questionsPerDomain = [];

    public array $questionsPerTopic = [];

    public array $questionsPerConcept = [];

    public array $coverage = [];
}
