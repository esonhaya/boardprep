(() => {
  const form = document.querySelector('.flashcard-filter-card form');
  const board = document.getElementById('flashcard-board');
  const subject = document.getElementById('flashcard-subject');
  const topic = document.getElementById('flashcard-topic');
  if (!form || !board || !subject || !topic) return;
  board.addEventListener('change', () => {
    subject.value = '';
    topic.value = '';
    form.requestSubmit();
  });
  subject.addEventListener('change', () => {
    topic.value = '';
    form.requestSubmit();
  });
})();
