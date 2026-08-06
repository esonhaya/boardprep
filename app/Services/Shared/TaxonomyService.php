<?php

class TaxonomyService
{

    public static function addDomain(
        string $name
    ): void
    {

        $name = trim($name);

        if ($name === "") {
            return;
        }

        $domains =
            TaxonomyStorageService::domains();

        $domains[] = $name;

        sort($domains);

        TaxonomyStorageService::saveDomains($domains);

    }


    public static function addTopic(
        string $name
    ): void
    {

        $name = trim($name);

        if ($name === "") {
            return;
        }

        $topics =
            TaxonomyStorageService::topics();

        $topics[] = $name;

        sort($topics);

        TaxonomyStorageService::saveTopics($topics);

    }


    public static function addConcept(
        string $name
    ): void
    {

        $name = trim($name);

        if ($name === "") {
            return;
        }

        $concepts =
            TaxonomyStorageService::concepts();

        $concepts[] = $name;

        sort($concepts);

        TaxonomyStorageService::saveConcepts($concepts);

    }

}
