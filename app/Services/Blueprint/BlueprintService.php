<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Core\App;
use App\Repositories\BlueprintRepository;
use App\Services\Blueprint\Creation\BlueprintCreationService;

final class BlueprintService
{
    public static function all(): array
    {
        return self::repository()->all();
    }

    public static function create(array $data): array
    {
        return BlueprintCreationService::create(
            self::repository(),
            $data
        );
    }

    private static function repository(): BlueprintRepository
    {
        return App::container()->get(BlueprintRepository::class);
    }
}
