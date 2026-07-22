<?php

class QuestionMetadataService
{

    public static function build(
        int $id,
        array $formData
    ): array
    {

        $existing =
            QuestionRepository::find($id) ?? [];

        return array_merge(

            [

                "id" => $id,

                "status" =>
                    $existing["status"] ?? "active"

            ],

            QuestionAnalyticsService::defaults(
                $existing
            ),

            $formData

        );

    }

}
