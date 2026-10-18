<?php

declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('BOARDPREP_ACCOUNTS_ENABLED=true');
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

function accountHttpAssert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function accountHttpCsrf(string $html): string
{
    if (preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match) !== 1) {
        throw new RuntimeException('Account form did not contain a CSRF token.');
    }
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function accountHttpRotatedSessionId(string $sessionDirectory, string $accountId): string
{
    foreach (glob(rtrim($sessionDirectory, DIRECTORY_SEPARATOR) . '/sess_*') ?: [] as $path) {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if (is_string($contents) && str_contains($contents, $accountId)) {
            return substr(basename($path), strlen('sess_'));
        }
    }
    throw new RuntimeException('The synthetic authenticated session rotation could not be followed.');
}

$http = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$storage = \Tools\Doctor\Project\BoardPrep\Simulation\SimulationStorageFixture::storagePath();
$cookieA = ['PHPSESSID' => 'batch692-auth-' . bin2hex(random_bytes(8))];
$cookieB = ['PHPSESSID' => 'batch692-auth-' . bin2hex(random_bytes(8))];

$anonymousHome = $http->request('GET', '/', [], [], [], $cookieB);
accountHttpAssert($anonymousHome['status'] === 200, 'Anonymous learner home was blocked by optional account features.');
$unauthorizedProfile = $http->request('GET', '/account/profile', [], [], [], $cookieB);
accountHttpAssert($unauthorizedProfile['status'] === 303
    && $unauthorizedProfile['location'] === '/account/login',
    'Unauthenticated account profile access was not redirected to sign-in.');
$noLinkRoute = $http->request('GET', '/account/link-anonymous');
$noRepeatLinkRoute = $http->request('POST', '/account/link-anonymous');
accountHttpAssert($noLinkRoute['status'] === 404 && $noRepeatLinkRoute['status'] === 404,
    'Unimplemented anonymous-data linking route was exposed.');

$registerPage = $http->request('GET', '/account/register', [], [], [], $cookieA);
accountHttpAssert($registerPage['status'] === 200
    && str_contains($registerPage['output'], 'Continue anonymously')
    && str_contains($registerPage['output'], 'does not send email'),
    'Registration page did not explain optional, non-email accounts. status=' . $registerPage['status'] . ' body=' . substr(strip_tags($registerPage['output']), 0, 220) . ' stderr=' . substr((string) ($registerPage['stderr'] ?? ''), 0, 400));
$missingCsrf = $http->request('POST', '/account/register', [], [
    'username' => 'httpaccount',
    'password' => 'Batch692 HTTP strong passphrase',
    'password_confirmation' => 'Batch692 HTTP strong passphrase',
], [], $cookieA);
accountHttpAssert($missingCsrf['status'] === 200
    && str_contains($missingCsrf['output'], 'form expired'),
    'Registration request without CSRF was not rejected.');

$registerPage = $http->request('GET', '/account/register', [], [], [], $cookieA);
$register = $http->request('POST', '/account/register', [], [
    'csrf_token' => accountHttpCsrf($registerPage['output']),
    'username' => 'httpaccount',
    'password' => 'Batch692 HTTP strong passphrase',
    'password_confirmation' => 'Batch692 HTTP strong passphrase',
], [], $cookieA);
accountHttpAssert($register['status'] === 200
    && substr_count($register['output'], '<code>') === 8
    && str_contains($register['output'], 'If this username was available'),
    'Registration did not display the one-time recovery list with an enumeration-safe message.');

$duplicatePage = $http->request('GET', '/account/register', [], [], [], $cookieB);
$duplicate = $http->request('POST', '/account/register', [], [
    'csrf_token' => accountHttpCsrf($duplicatePage['output']),
    'username' => 'httpaccount',
    'password' => 'Batch692 HTTP other strong passphrase',
    'password_confirmation' => 'Batch692 HTTP other strong passphrase',
], [], $cookieB);
accountHttpAssert($duplicate['status'] === 200
    && substr_count($duplicate['output'], '<code>') === 8
    && str_contains($duplicate['output'], 'If this username was available'),
    'Duplicate registration had a distinguishable response.');
