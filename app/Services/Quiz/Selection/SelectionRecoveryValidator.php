<?php
declare(strict_types=1);

final class SelectionRecoveryValidator
{
    public function valid(SelectionRecoveryResult $result): bool
    {
        return is_array($result->questions);
    }
}
