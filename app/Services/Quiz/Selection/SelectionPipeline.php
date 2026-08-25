<?php
declare(strict_types=1);
final class SelectionPipeline
{
    public static function run(array $pool, SelectionRequest $request): array
    {
        return SelectionDeduplicator::unique(WeightedShuffleService::shuffle(DifficultySelectionService::select($pool, $request->difficultyDistribution, $request->questionCount)));
    }
}
