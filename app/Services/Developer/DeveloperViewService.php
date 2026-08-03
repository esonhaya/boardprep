<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\ViewModels\Developer\ActionBarViewModel;
use App\ViewModels\Developer\EntityCardViewModel;
use App\ViewModels\Developer\PageHeaderViewModel;
use App\ViewModels\Developer\SummaryViewModel;

class DeveloperViewService
{
    public static function pageHeader(
        string $title,
        string $description = ""
    ): PageHeaderViewModel {

        return new PageHeaderViewModel(
            $title,
            $description
        );

    }

    public static function summary(
        array $items
    ): SummaryViewModel {

        return new SummaryViewModel(
            $items
        );

    }

    public static function actionBar(
        array $actions
    ): ActionBarViewModel {

        return new ActionBarViewModel(
            $actions
        );

    }

    public static function entity(
        array $entity,
        array $details,
        array $actions
    ): EntityCardViewModel {

        return new EntityCardViewModel(
            $entity,
            $details,
            $actions
        );

    }
}
