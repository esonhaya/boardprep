<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Shared\TaxonomyStorageService;

/**
 * Small, repository-backed LET planning view for the developer dashboard.
 * It reports inventory, not official weighting or readiness claims.
 */
final class LETContentCoverageService
{
    private const GEN_ED_AREAS = [
        'Purposive Communication in English',
        'Malayuning Komunikasyon sa Wikang Filipino',
        'Readings in Philippine History and Society',
        'Life and Works of Rizal',
        'The Contemporary World',
        'Art Appreciation',
        'Science and Technology',
        'Mathematics',
        'Ethics',
        'Understanding the Self',
    ];

    private const PROF_ED_AREAS = [
        'Teaching Profession',
        'Foundations',
        'Learning Theories',
        'Child/Adolescent Development',
        'Inclusive Education',
        'Curriculum',
        'Assessment',
        'Teaching Strategies',
        'Classroom Management',
        'Educational Technology',
        'Education Laws',
        'Code of Ethics',
        'PPST',
        'Professional Development',
        'School/Community Relationships',
    ];

    public static function report(): array
    {
        $questions = QuestionEligibilityService::eligible(App::storage()->all('questions'), 'let');
        $genEd = 0;
        $profEd = 0;
        $areas = array_fill_keys(array_merge(self::GEN_ED_AREAS, self::PROF_ED_AREAS), 0);
        $difficulty = ['easy' => 0, 'medium' => 0, 'hard' => 0];
        $domains = array_column(TaxonomyStorageService::domains(), null, 'id');
        $topics = array_column(TaxonomyStorageService::topics(), null, 'id');
        foreach ($questions as $question) {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $subject = (string) ($taxonomy['subject_id'] ?? '');
            $isProfEd = $subject === 'professional-education';
            if (!$isProfEd) {
                $genEd++;
            } else {
                $profEd++;
            }
            foreach (self::areasFor($question, $isProfEd, $domains, $topics) as $area) {
                if (isset($areas[$area])) $areas[$area]++;
            }
            $level = strtolower((string) ($question['difficulty'] ?? ''));
            if (isset($difficulty[$level])) {
                $difficulty[$level]++;
            }
        }
        ksort($areas);
        return [
            'total' => count($questions),
            'general_education' => $genEd,
            'professional_education' => $profEd,
            'areas' => $areas,
            'general_education_areas' => array_intersect_key($areas, array_flip(self::GEN_ED_AREAS)),
            'professional_education_areas' => array_intersect_key($areas, array_flip(self::PROF_ED_AREAS)),
            'difficulty' => $difficulty,
            'study_materials' => count(StudyLibraryService::all('let')),
            'secondary_signals' => count(array_filter(
                ExamIntelligenceService::all('let'),
                static fn(array $signal): bool => ($signal['signal_type'] ?? '') === 'REVIEW_CENTER_SIGNAL'
            )),
        ];
    }

    private static function areasFor(array $question, bool $isProfEd, array $domains, array $topics): array
    {
        $explicit = trim((string) ($question['let_area'] ?? ''));
        if (!$isProfEd && in_array($explicit, self::GEN_ED_AREAS, true)) return [$explicit];
        if (!$isProfEd) return [];
        if (in_array($explicit, self::PROF_ED_AREAS, true)) return [$explicit];

        $domain = (string) ($question['taxonomy']['domain_id'] ?? '');
        $topic = (string) ($question['taxonomy']['topic_id'] ?? '');
        $topicName = strtolower((string) ($topics[$topic]['name'] ?? ''));
        $legacy = match ($explicit) {
            'Teaching Profession and Learner Development' => ['Teaching Profession', 'Child/Adolescent Development', 'Professional Development'],
            'Foundations, Policy and Community' => ['Foundations', 'Education Laws', 'School/Community Relationships'],
            'Professional Ethics and Code of Ethics' => ['Code of Ethics', 'PPST'],
            default => [],
        };
        $canonical = match ($domain) {
            'professional-ethics' => str_contains($topicName, 'development') ? ['Professional Development'] : ['Code of Ethics', 'PPST'],
            'educational-foundations' => str_contains($topicName, 'law') || str_contains($topicName, 'policy') ? ['Education Laws'] : ['Foundations'],
            'learner-development' => ['Child/Adolescent Development'],
            'facilitating-learning' => str_contains($topicName, 'theor') ? ['Learning Theories'] : ['Teaching Strategies'],
            'inclusive-education' => ['Inclusive Education'],
            'curriculum-development' => ['Curriculum'],
            'assessment-of-learning' => ['Assessment'],
            'classroom-management' => ['Classroom Management'],
            'educational-technology' => ['Educational Technology'],
            'school-community-relations' => ['School/Community Relationships'],
            default => [],
        };
        return array_values(array_unique(array_merge($legacy, $canonical)));
    }
}
