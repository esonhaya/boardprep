<section class="account-page">
    <p class="eyebrow">Optional learner account</p>
    <h1>Create an account</h1>
    <p>Accounts are available only in the enabled nonproduction preview. You can continue practicing anonymously without registering.</p>
    <p>Choose a username, not an email address. BoardPrep does not send email or verify an email address in this foundation.</p>
    <?php if (is_string($message ?? null) && $message !== ''): ?>
        <p class="alert <?= ($error ?? false) ? 'alert-danger' : 'alert-success' ?>" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/account/register" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-field">
            <label for="account-username">Username</label>
            <input id="account-username" name="username" type="text" minlength="3" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,31}" autocomplete="username" required value="<?= htmlspecialchars((string) ($username ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <small>3–32 letters, numbers, periods, underscores, or hyphens.</small>
        </div>
        <div class="form-field">
            <label for="account-password">Password</label>
            <input id="account-password" name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required>
            <small>Use at least 12 characters. A long passphrase is suitable.</small>
        </div>
        <div class="form-field">
            <label for="account-password-confirmation">Confirm password</label>
            <input id="account-password-confirmation" name="password_confirmation" type="password" minlength="12" maxlength="128" autocomplete="new-password" required>
        </div>
        <button type="submit">Create account</button>
    </form>
    <p><a href="/account/login">Already have an account? Sign in</a></p>
    <p><a href="/">Continue anonymously</a></p>
</section>
