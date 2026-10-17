(() => {
    const exam = document.getElementById('quiz-exam');
    const subject = document.getElementById('quiz-subject');
    if (!exam || !subject) return;
    const mode = document.getElementById('quiz-mode');
    const timerFields = document.getElementById('exam-timer-fields');
    const timerMode = document.getElementById('quiz-timer-mode');
    const timeLimit = document.getElementById('quiz-time-limit');
    const timeLimitField = document.getElementById('quiz-time-limit-field');
    const startButton = document.getElementById('start-session-button');

    const isCivilService = () => ['cse', 'civil-service'].includes(String(exam.value).trim().toLowerCase());
    const update = () => {
        const cse = isCivilService();
        const selectedExam = String(exam.value).trim().toLowerCase();
        const examSimulation = mode?.value === 'exam';
        if (timerFields) timerFields.hidden = !examSimulation;
        if (timerMode) timerMode.disabled = !examSimulation;
        if (timeLimit) {
            const timed = examSimulation && timerMode?.value === 'timed';
            timeLimit.disabled = !timed;
            timeLimit.required = timed;
            if (timeLimitField) timeLimitField.hidden = !timed;
        }
        if (startButton) startButton.textContent = examSimulation ? 'Start exam simulation' : 'Start practice';

        for (const option of subject.options) {
            const examAllowed = option.dataset.exam === 'all'
                || String(option.dataset.exam || '').trim().toLowerCase() === selectedExam;
            const levelAllowed = !cse || !option.dataset.level;
            option.hidden = !examAllowed || !levelAllowed;
            option.disabled = !examAllowed || !levelAllowed;
        }

        const selected = subject.selectedOptions[0];
        if (!selected || selected.disabled) {
            subject.value = '*';
        }
    };

    exam.addEventListener('change', update);
    mode?.addEventListener('change', update);
    timerMode?.addEventListener('change', update);
    update();
})();
