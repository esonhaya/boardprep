<?php

declare(strict_types=1);

namespace App\Services\Quiz\Session;

use App\Core\Response;
use App\Services\Quiz\Start\QuizStartSessionWriter;

final class QuizSessionRecoveryService
{
    public static function abandonInvalidSession(): never
    {
        QuizStartSessionWriter::clear();
        \SessionService::flash(
            'error',
            'That quiz session was stale or invalid. Please start a new quiz.'
        );
        Response::redirect('/quiz');
    }
}
