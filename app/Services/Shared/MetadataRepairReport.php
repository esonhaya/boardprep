<?php

declare(strict_types=1);

final class MetadataRepairReport
{
    public int $repaired = 0;

    public int $skipped = 0;

    public array $changes = [];

    private array $changedIds = [];

    public function repaired(
        string $id,
        string $field
    ): void {

        $this->repaired++;

        $this->changedIds[$id] = true;

        $this->changes[] = [
            "id" => $id,
            "field" => $field,
        ];

    }

    public function skipped(): void
    {
        $this->skipped++;
    }

    public function hasChanges(
        string $id
    ): bool {

        return isset(
            $this->changedIds[$id]
        );

    }

    public function summary(): array
    {
        return [
            "repaired" => $this->repaired,
            "skipped" => $this->skipped,
            "changes" => $this->changes,
        ];
    }
}
