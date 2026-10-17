<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Environment;
use App\Core\ReleaseIdentity;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Acceptance\AcceptanceFeedbackReport;
use App\Services\Acceptance\AcceptanceScenarioCatalog;
use App\Services\Acceptance\AcceptanceSessionStore;
use Throwable;

final class AcceptanceCenterController
{
    private const COOKIE_NAME = 'BOARDPREP_ACCEPTANCE_OWNER';
    private const COOKIE_LIFETIME = 2592000;

    public static function index(): void
    {
        self::assertLocalNonproduction();
        View::render('acceptance/index', [
            'pageTitle' => 'Human Acceptance Center · BoardPrep',
            'layout' => 'acceptance',
        ]);
    }

    public static function data(): never
    {
        self::assertLocalNonproduction();
        try {
            $identity = self::ownerIdentity(true);
            $store = self::store();
            $overview = $store->ownerOverview($identity['owner_hash']);
            if ($overview['session_id'] === null) {
                $store->createSession($identity['owner_hash'], self::buildSha());
                $overview = $store->ownerOverview($identity['owner_hash']);
            }
            $requestedId = Request::queryString('session_id');
            $sessionId = $requestedId !== '' ? $requestedId : (string) $overview['session_id'];
            if ($requestedId !== '') {
                $store->selectSession($identity['owner_hash'], $sessionId);
                $overview = $store->ownerOverview($identity['owner_hash']);
            }
            $session = $store->getSession($identity['owner_hash'], $sessionId);
            self::json([
                'ok' => true,
                'csrf_token' => $overview['csrf_token'],
                'session' => $session,
                'sessions' => $overview['sessions'],
                'scenarios' => AcceptanceScenarioCatalog::all(),
                'current_build_sha' => self::buildSha(),
                'learner_base_url' => self::learnerBaseUrl(),
                'categories' => AcceptanceSessionStore::CATEGORIES,
                'priorities' => AcceptanceSessionStore::PRIORITIES,
                'results' => AcceptanceSessionStore::RESULTS,
            ]);
        } catch (Throwable $exception) {
            self::error($exception, 400);
        }
    }

    public static function update(): never
    {
        $request = self::authorizedRequest();
        try {
            $store = self::store();
            $sessionId = self::requiredString($request, 'session_id', 32);
            $action = self::requiredString($request, 'action', 24);
            $record = match ($action) {
                'new' => $store->createSession($request['owner_hash'], self::buildSha()),
                'select' => self::selectAndLoad($store, $request['owner_hash'], $sessionId),
                'focus' => $store->setActiveCase($request['owner_hash'], $sessionId, self::requiredString($request, 'case_id', 7)),
                'metadata' => $store->saveMetadata($request['owner_hash'], $sessionId, self::arrayField($request, 'metadata')),
                'draft' => self::saveDraftAction($store, $request['owner_hash'], $sessionId, $request),
                'outcome' => $store->setOutcome(
                    $request['owner_hash'],
                    $sessionId,
                    self::requiredString($request, 'case_id', 7),
                    self::requiredString($request, 'result', 24),
                    ($request['pass_confirmed'] ?? false) === true
                ),
                'launch' => $store->recordLaunch(
                    $request['owner_hash'],
                    $sessionId,
                    self::requiredString($request, 'case_id', 7),
                    self::requiredString($request, 'path', 1000)
                ),
                'observation' => self::saveObservationAction($store, $request['owner_hash'], $sessionId, $request),
                default => throw new \RuntimeException('Choose a supported Acceptance Center action.'),
            };
            self::json([
                'ok' => true,
                'session' => $record,
                'sessions' => $store->ownerOverview($request['owner_hash'])['sessions'],
            ]);
        } catch (Throwable $exception) {
            self::error($exception, 422);
        }
    }

