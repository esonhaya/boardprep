<?php

declare(strict_types=1);

namespace Tools\Doctor\Registry;

use Tools\Doctor\Engine\Doctor;

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
                    Doctor::checks()
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
