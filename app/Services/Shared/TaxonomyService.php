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
            TaxonomyRepository::domains();

        $domains[] = $name;

        sort($domains);

        TaxonomyRepository::saveDomains($domains);

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
            TaxonomyRepository::topics();

        $topics[] = $name;

        sort($topics);

        TaxonomyRepository::saveTopics($topics);

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
            TaxonomyRepository::concepts();

        $concepts[] = $name;

        sort($concepts);

        TaxonomyRepository::saveConcepts($concepts);

    }

}
