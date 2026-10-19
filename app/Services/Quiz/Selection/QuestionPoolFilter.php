<?php
declare(strict_types=1);

use App\Services\Quiz\LET\LETVariantRegistry;

final class QuestionPoolFilter
{
    public static function filter(array $questions, SelectionRequest $request, ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex = null): array
    {
        $civilServiceLevel = self::civilServiceLevel($request);
        $eligible=[]; $seenIds=[]; $seenTexts=[];
        $subjectAliases = $request->subject === '*'
            ? []
            : self::taxonomyMatchAliases('subjects', $request->subject);
        $domainAliases = $request->domain === null
            ? []
            : self::taxonomyMatchAliases('domains', $request->domain);
        $topicAliases = $request->topic === null
            ? []
            : self::taxonomyMatchAliases('topics', $request->topic);
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $prepared = self::prepareQuestion($question, $request, $eligibilityIndex, $civilServiceLevel);
            if ($prepared === null) continue;
            $question = $prepared['question'];
            $subject = $prepared['subject'];
            $domain = $prepared['domain'];
            $topic = $prepared['topic'];
            $matches=($request->subject === '*' || self::matchesTaxonomyAliases($subject, $subjectAliases))
                && ($request->domain===null||self::matchesTaxonomyAliases($domain, $domainAliases))
                && ($request->topic===null||self::matchesTaxonomyAliases($topic, $topicAliases));
            if (!$matches) continue;
            $id=trim((string)$question['id']);
            $text=self::normalizedText((string)$question['question']);
            if (isset($seenIds[$id])||isset($seenTexts[$text])) continue;
            $seenIds[$id]=true; $seenTexts[$text]=true; $eligible[]=$question;
        }
        return $eligible;
    }

    /**
     * Count each subject's independently filtered pool in one pass. Deduplication
     * remains scoped to the requested subject, matching separate filter calls.
     *
     * @param array<string, string> $subjects subject ID => display name
     * @return array<string, int>
     */
    public static function countBySubject(
        array $questions,
        array $subjects,
        SelectionRequest $request,
        ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex = null
    ): array {
        if ($request->subject !== '*' || $request->domain !== null || $request->topic !== null) {
            throw new InvalidArgumentException('Subject pool counts require an unscoped base request.');
        }
        $civilServiceLevel = self::civilServiceLevel($request);
        $aliasesBySubject = [];
        foreach ($subjects as $subjectId => $subjectName) {
            if (is_string($subjectName)) {
                $aliasesBySubject[(string) $subjectId] = self::taxonomyMatchAliases('subjects', $subjectName);
            }
        }
        $counts = array_fill_keys(array_keys($aliasesBySubject), 0);
        $seenIds = [];
        $seenTexts = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $prepared = self::prepareQuestion($question, $request, $eligibilityIndex, $civilServiceLevel);
            if ($prepared === null) continue;
            $id = trim((string) $prepared['question']['id']);
            $text = self::normalizedText((string) $prepared['question']['question']);
            foreach ($aliasesBySubject as $subjectId => $aliases) {
                if (!self::matchesTaxonomyAliases($prepared['subject'], $aliases)
                    || isset($seenIds[$subjectId][$id])
                    || isset($seenTexts[$subjectId][$text])) {
                    continue;
                }
                $seenIds[$subjectId][$id] = true;
                $seenTexts[$subjectId][$text] = true;
                $counts[$subjectId]++;
            }
        }
        return $counts;
    }

    private static function civilServiceLevel(SelectionRequest $request): ?string
    {
        if (!in_array(strtolower(trim((string) $request->exam)), ['civil-service', 'cse'], true)
            || $request->civilServiceLevel === null) {
            return null;
        }
        $level = strtoupper(trim($request->civilServiceLevel));
        if (!in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
            throw new InvalidArgumentException('Civil Service level must be PROFESSIONAL or SUBPROFESSIONAL.');
        }
        return $level;
    }

    private static function prepareQuestion(
        array $question,
        SelectionRequest $request,
        ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex,
        ?string $civilServiceLevel
    ): ?array {
        if ($request->exam !== null
            && $eligibilityIndex !== null
            && !$eligibilityIndex->mayBeEligibleForExam($question, $request->exam)) {
            return null;
        }
        $question = self::normalizeRuntimeFields($question);
        if (!self::hasValidRuntimeContent($question)) return null;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $examEligibility = $request->exam === null
            ? null
            : \App\Services\Question\QuestionEligibilityService::forExam($question, $request->exam, $eligibilityIndex);
        if ($request->exam !== null && $examEligibility === null
            && (array_key_exists('board', $question) || array_key_exists('taxonomy', $question))) return null;
        if ($request->exam !== null && trim((string) ($examEligibility['subject_id'] ?? '')) === '') {
            return null;
        }
        if ($civilServiceLevel !== null) {
            $subjectForLevel = trim((string) ($examEligibility['subject_id'] ?? ''));
            if ($subjectForLevel === '') {
                $subjectForLevel = trim((string) ($taxonomy['subject_id'] ?? $question['subject'] ?? ''));
            }
            $subjectForLevel = strtolower($subjectForLevel);
            $declaredLevels = $question['civil_service_levels'] ?? null;
            if (is_array($declaredLevels) && !in_array($civilServiceLevel, array_map(static fn(mixed $v): string => strtoupper(trim((string) $v)), $declaredLevels), true)) return null;
            if ($civilServiceLevel === 'PROFESSIONAL' && in_array($subjectForLevel, ['clerical-ability', 'clerical ability'], true)) return null;
        }
        if (strtolower(trim((string) $request->exam)) === 'let') {
            $matchedLetPlacement = LETVariantRegistry::placementForConfiguration(
                $question,
                $request->letVariant,
                $request->letComponent,
                $examEligibility
            );
            if ($matchedLetPlacement === null) return null;
            if (strtoupper((string) ($matchedLetPlacement['component'] ?? '')) === LETVariantRegistry::SPECIALIZATION) {
                $question['let_placement'] = $matchedLetPlacement;
            }
        }
        $status = strtolower(trim((string) ($question['status'] ?? 'active')));
        if (!in_array($status, ['active', 'approved'], true)) return null;
        return [
            'question' => $question,
            'subject' => $examEligibility !== null
                ? $examEligibility['subject_id']
                : ($taxonomy['subject_id'] ?? $question['subject'] ?? null),
            'domain' => $taxonomy['domain_id'] ?? $question['domain'] ?? null,
            'topic' => $taxonomy['topic_id'] ?? $question['topic'] ?? null,
        ];
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

    private static function taxonomyMatchAliases(string $dimension, string $expected): array
    {
        $expected = trim($expected);
        $aliases = [strtolower($expected) => true];
        foreach (\App\Services\Shared\TaxonomyStorageService::{$dimension}() as $record) {
            if (!is_array($record)) continue;
            $id=is_scalar($record['id']??null)?trim((string)$record['id']):'';
            $name=is_scalar($record['name']??null)?trim((string)$record['name']):'';
            if (($id !== '' || $name !== '')
                && (strcasecmp($expected, $id) === 0 || strcasecmp($expected, $name) === 0)) {
                $aliases[strtolower($id)] = true;
                $aliases[strtolower($name)] = true;
            }
        }
        return $aliases;
    }

    private static function matchesTaxonomyAliases(mixed $actual, array $aliases): bool
    {
        return is_scalar($actual)
            && isset($aliases[strtolower(trim((string) $actual))]);
    }
}