$accountState = json_decode((string) file_get_contents($storage . '/private/learner-accounts/accounts.json'), true);
accountHttpAssert(is_array($accountState) && count($accountState['accounts'] ?? []) === 1, 'Duplicate registration created another account.');

$registerCodes = [];
if (preg_match_all('/<code>([A-F0-9-]+)<\/code>/', $register['output'], $codes) === false || count($codes[1] ?? []) !== 8) {
    throw new RuntimeException('One-time recovery codes could not be parsed from the registration response.');
}
$registerCodes = $codes[1];
$recoveryPage = $http->request('GET', '/account/recover', [], [], [], $cookieA);
$recovered = $http->request('POST', '/account/recover', [], [
    'csrf_token' => accountHttpCsrf($recoveryPage['output']),
    'username' => 'httpaccount',
    'recovery_code' => $registerCodes[0],
    'new_password' => 'Batch692 HTTP recovered password',
    'new_password_confirmation' => 'Batch692 HTTP recovered password',
], [], $cookieA);
accountHttpAssert($recovered['status'] === 200
    && str_contains($recovered['output'], 'the recovery code has been consumed'),
    'A valid recovery request did not complete.');
$replayed = $http->request('POST', '/account/recover', [], [
    'csrf_token' => accountHttpCsrf($recoveryPage['output']),
    'username' => 'httpaccount',
    'recovery_code' => $registerCodes[0],
    'new_password' => 'Batch692 HTTP replay password',
    'new_password_confirmation' => 'Batch692 HTTP replay password',
], [], $cookieA);
accountHttpAssert(str_contains($replayed['output'], 'Recovery could not be completed'), 'Recovery code replay was not rejected.');

$loginPage = $http->request('GET', '/account/login', [], [], [], $cookieA);
$wrongPassword = $http->request('POST', '/account/login', [], [
    'csrf_token' => accountHttpCsrf($loginPage['output']),
    'username' => 'httpaccount',
    'password' => 'Batch692 HTTP original passphrase',
], [], $cookieA);
accountHttpAssert(str_contains($wrongPassword['output'], 'Sign-in could not be completed'), 'Wrong password did not use the generic sign-in response.');
$missingLoginCsrf = $http->request('POST', '/account/login', [], [
    'username' => 'httpaccount',
    'password' => 'Batch692 HTTP recovered password',
], [], $cookieA);
accountHttpAssert(str_contains($missingLoginCsrf['output'], 'form expired'), 'Login CSRF without a session token was accepted.');
$loginPage = $http->request('GET', '/account/login', [], [], [], $cookieA);
$correctLogin = $http->request('POST', '/account/login', [], [
    'csrf_token' => accountHttpCsrf($loginPage['output']),
    'username' => 'httpaccount',
    'password' => 'Batch692 HTTP recovered password',
], [], $cookieA);
accountHttpAssert($correctLogin['status'] === 303
    && $correctLogin['location'] === '/account/profile',
    'Correct credentials did not use the account profile redirect.');

$accountState = json_decode((string) file_get_contents($storage . '/private/learner-accounts/accounts.json'), true);
$accountId = $accountState['accounts']['httpaccount']['account_id'] ?? '';
accountHttpAssert(is_string($accountId) && $accountId !== '', 'Synthetic HTTP account identifier was unavailable.');
$cookieA['PHPSESSID'] = accountHttpRotatedSessionId($http->sessionPath(), $accountId);
$profilePage = $http->request('GET', '/account/profile', [], [], [], $cookieA);
accountHttpAssert(str_contains($profilePage['output'], 'Provider logs, historical backups, and external copies')
    && str_contains($profilePage['output'], 'quiz history may be lost'),
    'Deletion consequences and provider-copy limits were not explained before confirmation.');
