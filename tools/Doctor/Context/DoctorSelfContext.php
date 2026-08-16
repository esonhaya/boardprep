<?php

declare(strict_types=1);

namespace Tools\Doctor\Context;

use Tools\Doctor\Snapshot\ProjectSnapshot;

final class DoctorSelfContext
{
    private static ?ProjectSnapshot $snapshot = null;

    public static function setSnapshot(
        ProjectSnapshot $snapshot
    ): void {
        self::$snapshot = $snapshot;
    }

    public static function snapshot():
        ProjectSnapshot
    {
        if (
            self::$snapshot === null
        ) {
            self::$snapshot =
                new ProjectSnapshot();
        }

        return self::$snapshot;
    }
}
