<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Core\App;
use RuntimeException;

/** Inventory of durable records owned by the server-issued account learner ID. */
final class AccountDataInventoryService
{
    private const COLLECTIONS = [
        'attempts' => [
            'export_name' => 'quiz_attempts',
            'ownership_field' => 'user_id',
            'schema_version' => 1,
            'json_location' => 'APP_STORAGE_PATH/attempts.json',
            'sqlite_location' => 'storage_records collection=attempts',
            'read_path' => 'AttemptRepository::byUser via LearningHistoryService::all',
            'write_path' => 'AttemptService::save',
            'export' => 'Included without internal user_id or session_id.',
            'deletion' => 'Delete only records whose user_id exactly matches the verified account learner_id.',
            'backup' => 'Included in the authenticated encrypted account archive.',
            'retention' => 'Removed after confirmed application deletion; external copies are outside this operation.',
        ],
        'weakness' => [
            'export_name' => 'weakness_summaries',
            'ownership_field' => 'user_id',
            'schema_version' => 1,
            'json_location' => 'APP_STORAGE_PATH/weakness.json',
            'sqlite_location' => 'storage_records collection=weakness',
            'read_path' => 'WeaknessStorageService::all',
            'write_path' => 'WeaknessStorageService::save and clear',
            'export' => 'Included without internal user_id.',
            'deletion' => 'Delete only records whose user_id exactly matches the verified account learner_id.',
            'backup' => 'Included in the authenticated encrypted account archive.',
            'retention' => 'Removed after confirmed application deletion; external copies are outside this operation.',
        ],
        'flashcard_state' => [
            'export_name' => 'flashcard_reviews',
            'ownership_field' => 'user_id',
            'schema_version' => 1,
            'json_location' => 'APP_STORAGE_PATH/flashcard_state.json',
            'sqlite_location' => 'storage_records collection=flashcard_state',
            'read_path' => 'FlashcardStateRepository::all, find, and where',
            'write_path' => 'FlashcardStateRepository::save, delete, and replaceAll',
            'export' => 'Included without internal user_id.',
            'deletion' => 'Delete only records whose user_id exactly matches the verified account learner_id.',
            'backup' => 'Included in the authenticated encrypted account archive.',
            'retention' => 'Removed after confirmed application deletion; external copies are outside this operation.',
        ],
    ];

    /** @return array<string,mixed> */
    public function inspectAuthenticated(): array
    {
        return AccountLifecycleLock::withLock(function (): array {
            $account = AccountSessionService::current();
            if (!is_array($account)) {
                throw new RuntimeException('Sign in again to inspect account data.');
            }
            return $this->inspectServerAccount($account);
        });
    }

