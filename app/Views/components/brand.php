<?php
$brandHref = $brandHref ?? "/";
$brandClass = $brandClass ?? "brand";
$showWordmark = $showWordmark ?? true;
?>
<a class="<?= htmlspecialchars($brandClass, ENT_QUOTES, "UTF-8") ?>" href="<?= htmlspecialchars($brandHref, ENT_QUOTES, "UTF-8") ?>" aria-label="BoardPrep home">
    <svg class="brand-mark" viewBox="0 0 48 40" role="img" aria-labelledby="bp-logo-title">
        <title id="bp-logo-title">BoardPrep open book</title>
        <path d="M4 7c7-3 13-2 20 3v24c-7-5-13-6-20-3V7Z" fill="currentColor" opacity=".92"/>
        <path d="M44 7c-7-3-13-2-20 3v24c7-5 13-6 20-3V7Z" fill="currentColor"/>
        <path d="M8 12c4-1 8 0 12 3v3c-4-3-8-4-12-3v-3Zm0 8c4-1 8 0 12 3v3c-4-3-8-4-12-3v-3Z" fill="white" opacity=".88"/>
        <path d="M28 15c3-3 7-4 12-3v3c-4-1-7 0-10 3h-2v-3Zm0 8c3-3 7-4 12-3v3c-4-1-7 0-10 3h-2v-3Z" fill="white" opacity=".88"/>
        <path d="M24 10v25" stroke="white" stroke-width="2" opacity=".7"/>
    </svg>
    <?php if ($showWordmark): ?><span class="brand-wordmark">BoardPrep</span><?php endif; ?>
</a>