    public static function export(): never
    {
        self::assertLocalNonproduction();
        try {
            $identity = self::ownerIdentity(false);
            $sessionId = Request::queryString('session_id');
            $session = self::store()->getSession($identity['owner_hash'], $sessionId);
            self::json([
                'ok' => true,
                'report' => AcceptanceFeedbackReport::build($session),
            ]);
        } catch (Throwable $exception) {
            self::error($exception, 403);
        }
    }

    public static function build(): never
    {
        self::assertLocalNonproduction();
        self::json(['ok' => true, 'build_sha' => self::buildSha()]);
    }

    private static function saveDraftAction(AcceptanceSessionStore $store, string $ownerHash, string $sessionId, array $request): array
    {
        $draft = self::arrayField($request, 'draft');
        if (($request['general'] ?? false) === true) {
            return $store->saveGeneralDraft($ownerHash, $sessionId, $draft);
        }
        return $store->saveDraft(
            $ownerHash,
            $sessionId,
            self::requiredString($request, 'case_id', 7),
            $draft
        );
    }

    private static function saveObservationAction(AcceptanceSessionStore $store, string $ownerHash, string $sessionId, array $request): array
    {
        $general = ($request['general'] ?? false) === true;
        $action = self::requiredString($request, 'observation_action', 12);
        $caseId = $general ? '' : self::requiredString($request, 'case_id', 7);
        $observationId = isset($request['observation_id']) && is_string($request['observation_id'])
            ? $request['observation_id']
            : '';
        $observation = is_array($request['observation'] ?? null) ? $request['observation'] : [];

        return match ($action) {
            'add' => $general
                ? $store->addGeneralFeedback($ownerHash, $sessionId, $observation)
                : $store->addObservation($ownerHash, $sessionId, $caseId, $observation),
            'edit' => $general
                ? $store->editGeneralFeedback($ownerHash, $sessionId, $observationId, $observation)
                : $store->editObservation($ownerHash, $sessionId, $caseId, $observationId, $observation),
            'delete' => $general
                ? $store->deleteGeneralFeedback($ownerHash, $sessionId, $observationId)
                : $store->deleteObservation($ownerHash, $sessionId, $caseId, $observationId),
            'move' => $general
                ? $store->moveGeneralFeedback($ownerHash, $sessionId, $observationId, self::requiredString($request, 'direction', 4))
                : $store->moveObservation($ownerHash, $sessionId, $caseId, $observationId, self::requiredString($request, 'direction', 4)),
            default => throw new \RuntimeException('Choose a supported observation action.'),
        };
    }

    private static function selectAndLoad(AcceptanceSessionStore $store, string $ownerHash, string $sessionId): array
    {
        $store->selectSession($ownerHash, $sessionId);
        return $store->getSession($ownerHash, $sessionId);
    }

