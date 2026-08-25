<?php

declare(strict_types=1);

final class QuizResultPersistenceGuard
{
    /**
     * Persistence requires a concrete session id and must happen only once.
     */
    public static function shouldPersist(
        array $session,
        bool $alreadyPersisted
    ): bool {
        return !empty($session["id"]) && !$alreadyPersisted;
    }
}
