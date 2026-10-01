(() => {
  'use strict';
  const ready = () => {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const hamburger = document.getElementById('hamburger');
    const overlay = document.getElementById('sidebarOverlay');
    const main = document.querySelector('.main');
    if (main && !main.id) main.id = 'se-main-content';
    const skip = document.querySelector('.se-skip');
    if (main && skip) { skip.href = '#' + main.id; main.tabIndex = -1; }
    let lastMenuFocus;
    const closeMenu = (restore = false) => {
      sidebar.classList.remove('open'); overlay?.classList.remove('open');
      sidebar.inert = window.innerWidth <= 768;
      hamburger?.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
      if (restore && lastMenuFocus?.isConnected) lastMenuFocus.focus();
    };
    window.toggleSidebar = () => {
      if (sidebar.classList.contains('open')) { closeMenu(true); return; }
      lastMenuFocus = document.activeElement;
      sidebar.inert = false;
      sidebar.classList.add('open'); overlay?.classList.add('open');
      hamburger?.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
      sidebar.querySelector('a,button')?.focus();
    };
    window.toggleNavGroup = btn => {
      const group = btn.closest('.nav-group');
      if (!group) return;
      group.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(group.classList.contains('open')));
      try { sessionStorage.setItem('se-nav-' + group.dataset.group, String(group.classList.contains('open'))); } catch (_) {}
    };
    sidebar.querySelectorAll('.nav-group').forEach(group => {
      try { if (!group.querySelector('a.active') && sessionStorage.getItem('se-nav-' + group.dataset.group) === 'true') group.classList.add('open'); } catch (_) {}
      const btn = group.querySelector('.nav-group-toggle'), inner = group.querySelector('.nav-group-inner');
      if (btn && inner) { inner.id = 'se-nav-' + group.dataset.group; btn.setAttribute('aria-controls', inner.id); btn.setAttribute('aria-expanded', String(group.classList.contains('open'))); }
    });
    sidebar.querySelectorAll('a.active').forEach(a => a.setAttribute('aria-current','page'));
    sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', () => closeMenu()));
    const media = window.matchMedia('(max-width:768px)');
    media.addEventListener('change', () => closeMenu());
    sidebar.inert = media.matches;
    const modal = document.getElementById('se-command');
    const input = document.getElementById('se-command-input');
    const results = document.getElementById('se-command-results');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
    const entries = Array.from(sidebar.querySelectorAll('nav a[href]')).map(a => {
      const copy = a.cloneNode(true); copy.querySelectorAll('.badge-count,svg').forEach(el => el.remove());
      return {label:copy.textContent.trim().replace(/\s+/g,' '),href:a.getAttribute('href'),active:a.classList.contains('active'),icon:a.querySelector('svg')?.cloneNode(true)};
    });
    let selected = 0, searchOpener;
    const renderResults = () => {
      const query = normalize(input.value.trim());
      results.replaceChildren(); selected = 0;
      entries.filter(item => normalize(item.label).includes(query)).forEach(item => {
        const a = document.createElement('a'); a.href = item.href;
        if (item.active) a.setAttribute('aria-current','page');
        if (item.icon) a.append(item.icon.cloneNode(true));
        const label = document.createElement('span'); label.textContent = item.label; a.append(label); results.append(a);
      });
      modal.querySelector('.se-command-empty').hidden = results.children.length > 0;
      results.firstElementChild?.classList.add('se-chosen');
    };
    document.querySelectorAll('[data-se-search]').forEach(btn => btn.addEventListener('click', () => {
      closeMenu(); searchOpener = btn; input.value = ''; renderResults(); modal.showModal(); input.focus();
    }));
    input.addEventListener('input', renderResults);
    modal.addEventListener('close', () => { if (searchOpener?.isConnected) searchOpener.focus(); });
    modal.addEventListener('click', e => { if (e.target === modal) { const b = modal.getBoundingClientRect(); if (e.clientX < b.left || e.clientX > b.right || e.clientY < b.top || e.clientY > b.bottom) modal.close(); } });
    modal.addEventListener('keydown', e => {
      if (e.key === 'Escape') { e.preventDefault(); modal.close(); return; }
      const links = Array.from(results.children);
      if (['ArrowDown','ArrowUp'].includes(e.key) && links.length) {
        e.preventDefault(); selected = (selected + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
        links.forEach((a,i) => a.classList.toggle('se-chosen',i === selected)); links[selected].scrollIntoView({block:'nearest'});
      }
      if (e.key === 'Enter' && e.target === input) { e.preventDefault(); links[selected]?.click(); }
    });
    document.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); if (modal.open) modal.close(); else Array.from(document.querySelectorAll('[data-se-search]')).find(btn => btn.getClientRects().length)?.click(); }
      if (e.key === 'Escape' && sidebar.classList.contains('open')) closeMenu(true);
      if (e.key === 'Tab' && sidebar.classList.contains('open') && media.matches) {
        const focusable = Array.from(sidebar.querySelectorAll('a,button')).filter(el => el.getClientRects().length);
        const first = focusable[0], last = focusable.at(-1);
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    if (window.matchMedia('(pointer:fine) and (prefers-reduced-motion:no-preference)').matches) {
      document.querySelectorAll('.se-revenue').forEach(card => card.addEventListener('pointermove', e => {
        const box = card.getBoundingClientRect(); card.style.setProperty('--se-x', (e.clientX-box.left)+'px'); card.style.setProperty('--se-y', (e.clientY-box.top)+'px');
      }, {passive:true}));
    }
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, {once:true}); else ready();
})();
