<?php

class QuestionEditorViewService
{

    public static function taxonomy(): array
    {

        return [

            "subjects" =>
                TaxonomyRepository::subjects(),

            "domains" =>
                TaxonomyRepository::domains(),

            "topics" =>
                TaxonomyRepository::topics(),

            "concepts" =>
                TaxonomyRepository::concepts()

        ];

    }

    public static function domainsForSubject(
        string $subjectId
    ): array
    {

        return TaxonomyRepository::domainsBySubject(
            $subjectId
        );

    }

}
