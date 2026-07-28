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
        string $id
    ): ?array {

        foreach (self::load() as $subject) {

            if (
                ($subject["id"] ?? "")
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
        ?string $ignoreId = null
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
                ($subject["id"] ?? "") === $ignoreId
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

        $subjects = self::load();

        $subject["id"] = Slugger::unique(
            $subject["name"],
            $subjects
        );

        $subjects[] = $subject;

        self::saveAll($subjects);

    }

    public static function update(
        string $id,
        array $subject
    ): void {

        $subjects = self::load();

        foreach ($subjects as &$row) {

            if (
                ($row["id"] ?? "") === $id
            ) {

                $subject["id"] = $id;

                $row = $subject;

                break;

            }

        }

        self::saveAll($subjects);

    }

    public static function delete(
        string $id
    ): void {

        $subjects = array_filter(

            self::load(),

            fn(array $row) =>
                ($row["id"] ?? "") !== $id

        );

        self::saveAll($subjects);

    }
}
