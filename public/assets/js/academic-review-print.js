'use strict';

(() => {
  const packet = document.querySelector('[data-review-packet]');
  const button = packet?.querySelector('[data-academic-review-print]');
  if (!packet || !button) return;

  document.body.classList.add('academic-review-print-mode');
  button.hidden = false;

  let closedDetails = [];
  window.addEventListener('beforeprint', () => {
    closedDetails = Array.from(packet.querySelectorAll('details:not([open])'));
    closedDetails.forEach((element) => { element.open = true; });
  });
  window.addEventListener('afterprint', () => {
    closedDetails.forEach((element) => { element.open = false; });
    closedDetails = [];
  });
  button.addEventListener('click', () => window.print());
})();
