<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\Request;
use App\Core\Response;
use App\Services\Question\QuestionAuthoringService;
use App\Services\Question\QuestionEditorService;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionQueryService;
use App\Services\Question\QuestionService;
use App\Services\Question\QuestionViewService;
use App\Services\Shared\CsrfTokenService;
use App\Services\Shared\TaxonomyStorageService;

class QuestionEditorController extends BaseDeveloperController
{
    private static function workspace(
        array $data = []
    ): void {
        $context = [
            "board" =>
                Request::queryString("board"),

            "subject" =>
                Request::queryString("subject"),

            "domain" =>
                Request::queryString("domain"),

            "topic" =>
                Request::queryString("topic"),

            "concept" =>
                Request::queryString("concept")
        ];

        $context = array_filter(
            $context,
            static fn(string $value): bool =>
                $value !== ""
        );

        $question = is_array($data['question'] ?? null) ? $data['question'] : [];
        $eligibility = $question !== []
            ? [
                'mappings' => QuestionEligibilityService::editorMappings($question),
                'options' => QuestionEligibilityService::editorOptions($question),
            ]
            : ['mappings' => [], 'options' => []];

        self::renderDeveloper(
            "developer/question/workspace",
            array_merge(
                $data,
                QuestionViewService::taxonomy($context),
                [
                    "context" => $context,
                    "returnUrl" => Request::queryString('return'),
                    "eligibility" => $eligibility,
                    "csrfToken" => CsrfTokenService::token(),
                ]
            )
        );
    }

