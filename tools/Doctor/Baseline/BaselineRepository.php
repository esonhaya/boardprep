<?php

declare(strict_types=1);

namespace Tools\Doctor\Baseline;

final class BaselineRepository
{
    private const FILE =
        "storage/doctor/baseline.json";

    public function load(): array
    {
        if (!is_file(self::FILE)) {
            return [];
        }

        $json = file_get_contents(
            self::FILE
        );

        if ($json === false) {
            return [];
        }

        $baseline =
            json_decode(
                $json,
                true
            );

        return is_array($baseline)
            ? $baseline
            : [];
    }

    public function save(
        array $baseline
    ): void {

        if (!is_dir("storage/doctor")) {

            mkdir(
                "storage/doctor",
                0777,
                true
            );

        }

        file_put_contents(

            self::FILE,

            json_encode(
                $baseline,
                JSON_PRETTY_PRINT
            )

        );

    }

    public function exists(): bool
    {
        return is_file(
            self::FILE
        );
    }
}
