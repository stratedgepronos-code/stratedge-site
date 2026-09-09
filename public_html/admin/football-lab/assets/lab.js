'use strict';
document.querySelector('#lab-search')?.addEventListener('input', (event) => {
  const query = event.target.value.trim().toLocaleLowerCase('fr');
  let visible = 0;
  document.querySelectorAll('[data-lab-search]').forEach((card) => {
    card.hidden = !card.dataset.labSearch.toLocaleLowerCase('fr').includes(query);
    if (!card.hidden) visible++;
  });
  const empty = document.querySelector('#lab-no-results');
  if (empty) empty.hidden = visible !== 0;
});
document.querySelectorAll('form').forEach((form) => {
  form.addEventListener('submit', () => {
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
      button.disabled = true;
      if (form.hasAttribute('data-lab-research')) button.textContent = 'Recherche en cours…';
    });
  });
});
