(() => {
    const exam = document.getElementById('quiz-exam');
    const subject = document.getElementById('quiz-subject');
    if (!exam || !subject) return;
    const mode = document.getElementById('quiz-mode');
    const timerFields = document.getElementById('exam-timer-fields');
    const blueprintPreviews = Array.from(document.querySelectorAll('[data-exam-blueprint-preview]'));
    const focusedBlueprintNote = document.querySelector('[data-exam-blueprint-focused-note]');
    const questionCount = document.getElementById('quiz-count');
    const timerMode = document.getElementById('quiz-timer-mode');
    const timeLimit = document.getElementById('quiz-time-limit');
    const timeLimitField = document.getElementById('quiz-time-limit-field');
    const startButton = document.getElementById('start-session-button');
    const adaptiveOption = document.getElementById('quiz-adaptive');
    const modeDescription = document.getElementById('quiz-mode-description');
    const variantGroups = Array.from(document.querySelectorAll('[data-candidate-variant]'));
    const letLevel = document.getElementById('quiz-let-level');
    const letSpecialization = document.getElementById('quiz-let-specialization');
    const civilServiceLevel = document.getElementById('quiz-cse-level');

    const isCivilService = () => ['cse', 'civil-service'].includes(String(exam.value).trim().toLowerCase());
    const allocate = (total, weightedEntries) => {
        const rows = weightedEntries.map((entry, index) => {
            const weight = Number(entry.weight);
            const exact = Number.isFinite(weight) && weight > 0 ? total * weight : 0;
            const whole = Math.floor(exact);
            return { entry, index, count: whole, remainder: exact - whole };
        });
        const weightTotal = rows.reduce((sum, row) => sum + (Number(row.entry.weight) > 0 ? Number(row.entry.weight) : 0), 0);
        if (total <= 0 || weightTotal <= 0) return rows.map(() => 0);
        let allocated = 0;
        for (const row of rows) {
            const weight = Number(row.entry.weight);
            const exact = Number.isFinite(weight) && weight > 0 ? total * weight / weightTotal : 0;
            row.count = Math.floor(exact);
            row.remainder = exact - row.count;
            allocated += row.count;
        }
        rows.slice().sort((left, right) => right.remainder - left.remainder || left.index - right.index)
            .slice(0, total - allocated)
            .forEach((row) => { row.count++; });
        return rows.map((row) => row.count);
    };
    const updateBlueprintCounts = () => {
        if (blueprintPreviews.length === 0) return;
        const total = Math.max(0, Number.parseInt(questionCount?.value || '0', 10) || 0);
        blueprintPreviews.forEach((blueprintPreview) => {
            const lines = Array.from(blueprintPreview.querySelectorAll('[data-blueprint-weight]'));
            const entries = lines.map((line) => ({
                weight: line.dataset.blueprintWeight,
                count: line.querySelector('[data-blueprint-count]'),
            }));
            const counts = allocate(total, entries);
            lines.forEach((line, index) => {
                const count = counts[index] || 0;
                const countNode = line.querySelector('[data-blueprint-count]');
                const totalNode = line.querySelector('[data-blueprint-total]');
                if (countNode) countNode.textContent = String(count);
                if (totalNode) totalNode.textContent = String(total);
                const subareas = Array.from(line.querySelectorAll('[data-blueprint-subarea-weight]'));
                if (subareas.length === 0) return;
                const subareaEntries = subareas.map((subarea) => ({
                    weight: subarea.dataset.blueprintSubareaWeight,
                    count: subarea.querySelector('[data-blueprint-subarea-count]'),
                }));
                const subareaCounts = allocate(count, subareaEntries);
                subareas.forEach((subarea, subareaIndex) => {
                    const node = subarea.querySelector('[data-blueprint-subarea-count]');
                    if (node) node.textContent = String(subareaCounts[subareaIndex] || 0);
                });
            });
        });
    };
    const update = () => {
        const cse = isCivilService();
        const selectedExam = String(exam.value).trim().toLowerCase();
        const examSimulation = mode?.value === 'exam';
        const practiceMode = !examSimulation;
        if (timerFields) timerFields.hidden = !examSimulation;
        if (timerMode) timerMode.disabled = !examSimulation;
        if (timeLimit) {
            const timed = examSimulation && timerMode?.value === 'timed';
            timeLimit.disabled = !timed;
            timeLimit.required = timed;
            if (timeLimitField) timeLimitField.hidden = !timed;
        }
        if (startButton) startButton.textContent = examSimulation ? 'Start exam simulation' : 'Start practice';
        if (adaptiveOption) adaptiveOption.disabled = !practiceMode;
        if (modeDescription) {
            modeDescription.textContent = examSimulation
                ? 'Exam simulation withholds answer feedback until submission and lets you review answers first. It is a BoardPrep study tool, separate from the official board examination.'
                : 'Practice shows answer feedback after each response. Focused practice uses the selected subject; adaptive selection is available for practice.';
        }

        for (const group of variantGroups) {
            const supportedBoards = String(group.dataset.candidateVariant || '').toLowerCase().split(/\s+/).filter(Boolean);
            const active = supportedBoards.includes(selectedExam);
            group.hidden = !active;
            for (const control of group.querySelectorAll('select, input')) {
                control.disabled = !active;
            }
        }

        const letVariantActive = selectedExam === 'let';
        if (letSpecialization) {
            const level = String(letLevel?.value || '').toUpperCase();
            for (const option of letSpecialization.options) {
                const optionLevel = String(option.dataset.level || '').toUpperCase();
                const incompatible = option.value !== '' && level !== '' && optionLevel !== level;
                option.hidden = incompatible;
                option.disabled = incompatible;
            }
            if (letSpecialization.selectedOptions?.[0]?.disabled) {
                letSpecialization.value = '';
            }
            letSpecialization.required = letVariantActive && level === 'SECONDARY';
            if (letLevel) letLevel.required = letVariantActive && letSpecialization.value !== '';
        }
        if (civilServiceLevel) civilServiceLevel.required = cse;

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
        const focusedSubject = String(subject.value || '*').trim() !== '*';
        for (const preview of blueprintPreviews) {
            preview.hidden = !examSimulation
                || focusedSubject
                || String(preview.dataset.board || '').trim().toLowerCase() !== selectedExam;
        }
        if (focusedBlueprintNote) focusedBlueprintNote.hidden = !examSimulation || !focusedSubject;
        updateBlueprintCounts();
    };

    exam.addEventListener('change', update);
    subject.addEventListener('change', update);
    mode?.addEventListener('change', update);
    letLevel?.addEventListener('change', update);
    letSpecialization?.addEventListener('change', update);
    timerMode?.addEventListener('change', update);
    questionCount?.addEventListener('change', updateBlueprintCounts);
    update();
})();
