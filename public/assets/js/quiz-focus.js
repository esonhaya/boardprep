(function () {
    function focusLearnerUpdate() {
        var target = document.querySelector('[role="alert"]')
            || document.querySelector('.quiz-feedback[role="status"]')
            || document.querySelector('#exam-review-heading')
            || document.querySelector('.quiz-result-page h1')
            || document.querySelector('#practice-question-heading');

        if (!target) return;
        if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', focusLearnerUpdate, { once: true });
    } else {
        focusLearnerUpdate();
    }
})();
