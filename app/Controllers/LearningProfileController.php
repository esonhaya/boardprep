<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningTimelineService;
use App\Services\Learning\PerformanceAnalyticsService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\TopicPerformanceService;
use App\Services\Learning\WeaknessService;
use App\Services\Learning\RecommendationService;
use App\Services\Learning\LearningCoachService;
use App\Services\Profile\LearningProfileService;
use App\Support\Presentation\PreviewCollection;
use App\Services\Shared\CsrfTokenService;
use App\Services\Profile\LearnerPreferenceService;
use App\Core\Request;
use App\Core\Response;
use App\Services\Board\BoardViewService;

final class LearningProfileController
{
    public static function savePreferences(): void
    {
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'Your profile form expired. Please try again.');
            Response::redirect('/profile', 303);
        }
        try {
            $accountPreferences = \App\Services\Accounts\AccountFeatureGate::enabled()
                && \App\Services\Accounts\AccountSessionService::current() !== null;
            LearnerPreferenceService::save(Request::input());
            \SessionService::flash(
                'success',
                $accountPreferences
                    ? 'Your learner preferences were saved to your account.'
                    : 'Your learner preferences were saved for this browser session.'
            );
        } catch (\InvalidArgumentException $exception) {
            \SessionService::flash('error', $exception->getMessage());
        }
        Response::redirect('/profile', 303);
    }

    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        $analytics = PerformanceAnalyticsService::summary($attempts);
        $weaknesses = WeaknessService::all();
        if ($attempts === []) {
            $weaknesses = [];
        }

        $profile =
            LearningProfileService::build(
                $analytics,
                $weaknesses
            );

        $recommendations =
            RecommendationService::generate(
                $analytics,
                $weaknesses
            );

        $coach =
            LearningCoachService::build(
                $analytics,
                $weaknesses,
                $recommendations
            );

        $dashboard =
            StudyDashboardService::build($attempts);

        View::render(
            "profile/index",
            [
                "profile" => $profile,
                "analytics" => $analytics,
                "weaknesses" => $weaknesses,
                "recommendations" => $recommendations,
                "coach" => $coach,
                "timeline" =>
                    LearningTimelineService::build(
                        PreviewCollection::items($attempts)
                    ),
                "timelineHasMore" => PreviewCollection::hasMore($attempts),
                "topics" =>
                    TopicPerformanceService::summarize($attempts),
                "subjects" => $dashboard["subjects"],
                "streak" =>
                    $dashboard["streak"],
                "insight" =>
                    $dashboard["insight"],
                "studyRecommendations" =>
                    $dashboard["recommendations"],
                'preferences' => LearnerPreferenceService::get(),
                'accountPreferences' => \App\Services\Accounts\AccountSessionService::current() !== null,
                'boards' => BoardViewService::all(),
                'letSpecializations' => \App\Services\Quiz\LET\LETVariantRegistry::specializations(),
                'csrfToken' => CsrfTokenService::token(),
                'flash' => \SessionService::consumeFlash(),
            ]
        );
    }
}
