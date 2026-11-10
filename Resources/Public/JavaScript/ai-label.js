/*
 * EXT:ai_labeling – second layer of the AI label.
 * Opening/closing is handled natively by the Popover API (popovertarget, Esc, light dismiss);
 * this script only keeps aria-expanded of the disclosure button in sync.
 */
(() => {
  document.addEventListener('toggle', (event) => {
    const popover = event.target;
    if (!(popover instanceof HTMLElement) || !popover.classList.contains('ai-label__details') || !popover.id) {
      return;
    }
    const expanded = event.newState === 'open' ? 'true' : 'false';
    document.querySelectorAll('.ai-label__more[aria-controls="' + CSS.escape(popover.id) + '"]').forEach((button) => {
      button.setAttribute('aria-expanded', expanded);
    });
  }, true);
})();
