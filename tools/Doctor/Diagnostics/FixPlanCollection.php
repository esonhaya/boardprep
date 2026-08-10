<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

final class FixPlanCollection
{
    /**
     * @param FixPlan[] $plans
     */
    public function __construct(
        private array $plans = [],
    ) {
    }

    public function add(
        FixPlan $plan
    ): void {
        if ($plan->isEmpty()) {
            return;
        }

        $this->plans[] = $plan;
    }

    /**
     * @param FixPlan[] $plans
     */
    public function addMany(
        array $plans
    ): void {
        foreach ($plans as $plan) {
            $this->add($plan);
        }
    }

    /**
     * @return FixPlan[]
     */
    public function all(): array
    {
        return $this->plans;
    }

    public function count(): int
    {
        return count($this->plans);
    }

    public function isEmpty(): bool
    {
        return $this->plans === [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn(FixPlan $plan): array =>
                $plan->toArray(),
            $this->plans
        );
    }
}