$missingDeleteCsrf = $http->request('POST', '/account/deletion-request', [], [
    'current_password' => 'Batch692 HTTP recovered password',
    'confirm_deletion' => 'DELETE',
], [], $cookieA);
accountHttpAssert($missingDeleteCsrf['status'] === 303, 'Account deletion accepted a request without CSRF.');
$accountState = json_decode((string) file_get_contents($storage . '/private/learner-accounts/accounts.json'), true);
$accountId = $accountState['accounts']['httpaccount']['account_id'] ?? '';
$learnerId = $accountState['accounts']['httpaccount']['learner_id'] ?? '';
accountHttpAssert(is_string($accountId) && $accountId !== '' && is_string($learnerId) && $learnerId !== '', 'Synthetic deletion account identity was unavailable.');
$attemptPath = $storage . '/attempts.json';
$attemptRecords = is_file($attemptPath) ? json_decode((string) file_get_contents($attemptPath), true) : [];
accountHttpAssert(is_array($attemptRecords), 'Synthetic attempt collection was malformed before deletion.');
$attemptRecords[] = ['id' => 'synthetic-http-account-attempt', 'user_id' => $learnerId, 'score' => 7, 'total' => 10];
$attemptRecords[] = ['id' => 'synthetic-http-anonymous-attempt', 'user_id' => 'learner-' . str_repeat('a', 32), 'score' => 5, 'total' => 10];
file_put_contents($attemptPath, json_encode($attemptRecords, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
$profilePage = $http->request('GET', '/account/profile', [], [], [], $cookieA);
$validDelete = $http->request('POST', '/account/deletion-request', [], [
    'csrf_token' => accountHttpCsrf($profilePage['output']),
    'current_password' => 'Batch692 HTTP recovered password',
    'confirm_deletion' => 'DELETE',
], [], $cookieA);
accountHttpAssert($validDelete['status'] === 200
    && str_contains($validDelete['output'], 'Application account data deleted'),
    'Confirmed password-reauthenticated HTTP deletion did not complete.');
$accountState = json_decode((string) file_get_contents($storage . '/private/learner-accounts/accounts.json'), true);
$tombstone = $accountState['accounts']['deleted:' . hash('sha256', $accountId)] ?? null;
$deletedRecords = json_decode((string) file_get_contents($attemptPath), true);
accountHttpAssert(is_array($deletedRecords)
    && !in_array('synthetic-http-account-attempt', array_column($deletedRecords, 'id'), true)
    && in_array('synthetic-http-anonymous-attempt', array_column($deletedRecords, 'id'), true),
    'HTTP deletion failed to remove only account-owned attempts.');
accountHttpAssert(is_array($tombstone) && ($tombstone['status'] ?? null) === 'deletion_completed'
    && !isset($tombstone['username'], $tombstone['password_hash'], $tombstone['recovery_code_hashes'], $tombstone['preferences'])
    && !isset($accountState['accounts']['httpaccount']),
    'HTTP deletion did not replace synthetic credentials with a minimal tombstone.');

putenv('APP_ENV=production');
$_ENV['APP_ENV'] = 'production';
$productionRoute = $http->request('GET', '/account/register');
accountHttpAssert($productionRoute['status'] === 404, 'Account registration route was exposed in production.');
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
putenv('BOARDPREP_ACCOUNTS_ENABLED=false');
$_ENV['BOARDPREP_ACCOUNTS_ENABLED'] = 'false';
$disabledPreview = $http->request('GET', '/account/register');
accountHttpAssert($disabledPreview['status'] === 404, 'The nonproduction account route ignored its explicit default-off flag.');

echo "[PASS] Account registration/login/recovery/deletion HTTP routes enforce CSRF, hide duplicate-user status, explain deletion limits, preserve anonymous records, and keep production routes absent.\n";
