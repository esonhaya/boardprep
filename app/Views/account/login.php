<section class="account-page">
    <p class="eyebrow">Optional learner account</p>
    <h1>Sign in</h1>
    <p>Anonymous practice remains available without an account.</p>
    <?php if (is_string($message ?? null) && $message !== ''): ?>
        <p class="alert <?= ($error ?? false) ? 'alert-danger' : 'alert-success' ?>" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/account/login" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-field">
            <label for="account-username">Username</label>
            <input id="account-username" name="username" type="text" maxlength="32" autocomplete="username" required value="<?= htmlspecialchars((string) ($username ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-field">
            <label for="account-password">Password</label>
            <input id="account-password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button type="submit">Sign in</button>
    </form>
    <p><a href="/account/recover">Recover an account with a saved recovery code</a></p>
    <p><a href="/account/register">Create an account</a> · <a href="/">Continue anonymously</a></p>
</section>
