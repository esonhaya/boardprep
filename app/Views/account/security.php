<section class="account-page">
    <p class="eyebrow">Account security</p>
    <h1>Password and recovery</h1>
    <?php if (is_string($message ?? null) && $message !== ''): ?>
        <p class="alert <?= ($error ?? false) ? 'alert-danger' : 'alert-success' ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <section class="card">
        <h2>Change password</h2>
        <p>Changing your password signs out other active sessions.</p>
        <form method="post" action="/account/password">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-field"><label for="current-password">Current password</label><input id="current-password" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="form-field"><label for="new-password">New password</label><input id="new-password" name="new_password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></div>
            <div class="form-field"><label for="new-password-confirmation">Confirm new password</label><input id="new-password-confirmation" name="new_password_confirmation" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></div>
            <button type="submit">Change password</button>
        </form>
    </section>
    <section class="card">
        <h2>Replace recovery codes</h2>
        <p>All existing codes stop working when a replacement list is created.</p>
        <form method="post" action="/account/recovery-codes">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-field"><label for="recovery-current-password">Current password</label><input id="recovery-current-password" name="current_password" type="password" autocomplete="current-password" required></div>
            <button type="submit" class="button button-outline">Create new recovery codes</button>
        </form>
    </section>
    <p><a href="/account/profile">Return to account profile</a></p>
</section>
