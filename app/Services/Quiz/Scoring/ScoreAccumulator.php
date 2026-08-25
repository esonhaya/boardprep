<?php

declare(strict_types=1);

final class ScoreAccumulator
{
    private int $correct = 0;
    private int $incorrect = 0;
    private int $unanswered = 0;

    public function record(bool $correct, bool $answered): void
    {
        if ($correct) {
            $this->correct++;
        } elseif ($answered) {
            $this->incorrect++;
        } else {
            $this->unanswered++;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $results
     * @return array<string,mixed>
     */
    public function summarize(array $results): array
    {
        $total = count($results);

        return [
            "score" => $this->correct,
            "correct" => $this->correct,
            "incorrect" => $this->incorrect,
            "unanswered" => $this->unanswered,
            "total" => $total,
            "percentage" => $total > 0 ? (int) round(($this->correct / $total) * 100) : 0,
            "results" => $results,
        ];
    }
}
