<?php

class QuestionEditorViewService
{

    public static function taxonomy(): array
    {

        return [

            "domains" =>
                TaxonomyRepository::domains(),

            "topics" =>
                TaxonomyRepository::topics(),

            "concepts" =>
                TaxonomyRepository::concepts()

        ];

    }

}
