<?php

declare(strict_types=1);

final class SelectionResult
{
    public function __construct(

        public readonly array $questions,

        public readonly bool $fulfilled,

        public readonly SelectionRequest $request,

    ) {
    }

    public function count(): int
    {
        return count($this->questions);
    }

    public function shortage(): int
    {
        return max(0, $this->request->questionCount - $this->count());
    }

    public function hasShortage(): bool
    {
        return $this->shortage() > 0;
    }
}

