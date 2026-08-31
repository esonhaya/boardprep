<?php

use App\Services\Shared\TaxonomyStorageService;

class CoverageSummaryService
{

    public static function summary(): array
    {

        return [

            "domains" =>
                count(
                    TaxonomyStorageService::domains()
                ),

            "topics" =>
                count(
                    TaxonomyStorageService::topics()
                ),

            "concepts" =>
                count(
                    TaxonomyStorageService::concepts()
                )

        ];

    }

}
