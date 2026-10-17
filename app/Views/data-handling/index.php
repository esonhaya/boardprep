<div class="privacy-page">
<section class="page-header">
    <div>
        <p class="eyebrow">Learner information</p>
        <h1>Data and privacy</h1>
        <p>What BoardPrep stores in this release and what the deployment operator controls.</p>
    </div>
</section>

<section class="card" aria-labelledby="accounts-heading">
    <?php if (\App\Services\Accounts\AccountFeatureGate::enabled()): ?>
        <h2 id="accounts-heading">Nonproduction account preview</h2>
        <p>Account creation and sign-in are enabled only in this nonproduction preview. A public account service is not approved or enabled. Anonymous learning remains available.</p>
        <p><a href="/account/register">Create a preview account</a> · <a href="/account/login">Sign in</a></p>
    <?php else: ?>
        <h2 id="accounts-heading">No learner accounts</h2>
        <p>Public BoardPrep currently uses a random learner identifier in your PHP browser session and does not offer public account registration or sign-in. Anonymous learning remains available.</p>
        <p>Clearing this site's cookies or changing browsers/devices can make earlier progress unavailable to you, even though a deployment may still retain its stored records.</p>
    <?php endif; ?>
</section>

<section class="card" aria-labelledby="stored-data-heading">
    <h2 id="stored-data-heading">Stored learning data</h2>
    <p>Completed quiz attempts can include your selected answers, scores, question review, and study context. BoardPrep also stores derived weakness/progress information and flashcard review state. This release uses private JSON storage by default; an operator may configure SQLite for supported mutable learner state.</p>
    <p>Current records associated with this browser session:</p>
    <ul>
        <?php foreach (($learnerDataSummary ?? []) as $recordType => $count): ?>
            <li><?= htmlspecialchars((string) $recordType, ENT_QUOTES, 'UTF-8') ?>: <?= (int) $count ?></li>
        <?php endforeach; ?>
    </ul>
    <p>These controls apply to the current anonymous session only. Signed-in account data is handled through the account profile. Clearing browser cookies or using another browser can make previous anonymous records inaccessible without deleting them.</p>
    <p><a class="button button-outline" href="/privacy/export">Download my saved learning data</a></p>
    <form class="privacy-delete-form" method="post" action="/privacy/data/clear">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-field">
            <label>
                <input type="checkbox" name="confirm_clear" value="yes" required>
                I understand this clears saved learning data for this browser session.
            </label>
        </div>
        <button type="submit" class="button button-danger">Clear my learning data</button>
    </form>
</section>

<section class="card" aria-labelledby="session-heading">
    <h2 id="session-heading">Session cookie and HTTPS</h2>
    <p>The application session cookie is HttpOnly and SameSite=Lax. The Secure flag depends on the production server passing HTTPS state through to PHP. Use BoardPrep only over HTTPS and contact the site operator if the browser reports an insecure connection.</p>
</section>

<?php $privacy = is_array($privacy ?? null) ? $privacy : []; ?>
<?php if (($privacy['site_policy_complete'] ?? false) === true): ?>
    <section class="card" aria-labelledby="operator-policy-heading">
        <h2 id="operator-policy-heading">Site operator's privacy terms</h2>
        <dl>
            <dt>Operator</dt><dd><?= htmlspecialchars((string) ($privacy['operator_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Contact</dt><dd><?= htmlspecialchars((string) ($privacy['operator_contact'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>Retention</dt><dd><?= nl2br(htmlspecialchars((string) ($privacy['retention_notice'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></dd>
            <dt>Hosting, logs, and backups</dt><dd><?= nl2br(htmlspecialchars((string) ($privacy['hosting_notice'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></dd>
        </dl>
    </section>
<?php else: ?>
    <p class="flash-message flash-error" role="status">The site operator has not supplied and approved deployment-specific privacy terms. Public launch must wait until the operator identity, contact, retention, and hosting/log/backup terms are completed and reviewed.</p>
<?php endif; ?>

<p class="muted-note">Deleting app records does not erase hosting access/error logs, external backups, or copies retained outside BoardPrep. The site operator and hosting provider control those systems and must state their own practices. Clearing browser cookies alone does not delete server-side records.</p>
</div>
