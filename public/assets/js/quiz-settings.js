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
        const selectedExam = String(exam.value).trim().toLowerCase();
        civilServiceFields.hidden = !cse;
        civilServiceLevel.required = cse;
        letFields.hidden = String(exam.value).trim().toLowerCase() !== 'let';

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
    update();
})();
