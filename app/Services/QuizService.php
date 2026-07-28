<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\QuestionRepository;

class QuizService
{
    public function __construct(
        private QuestionRepository $questions
    ) {
    }

    public function practiceQuestions(
        string $subject,
        string $topic,
        int $limit = 20
    ): array {
        $questions = $this->questions->where([
            'subject' => $subject,
            'topic' => $topic,
        ]);

        shuffle($questions);

        return array_slice($questions, 0, $limit);
    }

    public function examQuestions(
        string $subject,
        int $limit = 100
    ): array {
        $questions = $this->questions->bySubject($subject);

        shuffle($questions);

        return array_slice($questions, 0, $limit);
    }

    public function randomQuestions(
        int $limit = 20
    ): array {
        $questions = $this->questions->all();

        shuffle($questions);

        return array_slice($questions, 0, $limit);
    }
}