    private static function authorizedRequest(): array
    {
        self::assertLocalNonproduction();
        if (Request::method() !== 'POST') {
            self::json(['ok' => false, 'error' => 'Use the provided save controls.'], 405);
        }
        self::assertSameOrigin();
        $identity = self::ownerIdentity(false);
        $overview = self::store()->ownerOverview($identity['owner_hash']);
        $csrf = (string) ($_SERVER['HTTP_X_BOARDPREP_CSRF'] ?? '');
        if ($csrf === '' || !hash_equals((string) $overview['csrf_token'], $csrf)) {
            self::json(['ok' => false, 'error' => 'This page expired. Reload it before saving.'], 403);
        }
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length > 131072) {
            self::json(['ok' => false, 'error' => 'This change is too large to save.'], 413);
        }
        $body = file_get_contents('php://input');
        if (!is_string($body) || strlen($body) > 131072) {
            self::json(['ok' => false, 'error' => 'This change is too large to save.'], 413);
        }
        $request = json_decode($body, true);
        if (!is_array($request)) {
            self::json(['ok' => false, 'error' => 'The save request could not be read.'], 400);
        }
        $request['owner_hash'] = $identity['owner_hash'];
        return $request;
    }

    private static function ownerIdentity(bool $createCookie): array
    {
        $token = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            if (!$createCookie) {
                throw new \RuntimeException('No saved Acceptance Center identity was found in this browser. Open the Acceptance Center first.');
            }
            $token = bin2hex(random_bytes(32));
            $https = ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
            setcookie(self::COOKIE_NAME, $token, [
                'expires' => time() + self::COOKIE_LIFETIME,
                'path' => '/developer/acceptance',
                'secure' => $https,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            $_COOKIE[self::COOKIE_NAME] = $token;
        }
        return ['owner_hash' => hash('sha256', $token)];
    }

    private static function store(): AcceptanceSessionStore
    {
        return new AcceptanceSessionStore();
    }

    private static function buildSha(): string
    {
        return ReleaseIdentity::current()['build_commit'];
    }

    private static function learnerBaseUrl(): string
    {
        $configured = trim((string) Environment::get('BOARDPREP_ACCEPTANCE_LEARNER_BASE_URL', ''));
        if ($configured === '') {
            $https = ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
            $scheme = $https ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
            return $scheme . '://' . $host;
        }
        $parts = parse_url($configured);
        $allowedHosts = ['127.0.0.1', 'localhost', '[::1]', '::1'];
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'http'
            || !in_array(strtolower((string) ($parts['host'] ?? '')), $allowedHosts, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || isset($parts['path']) && !in_array($parts['path'], ['', '/'], true)
            || isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535)) {
            throw new \RuntimeException('The isolated learner preview address must be a loopback-only HTTP URL.');
        }
        return rtrim($configured, '/');
    }

    private static function assertSameOrigin(): void
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '') {
            return;
        }
        $originParts = parse_url($origin);
        if (!is_array($originParts) || !isset($originParts['scheme'], $originParts['host'])) {
            self::json(['ok' => false, 'error' => 'Cross-site saves are not allowed.'], 403);
        }
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $requestScheme = $https ? 'https' : 'http';
        $originScheme = strtolower((string) $originParts['scheme']);
        $originHost = strtolower((string) $originParts['host']);
        $originPort = (int) ($originParts['port'] ?? ($originScheme === 'https' ? 443 : 80));
        $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $requestPort = (int) ($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
        if ($originScheme !== $requestScheme || $originHost !== preg_replace('/:\d+$/', '', $requestHost)
            || $originPort !== $requestPort) {
            self::json(['ok' => false, 'error' => 'Cross-site saves are not allowed.'], 403);
        }
    }

    private static function assertLocalNonproduction(): void
    {
        if ((string) App::config('environment', 'production') === 'production') {
            http_response_code(404);
            Response::text('Not found.', 404);
        }
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (!in_array($remote, ['127.0.0.1', '::1', '::ffff:127.0.0.1', ''], true)) {
            http_response_code(404);
            Response::text('Not found.', 404);
        }
        $hostHeader = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\\d+$/', '', $hostHeader) ?? $hostHeader;
        if ($hostHeader !== '' && !in_array($host, ['127.0.0.1', 'localhost', '[::1]', '::1'], true)) {
            http_response_code(404);
            Response::text('Not found.', 404);
        }
    }

    private static function requiredString(array $request, string $key, int $maxLength): string
    {
        $value = $request[$key] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $maxLength) {
            throw new \RuntimeException('The save request is missing a valid ' . $key . '.');
        }
        return $value;
    }

    private static function arrayField(array $request, string $key): array
    {
        if (!is_array($request[$key] ?? null)) {
            throw new \RuntimeException('The save request is missing valid ' . $key . ' details.');
        }
        return $request[$key];
    }

    private static function json(array $data, int $status = 200): never
    {
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        Response::json($data, $status);
    }

    private static function error(Throwable $exception, int $status): never
    {
        $message = $exception instanceof \RuntimeException
            ? $exception->getMessage()
            : 'The Acceptance Center could not complete that action.';
        self::json(['ok' => false, 'error' => $message], $status);
    }
}
