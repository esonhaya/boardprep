(() => {
    const exam = document.getElementById('quiz-exam');
    const subject = document.getElementById('quiz-subject');
    const civilServiceFields = document.getElementById('civil-service-fields');
    const civilServiceLevel = document.getElementById('civil-service-level');
    const letFields = document.getElementById('let-variant-fields');
    if (!exam || !subject || !civilServiceFields || !civilServiceLevel || !letFields) return;
    const mode = document.getElementById('quiz-mode');
    const timerFields = document.getElementById('exam-timer-fields');
    const timerMode = document.getElementById('quiz-timer-mode');
    const timeLimit = document.getElementById('quiz-time-limit');
    const startButton = document.getElementById('start-session-button');

    const isCivilService = () => ['cse', 'civil-service'].includes(String(exam.value).trim().toLowerCase());
    const update = () => {
        const cse = isCivilService();
        const selectedExam = String(exam.value).trim().toLowerCase();
        const examSimulation = mode?.value === 'exam';
        civilServiceFields.hidden = !cse;
        civilServiceLevel.required = cse;
        letFields.hidden = String(exam.value).trim().toLowerCase() !== 'let';
        if (timerFields) timerFields.hidden = !examSimulation;
        if (timerMode) timerMode.disabled = !examSimulation;
        if (timeLimit) {
            const timed = examSimulation && timerMode?.value === 'timed';
            timeLimit.disabled = !timed;
            timeLimit.required = timed;
        }
        if (startButton) startButton.textContent = examSimulation ? 'Start exam simulation' : 'Start practice';

        for (const option of subject.options) {
            const examAllowed = option.dataset.exam === 'all'
                || String(option.dataset.exam || '').trim().toLowerCase() === selectedExam;
            const levelAllowed = !cse || !option.dataset.level || option.dataset.level === civilServiceLevel.value;
            option.hidden = !examAllowed || !levelAllowed;
            option.disabled = !examAllowed || !levelAllowed;
        }

        const selected = subject.selectedOptions[0];
        if (!selected || selected.disabled) {
            subject.value = cse ? 'general-information' : (selectedExam === 'let' ? 'english' : '*');
        }
    };

    exam.addEventListener('change', update);
    civilServiceLevel.addEventListener('change', update);
    mode?.addEventListener('change', update);
    timerMode?.addEventListener('change', update);
    update();
})();
