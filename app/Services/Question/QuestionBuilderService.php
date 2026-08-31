<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Services\Question\Builder\QuestionInputReader;
use App\Services\Question\Builder\QuestionOptionBuilder;
use App\Services\Question\Builder\QuestionTaxonomyResolver;

final class QuestionBuilderService
{
    public static function build(int|string $id, array $input, ?array $existing = null): array
    {
        $now = date(DATE_ATOM);

        return array_merge(
            QuestionMetadataBuilderService::build($id, $existing, $now),
            [
                'taxonomy' => QuestionTaxonomyResolver::resolve($input, $existing),
                'status' => QuestionInputReader::text($input, 'status', $existing, 'active'),
                'difficulty' => QuestionInputReader::text($input, 'difficulty', $existing),
                'type' => QuestionInputReader::text(
                    $input,
                    'type',
                    $existing,
                    'multiple_choice'
                ),
                'question' => QuestionInputReader::text($input, 'question', $existing),
                'options' => QuestionOptionBuilder::build($input, $existing),
                'explanation' => QuestionInputReader::text($input, 'explanation', $existing),
            ]
        );
    }
}
