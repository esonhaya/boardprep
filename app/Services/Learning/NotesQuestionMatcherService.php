<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Shared\TaxonomyStorageService;

/** Conservative, local matching against existing eligible canonical questions. */
final class NotesQuestionMatcherService
{
    /**
     * @return array{
     *   board_id:string,topics:array<int,array{name:string,question_count:int}>,
     *   matched_topics:array<int,string>,limited_topics:array<int,string>,uncovered_topics:array<int,string>,
     *   question_count:int,candidates:array<int,array<string,mixed>>
     * }
     */
    public function match(string $boardId, string $noteText, ?string $civilServiceLevel = null): array
    {
        $boardId = $this->canonicalBoardId($boardId);
        if ($boardId === '' || trim($noteText) === '') {
            return $this->emptyMatch($boardId);
        }

        return $this->matchWithIndex($boardId, $noteText, $civilServiceLevel, QuestionEligibilityService::index());
    }

    /** Reuse the request-scoped eligibility snapshot at the final quiz selection boundary. */
    public function matchForPractice(string $boardId, string $noteText, ?string $civilServiceLevel = null): array
    {
        $boardId = $this->canonicalBoardId($boardId);
        $eligibility = QuestionEligibilityService::index();
        if ($boardId === '' || trim($noteText) === '') {
            return ['match' => $this->emptyMatch($boardId), 'eligibility_index' => $eligibility];
        }
        return [
            'match' => $this->matchWithIndex($boardId, $noteText, $civilServiceLevel, $eligibility),
            'eligibility_index' => $eligibility,
        ];
    }

    private function matchWithIndex(
        string $boardId,
        string $noteText,
        ?string $civilServiceLevel,
        \App\Services\Question\QuestionEligibilityIndex $eligibility
    ): array {

        $segments = $this->segments($noteText);
        if ($segments === []) {
            return $this->emptyMatch($boardId);
        }

        // Canonical content is read once without hydrating unrelated usage statistics.
        $snapshot = array_values(array_filter(App::storage()->all('questions'), 'is_array'));
        $boardCandidates = [];
        foreach ($snapshot as $question) {
            if (!in_array(strtolower(trim((string) ($question['status'] ?? 'active'))), ['active', 'approved'], true)
                || !$eligibility->mayBeEligibleForExam($question, $boardId)) {
                continue;
            }
            $boardCandidates[] = $question;
        }
        $lessonTextByQuestion = $this->exactLessonTextByQuestion($boardCandidates, $boardId, $eligibility);
        $preliminary = [];
        $matchById = [];
        foreach ($boardCandidates as $question) {
            $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
            if ($questionId === '') continue;
            $evidence = $this->questionEvidence($question, $lessonTextByQuestion[$questionId] ?? []);
            $best = null;
            foreach ($segments as $segment) {
                $candidateMatch = $this->score($segment, $evidence);
                if ($candidateMatch !== null && ($best === null || $candidateMatch['score'] > $best['score'])) {
                    $best = $candidateMatch + ['segment' => $segment];
                }
            }
            if ($best === null) continue;

            $preliminary[] = $question;
            $matchById[$questionId] = [
                'topic' => $best['segment']['label'],
                'note_excerpt' => self::excerpt($best['segment']['text']),
                'reason' => $best['reason'],
                'relevance_score' => $best['score'],
                'possible_answer_discrepancy' => $this->possibleAnswerDiscrepancy($question, $best['segment']['text']),
            ];
        }

        $selection = new \SelectionRequest(
            subject: '*',
            domain: null,
            difficultyDistribution: [],
            questionCount: max(1, count($preliminary)),
            exam: $boardId,
            civilServiceLevel: $civilServiceLevel
        );
        // Topic matching is a cheap prefilter; the standard selector remains the
        // final authority for exact placement, content validity, variants, and holds.
        $eligible = $preliminary === [] ? [] : \QuestionPoolFilter::filter($preliminary, $selection, $eligibility);

        $candidates = [];
        $countsByTopic = [];
        foreach ($eligible as $question) {
            $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
            if ($questionId === '' || !isset($matchById[$questionId])) continue;
            $match = $matchById[$questionId];
            $topicName = $match['topic'];
            $countsByTopic[$topicName] = ($countsByTopic[$topicName] ?? 0) + 1;
            $candidates[] = [
                'question' => $question,
                'question_id' => $questionId,
                ...$match,
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            $scoreOrder = (int) ($b['relevance_score'] ?? 0) <=> (int) ($a['relevance_score'] ?? 0);
            return $scoreOrder !== 0 ? $scoreOrder : strcmp((string) $a['question_id'], (string) $b['question_id']);
        });

        $topics = [];
        foreach ($segments as $segment) {
            $name = (string) $segment['label'];
            if (isset($topics[$name])) continue;
            $topics[$name] = ['name' => $name, 'question_count' => (int) ($countsByTopic[$name] ?? 0)];
        }
        $matchedTopics = [];
        $limitedTopics = [];
        $uncoveredTopics = [];
        foreach ($topics as $topic) {
            if ($topic['question_count'] > 0) {
                $matchedTopics[] = $topic['name'];
                if ($topic['question_count'] < 3) $limitedTopics[] = $topic['name'];
            } else {
                $uncoveredTopics[] = $topic['name'];
            }
        }

        return [
            'board_id' => $boardId,
            'topics' => array_values($topics),
            'matched_topics' => $matchedTopics,
            'limited_topics' => $limitedTopics,
            'uncovered_topics' => $uncoveredTopics,
            'question_count' => count($candidates),
            'candidates' => $candidates,
        ];
    }

