<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Constants\Status;
use App\Repositories\BoardRepository;
use App\Repositories\BlueprintRepository;
use App\Repositories\SubjectRepository;

final class RepositoryTest extends TestCase
{
    public function run(): void
    {
        $storage = new MemoryStorage();

        $this->testBoardRepository($storage);
        $this->testSubjectRepository($storage);
        $this->testBlueprintRepository($storage);
    }

    private function testBoardRepository(
        MemoryStorage $storage
    ): void {
        $repository = new BoardRepository($storage);

        $created = $repository->create([
            'id' => 'let',
            'name' => 'LET',
            'status' => Status::ACTIVE,
        ]);

        $this->assertSame(
            'LET',
            $created['name'] ?? null
        );

        $this->assertSame(
            1,
            count($repository->active())
        );

        $repository->archive('let');

        $this->assertSame(
            0,
            count($repository->active())
        );

        $this->assertSame(
            1,
            count($repository->archived())
        );

        $repository->activate('let');

        $this->assertSame(
            Status::ACTIVE,
            $repository->find('let')['status'] ?? null
        );
    }

    private function testSubjectRepository(
        MemoryStorage $storage
    ): void {
        $repository = new SubjectRepository($storage);

        $repository->create([
            'id' => 'english',
            'name' => 'English',
            'status' => Status::ACTIVE,
        ]);

        $repository->create([
            'id' => 'math',
            'name' => 'Mathematics',
            'status' => Status::ACTIVE,
        ]);

        $this->assertTrue(
            $repository->existsByName('english')
        );

        $this->assertTrue(
            $repository->existsByName('ENGLISH')
        );

        $this->assertFalse(
            $repository->existsByName(
                'English',
                'english'
            )
        );

        $this->assertFalse(
            $repository->existsByName('Science')
        );
    }

    private function testBlueprintRepository(
        MemoryStorage $storage
    ): void {
        $repository = new BlueprintRepository($storage);

        $repository->create([
            'id' => 'let-v1',
            'board_id' => 'let',
            'scope' => 'board',
            'version' => 1,
            'status' => Status::ARCHIVED,
        ]);

        $repository->create([
            'id' => 'let-v2',
            'board_id' => 'let',
            'scope' => 'board',
            'version' => 2,
            'status' => Status::ACTIVE,
        ]);

        $versions = $repository->versions('let');

        $this->assertSame(
            'let-v2',
            $versions[0]['id'] ?? null
        );

        $repository->activate('let-v1');

        $this->assertSame(
            Status::ACTIVE,
            $repository->find('let-v1')['status'] ?? null
        );

        $this->assertSame(
            Status::ARCHIVED,
            $repository->find('let-v2')['status'] ?? null
        );

        $this->assertNotNull(
            $repository->board('let')
        );
    }
}
