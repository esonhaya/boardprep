<?php

declare(strict_types=1);

namespace Tools\Doctor\Snapshot;

final class DoctorSnapshotBuilder
{
    public function build(): ProjectSnapshot
    {
        return (new SourceSnapshotBuilder())
            ->build(
                static function (
                    string $path
                ): bool {
                    return str_contains(
                        str_replace(
                            "\\",
                            "/",
                            $path
                        ),
                        "/tools/Doctor/"
                    );
                }
            );
    }
}
