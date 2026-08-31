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
            $this->verifyInvalidCreate($simulation, $repository, count($questionsBefore));

            $input = $this->authoringInput('BoardPrep developer lifecycle question');
            $duplicateInput = $this->authoringInput(
                (string) ($questionsBefore[0]['question'] ?? 'Which word is a noun?')
            );
            $this->verifyDuplicateWarning(
                $simulation,
                $duplicateInput,
                count($questionsBefore)
            );
            $id = $this->createQuestion($simulation, $repository, $input, $questionsBefore);
            $this->verifyEditAndReload($simulation, $repository, $id);
            $this->verifyStatusEligibility($simulation, $repository, $id);
            $this->verifyFailureRecovery($simulation);
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
            ['/question-inspector', 'Question Inspector'],
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

        $simulation->get('/question-export')->execute()->assertSuccessful();
    }

    private function verifyInvalidCreate(
        ApplicationSimulator $simulation,
        QuestionRepository $repository,
        int $expectedCount
    ): void {
        $simulation
            ->post('/question-editor/save', ['question' => ''])
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
        int $expectedCount
    ): void {
        $simulation
            ->post('/question-editor/save', $input)
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
        array $questionsBefore
    ): string {
        $simulation->post('/question-editor/save', $input)->execute()->assertStatus(302);
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
        string $id
    ): void {
        $simulation
            ->get('/question-editor/edit?id=' . urlencode($id))
            ->execute()
            ->assertSuccessful()
            ->assertContains('Edit Question');

        $updatedText = 'BoardPrep developer lifecycle question updated';
        $simulation
            ->post('/question-editor/update?id=' . urlencode($id), $this->authoringInput($updatedText))
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
        string $id
    ): void {
        $simulation
            ->post('/question-editor/archive?id=' . urlencode($id))
            ->execute()
            ->assertStatus(302);

        $archived = $repository->find($id);
        if (($archived['status'] ?? '') !== 'archived') {
            throw new \RuntimeException('Archive action did not persist status.');
        }
        $this->assertNotEligible($repository, $id);

        $simulation
            ->post('/question-editor/restore?id=' . urlencode($id))
            ->execute()
            ->assertStatus(302);

        $restored = $repository->find($id);
        if (($restored['status'] ?? '') !== 'approved') {
            throw new \RuntimeException('Restore action did not persist approved status.');
        }
        $this->assertEligible($repository, $id);
    }

    private function verifyFailureRecovery(ApplicationSimulator $simulation): void
    {
        $simulation
            ->get('/question-editor/edit?id=missing-developer-question')
            ->execute()
            ->assertStatus(302);

        $simulation
            ->post('/question-import/import')
            ->execute()
            ->assertStatus(302);
    }

    private function assertEligible(QuestionRepository $repository, string $id): void
    {
        $request = new \SelectionRequest(
            'english',
            'grammar',
            ['medium' => 1],
            1,
            'parts-of-speech'
        );
        $selected = \QuestionSelectionService::fulfillRequest($repository->all(), $request)->questions;
        if (!in_array($id, array_map(static fn (array $question): string => (string) $question['id'], $selected), true)) {
            throw new \RuntimeException('Approved authored question was not learner eligible.');
        }
    }

    private function assertNotEligible(QuestionRepository $repository, string $id): void
    {
        $request = new \SelectionRequest(
            'english',
            'grammar',
            ['medium' => 1],
            1,
            'parts-of-speech'
        );
        $selected = \QuestionSelectionService::fulfillRequest($repository->all(), $request)->questions;
        if (in_array($id, array_map(static fn (array $question): string => (string) $question['id'], $selected), true)) {
            throw new \RuntimeException('Archived authored question became learner eligible.');
        }
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
