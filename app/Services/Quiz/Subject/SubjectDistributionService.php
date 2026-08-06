<?php

declare(strict_types=1);

final class SubjectDistributionService
{
    /**
     * @return SelectionRequest[]
     */
    public static function requests(
        array $subjectBlueprint,
        int $questionCount
    ): array {

        return BlueprintDistributionService::distribution(
            [
                "sections" =>
                    SubjectBlueprintResolver::sections(
                        $subjectBlueprint
                    )
            ],
            $questionCount
        );

    }
}
