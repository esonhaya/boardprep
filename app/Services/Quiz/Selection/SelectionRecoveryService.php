<?php
declare(strict_types=1);

final class SelectionRecoveryService
{
    public function needsRecovery(SelectionResult $result): bool
    {
        return $result->hasShortage();
    }
}