    /** @return array<string,list<string>> Exact published lesson-section text by eligible question ID. */
    private function exactLessonTextByQuestion(
        array $eligibleQuestions,
        string $boardId,
        \App\Services\Question\QuestionEligibilityIndex $eligibility
    ): array {
        $questionsById = [];
        foreach ($eligibleQuestions as $question) {
            if (is_array($question) && is_scalar($question['id'] ?? null)) {
                $questionsById[(string) $question['id']] = $question;
            }
        }
        if ($questionsById === []) return [];

        $materials = [];
        foreach (App::storage()->all('study-materials') as $material) {
            if (!is_array($material) || ($material['status'] ?? 'published') !== 'published') continue;
            $id = is_scalar($material['id'] ?? null) ? trim((string) $material['id']) : '';
            if ($id !== '') $materials[$id] = trim((string) ($material['title'] ?? ''));
        }
        if ($materials === []) return [];

        $lessons = [];
        foreach (App::storage()->all('study-lessons') as $lesson) {
            if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') continue;
            $materialId = is_scalar($lesson['material_id'] ?? null) ? trim((string) $lesson['material_id']) : '';
            if ($materialId !== '' && isset($materials[$materialId]) && !isset($lessons[$materialId])) {
                $lessons[$materialId] = $lesson;
            }
        }
        if ($lessons === []) return [];

        $subjectByQuestion = [];
        $textByQuestion = [];
        foreach (App::storage()->all('study-question-links') as $link) {
            if (!is_array($link)) continue;
            $questionId = is_scalar($link['question_id'] ?? null) ? trim((string) $link['question_id']) : '';
            $materialId = is_scalar($link['material_id'] ?? null) ? trim((string) $link['material_id']) : '';
            if (!isset($questionsById[$questionId], $lessons[$materialId])) continue;

            $lesson = $lessons[$materialId];
            if ($this->canonicalBoardId((string) ($lesson['board_id'] ?? '')) !== $boardId) continue;
            $lessonId = is_scalar($lesson['id'] ?? null) ? trim((string) $lesson['id']) : '';
            $linkedLessonId = is_scalar($link['lesson_id'] ?? null) ? trim((string) $link['lesson_id']) : '';
            if ($lessonId === '' || ($linkedLessonId !== '' && $linkedLessonId !== $lessonId)) continue;
            $sectionId = is_scalar($link['section_id'] ?? $link['lesson_section_id'] ?? null)
                ? trim((string) ($link['section_id'] ?? $link['lesson_section_id']))
                : '';
            if ($sectionId === '') continue;

            $section = null;
            foreach ((array) ($lesson['sections'] ?? []) as $candidateSection) {
                if (is_array($candidateSection) && (string) ($candidateSection['id'] ?? '') === $sectionId) {
                    $section = $candidateSection;
                    break;
                }
            }
            if (!is_array($section)) continue;

            if (!array_key_exists($questionId, $subjectByQuestion)) {
                $placement = QuestionEligibilityService::forExam($questionsById[$questionId], $boardId, $eligibility);
                $subjectByQuestion[$questionId] = is_array($placement)
                    ? strtolower(trim((string) ($placement['subject_id'] ?? '')))
                    : '';
            }
            $lessonSubject = strtolower(trim((string) ($lesson['subject_id'] ?? '')));
            if ($subjectByQuestion[$questionId] === '' || $subjectByQuestion[$questionId] !== $lessonSubject) continue;

            // Source-only relationships never contribute relevance. A valid published
            // section anchor and lesson ownership are required before using its text.
            $textByQuestion[$questionId][] = $materials[$materialId];
            foreach (['heading', 'body'] as $field) {
                if (is_scalar($section[$field] ?? null)) $textByQuestion[$questionId][] = (string) $section[$field];
            }
            if (is_scalar($link['review_guidance'] ?? null)) $textByQuestion[$questionId][] = (string) $link['review_guidance'];
        }
        return $textByQuestion;
    }

