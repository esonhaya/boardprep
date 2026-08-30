<?php
declare(strict_types=1);
final class QuestionImportParser
{
    private array $errors=[];

    public function parse(string $json): array
    {
        $this->errors=[];
        try {
            $decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->errors[]='Invalid JSON: '.$exception->getMessage();
            return [];
        }
        if (!is_array($decoded)||!array_is_list($decoded)) {
            $this->errors[]='Import payload must be a JSON list of questions.';
            return [];
        }
        foreach ($decoded as $index=>$record) {
            if (!is_array($record)) $this->errors[]='Record '.($index+1).' must be an object.';
        }
        return array_values(array_filter($decoded,'is_array'));
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
