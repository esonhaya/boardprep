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

    public array $boards = [];

    /** @var array{canonical:int, effective_declared:int, runtime:int} */
    public array $totals = ['canonical' => 0, 'effective_declared' => 0, 'runtime' => 0];

    public function toArray(): array
    {
        return [
            'total_questions' => $this->totalQuestions,
            'total_issues' => $this->totalIssues,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'infos' => $this->infos,
            'issues_by_category' => $this->issuesByCategory,
            'issues_by_validator' => $this->issuesByValidator,
            'questions_per_difficulty' => $this->questionsPerDifficulty,
            'questions_per_status' => $this->questionsPerStatus,
            'questions_per_board' => $this->questionsPerBoard,
            'questions_per_subject' => $this->questionsPerSubject,
            'questions_per_domain' => $this->questionsPerDomain,
            'questions_per_topic' => $this->questionsPerTopic,
            'questions_per_concept' => $this->questionsPerConcept,
            'coverage' => $this->coverage,
            'boards' => $this->boards,
            'totals' => $this->totals,
        ];
    }
}
