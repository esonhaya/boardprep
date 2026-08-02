<?php

declare(strict_types=1);

class QuestionViewService
{
    public static function taxonomy(
        array $context = []
    ): array {

        $subjects =
            TaxonomyRepository::subjects();

        $domains =
            empty($context["subject"])
                ? TaxonomyRepository::domains()
                : TaxonomyRepository::domainsBySubject(
                    $context["subject"]
                );

        $topics =
            empty($context["domain"])
                ? TaxonomyRepository::topics()
                : TaxonomyRepository::topicsByDomain(
                    $context["domain"]
                );

        $concepts =
            empty($context["topic"])
                ? TaxonomyRepository::concepts()
                : TaxonomyRepository::conceptsByTopic(
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
