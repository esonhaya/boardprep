<?php

$root = dirname(__DIR__, 2);

$controller = file_get_contents(
    $root . '/app/Controllers/BlueprintController.php'
);

$legacy = file_get_contents(
    $root . '/app/Services/BlueprintService.php'
);

if (
    !str_contains(
        $controller,
        'use App\\Services\\Blueprint\\BlueprintService;'
    )
) {
    throw new RuntimeException(
        'controller not using canonical blueprint service'
    );
}

if (
    !str_contains(
        $legacy,
        '\\App\\Services\\Blueprint\\BlueprintService::all()'
    )
    ||
    !str_contains(
        $legacy,
        '\\App\\Services\\Blueprint\\BlueprintService::create($data)'
    )
) {
    throw new RuntimeException(
        'legacy service not routed to canonical blueprint service'
    );
}

echo "[PASS] Blueprint UI and legacy callers share one service path.\n";
