<?php

declare(strict_types=1);

namespace App\Services\Accounts;

final class AccountDataService
{
    public function exportCurrent(): array
    {
        $inventory = (new AccountDataInventoryService())->inspectAuthenticated();
        $account = $inventory['account'];
        $records = [];
        foreach ($inventory['records'] as $collection => $ownedRecords) {
            $records[$collection] = array_map(static function (array $record): array {
                unset($record['user_id'], $record['session_id']);
                return $record;
            }, $ownedRecords);
        }

        $counts = ['account_profile' => 1];
        $schemas = ['account_profile' => 1];
        foreach ($inventory['collections'] as $name => $definition) {
            $counts[$name] = (int) $definition['count'];
            $schemas[$name] = (int) $definition['schema_version'];
        }

        return [
            'format' => 'boardprep-account-data-v1',
            'schema_version' => 1,
            'exported_at' => gmdate(DATE_ATOM),
            'manifest' => [
                'included_collections' => array_keys($counts),
                'record_counts' => $counts,
                'collection_schema_versions' => $schemas,
                'excluded_secrets' => $inventory['excluded'],
                'deletion_limitations' => [
                    'application_owned_records can be removed by BoardPrep after confirmed deletion and ownership verification',
                    'provider logs, historical backups, and external copies may persist independently until their verified retention expiry',
                    'shared question usage aggregates and anonymous learner data are outside this account export and deletion scope',
                ],
            ],
            'account' => [
                'username' => (string) ($account['username'] ?? ''),
                'created_at' => (string) ($account['created_at'] ?? ''),
                'updated_at' => (string) ($account['updated_at'] ?? ''),
                'preferences' => is_array($account['preferences'] ?? null) ? $account['preferences'] : [],
            ],
            'scope' => 'Saved quiz attempts, derived weakness summaries, flashcard review state, and account preferences owned by this account.',
            'records' => $records,
        ];
    }
}
