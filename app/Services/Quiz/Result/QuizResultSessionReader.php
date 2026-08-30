<?php

declare(strict_types=1);

final class QuizResultSessionReader
{
    /**
     * @return array{questions:array,answers:array,session:array}
     */
    public static function read(): array
    {
        $questions = SessionService::get("questions", []);
        $answers = SessionService::get("answers", []);
        $session = SessionService::get("quiz_session", []);

        return [
            "questions" => self::questions($questions),
            "answers" => self::answers($answers),
            "session" => is_array($session) ? $session : [],
        ];
    }

    private static function questions(mixed $questions): array
    {
        if (!is_array($questions)) {
            return [];
        }

        return array_values(array_filter(
            $questions,
            static function (mixed $question): bool {
                if (!is_array($question)) {
                    return false;
                }

                $id = $question['id'] ?? null;
                return is_scalar($id) && trim((string) $id) !== '';
            }
        ));
    }

    private static function answers(mixed $answers): array
    {
        if (!is_array($answers)) {
            return [];
        }

        $normalized = [];
        foreach ($answers as $id => $answer) {
            if (is_scalar($id) && is_scalar($answer)
                && trim((string) $id) !== '' && (string) $id !== '0') {
                $normalized[(string) $id] = (string) $answer;
            }
        }

        return $normalized;
    }
}
