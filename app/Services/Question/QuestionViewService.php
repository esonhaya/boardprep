<?php

declare(strict_types=1);

class QuestionViewService
{
    public static function taxonomy(
        array $context = []
    ): array {

        $subjects =
            TaxonomyStorageService::subjects();

        $domains =
            empty($context["subject"])
                ? TaxonomyStorageService::domains()
                : TaxonomyStorageService::domainsBySubject(
                    $context["subject"]
                );

        $topics =
            empty($context["domain"])
                ? TaxonomyStorageService::topics()
                : TaxonomyStorageService::topicsByDomain(
                    $context["domain"]
                );

        $concepts =
            empty($context["topic"])
                ? TaxonomyStorageService::concepts()
                : TaxonomyStorageService::conceptsByTopic(
                    $context["topic"]
                );

        return [

            "context" => $context,

            "subjects" => $subjects,

            "domains" => $domains,

            "topics" => $topics,

            "concepts" => $concepts

        ];

    }
}
