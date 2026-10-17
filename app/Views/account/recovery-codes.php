<section class="account-page" aria-labelledby="recovery-codes-title">
    <p class="eyebrow">Save these codes once</p>
    <h1 id="recovery-codes-title">Account recovery codes</h1>
    <p role="status"><?= htmlspecialchars((string) ($message ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <div class="recovery-code-list" aria-label="Recovery codes">
        <?php foreach (($codes ?? []) as $code): ?>
            <code><?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?></code>
        <?php endforeach; ?>
    </div>
    <p>Each code can be used once. BoardPrep stores only a one-way hash. Keep this list private and outside shared screenshots or messages.</p>
    <a class="button" href="<?= htmlspecialchars((string) ($nextUrl ?? '/account/login'), ENT_QUOTES, 'UTF-8') ?>">Continue</a>
</section>
