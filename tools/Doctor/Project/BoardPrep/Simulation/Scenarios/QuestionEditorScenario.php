<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Scenarios;

use App\Core\App;
use App\Repositories\QuestionRepository;
use Tools\Doctor\Project\BoardPrep\Simulation\ApplicationSimulator;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationScenario;

final class QuestionEditorScenario extends SimulationScenario
{
    public function name(): string
    {
        return 'Developer content lifecycle';
    }

    public function audience(): string
    {
        return 'developer';
    }

    public function run(
        ApplicationSimulator $simulation
    ): void {
        $environmentBefore = getenv('APP_ENV');
        putenv('APP_ENV=development');
        $storagePath = (string) (App::config('database')['path'] ?? '');
        $questionPath = $storagePath . '/questions.json';
        $questionBefore = $this->fileContents($questionPath);
        $taxonomyBefore = $this->taxonomyContents($storagePath);
        $otherBefore = $this->otherContents($storagePath);
        $repository = App::container()->get(QuestionRepository::class);
        $questionsBefore = $repository->all();

        try {
            $this->verifyDeveloperSurfaces($simulation);
            $csrfToken = $this->csrfToken($simulation);
            $this->verifyInvalidCreate($simulation, $repository, count($questionsBefore), $csrfToken);

            $input = $this->authoringInput('BoardPrep developer lifecycle question');
            $duplicateInput = $this->authoringInput(
                (string) ($questionsBefore[0]['question'] ?? 'Which word is a noun?')
            );
            $this->verifyDuplicateWarning(
                $simulation,
                $duplicateInput,
                count($questionsBefore),
                $csrfToken
            );
            $id = $this->createQuestion($simulation, $repository, $input, $questionsBefore, $csrfToken);
            $this->verifyEditAndReload($simulation, $repository, $id, $csrfToken);
            $this->verifyStatusEligibility($simulation, $repository, $id, $csrfToken);
            $this->verifyFailureRecovery($simulation, $csrfToken);
        } finally {
            $this->restore($questionPath, $questionBefore);
            if ($environmentBefore === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $environmentBefore);
            }
        }