    /** @return array{tokens:array<string,true>,taxonomy_tokens:array<string,true>,question_tokens:array<string,true>,lesson_tokens:array<string,true>,taxonomy_phrases:array<int,array<string,true>>} */
    private function questionEvidence(array $question, array $lessonText): array
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $taxonomyParts = [];
        $taxonomyPhrases = [];
        foreach ([
            'domains' => $taxonomy['domain_id'] ?? $question['domain'] ?? null,
            'topics' => $taxonomy['topic_id'] ?? $question['topic'] ?? null,
            'concepts' => $taxonomy['concept_id'] ?? $question['concept'] ?? null,
            'subjects' => $taxonomy['subject_id'] ?? $question['subject_id'] ?? $question['subject'] ?? null,
        ] as $dimension => $value) {
            if (!is_scalar($value) || trim((string) $value) === '') continue;
            $id = trim((string) $value);
            $label = TaxonomyStorageService::label($dimension, $id);
            $taxonomyParts[] = $id;
            if ($label !== '' && $label !== $id) $taxonomyParts[] = $label;
            $taxonomyPhrases[] = self::tokenSet($id);
            if ($label !== '' && $label !== $id) $taxonomyPhrases[] = self::tokenSet($label);
        }
        foreach (['domain', 'topic', 'concept'] as $field) {
            if (is_scalar($question[$field] ?? null) && trim((string) $question[$field]) !== '') {
                $taxonomyParts[] = (string) $question[$field];
                $taxonomyPhrases[] = self::tokenSet((string) $question[$field]);
            }
        }
        $taxonomyTokens = self::tokenSet(implode(' ', $taxonomyParts));
        $questionTokens = self::tokenSet((string) ($question['question'] ?? ''));
        $lessonTokens = self::tokenSet(implode(' ', $lessonText));
        $all = $taxonomyTokens + $questionTokens + $lessonTokens;
        return [
            'tokens' => $all,
            'taxonomy_tokens' => $taxonomyTokens,
            'question_tokens' => $questionTokens,
            'lesson_tokens' => $lessonTokens,
            'taxonomy_phrases' => $taxonomyPhrases,
        ];
    }

    private function score(array $segment, array $evidence): ?array
    {
        $noteTokens = $segment['tokens'];
        if ($noteTokens === []) return null;
        $questionOverlap = count(array_intersect_key($noteTokens, $evidence['question_tokens']));
        $taxonomyOverlap = count(array_intersect_key($noteTokens, $evidence['taxonomy_tokens']));
        $lessonOverlap = count(array_intersect_key($noteTokens, $evidence['lesson_tokens']));
        $allOverlap = count(array_intersect_key($noteTokens, $evidence['tokens']));
        $taxonomyExact = false;
        foreach ($evidence['taxonomy_phrases'] as $phrase) {
            if ($phrase !== [] && $phrase === ($segment['label_tokens'] ?? [])) {
                $taxonomyExact = true;
                break;
            }
        }

        // A generic one-word coincidence cannot qualify. A single concept can qualify
        // only when the learner's topic exactly matches canonical taxonomy metadata.
        if (!$taxonomyExact && $allOverlap < 2) return null;

        $score = ($questionOverlap * 6) + ($taxonomyOverlap * 7) + ($lessonOverlap * 4) + ($taxonomyExact ? 12 : 0);
        $reason = $taxonomyExact
            ? 'The note topic matches this question’s existing taxonomy label.'
            : 'The note and question share multiple topic terms in the question text, taxonomy, or an exact lesson link.';
        return ['score' => $score, 'reason' => $reason];
    }

    /** @return list<array{label:string,text:string,tokens:array<string,true>}> */
    private function segments(string $noteText): array
    {
        $plain = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/isu', ' ', $noteText) ?? $noteText;
        $plain = strip_tags($plain);
        $plain = str_replace(["\r\n", "\r"], "\n", $plain);
        $lines = explode("\n", $plain);
        $sections = [];
        $heading = '';
        $body = [];
        $hasHeading = false;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            $cleanHeading = self::heading($trimmed);
            if ($cleanHeading !== null) {
                $hasHeading = true;
                if ($heading !== '' || trim(implode("\n", $body)) !== '') {
                    $sections[] = ['heading' => $heading, 'body' => trim(implode("\n", $body))];
                }
                $heading = $cleanHeading;
                $body = [];
            } else {
                $body[] = $line;
            }
        }
        if ($heading !== '' || trim(implode("\n", $body)) !== '') {
            $sections[] = ['heading' => $heading, 'body' => trim(implode("\n", $body))];
        }
        if ($sections === []) $sections[] = ['heading' => '', 'body' => $plain];
        if (!$hasHeading && count($sections) === 1) {
            $paragraphs = preg_split('/\n[\t ]*\n+/u', (string) $sections[0]['body']) ?: [];
            $paragraphs = array_values(array_filter($paragraphs, static fn(string $paragraph): bool => trim($paragraph) !== ''));
            if (count($paragraphs) > 1) {
                $sections = array_map(static fn(string $paragraph): array => ['heading' => '', 'body' => trim($paragraph)], $paragraphs);
            }
        }

        $segments = [];
        foreach (array_slice($sections, 0, 40) as $index => $section) {
            $text = trim((string) $section['heading'] . "\n" . (string) $section['body']);
            $tokens = self::tokenSet($text);
            if ($tokens === []) continue;
            $label = trim((string) $section['heading']);
            if ($label === '') $label = self::topicLabel((string) $section['body'], $tokens, $index + 1);
            $segments[] = [
                'label' => $label,
                'text' => $text,
                'tokens' => $tokens,
                'label_tokens' => self::tokenSet($label),
            ];
        }
        return $segments;
    }

    private static function heading(string $line): ?string
    {
        if ($line === '') return null;
        $candidate = preg_replace('/^\s{0,3}#{1,6}\s*/u', '', $line) ?? $line;
        if ($candidate !== $line) return trim($candidate) !== '' ? trim($candidate) : null;
        $candidate = preg_replace('/^\s*(?:\d+(?:\.\d+)*[.)]?|[-*])\s+/u', '', $line) ?? $line;
        if (str_ends_with($candidate, ':') && self::characterLength($candidate) <= 100) {
            return trim(rtrim($candidate, ': '));
        }
        if (self::characterLength($candidate) <= 72
            && preg_match('/[\p{L}]/u', $candidate) === 1
            && preg_match('/\p{L}/u', $candidate) === 1
            && preg_match('/[a-z]/u', $candidate) !== 1
            && count(self::tokenSet($candidate)) <= 8) {
            return trim($candidate);
        }
        return null;
    }

    private static function topicLabel(string $body, array $tokens, int $index): string
    {
        $frequency = [];
        foreach (self::tokenize($body) as $token) $frequency[$token] = ($frequency[$token] ?? 0) + 1;
        if ($frequency === []) return 'Notes topic ' . $index;
        arsort($frequency);
        $terms = array_slice(array_keys($frequency), 0, 3);
        return implode(' · ', $terms);
    }

    /** @return array<string,true> */
    private static function tokenSet(string $text): array
    {
        return array_fill_keys(self::tokenize($text), true);
    }

    /** @return list<string> */
    private static function tokenize(string $text): array
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = preg_replace('/\b(?:blood\s+pressure|presyon\s+ng\s+dugo|presyon|\bbp\b)\b/u', ' bloodpressure ', $text) ?? $text;
        $text = preg_replace('/\b(?:puso|heart)\b/u', ' heart ', $text) ?? $text;
        $text = preg_replace('/\b(?:pagbubuntis|pregnancy)\b/u', ' pregnancy ', $text) ?? $text;
        $text = preg_replace('/\b(?:impeksiyon|impeksyon|infection)\b/u', ' infection ', $text) ?? $text;
        $text = preg_replace('/\b(?:cerebrovascular\s+accident|\bcva\b|stroke)\b/u', ' stroke ', $text) ?? $text;
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);
        $stop = array_fill_keys([
            'the','and','for','with','from','that','this','these','those','into','onto','when','where','which','while','what','who','why','how','then','than','are','was','were','been','being','have','has','had','will','would','should','could','can','may','might','must','not','but','also','about','after','before','during','because','through','their','there','here','your','you','they','them','our','out','use','used','using','such','each','other','some','more','most','less','very','often','usually','common','important','patient','patients','nurse','nursing','care','health','study','notes','topic','include','including','example','examples','explain','review','need','needs','must','may','might','should','is','of','to','in','on','at','by','as','an','a','or','if','it','its','be','do','does','did','per','via','vs','ng','sa','ang','mga','at','ay','para','ito','iyan','iyon','nang','na','ni','kay','si','sina','kami','tayo','kayo','nila','natin','dito','doon','kung','kapag','dahil','mula','para','may','wala','hindi','oo','opo','rin','din','lang','lamang','pa','po','ba','man','raw','daw','ito','yung','iyon','isang','mga'
        ], true);
        $tokens = [];
        foreach ($matches[0] ?? [] as $token) {
            $token = trim((string) $token);
            if (isset($stop[$token]) || strlen($token) < 3) continue;
            $tokens[$token] = true;
        }
        return array_keys($tokens);
    }

    private function possibleAnswerDiscrepancy(array $question, string $noteText): bool
    {
        $normalizedNote = self::normalizeForComparison($noteText);
        $correct = self::normalizeForComparison((string) ($question['answer'] ?? ''));
        if ($correct === '') return false;
        $hasCorrect = str_contains($normalizedNote, $correct);
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            if (!is_scalar($choice)) continue;
            $normalizedChoice = self::normalizeForComparison((string) $choice);
            if ($normalizedChoice === '' || $normalizedChoice === $correct) continue;
            if (strlen($normalizedChoice) >= 5 && str_contains($normalizedNote, $normalizedChoice) && !$hasCorrect) {
                return true;
            }
        }
        return false;
    }

    private static function normalizeForComparison(string $text): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text);
        return function_exists('mb_strtolower') ? mb_strtolower($normalized, 'UTF-8') : strtolower($normalized);
    }

    private static function excerpt(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text);
        if (self::characterLength($text) <= 260) return $text;
        if (function_exists('mb_substr')) return mb_substr($text, 0, 257, 'UTF-8') . '…';
        return substr($text, 0, 257) . '…';
    }

    private function canonicalBoardId(string $value): string
    {
        foreach (TaxonomyStorageService::boards() as $board) {
            if (!is_array($board)) continue;
            foreach (['id', 'code', 'slug', 'short_code'] as $key) {
                if (is_scalar($board[$key] ?? null) && strcasecmp(trim((string) $board[$key]), trim($value)) === 0) {
                    return (string) ($board['id'] ?? '');
                }
            }
        }
        return '';
    }

    private static function characterLength(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
        $length = preg_match_all('/./us', $value, $matches);
        return is_int($length) ? $length : strlen($value);
    }

    private function emptyMatch(string $boardId): array
    {
        return [
            'board_id' => $boardId,
            'topics' => [],
            'matched_topics' => [],
            'limited_topics' => [],
            'uncovered_topics' => [],
            'question_count' => 0,
            'candidates' => [],
        ];
    }
}
