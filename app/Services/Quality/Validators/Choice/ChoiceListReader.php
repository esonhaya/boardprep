<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceListReader {
    public static function read(array $question): array { return $question["choices"] ?? []; }
}
