'use strict';
document.querySelector('#lab-search')?.addEventListener('input', (event) => {
  const query = event.target.value.trim().toLocaleLowerCase('fr');
  document.querySelectorAll('[data-lab-search]').forEach((card) => {
    card.hidden = !card.dataset.labSearch.toLocaleLowerCase('fr').includes(query);
  });
});
document.querySelectorAll('form').forEach((form) => {
  form.addEventListener('submit', () => {
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
      button.disabled = true;
      if (form.hasAttribute('data-lab-research')) button.textContent = 'Recherche en cours…';
    });
  });
});