        $simulation->result()->record(
            'QUESTION_STORAGE_RESTORED',
            $this->fileContents($questionPath) === $questionBefore
        );
        $simulation->result()->record(
            'TAXONOMY_STORAGE_RESTORED',
            $this->taxonomyContents($storagePath) === $taxonomyBefore
        );
        $simulation->result()->record(
            'OTHER_STORAGE_RESTORED',
            $this->otherContents($storagePath) === $otherBefore
        );
    }

    private function verifyDeveloperSurfaces(ApplicationSimulator $simulation): void
    {
        $surfaces = [
            ['/developer', 'Developer Dashboard'],
            ['/question-editor', 'Question Editor'],
            ['/question-editor/create', 'Create Question'],
            ['/question-editor?search=agreement', 'Question Editor'],
            ['/question-quality', 'Question Quality'],
            ['/taxonomy', 'Taxonomy Manager'],
            ['/subjects', 'Subject'],
            ['/boards', 'Board'],
            ['/blueprints', 'Blueprint'],
            ['/coverage', 'Coverage'],
            ['/blueprint-health', 'Blueprint'],
            ['/metadata-repair', 'Metadata'],
            ['/question-import', 'Import Questions'],
        ];

        foreach ($surfaces as [$uri, $heading]) {
            $simulation->get($uri)->execute()->assertSuccessful()->assertContains($heading);
        }

        $inspectorRedirect = $simulation->get('/question-inspector')->execute();
        $inspectorRedirect->assertStatus(302);
        $location = $simulation->context()->get('http', [])['location'] ?? null;
        if ($location !== '/question-editor') {
            throw new \RuntimeException('Question inspector entry point did not redirect to the bounded question repository.');
        }
        $simulation->get($location)->execute()->assertSuccessful()->assertContains('Question Editor');

        $simulation->get('/question-export')->execute()->assertSuccessful();
    }

    private function csrfToken(ApplicationSimulator $simulation): string
    {
        $simulation->get('/question-editor/create')->execute()->assertSuccessful();
        $body = $simulation->context()->get('http')['output'] ?? '';
        if (!is_string($body) || preg_match('/name="_csrf"\s+value="([^"]+)"/', $body, $match) !== 1) {
            throw new \RuntimeException('Question editor form did not provide its CSRF token.');
        }
        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function verifyInvalidCreate(
        ApplicationSimulator $simulation,
        QuestionRepository $repository,
        int $expectedCount,
        string $csrfToken
    ): void {
        $simulation
            ->post('/question-editor/save', ['question' => '', '_csrf' => $csrfToken])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Create Question');

        if (count($repository->all()) !== $expectedCount) {
            throw new \RuntimeException('Invalid question input changed storage.');
        }
    }

    private function verifyDuplicateWarning(
        ApplicationSimulator $simulation,
        array $input,
        int $expectedCount,
        string $csrfToken
    ): void {
        $simulation
            ->post('/question-editor/save', $input + ['_csrf' => $csrfToken])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Possible Duplicate Questions');

        $repository = App::container()->get(QuestionRepository::class);
        if (count($repository->all()) !== $expectedCount) {
            throw new \RuntimeException('Duplicate warning path changed storage.');
        }
    }

    private function createQuestion(
        ApplicationSimulator $simulation,
        QuestionRepository $repository,
        array $input,
        array $questionsBefore,
        string $csrfToken
    ): string {
        $simulation->post('/question-editor/save', $input + ['_csrf' => $csrfToken])->execute()->assertStatus(302);
        $created = array_values(array_filter(
            $repository->all(),
            static function (array $question) use ($questionsBefore): bool {
                $beforeIds = array_map('strval', array_column($questionsBefore, 'id'));
                return !in_array((string) ($question['id'] ?? ''), $beforeIds, true);
            }
        ));

        $id = (string) ($created[0]['id'] ?? '');
        if ($id === '' || count($created) !== 1) {
            throw new \RuntimeException('Valid question creation did not persist one record.');
        }

        return $id;
    }

    private function verifyEditAndReload(
        ApplicationSimulator $simulation,
        QuestionRepository $repository,
        string $id,
        string $csrfToken
    ): void {
        $simulation
            ->get('/question-editor/edit?id=' . urlencode($id))
            ->execute()
            ->assertSuccessful()
            ->assertContains('Edit Question');

        $updatedText = 'BoardPrep developer lifecycle question updated';
        $simulation
            ->post('/question-editor/update?id=' . urlencode($id), $this->authoringInput($updatedText) + ['_csrf' => $csrfToken])
            ->execute()
            ->assertStatus(302);

        $reloaded = $repository->find($id);
        if (($reloaded['question'] ?? '') !== $updatedText) {
            throw new \RuntimeException('Edited question did not reload with saved content.');
        }
    }

    private function verifyStatusEligibility(
        ApplicationSimulator $simulation,
        QuestionRepository $repository,
        string $id,
        string $csrfToken
    ): void {
        $archive = $simulation
            ->post('/question-editor/archive?id=' . urlencode($id), ['_csrf' => $csrfToken])
            ->execute();
        $archive->assertStatus(302);

        $archived = $this->freshQuestion($repository, $id);
        if (($archived['status'] ?? '') !== 'archived') {
            $http = $simulation->context()->get('http', []);
            throw new \RuntimeException(sprintf(
                'Archive action did not persist status for question %s (stored=%s, HTTP=%s, location=%s).',
                $id,
                (string) ($archived['status'] ?? 'missing'),
                (string) ($http['status'] ?? 'unknown'),
                (string) ($http['location'] ?? 'none')
            ));
        }
        $this->assertNotEligible($repository, $id);

        $simulation
            ->post('/question-editor/restore?id=' . urlencode($id), ['_csrf' => $csrfToken])
            ->execute()
            ->assertStatus(302);

        $restored = $this->freshQuestion($repository, $id);
        if (($restored['status'] ?? '') !== 'approved') {
            throw new \RuntimeException('Restore action did not persist approved status.');
        }
        $this->assertEligible($repository, $id);
    }

    private function verifyFailureRecovery(ApplicationSimulator $simulation, string $csrfToken): void
    {
        $simulation
            ->get('/question-editor/edit?id=missing-developer-question')
            ->execute()
            ->assertStatus(302);

        $simulation
            ->post('/question-import/import', ['_csrf' => $csrfToken])
            ->execute()
            ->assertStatus(302);
    }

    private function assertEligible(QuestionRepository $repository, string $id): void
    {
        $question = $this->freshQuestion($repository, $id);
        if ($question === null) {
            throw new \RuntimeException("Authored question {$id} was not found in fresh storage.");
        }
        $request = new \SelectionRequest(
            'english',
            'grammar',
            ['medium' => 1],
            1,
            'parts-of-speech'
        );
        $selected = \QuestionSelectionService::fulfillRequest([$question], $request)->questions;
        if (!in_array($id, array_map(static fn (array $question): string => (string) $question['id'], $selected), true)) {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            throw new \RuntimeException(sprintf(
                'Approved authored question was not learner eligible (id=%s, status=%s, subject=%s, domain=%s, topic=%s, difficulty=%s, selected=%d).',
                $id,
                (string) ($question['status'] ?? 'missing'),
                (string) ($question['subject'] ?? $taxonomy['subject_id'] ?? ''),
                (string) ($question['domain'] ?? $taxonomy['domain_id'] ?? ''),
                (string) ($question['topic'] ?? $taxonomy['topic_id'] ?? ''),
                (string) ($question['difficulty'] ?? ''),
                count($selected)
            ));
        }
    }

    private function assertNotEligible(QuestionRepository $repository, string $id): void
    {
        $question = $this->freshQuestion($repository, $id);
        if ($question === null) {
            throw new \RuntimeException("Authored question {$id} was not found in fresh storage.");
        }
        $request = new \SelectionRequest(
            'english',
            'grammar',
            ['medium' => 1],
            1,
            'parts-of-speech'
        );
        $selected = \QuestionSelectionService::fulfillRequest([$question], $request)->questions;
        if (in_array($id, array_map(static fn (array $question): string => (string) $question['id'], $selected), true)) {
            throw new \RuntimeException('Archived authored question became learner eligible.');
        }
    }

    private function freshQuestion(QuestionRepository $repository, string $id): ?array
    {
        foreach ($repository->all() as $question) {
            if ((string) ($question['id'] ?? '') === $id) {
                return $question;
            }
        }

        return null;
    }

    private function authoringInput(string $question): array
    {
        return [
            'board' => 'let',
            'subject' => 'english',
            'domain' => 'grammar',
            'topic' => 'parts-of-speech',
            'concept' => 'parts-of-speech-nouns',
            'difficulty' => 'medium',
            'question' => $question,
            'option_1' => 'Teacher',
            'option_2' => 'Quickly',
            'option_3' => 'Blue',
            'option_4' => 'Run',
            'correct_option' => 'option-1',
            'explanation' => 'Teacher names a person.',
        ];
    }

    private function fileContents(string $path): string
    {
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function taxonomyContents(string $storagePath): array
    {
        return $this->snapshot($storagePath, [
            'boards.json',
            'subjects.json',
            'taxonomy/domains.json',
            'taxonomy/topics.json',
            'taxonomy/concepts.json',
            'board-subjects.json',
        ]);
    }

    private function otherContents(string $storagePath): array
    {
        return $this->snapshot($storagePath, ['attempts.json', 'weakness.json']);
    }

    private function snapshot(string $storagePath, array $files): array
    {
        $snapshot = [];
        foreach ($files as $file) {
            $path = $storagePath . '/' . $file;
            $snapshot[$file] = $this->fileContents($path);
        }
        return $snapshot;
    }

    private function restore(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Question storage could not be restored.');
        }
    }
}
