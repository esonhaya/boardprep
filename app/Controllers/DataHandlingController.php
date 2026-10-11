<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Privacy\AnonymousLearnerDataService;
use App\Services\Shared\CsrfTokenService;
use Throwable;

final class DataHandlingController
{
    public static function index(): void
    {
        $privacy = (array) App::config('privacy', []);
        $privacy['site_policy_complete'] = (bool) ($privacy['terms_approved'] ?? false)
            && trim((string) ($privacy['operator_name'] ?? '')) !== ''
            && trim((string) ($privacy['operator_contact'] ?? '')) !== ''
            && trim((string) ($privacy['retention_notice'] ?? '')) !== ''
            && trim((string) ($privacy['hosting_notice'] ?? '')) !== '';

        View::render('data-handling/index', [
            'pageTitle' => 'Data and privacy | BoardPrep',
            'privacy' => $privacy,
            'learnerDataSummary' => AnonymousLearnerDataService::current()->summary(),
            'csrfToken' => CsrfTokenService::token(),
        ]);
    }

    public static function export(): void
    {
        try {
            $export = json_encode(
                AnonymousLearnerDataService::current()->export(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            \SessionService::flash('error', 'Your learner data export is temporarily unavailable. Please try again.');
            Response::redirect('/privacy', 303);
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="boardprep-learner-data.json"');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $export;
        exit;
    }

    public static function clear(): void
    {
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'Your privacy form expired. Please reload the page and try again.');
            Response::redirect('/privacy', 303);
        }

        if (Request::input('confirm_clear') !== 'yes') {
            \SessionService::flash('error', 'Confirm the data-clear action before continuing.');
            Response::redirect('/privacy', 303);
        }

        try {
            AnonymousLearnerDataService::current()->clear();
            \SessionService::clear();
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_regenerate_id(true);
            }
            $_SESSION = [];
            \SessionService::flash(
                'success',
                'Saved learning data for this browser session was cleared. Hosting logs and backups are controlled separately.'
            );
        } catch (Throwable) {
            \SessionService::flash(
                'error',
                'We could not confirm that all saved learner data was cleared. Please retry or contact the site operator.'
            );
        }

        Response::redirect('/privacy', 303);
    }
}
