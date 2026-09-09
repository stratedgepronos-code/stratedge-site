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

const packballForm = document.querySelector('#lab-packball-upload');
if (packballForm) {
  const fileInput = packballForm.querySelector('#lab-packball-file');
  const dropzone = packballForm.querySelector('#lab-dropzone');
  const status = packballForm.querySelector('#lab-upload-status');
  const error = packballForm.querySelector('#lab-upload-error');
  let sending = false;
  const showError = (message) => { error.textContent = message; error.hidden = false; };
  const sendFile = () => {
    if (sending || !fileInput.files.length) return;
    error.hidden = true;
    const file = fileInput.files[0];
    if (fileInput.files.length !== 1 || !/\.csv$/i.test(file.name)) {
      showError('Choisis un seul fichier CSV PackBall.'); fileInput.value = ''; return;
    }
    if (!file.size || file.size > 2097152) {
      showError('Le CSV doit contenir des données et ne pas dépasser 2 Mo.'); fileInput.value = ''; return;
    }
    packballForm.requestSubmit();
  };
  fileInput.addEventListener('change', sendFile);
  packballForm.addEventListener('submit', (event) => {
    if (sending) { event.preventDefault(); return; }
    sending = true;
    packballForm.setAttribute('aria-busy', 'true');
    dropzone.classList.add('is-loading');
    status.textContent = 'Lecture de ' + fileInput.files[0].name + ' et analyse des matchs…';
    status.hidden = false;
    // Keep the file input enabled so its bytes are included in the multipart request.
  });
  ['dragenter', 'dragover'].forEach((type) => dropzone.addEventListener(type, (event) => {
    event.preventDefault(); if (!sending) dropzone.classList.add('is-dragging');
  }));
  ['dragleave', 'drop'].forEach((type) => dropzone.addEventListener(type, () => dropzone.classList.remove('is-dragging')));
  dropzone.addEventListener('drop', (event) => {
    event.preventDefault();
    if (sending) return;
    if (event.dataTransfer.files.length !== 1) { showError('Dépose un seul fichier CSV PackBall.'); return; }
    fileInput.files = event.dataTransfer.files;
    sendFile();
  });
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    sending = false;
    fileInput.value = '';
    packballForm.removeAttribute('aria-busy');
    dropzone.classList.remove('is-loading');
    status.hidden = true;
  });
}
