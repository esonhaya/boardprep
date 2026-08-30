<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators\Content;

final class ContentValidationPipeline
{
    public static function validate(array $question): array
    {
        $text = ContentTextReader::read($question, 'question');
        $explanation = ContentTextReader::read($question, 'explanation');

        return array_merge(
            QuestionTextValidator::validate($text),
            ExplanationValidator::validate($explanation)
        );
    }
}
