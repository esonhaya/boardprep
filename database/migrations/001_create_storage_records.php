<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        'CREATE TABLE storage_records ('
        . 'collection TEXT NOT NULL, '
        . 'record_id TEXT NOT NULL, '
        . 'payload TEXT NOT NULL, '
        . 'created_at TEXT NOT NULL, '
        . 'updated_at TEXT NOT NULL, '
        . 'PRIMARY KEY (collection, record_id))'
    );
};
