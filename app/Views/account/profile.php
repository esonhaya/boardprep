<section class="account-page">
    <p class="eyebrow">Signed-in learner account</p>
    <h1>Account profile</h1>
    <?php if (is_array($flash ?? null)): ?>
        <p class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert-danger' : 'alert-success' ?>" role="status"><?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <dl>
        <dt>Username</dt><dd><?= htmlspecialchars((string) ($account['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Account created</dt><dd><?= htmlspecialchars((string) ($account['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Current session</dt><dd><?= htmlspecialchars((string) ($sessionDetails['started_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · idle expiry after <?= (int) ($sessionDetails['idle_expiry_minutes'] ?? 30) ?> minutes · maximum <?= (int) ($sessionDetails['absolute_expiry_hours'] ?? 12) ?> hours</dd>
    </dl>
    <p>Quiz history, results, progress, flashcard reviews, and preferences use this account's private learner identity. Anonymous records from before sign-in were not linked or merged.</p>
    <nav class="account-actions" aria-label="Account actions">
        <a class="button" href="/profile">Edit learner preferences</a>
        <a class="button button-outline" href="/account/security">Password and recovery</a>
    </nav>
    <form method="post" action="/account/export">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-field"><label for="export-current-password">Confirm password to export your account data</label><input id="export-current-password" name="current_password" type="password" autocomplete="current-password" required></div>
        <button type="submit" class="button button-outline">Export my account data</button>
    </form>
    <form method="post" action="/account/logout">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="button button-outline">Sign out</button>
    </form>
    <details class="account-delete-panel">
        <summary>Request account deletion</summary>
        <p>Confirming permanently removes this account's application records: quiz history may be lost, preferences and flashcard progress are erased, and recovery codes become unusable. Provider logs, historical backups, and external copies are outside this application operation and may persist independently until their verified retention expiry. Their retention details and the owner-approved privacy terms are still pending. This account preview and deletion route are unavailable in production.</p>
        <form method="post" action="/account/deletion-request">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-field"><label for="deletion-current-password">Confirm password</label><input id="deletion-current-password" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="form-field"><label for="deletion-confirmation">Type DELETE to permanently delete application records</label><input id="deletion-confirmation" name="confirm_deletion" type="text" autocomplete="off" required></div>
            <button type="submit" class="button button-danger">Delete account and application data</button>
        </form>
    </details>
</section>
