<?php
declare(strict_types=1);

final class SelectionShortagePolicy
{
    public function shouldRecover(int $shortage): bool
    {
        return $shortage > 0;
    }
}
