<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Response;
use App\Core\View;
use App\Repositories\QuestionRepository;

class QuizStartService
{
    public static function start(): void
    {
        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        $specification =
            BaseSpecificationFactory::create(
                [
                    'board' =>
                        $_GET['exam'] ?? 'LET',

                    'subject' =>
                        $_GET['subject'] ?? '',

                    'domain' =>
                        $_GET['domain'] ?? null,

                    'difficulty' =>
                        $_GET['difficulty'] ?? 'mixed',

                    'count' =>
                        (int) (
                            $_GET['count']
                            ?? 10
                        ),

                    'mode' =>
                        $_GET['mode'] ?? 'practice',

                    'adaptive' =>
                        isset($_GET['adaptive']),

                    'shuffle' =>
                        true,
                ]
            );

        $result =
            QuizGenerationService::generate(
                $questions,
                $specification
            );

        if (
            empty($result->questions)
        ) {
            Response::redirect(
                '/quiz',
                302
            );
        }

        SessionService::set(
            'questions',
            $result->questions
        );

        SessionService::set(
            'answers',
            []
        );

        SessionService::set(
            'feedback',
            null
        );

        SessionService::set(
            'mode',
            $specification->mode
        );

        QuizNavigationService::reset();

        View::render(
            'quiz/index',
            [
                'question' =>
                    $result->questions[0],

                'current' =>
                    0,

                'total' =>
                    count(
                        $result->questions
                    ),

                'mode' =>
                    $specification->mode,

                'feedback' =>
                    null,
            ]
        );
    }
}
