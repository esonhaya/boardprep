<?php

declare(strict_types=1);

final class QuizSpecificationBuilder
{
    public static function build(
        array $options = []
    ): QuizSpecification {

        $specification =
            BaseSpecificationFactory::create(
                $options
            );

        return BlueprintSpecificationMerger::merge(
            $specification,
            BlueprintResolverService::resolve(
                $specification
            )
        );

    }
}
