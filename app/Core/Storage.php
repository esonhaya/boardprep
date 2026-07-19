<?php

class Storage
{
    public static function read(
        string $file
    ): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $json =
            file_get_contents($file);

        $data =
            json_decode(
                $json,
                true
            );

        return is_array($data)
            ? $data
            : [];
    }

    public static function write(
        string $file,
        array $data
    ): void
    {
        $directory =
            dirname($file);

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0777,
                true
            );
        }

        file_put_contents(
            $file,
            json_encode(
                $data,
                JSON_PRETTY_PRINT
            )
        );
    }
}
