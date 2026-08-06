<?php

declare(strict_types=1);

namespace App\Services\Developer;

final class DoctorHistoryService
{
    public function latest(
        int $limit = 10
    ): array {

        $file =
            './storage/doctor/history.json';

        if (!is_file($file)) {
            return [];
        }

        $history =
            json_decode(
                file_get_contents($file),
                true
            ) ?? [];

        return array_slice(
            array_reverse($history),
            0,
            $limit
        );

    }

    public function previous(): ?array
    {
        $history =
            $this->latest(2);

        return $history[1] ?? null;
    }

    public function current(): ?array
    {
        $history =
            $this->latest(1);

        return $history[0] ?? null;
    }

    public function compare(): array
    {
        $current =
            $this->current();

        $previous =
            $this->previous();

        if (
            $current === null
            || $previous === null
        ) {
            return [];
        }

        return [

            'health' =>
                ($current['health'] ?? 0)
                -
                ($previous['health'] ?? 0),

            'pass' =>
                ($current['pass'] ?? 0)
                -
                ($previous['pass'] ?? 0),

            'warning' =>
                ($current['warning'] ?? 0)
                -
                ($previous['warning'] ?? 0),

            'fail' =>
                ($current['fail'] ?? 0)
                -
                ($previous['fail'] ?? 0),

        ];

    }
}
