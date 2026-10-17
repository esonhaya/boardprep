<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Accounts\AccountDataService;
use App\Services\Accounts\AccountFeatureGate;
use App\Services\Accounts\AccountService;
use App\Services\Accounts\AccountSessionService;
use App\Services\Shared\CsrfTokenService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AccountController
{
    public static function index(): void
    {
        self::guard();
        Response::redirect(AccountSessionService::isAuthenticated() ? '/account/profile' : '/account/login', 303);
    }

    public static function registrationForm(): void
    {
        self::guestOnly();
        self::render('account/register', [
            'pageTitle' => 'Create an account | BoardPrep',
            'csrfToken' => CsrfTokenService::token(),
            'message' => null,
        ]);
    }

    public static function register(): void
    {
        self::guestOnly();
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            self::render('account/register', [
                'pageTitle' => 'Create an account | BoardPrep',
                'csrfToken' => CsrfTokenService::token(),
                'message' => 'This form expired. Reload it and try again.',
                'error' => true,
            ]);
            return;
        }

        $username = self::text(Request::input('username'));
        $password = self::text(Request::input('password'));
        $confirmation = self::text(Request::input('password_confirmation'));
        if ($password !== $confirmation) {
            self::render('account/register', [
                'pageTitle' => 'Create an account | BoardPrep',
                'csrfToken' => CsrfTokenService::token(),
                'username' => $username,
                'message' => 'The password entries do not match.',
                'error' => true,
            ]);
            return;
        }

        try {
            $result = (new AccountService())->register($username, $password);
        } catch (InvalidArgumentException $exception) {
            self::render('account/register', [
                'pageTitle' => 'Create an account | BoardPrep',
                'csrfToken' => CsrfTokenService::token(),
                'username' => $username,
                'message' => $exception->getMessage(),
                'error' => true,
            ]);
            return;
        }

        self::render('account/recovery-codes', [
            'pageTitle' => 'Save account recovery codes | BoardPrep',
            'codes' => $result['recovery_codes'],
            'message' => 'If this username was available, an account was created. Save these codes now. If the username was already registered, these codes are not linked to that account.',
            'nextUrl' => '/account/login',
        ]);
    }

    public static function loginForm(): void
    {
        self::guestOnly();
        self::render('account/login', [
            'pageTitle' => 'Sign in | BoardPrep',
            'csrfToken' => CsrfTokenService::token(),
            'message' => null,
        ]);
    }

    public static function login(): void
    {
        self::guestOnly();
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            self::render('account/login', [
                'pageTitle' => 'Sign in | BoardPrep',
                'csrfToken' => CsrfTokenService::token(),
                'message' => 'This form expired. Reload it and try again.',
                'error' => true,
            ]);
            return;
        }
        $username = self::text(Request::input('username'));
        $password = self::text(Request::input('password'));
        if ((new AccountService())->signIn($username, $password)) {
            \SessionService::flash('success', 'You are signed in. Your account learning data is kept separate from anonymous browsing.');
            Response::redirect('/account/profile', 303);
        }
        self::render('account/login', [
            'pageTitle' => 'Sign in | BoardPrep',
            'csrfToken' => CsrfTokenService::token(),
            'username' => $username,
            'message' => 'Sign-in could not be completed. Check your details or try again later.',
            'error' => true,
        ]);
    }

    public static function logout(): void
    {
        self::guard();
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'This sign-out form expired. Reload the account page and try again.');
            Response::redirect('/account/profile', 303);
        }
        AccountSessionService::logout();
        \SessionService::flash('success', 'You have signed out. Anonymous browsing remains available.');
        Response::redirect('/', 303);
    }

    public static function profile(): void
    {
        $account = self::requireAccount();
        self::render('account/profile', [
            'pageTitle' => 'Account profile | BoardPrep',
            'account' => $account,
            'csrfToken' => CsrfTokenService::token(),
            'sessionDetails' => AccountSessionService::sessionDetails(),
            'flash' => \SessionService::consumeFlash(),
        ]);
    }

    public static function security(): void
    {
        $account = self::requireAccount();
        self::render('account/security', [
            'pageTitle' => 'Account security | BoardPrep',
            'account' => $account,
            'csrfToken' => CsrfTokenService::token(),
            'message' => null,
        ]);
    }

    public static function changePassword(): void
    {
        self::requireAccount();
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            self::securityMessage('This form expired. Reload it and try again.', true);
        }
        $current = self::text(Request::input('current_password'));
        $password = self::text(Request::input('new_password'));
        $confirmation = self::text(Request::input('new_password_confirmation'));
        if ($password !== $confirmation) {
            self::securityMessage('The new password entries do not match.', true);
        }
        try {
            $changed = (new AccountService())->changePassword($current, $password);
        } catch (InvalidArgumentException $exception) {
            self::securityMessage($exception->getMessage(), true);
        }
        if (!$changed) {
            self::securityMessage('The password could not be changed. Check your current password and try again.', true);
        }
        \SessionService::flash('success', 'Your password changed. Other signed-in sessions have been invalidated.');
        Response::redirect('/account/security', 303);
    }

    public static function rotateRecoveryCodes(): void
    {
        self::requireAccount();
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            self::securityMessage('This form expired. Reload it and try again.', true);
        }
        $codes = (new AccountService())->rotateRecoveryCodes(self::text(Request::input('current_password')));
        if (!is_array($codes)) {
            self::securityMessage('Recovery codes were not changed. Check your current password and try again.', true);
        }
        self::render('account/recovery-codes', [
            'pageTitle' => 'Save new recovery codes | BoardPrep',
            'codes' => $codes,
            'message' => 'These codes replace all previous codes. Each code works once. Save this list now; it will not be shown again.',
            'nextUrl' => '/account/security',
        ]);
    }

    public static function recoveryForm(): void
    {
        self::guestOnly();
        self::render('account/recover', [
            'pageTitle' => 'Recover account | BoardPrep',
            'csrfToken' => CsrfTokenService::token(),
            'message' => null,
        ]);
    }

    public static function recover(): void
    {
        self::guestOnly();
        $validCsrf = CsrfTokenService::valid(Request::input('csrf_token'));
        $username = self::text(Request::input('username'));
        $success = false;
        if ($validCsrf) {
            $password = self::text(Request::input('new_password'));
            if ($password === self::text(Request::input('new_password_confirmation'))) {
                $success = (new AccountService())->recover(
                    $username,
                    self::text(Request::input('recovery_code')),
                    $password
                );
            }
        }
        self::render('account/recover', [
            'pageTitle' => 'Recover account | BoardPrep',
            'csrfToken' => CsrfTokenService::token(),
            'username' => $username,
            'message' => $success
                ? 'If the supplied account details were valid, the recovery code has been consumed and the new password is ready. Try signing in.'
                : ($validCsrf
                    ? 'Recovery could not be completed. Check the details, password confirmation, and try again.'
                    : 'This form expired. Reload it and try again.'),
            'error' => !$success,
        ]);
    }

    public static function export(): void
    {
        self::requireAccount();
        if (!CsrfTokenService::valid(Request::input('csrf_token'))
            || !self::verifyCurrentPassword(self::text(Request::input('current_password')))) {
            \SessionService::flash('error', 'Confirm your password in a fresh account session before exporting your data.');
            Response::redirect('/account/profile', 303);
        }
        try {
            $json = json_encode(
                (new AccountDataService())->exportCurrent(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            \SessionService::flash('error', 'Your account export is temporarily unavailable. Please try again.');
            Response::redirect('/account/profile', 303);
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="boardprep-account-data.json"');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $json;
        exit;
    }

    public static function requestDeletion(): void
    {
        self::requireAccount();
        $confirmed = Request::input('confirm_deletion') === 'DELETE';
        $valid = CsrfTokenService::valid(Request::input('csrf_token'))
            && $confirmed
            && (new AccountService())->requestDeletion(self::text(Request::input('current_password')));
        if (!$valid) {
            \SessionService::flash('error', 'The request was not recorded. Confirm the action and current password, then try again.');
            Response::redirect('/account/profile', 303);
        }
        AccountSessionService::logout();
        self::render('account/deletion-requested', [
            'pageTitle' => 'Account deletion request recorded | BoardPrep',
        ]);
    }

    private static function verifyCurrentPassword(string $password): bool
    {
        return (new AccountService())->verifyCurrentPassword($password);
    }

    private static function securityMessage(string $message, bool $error): never
    {
        $account = self::requireAccount();
        self::render('account/security', [
            'pageTitle' => 'Account security | BoardPrep',
            'account' => $account,
            'csrfToken' => CsrfTokenService::token(),
            'message' => $message,
            'error' => $error,
        ]);
        exit;
    }

    private static function guestOnly(): void
    {
        self::guard();
        if (AccountSessionService::isAuthenticated()) {
            Response::redirect('/account/profile', 303);
        }
    }

    private static function requireAccount(): array
    {
        self::guard();
        $account = AccountSessionService::current();
        if (!is_array($account)) {
            \SessionService::flash('error', 'Sign in to open this account page.');
            Response::redirect('/account/login', 303);
        }
        return $account;
    }

    private static function guard(): void
    {
        if (!AccountFeatureGate::enabled()) {
            throw new RuntimeException('Route not found.', 404);
        }
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('Referrer-Policy: no-referrer');
    }

    private static function render(string $view, array $data): void
    {
        self::guard();
        View::render($view, $data);
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
