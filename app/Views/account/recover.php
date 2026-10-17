<section class="account-page">
    <p class="eyebrow">Account recovery</p>
    <h1>Recover account</h1>
    <p>Enter one unused recovery code saved when the account was created or when recovery codes were replaced. No email reset service is configured.</p>
    <?php if (is_string($message ?? null) && $message !== ''): ?>
        <p class="alert <?= ($error ?? false) ? 'alert-danger' : 'alert-success' ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/account/recover" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-field">
            <label for="recovery-username">Username</label>
            <input id="recovery-username" name="username" type="text" maxlength="32" autocomplete="username" required value="<?= htmlspecialchars((string) ($username ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-field">
            <label for="recovery-code">Unused recovery code</label>
            <input id="recovery-code" name="recovery_code" type="text" maxlength="256" autocomplete="off" autocapitalize="characters" required>
        </div>
        <div class="form-field">
            <label for="new-account-password">New password</label>
            <input id="new-account-password" name="new_password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required>
        </div>
        <div class="form-field">
            <label for="new-account-password-confirmation">Confirm new password</label>
            <input id="new-account-password-confirmation" name="new_password_confirmation" type="password" minlength="12" maxlength="128" autocomplete="new-password" required>
        </div>
        <button type="submit">Use recovery code</button>
    </form>
    <p><a href="/account/login">Return to sign in</a></p>
</section>
