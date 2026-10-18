<section class="account-page">
    <p class="eyebrow">Account request</p>
    <?php if (($completed ?? false) === true): ?>
        <h1>Application account data deleted</h1>
        <p role="status">The account credentials, recovery codes, preferences, quiz history, weakness summaries, and flashcard review records were removed from this application and verified absent. The account can no longer sign in.</p>
    <?php else: ?>
        <h1>Deletion needs a safe retry</h1>
        <p role="status">The account is blocked from signing in, but BoardPrep could not verify that every application record was removed. The deletion journal is available for an operator to retry safely. No completed-deletion claim has been recorded.</p>
    <?php endif; ?>
    <p>Quiz history may be permanently lost. Preferences and flashcard progress are erased after a successful application deletion, and recovery codes become unusable. Provider logs, historical backups, and external copies are outside this application operation and may persist independently until their verified retention expiry. Owner and provider retention details are still pending.</p>
    <a class="button" href="/">Continue anonymously</a>
</section>
