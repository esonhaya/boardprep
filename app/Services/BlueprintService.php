<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Repositories\BlueprintRepository;
use InvalidArgumentException;

final class BlueprintService
{
    public static function all(): array
    {
        return App::container()
            ->get(BlueprintRepository::class)
            ->all();
    }

    public static function create(array $data): array
    {
        $board = trim($data['board'] ?? '');
        $subject = trim($data['subject'] ?? '');
        $name = trim($data['name'] ?? '');

        $questionCount = (int) ($data['questionCount'] ?? 0);

        $easy = (int) ($data['easy'] ?? 0);
        $medium = (int) ($data['medium'] ?? 0);
        $hard = (int) ($data['hard'] ?? 0);

        $errors = [];

        if ($board === '') {
            $errors[] = 'Missing board.';
        }

        if ($subject === '') {
            $errors[] = 'Missing subject.';
        }

        if ($name === '') {
            $errors[] = 'Missing blueprint name.';
        }

        if ($questionCount <= 0) {
            $errors[] = 'Question count must be greater than zero.';
        }

        foreach ([
            'Easy' => $easy,
            'Medium' => $medium,
            'Hard' => $hard,
        ] as $label => $value) {

            if ($value < 0 || $value > 100) {
                $errors[] =
                    "{$label} difficulty must be between 0 and 100.";
            }
        }

        if (($easy + $medium + $hard) !== 100) {
            $errors[] =
                'Difficulty distribution must equal 100%.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $repository = App::container()
            ->get(BlueprintRepository::class);

        $version = self::nextVersion(
            $repository,
            $board,
            $subject
        );

        $blueprint = [
            'id' => self::generateId(
                $board,
                $subject,
                $version
            ),

            'board' => $board,
            'subject' => $subject,
            'name' => $name,
            'version' => $version,

            'questionCount' => $questionCount,

            'difficulty' => [
                'easy' => $easy,
                'medium' => $medium,
                'hard' => $hard,
            ],

            'topicWeights' => [],
            'conceptWeights' => [],
        ];

        $repository->create($blueprint);

        return [
            'success' => true,
            'blueprint' => $blueprint,
        ];
    }

    private static function generateId(
        string $board,
        string $subject,
        int $version
    ): string {
        $board = preg_replace(
            '/\s+/',
            '-',
            trim($board)
        );

        $subject = preg_replace(
            '/\s+/',
            '-',
            trim($subject)
        );

        return strtolower(
            $board
            . '-'
            . $subject
            . '-v'
            . $version
        );
    }

    private static function nextVersion(
        BlueprintRepository $repository,
        string $board,
        string $subject
    ): int {
        $highest = 0;

        foreach ($repository->all() as $blueprint) {

            if (
                ($blueprint['board'] ?? '') === $board
                && ($blueprint['subject'] ?? '') === $subject
            ) {
                $highest = max(
                    $highest,
                    (int) ($blueprint['version'] ?? 0)
                );
            }
        }

        return $highest + 1;
    }
}
