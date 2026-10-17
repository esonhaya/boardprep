(() => {
  const board = document.getElementById('preferred-board');
  const subject = document.getElementById('preferred-subject');
  if (!board || !subject) return;
  const updateSubjects = (clear = false) => {
    const selectedBoard = String(board.value || '').toLowerCase();
    for (const option of subject.options) {
      if (!option.value) continue;
      const matches = !selectedBoard || String(option.dataset.board || '').toLowerCase() === selectedBoard;
      option.hidden = !matches;
      option.disabled = !matches;
    }
    if (clear) subject.value = '';
    if (subject.selectedOptions[0]?.disabled) subject.value = '';
  };
  board.addEventListener('change', () => updateSubjects(true));
  updateSubjects(false);
})();
