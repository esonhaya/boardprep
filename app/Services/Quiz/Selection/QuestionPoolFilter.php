<?php
declare(strict_types=1);
final class QuestionPoolFilter
{
    public static function filter(array $questions, SelectionRequest $request): array
    {
        $eligible=[]; $seenIds=[]; $seenTexts=[];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $question=self::normalizeRuntimeFields($question);
            if (!self::hasValidRuntimeContent($question)) continue;
            $taxonomy=is_array($question['taxonomy']??null)?$question['taxonomy']:[];
            $subject=$question['subject']??$taxonomy['subject_id']??null;
            $domain=$question['domain']??$taxonomy['domain_id']??null;
            $topic=$question['topic']??$taxonomy['topic_id']??null;
            $status=strtolower(trim((string)($question['status']??'active')));
            $matches=in_array($status,['active','approved'],true)
                && self::same($subject,$request->subject)
                && ($request->domain===null||self::same($domain,$request->domain)||$request->topic===null)
                && ($request->topic===null||self::same($topic,$request->topic));
            if (!$matches) continue;
            $id=trim((string)$question['id']);
            $text=self::normalizedText((string)$question['question']);
            if (isset($seenIds[$id])||isset($seenTexts[$text])) continue;
            $seenIds[$id]=true; $seenTexts[$text]=true; $eligible[]=$question;
        }
        return $eligible;
    }

    private static function normalizeRuntimeFields(array $question): array
    {
        if (is_scalar($question['difficulty'] ?? null)) {
            $question['difficulty']=strtolower(trim((string)$question['difficulty']));
        }
        if (is_array($question['choices']??null)&&is_scalar($question['answer']??null)) {
            $question['choices']=array_map(
                static fn(mixed $choice): mixed=>is_scalar($choice)?trim((string)$choice):$choice,
                array_values($question['choices'])
            );
            $answer=self::matchingChoice($question['choices'],(string)$question['answer']);
            if ($answer!==null) $question['answer']=$answer;
            return $question;
        }
        $options=$question['options']??null;
        if (!is_array($options)) return $question;
        $choices=[]; $answer=null;
        foreach ($options as $option) {
            if (!is_array($option)||!is_scalar($option['text']??null)) continue;
            $text=trim((string)$option['text']);
            if ($text==='') continue;
            $choices[]=$text;
            if (($option['correct']??false)===true&&$answer===null) $answer=$text;
        }
        if ($choices!==[]&&$answer!==null) {
            $question['choices']=$choices; $question['answer']=$answer;
        }
        return $question;
    }

    private static function hasValidRuntimeContent(array $question): bool
    {
        foreach (['id','question','explanation'] as $field) {
            if (!is_scalar($question[$field]??null)||trim((string)$question[$field])==='') return false;
        }
        $difficulty=is_scalar($question['difficulty']??null)?strtolower(trim((string)$question['difficulty'])):'';
        if (!in_array($difficulty,['easy','medium','hard'],true)) return false;
        $choices=$question['choices']??null;
        if (!is_array($choices)||count($choices)<2||!is_scalar($question['answer']??null)) return false;
        $seen=[];
        foreach ($choices as $choice) {
            if (!is_scalar($choice)||trim((string)$choice)==='') return false;
            $key=self::normalizedText((string)$choice);
            if (isset($seen[$key])) return false;
            $seen[$key]=true;
        }
        return self::matchingChoice($choices,(string)$question['answer'])!==null;
    }

    private static function matchingChoice(array $choices,string $answer): ?string
    {
        $answer=self::normalizedText($answer);
        foreach ($choices as $choice) {
            if (is_scalar($choice)&&self::normalizedText((string)$choice)===$answer) return trim((string)$choice);
        }
        return null;
    }

    private static function normalizedText(string $text): string
    {
        $text=preg_replace('/\s+/',' ',trim($text))??trim($text);
        return strtolower($text);
    }

    private static function same(mixed $actual,?string $expected): bool
    {
        return is_scalar($actual)&&strcasecmp(trim((string)$actual),trim((string)$expected))===0;
    }
}
