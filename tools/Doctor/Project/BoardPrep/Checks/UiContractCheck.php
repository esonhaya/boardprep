<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Contracts\UiContract;
use Tools\Doctor\DTO\CheckResult;

final class UiContractCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $root = dirname(__DIR__, 5);
        $analysis = UiContract::analyze($root, [
            'root' => $root,
            'templates_glob' => 'app/Views/**/*.php',
            'stylesheets' => ['public/assets/css/style.css', 'public/assets/css/rich-product.css'],
            'max_preview_limit' => 5,
        ]);
        $counts = $analysis['counts'];
        $findings = $analysis['findings'];
        $summary = sprintf('UI health %d%% across %d production templates; %d finding(s).', $analysis['score'], $analysis['files'], count($findings));
        $details = [];
        foreach (['ui.legacy.presentational_table','ui.table.mobile_risk','ui.heading.wrap_risk','ui.collection.preview_too_large','ui.missing.view_all','ui.action.emoji','ui.form.unlabelled','ui.archetype.incomplete'] as $id) {
            $details[] = strtoupper(str_replace(['ui.','_','.'], ['', '_', '_'], $id)) . '=' . ($counts[$id] ?? 0);
        }
        $result = new CheckResult(
            title: 'UI Contract Engine',
            status: $findings === [] ? 'PASS' : 'WARNING',
            summary: $summary,
            details: $details,
            recommendations: $findings === [] ? [] : ['Adopt shared ui-* components and explicit responsive contracts before adding page-specific styling.'],
            score: $analysis['score'],
        );
        $result->addFindings($findings);
        return $result;
    }

    public function category(): string
    {
        return 'UI';
    }

    public function priority(): int
    {
        return 80;
    }
}
