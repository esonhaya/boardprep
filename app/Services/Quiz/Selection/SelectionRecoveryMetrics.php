<?php
declare(strict_types=1);

final class SelectionRecoveryMetrics
{
    public function describe(SelectionResult $result): array
    {
        return ['shortage' => $result->shortage()];
    }
}
