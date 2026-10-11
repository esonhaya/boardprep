<section class="page-header">
    <div>
        <p class="eyebrow">Learner information</p>
        <h1>Data and privacy</h1>
        <p>What BoardPrep stores in this release and what the deployment operator controls.</p>
    </div>
</section>

<section class="card" aria-labelledby="accounts-heading">
    <h2 id="accounts-heading">No learner accounts</h2>
    <p>BoardPrep v1.0.1 does not ask you to register or sign in. It creates a random learner identifier in your PHP browser session and uses it to associate your quiz history, progress, weakness summaries, and flashcard reviews.</p>
    <p>There is no account recovery or cross-device synchronization. Clearing this site's cookies or changing browsers/devices can make earlier progress unavailable to you, even though the deployment may still retain its stored records.</p>
</section>

<section class="card" aria-labelledby="stored-data-heading">
    <h2 id="stored-data-heading">Stored learning data</h2>
    <p>Completed quiz attempts can include your selected answers, scores, question review, and study context. BoardPrep also stores derived weakness/progress information and flashcard review state. This release uses private JSON storage by default; an operator may configure SQLite for supported mutable learner state.</p>
    <p>The site operator and hosting provider control server-side access, backups, logs, and retention. There is no learner-facing export or deletion control in this release. Clearing a cookie does not delete server-side records. Ask the site's operator about its retention and deletion process before entering information you consider sensitive.</p>
</section>

<section class="card" aria-labelledby="session-heading">
    <h2 id="session-heading">Session cookie and HTTPS</h2>
    <p>The application session cookie is HttpOnly and SameSite=Lax. The Secure flag depends on the production server passing HTTPS state through to PHP. Use BoardPrep only over HTTPS and contact the site operator if the browser reports an insecure connection.</p>
</section>

<p class="muted-note">This release notice describes application behavior; it is not a complete deployment-specific privacy policy. The site operator must publish its identity and contact method, applicable data-handling terms, retention schedule, and hosting/log/backup practices before public launch.</p>
