(() => {
    const exam = document.getElementById('quiz-exam');
    const subject = document.getElementById('quiz-subject');
    const civilServiceFields = document.getElementById('civil-service-fields');
    const civilServiceLevel = document.getElementById('civil-service-level');
    const letFields = document.getElementById('let-variant-fields');
    if (!exam || !subject || !civilServiceFields || !civilServiceLevel || !letFields) return;

    const isCivilService = () => ['cse', 'civil-service'].includes(String(exam.value).trim().toLowerCase());
    const update = () => {
        const cse = isCivilService();
        civilServiceFields.hidden = !cse;
        civilServiceLevel.required = cse;
        letFields.hidden = String(exam.value).trim().toLowerCase() !== 'let';

        for (const option of subject.options) {
            const examAllowed = cse ? option.dataset.exam === 'cse' : option.dataset.exam !== 'cse';
            const levelAllowed = !cse || !option.dataset.level || option.dataset.level === civilServiceLevel.value;
            option.hidden = !examAllowed || !levelAllowed;
            option.disabled = !examAllowed || !levelAllowed;
        }

        const selected = subject.selectedOptions[0];
        if (!selected || selected.disabled) {
            subject.value = cse ? 'general-information' : 'English';
        }
    };

    exam.addEventListener('change', update);
    civilServiceLevel.addEventListener('change', update);
    update();
})();
