<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Contracts\UiContract;
use Tools\Doctor\Diagnostics\DiagnosticFinding;
use Tools\Doctor\DTO\CheckResult;

final class UiContractCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $root = dirname(__DIR__, 5);
        $config = [
            'root' => $root,
            'templates_glob' => 'app/Views/**/*.php',
            'stylesheets' => ['public/assets/css/style.css', 'public/assets/css/rich-product.css'],
            'max_preview_limit' => 5,
        ];
        if (method_exists(UiContract::class, 'analyze')) {
            /** @var array<string, mixed> $analysis */
            $analysis = UiContract::analyze($root, $config);
        } else {
            // The current HayaDoctor contract exposes check/score rather than
            // the older BoardPrep-specific analyze facade. Keep this check
            // usable through a small shape adapter; Haya remains the owner of
            // the reusable UI checks.
            $templates = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app/Views', \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $templates[] = str_replace($root . '/', '', $file->getPathname());
                }
            }
            $score = UiContract::score($root, [
                'templates' => $templates,
                'stylesheets' => $config['stylesheets'],
                'scripts' => [],
            ]);
            $analysis = [
                'score' => $score['score'] ?? 0,
                'files' => count($templates),
                'counts' => [],
                'findings' => ($score['score'] ?? 0) < 100 ? [new DiagnosticFinding(
                    id: 'UI_CONTRACT_COMPATIBILITY_SCORE',
                    severity: 'WARNING',
                    category: 'UI',
                    rule: 'haya-ui-score',
                    title: 'HayaDoctor UI score',
                    message: 'HayaDoctor reported a UI score below 100%.',
                    recommendation: 'Review the HayaDoctor UI score before adding page-specific UI.'
                )] : [],
            ];
        }
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
