<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Core\App;
use RuntimeException;

final class AccountDataService
{
    private const COLLECTIONS = [
        'attempts' => 'quiz_attempts',
        'weakness' => 'weakness_summaries',
        'flashcard_state' => 'flashcard_reviews',
    ];

    public function exportCurrent(): array
    {
        $account = AccountSessionService::current();
        if (!is_array($account)) {
            throw new RuntimeException('Sign in again to export account data.');
        }

        $userId = (string) $account['learner_id'];
        $records = [];
        foreach (self::COLLECTIONS as $collection => $label) {
            $owned = App::storage()->where($collection, ['user_id' => $userId]);
            $records[$label] = array_map(static function (array $record) use ($collection): array {
                unset($record['user_id'], $record['session_id']);
                if ($collection !== 'attempts') {
                    unset($record['id']);
                }
                return $record;
            }, array_values(array_filter($owned, 'is_array')));
        }

        return [
            'format' => 'boardprep-account-data-v1',
            'exported_at' => gmdate(DATE_ATOM),
            'account' => [
                'username' => $account['username'],
                'created_at' => $account['created_at'],
                'preferences' => $account['preferences'],
            ],
            'scope' => 'Saved quiz attempts, derived weakness summaries, flashcard review state, and exam preferences owned by this account.',
            'records' => $records,
        ];
    }
}
