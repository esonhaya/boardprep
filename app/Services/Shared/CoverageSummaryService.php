<?php

class CoverageSummaryService
{

    public static function summary(): array
    {

        return [

            "domains" =>
                count(
                    TaxonomyRepository::domains()
                ),

            "topics" =>
                count(
                    TaxonomyRepository::topics()
                ),

            "concepts" =>
                count(
                    TaxonomyRepository::concepts()
                )

        ];

    }

}
