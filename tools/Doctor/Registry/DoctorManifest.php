<?php

declare(strict_types=1);

namespace Tools\Doctor\Registry;

use Tools\Doctor\Registry\CheckRegistry;

final class DoctorManifest
{
    public function info(): array
    {
        return [

            'name' =>
                'BoardPrep Doctor',

            'version' =>
                '1.0.0',

            'engine' =>
                'Snapshot + Knowledge Graph',

            'analyzers' =>
                count(
                    (new AnalyzerRegistry())
                        ->all()
                ),

            'checks' =>
                count(
                    (new CheckRegistry())
                        ->fromDirectories([
                            dirname(__DIR__, 3)
                                . "/tools/Doctor/Project/Shared/Checks",
                            dirname(__DIR__, 3)
                                . "/tools/Doctor/Project/BoardPrep/Checks",
                            dirname(__DIR__, 3)
                                . "/tools/Doctor/Self/Checks",
                        ])
                ),

            'renderers' => [

                'Console',

                'Markdown',

                'JSON',

                'Dashboard',

            ],

            'generated' =>
                date(DATE_ATOM),

        ];
    }
}
