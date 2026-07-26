<?php

class BaseDeveloperController extends BaseController
{

    protected static function renderDeveloper(
        string $view,
        array $data = [],
        bool $showFooter = true
    ): void
    {

        $data["showDeveloperFooter"] =
            $showFooter;

        View::render(
            $view,
            $data
        );

    }

    protected static function developerRedirect(
        string $page
    ): void
    {

        header(
            "Location: ?page=" .
            $page
        );

        exit;

    }

    protected static function renderDeveloperErrors(
        string $view,
        array $errors,
        array $old = []
    ): void
    {

        self::renderDeveloper(

            $view,

            [
                "errors" => $errors,
                "old" => $old
            ]

        );

    }

}
