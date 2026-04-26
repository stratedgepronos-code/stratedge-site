/**
 * StratEdge Edge Finder — Dashboard interactivity
 *
 * Délègue les clicks sur boutons de décision à un endpoint AJAX
 * api/decision.php qui update user_decision en DB. Au succès, on reload
 * la ligne du candidat (ou la page entière si trop complexe).
 */
(function () {
  'use strict';

  const API_DECISION = 'api/decision.php';

  // ──────────────────────────────────────────────────────────────────────
  // Helpers
  // ──────────────────────────────────────────────────────────────────────

  function $$(sel, ctx = document) { return Array.from((ctx || document).querySelectorAll(sel)); }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = data.error || `HTTP ${res.status}`;
      throw new Error(msg);
    }
    return data;
  }

  function flash(el, type) {
    const cls = type === 'success' ? 'ef-flash-success' : 'ef-flash-error';
    el.classList.add(cls);
    setTimeout(() => el.classList.remove(cls), 1100);
  }

  function setLoading(el, on) {
    el.style.opacity = on ? '0.5' : '';
    el.style.pointerEvents = on ? 'none' : '';
  }

  // ──────────────────────────────────────────────────────────────────────
  // Click handler
  // ──────────────────────────────────────────────────────────────────────

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.ef-btn');
    if (!btn) return;

    const action = btn.dataset.action;
    if (!action) return;

    const candEl = btn.closest('.ef-cand');
    if (!candEl) return;

    const candidateId = parseInt(candEl.dataset.candidateId, 10);
    if (!candidateId) {
      console.error('No candidate_id on .ef-cand element');
      return;
    }

    // Confirmation pour les actions destructives
    if (action === 'won' || action === 'lost') {
      const lbl = action === 'won' ? 'GAGNÉ' : 'PERDU';
      if (!confirm(`Marquer ce pick comme ${lbl} ?`)) return;
    }

    setLoading(candEl, true);
    try {
      await postJson(API_DECISION, {
        candidate_id: candidateId,
        decision: action,
      });
      flash(candEl, 'success');
      // Recharger la page après animation pour refléter l'état serveur
      // (plus simple que de patcher le DOM côté client pour tous les états)
      setTimeout(() => location.reload(), 600);
    } catch (err) {
      console.error('Decision update failed:', err);
      flash(candEl, 'error');
      alert('Erreur : ' + err.message);
      setLoading(candEl, false);
    }
  });

  // ──────────────────────────────────────────────────────────────────────
  // Auto-submit du filtre conviction (debounced)
  // ──────────────────────────────────────────────────────────────────────

  const convInput = document.querySelector('.ef-filter-conv input');
  if (convInput) {
    let to;
    convInput.addEventListener('input', () => {
      clearTimeout(to);
      to = setTimeout(() => convInput.form.submit(), 700);
    });
  }

  // Submit form on select change
  $$('.ef-filters select').forEach((sel) => {
    sel.addEventListener('change', () => sel.form.submit());
  });

  // ──────────────────────────────────────────────────────────────────────
  // Stats : compteur en temps réel des picks pending visibles
  // ──────────────────────────────────────────────────────────────────────

  function updateLiveCounters() {
    const visible = $$('.ef-cand').filter((el) => {
      return el.classList.contains('ef-cand-decision-pending');
    });
    const auto   = visible.filter((el) => el.classList.contains('ef-cand-auto')).length;
    const manual = visible.filter((el) => el.classList.contains('ef-cand-manual')).length;
    // Pas critique, juste pour cohérence visuelle si besoin plus tard
    document.documentElement.style.setProperty('--ef-pending-auto', auto);
    document.documentElement.style.setProperty('--ef-pending-manual', manual);
  }

  updateLiveCounters();

})();
