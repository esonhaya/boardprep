(() => {
    const timer = document.querySelector('.exam-countdown[data-remaining-seconds]');
    const expiryForm = document.getElementById('exam-expiry-check');
    const value = document.getElementById('exam-countdown-value');
    const announcement = document.getElementById('exam-countdown-announcement');
    if (!timer || !expiryForm || !value) return;

    const initialSeconds = Number.parseInt(timer.dataset.remainingSeconds || '', 10);
    if (!Number.isFinite(initialSeconds) || initialSeconds < 0) return;

    const startedAt = performance.now();
    const startedAtWall = Date.now();
    const navigation = typeof performance.getEntriesByType === 'function'
        ? performance.getEntriesByType('navigation')[0]
        : null;
    const responseStart = Number(navigation?.responseStart);
    const elapsedBeforeScript = Number.isFinite(responseStart) && responseStart > 0
        ? Math.max(0, startedAt - responseStart)
        : 0;
    let submitted = false;
    let lastAnnouncement = null;
    const format = (seconds) => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainder = seconds % 60;
        return hours > 0
            ? `${hours}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`
            : `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
    };

    const update = () => {
        const elapsedSinceScript = Math.max(
            performance.now() - startedAt,
            Date.now() - startedAtWall
        );
        const elapsedSeconds = Math.floor((elapsedBeforeScript + elapsedSinceScript) / 1000);
        const remaining = Math.max(0, initialSeconds - elapsedSeconds);
        value.textContent = format(remaining);
        timer.dataset.remainingSeconds = String(remaining);

        const announce = remaining === 0 || remaining === 30 || remaining === 60
            || (remaining > 0 && remaining <= 300 && remaining % 60 === 0);
        if (announce && remaining !== lastAnnouncement && announcement) {
            announcement.textContent = remaining === 0
                ? 'Time is up on this device. Checking the server deadline.'
                : `${format(remaining)} remaining.`;
            lastAnnouncement = remaining;
        }

        if (remaining === 0 && !submitted) {
            submitted = true;
            if (typeof expiryForm.requestSubmit === 'function') expiryForm.requestSubmit();
            else expiryForm.submit();
        }
    };

    const refreshWhenVisible = () => {
        if (!document.hidden) update();
    };

    update();
    window.setInterval(update, 1000);
    document.addEventListener('visibilitychange', refreshWhenVisible);
    window.addEventListener('pageshow', update);
    window.addEventListener('focus', update);
})();
