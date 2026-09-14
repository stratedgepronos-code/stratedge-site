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
  const sendFiles = () => {
    if (sending) return;
    const files = Array.from(fileInput.files);
    error.hidden = true;
    if (files.length !== 2 || files.some(file => !/\.csv$/i.test(file.name) || !file.size || file.size > 2097152)) {
      error.textContent = 'Sélectionne ensemble les deux CSV GPT et GPT-2, de 2 Mo maximum chacun.';
      error.hidden = false; return;
    }
    packballForm.requestSubmit();
  };
  fileInput.addEventListener('change', sendFiles);
  packballForm.addEventListener('submit', event => {
    if (sending) { event.preventDefault(); return; }
    sending = true;
    packballForm.setAttribute('aria-busy', 'true');
    dropzone.classList.add('is-loading');
    status.textContent = 'Rapprochement des deux fichiers et analyse des matchs…';
    status.hidden = false;
  });
  ['dragenter','dragover'].forEach(type => dropzone.addEventListener(type, event => {
    event.preventDefault(); if (!sending) dropzone.classList.add('is-dragging');
  }));
  ['dragleave','drop'].forEach(type => dropzone.addEventListener(type, () => dropzone.classList.remove('is-dragging')));
  dropzone.addEventListener('drop', event => {
    event.preventDefault(); if (sending) return;
    fileInput.files = event.dataTransfer.files; sendFiles();
  });
  window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    sending = false; fileInput.value = '';
    packballForm.removeAttribute('aria-busy'); dropzone.classList.remove('is-loading'); status.hidden = true;
  });
}
const resultsForm = document.querySelector('#lab-results-upload');
if (resultsForm) {
  const input = resultsForm.querySelector('#lab-results-file');
  input.addEventListener('change', () => {
    if (input.files.length === 1 && /\.csv$/i.test(input.files[0].name) && input.files[0].size <= 2097152) resultsForm.requestSubmit();
  });
}

const copyChat = document.querySelector('#lab-copy-chat');
copyChat?.addEventListener('click', async () => {
  const field = document.querySelector('#lab-chat-export');
  const status = document.querySelector('#lab-copy-status');
  try {
    await navigator.clipboard.writeText(field.value);
    status.textContent = ' Copié ! Colle ce texte dans ta conversation ChatGPT.';
  } catch {
    field.closest('details').open = true;
    field.focus(); field.select();
    status.textContent = ' Texte sélectionné : utilise Copier ou Ctrl+C.';
  }
});
