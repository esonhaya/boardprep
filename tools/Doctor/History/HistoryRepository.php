<?php

declare(strict_types=1);

namespace Tools\Doctor\History;

final class HistoryRepository
{
    private const FILE =
        'storage/doctor/history.json';

    public function load(): array
    {
        if (!is_file(self::FILE)) {
            return [];
        }

        $history = json_decode(
            file_get_contents(self::FILE) ?: '[]',
            true
        );

        return is_array($history)
            ? $history
            : [];
    }

    public function append(
        array $entry
    ): void {

        $history =
            $this->load();

        $history[] =
            $entry;

        if (count($history) > 100) {

            $history =
                array_slice(
                    $history,
                    -100
                );

        }

        if (!is_dir('storage/doctor')) {

            mkdir(
                'storage/doctor',
                0777,
                true
            );

        }

        file_put_contents(

            self::FILE,

            json_encode(
                $history,
                JSON_PRETTY_PRINT
            )

        );

    }
}
