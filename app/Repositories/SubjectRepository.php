<?php

class SubjectRepository
{
    private const FILE =
        __DIR__ .
        "/../../storage/subjects/subjects.json";

    private static function load(): array
    {
        if (!file_exists(self::FILE)) {
            return [];
        }

        $data = json_decode(
            file_get_contents(self::FILE),
            true
        );

        return is_array($data)
            ? $data
            : [];
    }

    private static function saveAll(
        array $subjects
    ): void {

        file_put_contents(

            self::FILE,

            json_encode(
                array_values($subjects),
                JSON_PRETTY_PRINT
            )

        );

    }

    public static function all(): array
    {
        return self::load();
    }

    public static function find(
        int $id
    ): ?array {

        foreach (self::load() as $subject) {

            if (
                (int)($subject["id"] ?? 0)
                ===
                $id
            ) {
                return $subject;
            }

        }

        return null;

    }

    public static function exists(
        string $name,
        ?int $ignoreId = null
    ): bool {

        foreach (self::load() as $subject) {

            if (
                strcasecmp(
                    $subject["name"] ?? "",
                    $name
                ) !== 0
            ) {
                continue;
            }

            if (
                $ignoreId !== null &&
                (int)$subject["id"] === $ignoreId
            ) {
                continue;
            }

            return true;

        }

        return false;

    }

    public static function create(
        array $subject
    ): void {

        $subjects =
            self::load();

        $maxId = 0;

        foreach ($subjects as $row) {

            $maxId = max(
                $maxId,
                (int)($row["id"] ?? 0)
            );

        }

        $subject["id"] = $maxId + 1;

        $subjects[] = $subject;

        self::saveAll(
            $subjects
        );

    }

    public static function update(
        int $id,
        array $subject
    ): void {

        $subjects =
            self::load();

        foreach ($subjects as &$row) {

            if (
                (int)$row["id"] === $id
            ) {

                $subject["id"] = $id;

                $row = $subject;

                break;

            }

        }

        self::saveAll(
            $subjects
        );

    }

    public static function delete(
        int $id
    ): void {

        $subjects = array_filter(

            self::load(),

            fn(array $row) =>
                (int)$row["id"] !== $id

        );

        self::saveAll(
            $subjects
        );

    }
}
