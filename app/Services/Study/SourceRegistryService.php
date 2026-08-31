<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Core\App;

final class SourceRegistryService
{
    public static function all(): array
    {
        return array_values(array_filter(App::storage()->all('sources'), 'is_array'));
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $source) {
            if ((string) ($source['id'] ?? '') === $id) {
                return $source;
            }
        }
        return null;
    }

    public static function validate(): array
    {
        $validTypes = ['PRIMARY_LAW', 'OFFICIAL_EXAM_SPEC', 'OFFICIAL_PROGRAM', 'OFFICIAL_GUIDANCE', 'SECONDARY_REVIEW', 'COMMUNITY_REPORT', 'BOARDPREP_EDITORIAL'];
        $invalid = [];
        $ids = [];
        foreach (self::all() as $source) {
            $id = (string) ($source['id'] ?? '');
            if ($id === '' || isset($ids[$id]) || !in_array((string) ($source['source_type'] ?? ''), $validTypes, true)
                || trim((string) ($source['title'] ?? '')) === '' || trim((string) ($source['authority'] ?? '')) === '') {
                $invalid[] = $id === '' ? '<missing>' : $id;
            }
            $ids[$id] = true;
        }
        return ['valid' => $invalid === [], 'invalid' => $invalid, 'total' => count(self::all())];
    }
}
