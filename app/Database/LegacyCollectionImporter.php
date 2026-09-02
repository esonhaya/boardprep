<?php

declare(strict_types=1);

namespace App\Database;

use App\Contracts\StorageInterface;

final class LegacyCollectionImporter
{
    /** @return array{existing:int, imported:int, skipped:int, invalid:int} */
    public function import(StorageInterface $source, StorageInterface $target, string $collection): array
    {
        $result = ['existing' => 0, 'imported' => 0, 'skipped' => 0, 'invalid' => 0];
        foreach ($source->all($collection) as $index => $record) {
            if (!is_array($record)) {
                $result['invalid']++;
                continue;
            }
            if (!isset($record['id']) || !is_scalar($record['id']) || trim((string) $record['id']) === '') {
                $id = 'legacy-' . sha1(json_encode($record) . ':' . $index);
                $record['id'] = $id;
            } else {
                $id = (string) $record['id'];
            }
            if ($target->exists($collection, $id)) {
                $result['existing']++;
                continue;
            }
            $target->create($collection, $record);
            $result['imported']++;
        }
        $result['skipped'] = $result['existing'] + $result['invalid'];
        return $result;
    }
}
