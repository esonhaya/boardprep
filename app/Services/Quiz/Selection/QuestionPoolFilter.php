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
            $examEligibility = $request->exam === null ? null : \App\Services\Question\QuestionEligibilityService::forExam($question, $request->exam);
            if ($request->exam !== null && $examEligibility === null
                && (array_key_exists('board', $question) || array_key_exists('taxonomy', $question))) continue;
            $subject=$question['subject']??$taxonomy['subject_id']??null;
            if ($examEligibility !== null && ($examEligibility['subject_id'] ?? '') !== '') {
                $subject = $examEligibility['subject_id'];
            }
            $domain=$question['domain']??$taxonomy['domain_id']??null;
            $topic=$question['topic']??$taxonomy['topic_id']??null;
            $status=strtolower(trim((string)($question['status']??'active')));
            $matches=in_array($status,['active','approved'],true)
                && self::sameTaxonomy($subject,$request->subject,'subjects')
                && ($request->domain===null||self::sameTaxonomy($domain,$request->domain,'domains')||$request->topic===null)
                && ($request->topic===null||self::sameTaxonomy($topic,$request->topic,'topics'));
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

    private static function sameTaxonomy(mixed $actual,?string $expected,string $dimension): bool
    {
        if (!is_scalar($actual)||$expected===null) return false;
        $actual=trim((string)$actual); $expected=trim($expected);
        if (strcasecmp($actual,$expected)===0) return true;
        $method=$dimension;
        foreach (\App\Services\Shared\TaxonomyStorageService::{$method}() as $record) {
            if (!is_array($record)) continue;
            $id=is_scalar($record['id']??null)?trim((string)$record['id']):'';
            $name=is_scalar($record['name']??null)?trim((string)$record['name']):'';
            if (($id!==''||$name!=='')
                && (strcasecmp($actual,$id)===0||strcasecmp($actual,$name)===0)
                && (strcasecmp($expected,$id)===0||strcasecmp($expected,$name)===0)) return true;
        }
        return false;
    }
}
