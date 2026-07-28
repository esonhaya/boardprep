<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StorageInterface;

class QuestionRepository extends BaseRepository
{
    protected string $collection = 'questions';

    public function __construct(
        StorageInterface $storage
    ) {
        parent::__construct($storage);
    }

    public function bySubject(
        string $subject
    ): array {
        return $this->where([
            'subject' => $subject,
        ]);
    }

    public function byTopic(
        string $topic
    ): array {
        return $this->where([
            'topic' => $topic,
        ]);
    }

    public function bySubjectAndTopic(
        string $subject,
        string $topic
    ): array {
        return $this->where([
            'subject' => $subject,
            'topic' => $topic,
        ]);
    }
}
