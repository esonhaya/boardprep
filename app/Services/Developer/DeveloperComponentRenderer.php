<?php

declare(strict_types=1);

namespace App\Services\Developer;

class DeveloperComponentRenderer
{
    private const COMPONENT_PATH =
        __DIR__
        .
        "/../../Views/developer/components/";

    public static function pageHeader(
        object $pageHeader
    ): void {

        require self::COMPONENT_PATH
            . "page-header.php";

    }

    public static function summary(
        object $summary
    ): void {

        require self::COMPONENT_PATH
            . "summary.php";

    }

    public static function actionBar(
        object $actionBar
    ): void {

        require self::COMPONENT_PATH
            . "action-bar.php";

    }

    public static function entity(
        object $entityCard
    ): void {

        require self::COMPONENT_PATH
            . "entity-card.php";

    }

    public static function emptyState(
        string $message
    ): void {

        $emptyMessage =
            $message;

        require self::COMPONENT_PATH
            . "empty-state.php";

    }
}