    public static function index(): void
    {
        $search =
            Request::queryString("search");

        $domain =
            Request::queryString("domain");

        $difficulty =
            Request::queryString("difficulty");

        $topic =
            Request::queryString("topic");

        $status = Request::queryString("status");
        $board = Request::queryString("board");
        $subject = Request::queryString("subject");

        $pageNumber = filter_var(Request::queryString('page_num'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $page = QuestionQueryService::getQuestionPage([
                "search" => $search,
                "domain" => $domain,
                "difficulty" => $difficulty,
                "topic" => $topic,
                "status" => $status,
                "board" => $board,
                "subject" => $subject
            ], $pageNumber, 50);

        self::renderDeveloper(
            "developer/question/editor",
            array_merge(
                [
                    "pageTitle" =>
                        "Question Editor",

                    "questions" => $page['items'],
                    "totalQuestions" => $page['total'],
                    "canonicalQuestionCount" => $page['canonical_total'],
                    "pageNumber" => $page['page'],
                    "pageCount" => $page['pages'],
                    "pageSize" => $page['per_page'],

                    "search" =>
                        $search,

                    "domain" =>
                        $domain,

                    "difficulty" =>
                        $difficulty,

                    "topic" =>
                        $topic,
                    "status" => $status,
                    "board" => $board,
                    "subject" => $subject
                ],
                QuestionViewService::taxonomy(),
                [
                    "taxonomyNames" => self::taxonomyNames(),
                    "csrfToken" => CsrfTokenService::token(),
                ]
            )
        );
    }

    public static function create(): void
    {
        self::workspace([
            "pageTitle" => "Create Question",
            "contentMode" => "create"
        ]);
    }

    public static function edit(): void
    {
        $question =
            QuestionEditorService::find(
                Request::queryString("id")
            );

        if ($question === null) {
            Response::redirect("/question-editor");
        }

        self::workspace([
            "pageTitle" => "Edit Question",
            "contentMode" => "edit",
            "question" => $question,
            "returnUrl" => Request::queryString('return'),
            "eligibilityNotice" => Request::queryString('notice'),
        ]);
    }

    public static function save(): void
    {
        self::requireCsrf();
        self::submitAuthoring(0, "Create Question", "create");
    }

    public static function update(): void
    {
        self::requireCsrf();
        self::submitAuthoring(
            Request::queryString("id"),
            "Edit Question",
            "edit"
        );
    }

    private static function submitAuthoring(
        int|string $id,
        string $pageTitle,
        string $contentMode
    ): void {
        $result = QuestionAuthoringService::submit(
            $id,
            Request::input()
        );

        if (($result["saved"] ?? false) !== true) {
            self::workspace([
                "pageTitle" => $pageTitle,
                "contentMode" => $contentMode,
                "question" => $result["question"] ?? [],
                "errors" => $result["errors"] ?? [],
                "duplicates" => $result["duplicates"] ?? [],
                "returnUrl" => Request::input('return', Request::queryString('return'))
            ]);
            return;
        }

        if (trim((string) $id) === '' || trim((string) $id) === '0') {
            $taxonomy = is_array($result['persisted']['taxonomy'] ?? null) ? $result['persisted']['taxonomy'] : [];
            $boardId = (string) ($taxonomy['board_id'] ?? '');
            $subjectId = (string) ($taxonomy['subject_id'] ?? '');
            if ($boardId !== '' && $subjectId !== '') {
                QuestionEligibilityService::persist($result['persisted'], [
                    'exam_ids' => [$boardId],
                    'subject_ids' => [$boardId => $subjectId],
                    'placements' => [$boardId => [
                        'board_id' => $boardId,
                        'subject_id' => $subjectId,
                        'domain_id' => (string) ($taxonomy['domain_id'] ?? ''),
                        'topic_id' => (string) ($taxonomy['topic_id'] ?? ''),
                        'concept_id' => (string) ($taxonomy['concept_id'] ?? ''),
                        'blueprint_node' => (string) ($taxonomy['concept_id'] ?? $subjectId),
                    ]],
                ]);
            }
        }

        if (Request::input('action') === 'save_next') {
            Response::redirect('/question-editor/create');
        }
        $return = Request::input('return', Request::queryString('return'));
        $destination = self::safeReturn($return, '/question-editor');
        Response::redirect($destination . (str_contains($destination, '?') ? '&' : '?') . 'saved=1');
    }

    public static function archive(): void
    {
        self::requireCsrf();
        $id =
            Request::queryString("id");

        $question =
            QuestionEditorService::find($id);

        if ($question !== null) {
            $question["status"] = "archived";

            QuestionService::update(
                $id,
                $question
            );
        }

        $return = Request::queryString('return');
        $destination = self::safeReturn($return, '/question-editor');
        Response::redirect($destination . (str_contains($destination, '?') ? '&' : '?') . 'notice=archived');
    }

    public static function restore(): void
    {
        self::requireCsrf();
        $id =
            Request::queryString("id");

        $question =
            QuestionEditorService::find($id);

        if ($question !== null) {
            $question["status"] = "approved";

            QuestionService::update(
                $id,
                $question
            );
        }

        Response::redirect('/question-editor?notice=restored');
    }

    public static function share(): void
    {
        self::requireCsrf();
        self::mutateEligibility('share');
    }

    public static function placement(): void
    {
        self::requireCsrf();
        self::mutateEligibility('placement');
    }

    public static function unshare(): void
    {
        self::requireCsrf();
        $question = QuestionEditorService::find(Request::queryString('id'));
        if ($question === null) {
            Response::redirect('/question-editor');
        }
        $result = QuestionEligibilityService::removeMapping(
            $question,
            self::inputString('target_board')
        );
        if (($result['valid'] ?? false) !== true) {
            self::workspace([
                'pageTitle' => 'Edit Question',
                'contentMode' => 'edit',
                'question' => $question,
                'eligibilityErrors' => $result['errors'] ?? [],
                'eligibilityState' => 'unshare',
            ]);
            return;
        }
        Response::redirect('/question-editor/edit?id=' . urlencode((string) $question['id']) . '&notice=unshared');
    }

    public static function duplicate(): void
    {
        self::requireCsrf();
        $source = QuestionEditorService::find(Request::queryString('id'));
        if ($source === null) {
            Response::redirect('/question-editor');
        }
        $mapping = QuestionEligibilityService::validateMapping($source, Request::input());
        foreach (['target_domain', 'target_topic', 'target_concept'] as $field) {
            if (self::inputString($field) === '') {
                $mapping['errors']['MISSING_REQUIRED_MAPPING_METADATA'] = 'Choose the complete target taxonomy path before creating an independent question.';
            }
        }
        if (($mapping['valid'] ?? false) !== true || !empty($mapping['errors'])) {
            self::workspace([
                'pageTitle' => 'Edit Question',
                'contentMode' => 'edit',
                'question' => $source,
                'eligibilityErrors' => $mapping['errors'] ?? [],
                'eligibilityState' => 'duplicate',
            ]);
            return;
        }

        $input = self::duplicateInput($source, $mapping['mapping']);
        $draft = QuestionAuthoringService::prepare(0, $input);
        if (($draft['valid'] ?? false) !== true) {
            self::workspace([
                'pageTitle' => 'Duplicate Question',
                'contentMode' => 'create',
                'question' => $source,
                'errors' => $draft['errors'] ?? ['The source question cannot start a duplicate draft.'],
                'duplicates' => $draft['duplicates'] ?? [],
                'duplicateTarget' => $mapping['mapping'],
            ]);
            return;
        }
        self::workspace([
            'pageTitle' => 'Duplicate Question',
            'contentMode' => 'create',
            'question' => $draft['question'],
            'duplicateSourceNotice' => 'Draft copied from question ' . (string) $source['id'] . '. Review or revise it, then save through the normal creation workflow.',
            'duplicateTarget' => $mapping['mapping'],
        ]);
    }

    private static function mutateEligibility(string $operation): void
    {
        $question = QuestionEditorService::find(self::inputString('id', Request::queryString('id')));
        if ($question === null) {
            Response::redirect('/question-editor');
        }
        $result = $operation === 'placement'
            ? QuestionEligibilityService::updateMapping($question, Request::input())
            : QuestionEligibilityService::addMapping($question, Request::input());
        if (($result['valid'] ?? false) !== true) {
            self::workspace([
                'pageTitle' => 'Edit Question',
                'contentMode' => 'edit',
                'question' => $question,
                'eligibilityErrors' => $result['errors'] ?? [],
                'eligibilityState' => $operation,
            ]);
            return;
        }
        Response::redirect('/question-editor/edit?id=' . urlencode((string) $question['id']) . '&notice=' . urlencode($operation === 'placement' ? 'placement-updated' : 'shared'));
    }

    private static function duplicateInput(array $source, array $mapping): array
    {
        $options = is_array($source['options'] ?? null) ? $source['options'] : [];
        if ($options === [] && is_array($source['choices'] ?? null)) {
            $answer = (string) ($source['answer'] ?? '');
            foreach (array_values($source['choices']) as $index => $choice) {
                if (!is_scalar($choice)) {
                    continue;
                }
                $options[] = [
                    'id' => 'option-' . ($index + 1),
                    'text' => (string) $choice,
                    'correct' => strcasecmp(trim((string) $choice), trim($answer)) === 0,
                ];
            }
        }
        $input = [
            'board' => $mapping['board_id'] ?? '',
            'subject' => $mapping['subject_id'] ?? '',
            'domain' => $mapping['domain_id'] ?? '',
            'topic' => $mapping['topic_id'] ?? '',
            'concept' => $mapping['concept_id'] ?? '',
            'difficulty' => $source['difficulty'] ?? 'medium',
            'status' => 'draft',
            'type' => $source['type'] ?? 'multiple_choice',
            'question' => $source['question'] ?? '',
            'explanation' => $source['explanation'] ?? '',
            'content_blocks_json' => json_encode($source['content_blocks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        foreach (array_values($options) as $index => $option) {
            $input['option_' . ($index + 1)] = (string) ($option['text'] ?? '');
            if (($option['correct'] ?? false) === true) {
                $input['correct_option'] = 'option-' . ($index + 1);
            }
        }
        return $input;
    }

    private static function requireCsrf(): void
    {
        if (!CsrfTokenService::valid(Request::input('_csrf'))) {
            Response::text('Invalid CSRF token.', 403);
        }
    }

    private static function inputString(string $key, mixed $fallback = ''): string
    {
        $value = Request::input($key, $fallback);
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function safeReturn(mixed $return, string $fallback): string
    {
        return is_string($return) && str_starts_with($return, '/') && !str_starts_with($return, '//') ? $return : $fallback;
    }

    private static function taxonomyNames(): array
    {
        $names = [];
        foreach ([
            'board' => TaxonomyStorageService::boards(), 'subject' => TaxonomyStorageService::subjects(),
            'domain' => TaxonomyStorageService::domains(), 'topic' => TaxonomyStorageService::topics(),
            'concept' => TaxonomyStorageService::concepts(),
        ] as $key => $items) {
            foreach ($items as $item) if (is_array($item) && isset($item['id'])) $names[$key][(string) $item['id']] = (string) ($item['name'] ?? $item['id']);
        }
        return $names;
    }
}
