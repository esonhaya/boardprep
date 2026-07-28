<?php

class EntityFactory
{
    /**
     * Create a standard BoardPrep entity.
     */
    public static function make(
        array $data,
        array $existing = [],
        ?string $ignoreId = null
    ): array {

        $now = Clock::now();

        $name = trim(
            (string)($data["name"] ?? "")
        );

        $entity = [

            "id" => Slugger::unique(
                $name,
                $existing,
                "id",
                $ignoreId
            ),

            "name" => $name,

            "description" => trim(
                (string)($data["description"] ?? "")
            ),

            "status" => $data["status"] ?? "active",

            "created_at" =>
                $data["created_at"] ?? $now,

            "updated_at" => $now,

        ];

        /*
         * Preserve any additional fields
         * while preventing core fields
         * from being overwritten.
         */
        foreach ($data as $key => $value) {

            if (!array_key_exists($key, $entity)) {

                $entity[$key] = $value;

            }

        }

        return $entity;

    }

    /**
     * Refresh update timestamp.
     */
    public static function touch(
        array $entity
    ): array {

        $entity["updated_at"] = Clock::now();

        return $entity;

    }

    /**
     * Archive entity.
     */
    public static function archive(
        array $entity
    ): array {

        $entity["status"] = "archived";

        return self::touch($entity);

    }

    /**
     * Activate entity.
     */
    public static function activate(
        array $entity
    ): array {

        $entity["status"] = "active";

        return self::touch($entity);

    }

    /**
     * Soft delete.
     */
    public static function delete(
        array $entity
    ): array {

        $entity["status"] = "deleted";

        return self::touch($entity);

    }

    /**
     * Check whether entity is active.
     */
    public static function isActive(
        array $entity
    ): bool {

        return
            ($entity["status"] ?? "")
            ===
            "active";

    }
}
