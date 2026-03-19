/**
 * StratEdge — Calendrier (servi depuis /assets/ pour éviter blocage /includes/)
 */
(function() {
  var MOIS = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  var JOURS = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

  function parseYmd(str) {
    if (!str || !/^\d{4}-\d{2}-\d{2}$/.test(str)) return null;
    var p = str.split('-');
    return new Date(parseInt(p[0],10), parseInt(p[1],10)-1, parseInt(p[2],10));
  }
  function ymd(d) {
    var y = d.getFullYear(), m = d.getMonth()+1, day = d.getDate();
    return y + '-' + (m<10?'0':'') + m + '-' + (day<10?'0':'') + day;
  }
  function formatDisplay(d) {
    var day = d.getDate(), m = d.getMonth()+1, y = d.getFullYear();
    return (day<10?'0':'') + day + '/' + (m<10?'0':'') + m + '/' + y;
  }

  function initOne(wrap) {
    var pop = wrap.querySelector('.cal-popover');
    var inputVal = wrap.querySelector('input[type="hidden"]');
    var inputDisplay = wrap.querySelector('.strateedge-date-display') || wrap.querySelector('input[type="text"][readonly]') || wrap.querySelector('input[type="text"]');
    if (!pop || !inputVal || !inputDisplay) return;

    var view = { year: new Date().getFullYear(), month: new Date().getMonth() };
    var selected = null;

    function render() {
      var first = new Date(view.year, view.month, 1);
      var last = new Date(view.year, view.month + 1, 0);
      var offset = (first.getDay() + 6) % 7;
      var start = new Date(first);
      start.setDate(start.getDate() - offset);
      var today = new Date();
      today.setHours(0,0,0,0);

      var html = '<div class="cal-header">';
      html += '<span class="cal-title">' + MOIS[view.month] + ' ' + view.year + '</span>';
      html += '<div class="cal-nav"><button type="button" class="cal-nav-btn" data-dir="-1" aria-label="Mois précédent"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>';
      html += '<button type="button" class="cal-nav-btn" data-dir="1" aria-label="Mois suivant"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button></div></div>';
      html += '<div class="cal-weekdays">';
      JOURS.forEach(function(j){ html += '<span>' + j + '</span>'; });
      html += '</div><div class="cal-weekdays-sep"></div><div class="cal-grid">';

      var d = new Date(start);
      var rows = last.getDate() + offset > 35 ? 42 : 35;
      for (var i = 0; i < rows; i++) {
        var ymdStr = d.getFullYear() + '-' + (d.getMonth()+1<10?'0':'') + (d.getMonth()+1) + '-' + (d.getDate()<10?'0':'') + d.getDate();
        var other = d.getMonth() !== view.month;
        var isToday = d.getTime() === today.getTime();
        var isSelected = selected && ymd(selected) === ymdStr;
        var cls = 'cal-day' + (other ? ' other-month' : '') + (isToday ? ' today' : '') + (isSelected ? ' selected' : '');
        html += '<button type="button" class="' + cls + '" data-ymd="' + ymdStr + '">' + d.getDate() + '</button>';
        d.setDate(d.getDate() + 1);
      }
      html += '</div>';
      html += '<div class="cal-footer"><button type="button" class="cal-clear-btn" data-action="clear">Effacer</button></div>';
      pop.innerHTML = html;

      pop.querySelectorAll('.cal-nav-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          var dir = parseInt(btn.getAttribute('data-dir'), 10);
          view.month += dir;
          if (view.month > 11) { view.month = 0; view.year++; }
          if (view.month < 0) { view.month = 11; view.year--; }
          render();
        });
      });
      pop.querySelectorAll('.cal-day').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var y = btn.getAttribute('data-ymd');
          if (!y) return;
          selected = parseYmd(y) || new Date(y);
          inputVal.value = y;
          inputDisplay.value = formatDisplay(selected);
          inputDisplay.placeholder = '';
          pop.classList.remove('is-open');
        });
      });
      var clearBtn = pop.querySelector('.cal-clear-btn');
      if (clearBtn) clearBtn.addEventListener('click', function() {
        selected = null;
        inputVal.value = '';
        inputDisplay.value = '';
        inputDisplay.placeholder = inputDisplay.getAttribute('placeholder') || 'jj/mm/aaaa';
        pop.classList.remove('is-open');
      });
    }

    wrap.addEventListener('click', function(e) {
      if (e.target.closest('.cal-popover')) return;
      pop.classList.toggle('is-open');
      if (pop.classList.contains('is-open')) {
        var v = parseYmd(inputVal.value);
        if (v) { view.year = v.getFullYear(); view.month = v.getMonth(); selected = v; } else { selected = null; }
        render();
      }
    });
    document.addEventListener('click', function(e) {
      if (!wrap.contains(e.target)) pop.classList.remove('is-open');
    });
    var cur = parseYmd(inputVal.value);
    if (cur) inputDisplay.value = formatDisplay(cur);
  }

  function buildAll() {
    var wraps = document.querySelectorAll('.strateedge-date-wrap, .date-picker-wrap.startedge-cal');
    var legacy = document.querySelectorAll('.date-picker-wrap');
    var seen = new Set();
    wraps.forEach(function(w) {
      if (seen.has(w)) return;
      if (w.querySelector('.cal-popover')) { seen.add(w); initOne(w); }
    });
    legacy.forEach(function(w) {
      if (seen.has(w)) return;
      if (w.querySelector('.cal-popover')) { seen.add(w); initOne(w); }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', buildAll);
  else buildAll();
})();
