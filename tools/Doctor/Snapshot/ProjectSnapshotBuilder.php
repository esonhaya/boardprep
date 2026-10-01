<?php

declare(strict_types=1);

namespace Tools\Doctor\Snapshot;

final class ProjectSnapshotBuilder
{
    public function build(): ProjectSnapshot
    {
        return (new SourceSnapshotBuilder())
            ->build(
                    static function (
                        string $path
                    ): bool {
                    $normalized = str_replace("\\", "/", $path);
                    return preg_match('~^(?:\\./)?(?:app|bootstrap|config|database|public|routes)/~', $normalized) === 1;
                }
            );
    }
}
