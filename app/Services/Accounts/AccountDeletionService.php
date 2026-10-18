<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Core\App;
use RuntimeException;
use Throwable;

/** Idempotent staged deletion for application-owned account records. */
final class AccountDeletionService
{
    /** @param null|callable(string):void $afterCollection Test-only fault injection point. */
    public function __construct(private $afterCollection = null)
    {
    }

    /** @return array{status:string,already_completed:bool,deleted:array<string,int>} */
    public function process(string $accountId): array
    {
        return AccountLifecycleLock::withLock(function () use ($accountId): array {
            $repository = new AccountRepository();
            $account = $repository->findById($accountId);
            if (!is_array($account)) {
                throw new RuntimeException('The requested account does not exist.');
            }
            if (($account['status'] ?? null) === 'deletion_completed') {
                return ['status' => 'deletion_completed', 'already_completed' => true, 'deleted' => []];
            }
            if (!in_array(($account['status'] ?? null), ['deletion_requested', 'deletion_processing', 'deletion_failed'], true)) {
                throw new RuntimeException('Only a confirmed deletion request can be processed.');
            }

            $learnerId = (string) ($account['learner_id'] ?? '');
            $journal = is_array($account['deletion_journal'] ?? null) ? $account['deletion_journal'] : [
                'schema_version' => 1,
                'requested_at' => (string) ($account['deletion_requested_at'] ?? gmdate(DATE_ATOM)),
                'initial_counts' => [],
                'completed_collections' => [],
            ];
            $journal['schema_version'] = 1;
            $journal['initial_counts'] = is_array($journal['initial_counts'] ?? null) ? $journal['initial_counts'] : [];
            $journal['completed_collections'] = is_array($journal['completed_collections'] ?? null)
                ? array_values($journal['completed_collections'])
                : [];

            $repository->updateById($accountId, static function (array $current) use (&$journal): array {
                if (!in_array(($current['status'] ?? null), ['deletion_requested', 'deletion_processing', 'deletion_failed'], true)) {
                    return $current;
                }
                $current['status'] = 'deletion_processing';
                $current['deletion_journal'] = $journal;
                $current['updated_at'] = gmdate(DATE_ATOM);
                return $current;
            });

            try {
                $inventoryService = new AccountDataInventoryService();
                $inventory = $inventoryService->inspectServerAccount($account);
                if ($journal['initial_counts'] === []) {
                    $journal['initial_counts'] = array_map(
                        static fn(array $collection): int => (int) $collection['count'],
                        $inventory['collections']
                    );
                }
                $repository->updateById($accountId, static function (array $current) use ($journal): array {
                    $current['deletion_journal'] = $journal;
                    return $current;
                });

                $deleted = [];
                foreach (AccountDataInventoryService::collectionDefinitions() as $storageName => $definition) {
                    $exportName = $definition['export_name'];
                    $deleted[$exportName] = 0;
                    foreach ($inventory['records'][$exportName] as $record) {
                        $id = (string) $record['id'];
                        $current = App::storage()->find($storageName, $id);
                        if ($current === null) {
                            continue;
                        }
                        if (($current['user_id'] ?? null) !== $learnerId) {
                            throw new RuntimeException('An owned data identity changed during deletion.');
                        }
                        if (App::storage()->delete($storageName, $id)) {
                            $deleted[$exportName]++;
                        } elseif (App::storage()->find($storageName, $id) !== null) {
                            throw new RuntimeException('An owned data record could not be deleted.');
                        }
                    }
                    if (App::storage()->where($storageName, ['user_id' => $learnerId]) !== []) {
                        throw new RuntimeException('Owned records remain in a collection after its deletion step.');
                    }
                    $journal['completed_collections'][] = $exportName;
                    $journal['completed_collections'] = array_values(array_unique($journal['completed_collections']));
                    $journal['last_checkpoint_at'] = gmdate(DATE_ATOM);
                    $repository->updateById($accountId, static function (array $current) use ($journal): array {
                        $current['deletion_journal'] = $journal;
                        $current['updated_at'] = gmdate(DATE_ATOM);
                        return $current;
                    });
                    if (is_callable($this->afterCollection)) {
                        ($this->afterCollection)($exportName);
                    }
                }

                $verified = $inventoryService->inspectServerAccount($account);
                foreach ($verified['collections'] as $name => $collection) {
                    if ($name === 'account_profile') {
                        continue;
                    }
                    if ((int) $collection['count'] !== 0) {
                        throw new RuntimeException('Account-owned records remain after deletion verification.');
                    }
                }

                $journal['verified_clean_at'] = gmdate(DATE_ATOM);
                if (!$repository->completeDeletion($accountId, $learnerId)) {
                    throw new RuntimeException('The account tombstone could not be committed.');
                }
                return ['status' => 'deletion_completed', 'already_completed' => false, 'deleted' => $deleted];
            } catch (Throwable $exception) {
                try {
                    $repository->updateById($accountId, static function (array $current) use (&$journal): array {
                        if (($current['status'] ?? null) !== 'deletion_completed') {
                            $current['status'] = 'deletion_failed';
                            $current['deletion_journal'] = $journal + [];
                            $current['deletion_journal']['failure_category'] = 'storage_or_integrity_failure';
                            $current['deletion_journal']['failed_at'] = gmdate(DATE_ATOM);
                            $current['updated_at'] = gmdate(DATE_ATOM);
                        }
                        return $current;
                    });
                } catch (Throwable) {
                    // The processing journal remains a safe retry point if the failure marker cannot be persisted.
                }
                if ($exception instanceof RuntimeException) {
                    throw $exception;
                }
                throw new RuntimeException('Account deletion failed and can be retried safely.', 0, $exception);
            }
        });
    }
}
