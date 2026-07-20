<?php

class DeveloperToolsController
{

    public static function index(): void
    {

        $audit =
            QuestionAuditService::summary();

        View::render(
            "developer/index",
            [
                "audit" => $audit
            ]
        );

    }

}