    /** @param array<string,mixed> $account @return array<string,mixed> */
    public function inspectServerAccount(array $account): array
    {
        $accountId = (string) ($account['account_id'] ?? '');
        $learnerId = (string) ($account['learner_id'] ?? '');
        $stored = $accountId !== '' ? (new AccountRepository())->findById($accountId) : null;
        if (!is_array($stored)
            || ($stored['learner_id'] ?? null) !== $learnerId
            || !in_array(($stored['status'] ?? null), ['active', 'deletion_requested', 'deletion_processing', 'deletion_failed'], true)
            || preg_match('/\Aaccount-learner-[a-f0-9]{32}\z/', $learnerId) !== 1) {
            throw new RuntimeException('Account data ownership could not be verified.');
        }

        $database = App::config('database', []);
        $driver = is_array($database) ? (string) ($database['driver'] ?? 'json') : 'json';
        $collections = [];
        $records = [];
        $nonAccountOwnedCounts = ['anonymous_learner_records' => [], 'unattributed_legacy_records' => []];
        foreach (self::COLLECTIONS as $name => $definition) {
            $all = App::storage()->all($name);
            $owned = [];
            $seenIds = [];
            $nonAccountOwnedCounts['anonymous_learner_records'][$definition['export_name']] = 0;
            $nonAccountOwnedCounts['unattributed_legacy_records'][$definition['export_name']] = 0;
            foreach ($all as $record) {
                if (!is_array($record)) {
                    throw new RuntimeException("The {$name} collection failed its integrity check.");
                }
                $ownerId = $record['user_id'] ?? null;
                if ($ownerId !== $learnerId) {
                    if (is_string($ownerId) && preg_match('/\\Alearner-[a-f0-9]{32}\\z/', $ownerId) === 1) {
                        $nonAccountOwnedCounts['anonymous_learner_records'][$definition['export_name']] =
                            ($nonAccountOwnedCounts['anonymous_learner_records'][$definition['export_name']] ?? 0) + 1;
                    } elseif (!is_string($ownerId) || trim($ownerId) === '') {
                        $nonAccountOwnedCounts['unattributed_legacy_records'][$definition['export_name']] =
                            ($nonAccountOwnedCounts['unattributed_legacy_records'][$definition['export_name']] ?? 0) + 1;
                    }
                    continue;
                }
                $id = $record['id'] ?? null;
                if ((!is_string($id) && !is_int($id)) || trim((string) $id) === '' || isset($seenIds[(string) $id])) {
                    throw new RuntimeException("An owned {$name} record failed its identity check.");
                }
                $seenIds[(string) $id] = true;
                $owned[] = $record;
            }
            $collections[$definition['export_name']] = [
                'schema_version' => $definition['schema_version'],
                'count' => count($owned),
                'ownership_field' => $definition['ownership_field'],
                'storage_location' => $driver === 'sqlite'
                    ? $definition['sqlite_location']
                    : $definition['json_location'],
            ];
            $records[$definition['export_name']] = $owned;
        }

        $preferences = is_array($stored['preferences'] ?? null) ? $stored['preferences'] : [];
        $collections['account_profile'] = [
            'schema_version' => 1,
            'count' => 1,
            'ownership_field' => 'account_id',
            'storage_location' => 'private/learner-accounts/accounts.json',
            'read_path' => 'AccountRepository::findById and AccountSessionService::current',
            'write_path' => 'AccountService registration, password, preferences, recovery, and deletion transitions',
            'export' => 'Username, timestamps, and preferences are included; credential material is excluded.',
            'deletion' => 'Credentials and preferences are replaced with a minimal non-login tombstone after learner records verify clean.',
            'backup' => 'Credential/profile record is included only inside authenticated encryption.',
            'retention' => 'Application profile values are removed on completion; tombstone holds only operational identity/journal metadata.',
        ];

        return [
            'account' => $stored,
            'learner_id' => $learnerId,
            'collections' => $collections,
            'records' => $records,
            'derived' => [
                'performance_analytics' => [
                    'storage' => 'derived from quiz attempts at read time',
                    'independent_records' => 0,
                    'deletion' => 'removed when source attempts are deleted',
                    'export' => 'Represented by the source quiz-attempt collection; not exported as a second stored copy.',
                    'backup' => 'Recomputed from restored source attempts; no independent backup record.',
                    'retention' => 'Follows the source quiz-attempt records.',
                ],
                'global_question_usage' => [
                    'storage' => 'shared educational content aggregate',
                    'account_owned' => false,
                    'deletion' => 'retained; shared counters are not attributed to one account',
                ],
                'anonymous_learner_data' => [
                    'storage' => 'separate learner identity and session scope',
                    'account_owned' => false,
                    'deletion' => 'not modified by account deletion',
                ],
                'current_storage_non_account_owned_counts' => $nonAccountOwnedCounts,
                'shared_storage' => [
                    'question_usage' => 'storage/questions.json; shared content aggregate and not account-attributed',
                    'question_statistics' => 'APP_STORAGE_PATH/question-statistics.json; shared aggregate',
                    'flashcard_exclusions' => 'APP_STORAGE_PATH/flashcard-exclusions.json; shared card-selection configuration',
                ],
                'legacy_progress_repository' => [
                    'storage' => 'legacy database/progress/progress.json path is not used by current application reads/writes',
                    'independent_records' => 0,
                    'export' => 'Not included; no active account-owned collection is read or written there.',
                    'deletion' => 'No current application-owned account records were found in this inactive path.',
                    'backup' => 'Not included.',
                    'retention' => 'No application retention rule is asserted for historical copies at that path.',
                ],
            ],
            'credential_material' => [
                'ownership_field' => 'account_id',
                'storage_location' => 'private/learner-accounts/accounts.json',
                'read_path' => 'AccountRepository and AccountSessionService',
                'write_path' => 'AccountService password and recovery transitions',
                'password_hash_count' => isset($stored['password_hash']) ? 1 : 0,
                'recovery_code_digest_count' => is_array($stored['recovery_code_hashes'] ?? null) ? count($stored['recovery_code_hashes']) : 0,
                'export' => 'Excluded from account data exports.',
                'deletion' => 'Removed when credentials are replaced with the verified deletion tombstone.',
                'backup' => 'Included only inside authenticated encryption for isolated recovery.',
                'retention' => 'Removed from application credential storage on deletion completion; external copies remain outside scope.',
            ],
            'session_authorizations' => [
                'ownership_field' => 'PHP session boardprep_account_auth.account_id and learner_id',
                'storage_location' => 'PHP session handler at the deployment-configured session.save_path',
                'read_path' => 'AccountSessionService::current and resolve',
                'write_path' => 'AccountSessionService::login, refresh, logout, and resolve',
                'count' => null,
                'count_limitation' => 'Concurrent PHP session files are not indexed by account, so a global count cannot be reported accurately.',
                'export' => 'Excluded because session identifiers and authorization state are secrets.',
                'deletion' => 'All sessions fail the account status/version lookup; the current request logs out. Physical PHP session cleanup follows PHP session-handler/GC behavior.',
                'backup' => 'Excluded.',
                'retention' => 'Deployment-specific PHP session-handler behavior; no BoardPrep retention period is asserted.',
            ],
            'shared_and_anonymous_state' => [
                'global_question_usage' => 'Shared aggregate in canonical question content; not account owned and not modified by account deletion.',
                'anonymous_learning_records' => 'Use separate learner-identity values in the same learner collections; excluded from account export, backup, and deletion.',
                'legacy_attempt_copies' => 'database/attempts/attempts.json contains historical rows without account ownership fields and is not a current account storage path.',
            ],
            'excluded' => [
                'active_session_identifiers',
                'session_authorization_records',
                'csrf_tokens',
                'rate_limit_bucket_secrets',
                'password_hashes_from_exports',
                'recovery_code_hashes_from_exports',
                'private_encryption_keys',
            ],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function collectionDefinitions(): array
    {
        return self::COLLECTIONS;
    }
}
