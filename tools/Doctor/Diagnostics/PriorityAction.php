<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

final class PriorityAction
{
    /**
     * @param string[] $actions
     */
    public function __construct(
        public readonly string $findingId,
        public readonly string $title,
        public readonly string $priority,
        public readonly int $impact,
        public readonly string $effort,
        public readonly array $actions,
    ) {
    }

    public function score(): int
    {
        $effortPenalty = match ($this->effort) {
            'Very Small' => 0,
            'Small' => 5,
            'Medium' => 15,
            'Large' => 25,
            default => 30,
        };

        return max(
            0,
            $this->impact - $effortPenalty
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'finding_id' => $this->findingId,
            'title' => $this->title,
            'priority' => $this->priority,
            'impact' => $this->impact,
            'effort' => $this->effort,
            'score' => $this->score(),
            'actions' => $this->actions,
        ];
    }
}
