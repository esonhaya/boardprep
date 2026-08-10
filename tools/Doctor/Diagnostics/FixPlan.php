<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

final class FixPlan
{
    /**
     * @param array<string,mixed> $finding
     * @param string[] $actions
     */
    public function __construct(
        public readonly array $finding,
        public readonly array $actions = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->finding === [];
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'finding' => $this->finding,
            'actions' => $this->actions,
        ];
    }
}
