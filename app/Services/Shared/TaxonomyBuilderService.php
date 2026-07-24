<?php

class TaxonomyBuilderService
{

    public static function rebuild(): array
    {

        $questions =
            QuestionRepository::all();


        $domains = [];
        $topics = [];
        $concepts = [];


        foreach ($questions as $question) {


            if (!empty($question["domain"])) {

                $domains[] =
                    trim(
                        $question["domain"]
                    );

            }


            if (!empty($question["topic"])) {

                $topics[] =
                    trim(
                        $question["topic"]
                    );

            }


            if (!empty($question["concept"])) {

                $concepts[] =
                    trim(
                        $question["concept"]
                    );

            }

        }


        $domains =
            self::clean($domains);

        $topics =
            self::clean($topics);

        $concepts =
            self::clean($concepts);


        TaxonomyRepository::saveDomains(
            $domains
        );

        TaxonomyRepository::saveTopics(
            $topics
        );

        TaxonomyRepository::saveConcepts(
            $concepts
        );


        return [

            "domains" =>
                count($domains),

            "topics" =>
                count($topics),

            "concepts" =>
                count($concepts)

        ];

    }



    private static function clean(
        array $items
    ): array
    {

        $items =
            array_map(
                fn($item) =>
                    trim($item),
                $items
            );


        $items =
            array_filter(
                $items
            );


        $items =
            array_unique(
                $items
            );


        sort(
            $items
        );


        return array_values(
            $items
        );

    }

}
