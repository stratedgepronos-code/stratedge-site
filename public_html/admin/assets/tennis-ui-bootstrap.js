(() => {
  'use strict';

  const FLAG_SCRIPT_SRC = '/panel-x9k3m/assets/tennis-country-flags.js?v=20260725-iframe5';
  const STYLE_ID = 'stratedge-tennis-mobile-filter-static';
  const initializedFrames = new WeakSet();

  const mobileCss = `
    @media (max-width: 760px) {
      .toolbar,
      .filter-panel,
      .filters-panel,
      [class*="filter-panel"] {
        position: static !important;
        inset: auto !important;
        top: auto !important;
        right: auto !important;
        bottom: auto !important;
        left: auto !important;
        z-index: auto !important;
        transform: none !important;
        max-height: none !important;
        overflow: visible !important;
        margin-bottom: 18px !important;
      }
    }
  `;

  function injectMobileStyle(doc) {
    if (!doc?.head || doc.getElementById(STYLE_ID)) return;
    const style = doc.createElement('style');
    style.id = STYLE_ID;
    style.textContent = mobileCss;
    doc.head.appendChild(style);
  }

  function patchFrame(frame) {
    if (!(frame instanceof HTMLIFrameElement) || initializedFrames.has(frame)) return;
    initializedFrames.add(frame);

    const apply = () => {
      try {
        const doc = frame.contentDocument || frame.contentWindow?.document;
        if (doc) injectMobileStyle(doc);
      } catch (_) {
        // Le cockpit est same-origin en production. Ignorer proprement sinon.
      }
    };

    frame.addEventListener('load', apply);
    apply();
  }

  function scanFrames(root = document) {
    if (root instanceof HTMLIFrameElement) patchFrame(root);
    root.querySelectorAll?.('iframe').forEach(patchFrame);
  }

  function loadFlagDecorator() {
    if (document.querySelector('script[data-tennis-country-flags]')) return;
    const script = document.createElement('script');
    script.src = FLAG_SCRIPT_SRC;
    script.defer = true;
    script.dataset.tennisCountryFlags = '1';
    document.head.appendChild(script);
  }

  function start() {
    loadFlagDecorator();
    scanFrames();

    if (!document.body) return;
    new MutationObserver(records => {
      for (const record of records) {
        for (const node of record.addedNodes) {
          if (node instanceof HTMLElement) scanFrames(node);
        }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', start, { once: true })
    : start();
})();
