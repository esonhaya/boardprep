<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\Advisor\PriorityAdvisor;
use Tools\Doctor\Advisor\RecommendationAdvisor;
use Tools\Doctor\DTO\DoctorResult;

final class ConsoleRenderer
{
    public function render(
        DoctorResult $report
    ): void {

        $pass = 0;
        $warning = 0;
        $fail = 0;
        $info = 0;

        echo PHP_EOL;
        echo "======================================" . PHP_EOL;
        echo " BoardPrep Doctor" . PHP_EOL;
        echo "======================================" . PHP_EOL;
        echo PHP_EOL;

        foreach ($report->checks as $check) {

            echo "[{$check->status}] {$check->title}" . PHP_EOL;

            if ($check->summary !== "") {
                echo "  > {$check->summary}" . PHP_EOL;
            }

            foreach ($check->details as $detail) {
                echo "  - {$detail}" . PHP_EOL;
            }

            echo PHP_EOL;

            switch ($check->status) {

                case "PASS":
                    $pass++;
                    break;

                case "WARNING":
                    $warning++;
                    break;

                case "FAIL":
                    $fail++;
                    break;

                default:
                    $info++;
                    break;

            }

        }

        $total = max(
            1,
            $pass + $warning + $fail + $info
        );

        $health = round(
            (
                ($pass * 100)
                + ($info * 100)
                + ($warning * 75)
            ) / $total
        );

        echo "======================================" . PHP_EOL;
        echo " SUMMARY" . PHP_EOL;
        echo "======================================" . PHP_EOL;
        echo PHP_EOL;

        echo "PASS     : {$pass}" . PHP_EOL;
        echo "WARNING  : {$warning}" . PHP_EOL;
        echo "FAIL     : {$fail}" . PHP_EOL;
        echo "INFO     : {$info}" . PHP_EOL;
        echo PHP_EOL;

        echo "Project Health : {$health}%" . PHP_EOL;

        if ($report->trend !== []) {

            echo PHP_EOL;
            echo "======================================" . PHP_EOL;
            echo " TRENDS" . PHP_EOL;
            echo "======================================" . PHP_EOL;
            echo PHP_EOL;

            foreach ($report->trend as $metric => $delta) {

                $symbol =
                    $delta > 0
                        ? "+"
                        : "";

                echo ucfirst($metric)
                    . " : "
                    . $symbol
                    . $delta
                    . PHP_EOL;

            }

        }

        $priorities = [];

        foreach ($report->checks as $check) {

            if ($check->status === "PASS") {
                continue;
            }

            $impact =
                PriorityAdvisor::impact(
                    $check->title
                );

            $priorities[] = [

                "title" =>
                    $check->title,

                "impact" =>
                    $impact,

                "label" =>
                    PriorityAdvisor::label(
                        $impact
                    ),

            ];

        }

        usort(
            $priorities,
            fn ($a, $b) =>
                $b["impact"] <=> $a["impact"]
        );

        if ($priorities !== []) {

            echo PHP_EOL;
            echo "======================================" . PHP_EOL;
            echo " TOP PRIORITIES" . PHP_EOL;
            echo "======================================" . PHP_EOL;
            echo PHP_EOL;

            foreach (

                array_slice(
                    $priorities,
                    0,
                    5
                )

                as $priority

            ) {

                echo "[{$priority["label"]}] {$priority["title"]}" . PHP_EOL;
                echo "  Impact : {$priority["impact"]}" . PHP_EOL;
                echo PHP_EOL;

            }

        }

        $advisor =
            new RecommendationAdvisor();

        $recommendations =
            $advisor->recommendations(
                $report
            );

        if ($recommendations !== []) {

            echo "======================================" . PHP_EOL;
            echo " RECOMMENDATIONS" . PHP_EOL;
            echo "======================================" . PHP_EOL;
            echo PHP_EOL;

            foreach ($recommendations as $recommendation) {

                echo "• {$recommendation}" . PHP_EOL;

            }

            echo PHP_EOL;

        }

    }
}
