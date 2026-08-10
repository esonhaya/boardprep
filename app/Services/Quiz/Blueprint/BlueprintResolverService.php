<?php

declare(strict_types=1);

final class BlueprintResolverService
{
    public static function resolve(
        QuizSpecification $specification
    ): array {

        /*
         * Until persisted blueprint resolution is fully wired,
         * provide a deterministic runtime blueprint from the
         * requested quiz specification.
         *
         * This keeps the quiz lifecycle functional while still
         * passing through the normal blueprint executor.
         */

        $subject =
            trim((string) $specification->subject);

        $domain =
            trim((string) ($specification->domain ?? ''));

        if ($subject === '') {
            return [
                'board' => [],
                'subjects' => [],
            ];
        }

        if ($domain === '') {
            $domain = 'Language';
        }

        $boardBlueprint = [
            'version' => 1,
            'subjects' => [
                [
                    'subject' => $subject,
                    'percentage' => 100,
                ],
            ],
        ];

        $subjectBlueprint = [
            'version' => 1,
            'domains' => [
                [
                    'domain' => $domain,
                    'percentage' => 100,
                ],
            ],
            'difficulty' => [
                'easy' => 40,
                'medium' => 40,
                'hard' => 20,
            ],
        ];

        $subjectBlueprints = [
            $subject => $subjectBlueprint,
        ];

        $errors =
            BlueprintIntegrityValidator::validate(
                $boardBlueprint,
                $subjectBlueprints
            );

        if (!empty($errors)) {
            throw new RuntimeException(
                implode(' ', $errors)
            );
        }

        return [
            'board' => $boardBlueprint,
            'subjects' => $subjectBlueprints,
        ];
    }
}
