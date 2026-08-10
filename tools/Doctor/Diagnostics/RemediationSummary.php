<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

final class RemediationSummary
{
    /**
     * @param PriorityAction[] $actions
     */
    public function __construct(
        public readonly array $actions,
    ) {
    }

    public function count(): int
    {
        return count($this->actions);
    }

    public function hasActions(): bool
    {
        return $this->actions !== [];
    }

    public function top(): ?PriorityAction
    {
        return $this->actions[0] ?? null;
    }

    public function highestScore(): int
    {
        return $this->top()?->score() ?? 0;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $top = $this->top();

        return [
            'action_count' => $this->count(),
            'actionable' => $this->hasActions(),
            'highest_score' => $this->highestScore(),
            'top_action' => $top?->toArray(),
            'actions' => array_map(
                static fn(PriorityAction $action): array =>
                    $action->toArray(),
                $this->actions
            ),
        ];
    }
}
