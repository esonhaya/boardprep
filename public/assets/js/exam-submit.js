(() => {
    const forms = document.querySelectorAll('[data-exam-submit-form]');
    forms.forEach((form) => {
        const button = form.querySelector('button[type="submit"]');
        const status = document.getElementById('exam-submit-status');

        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            if (button) button.disabled = true;
            if (status) {
                status.hidden = false;
                status.textContent = 'Submitting your exam answers. Wait for the server to confirm your result.';
            }
        });
    });

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;

        forms.forEach((form) => {
            if (form.dataset.submitting !== 'true') return;

            form.dataset.submitting = 'false';
            form.removeAttribute('aria-busy');
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = false;

            const status = document.getElementById('exam-submit-status');
            if (status) {
                status.hidden = false;
                status.textContent = 'This exam page was restored. If you did not reach your result, submit again; the server will not create a duplicate attempt.';
            }
        });
    });
})();
