(() => {
  'use strict';

  const ALIASES = {
    FRA:'FR', FRANCE:'FR', KAZ:'KZ', KAZAKHSTAN:'KZ', CZE:'CZ', CZECHIA:'CZ',
    ROU:'RO', ROMANIA:'RO', UKR:'UA', UKRAINE:'UA', USA:'US', GBR:'GB', GB:'GB',
    ESP:'ES', SPAIN:'ES', ITA:'IT', ITALY:'IT', GER:'DE', DEU:'DE', GERMANY:'DE',
    BEL:'BE', BELGIUM:'BE', NED:'NL', NLD:'NL', NETHERLANDS:'NL', SUI:'CH', CHE:'CH',
    SWITZERLAND:'CH', AUT:'AT', AUSTRIA:'AT', POL:'PL', POLAND:'PL', POR:'PT', PRT:'PT',
    PORTUGAL:'PT', BRA:'BR', BRAZIL:'BR', ARG:'AR', ARGENTINA:'AR', CHI:'CL', CHL:'CL',
    CHILE:'CL', COL:'CO', COLOMBIA:'CO', ECU:'EC', ECUADOR:'EC', MEX:'MX', MEXICO:'MX',
    CAN:'CA', CANADA:'CA', AUS:'AU', AUSTRALIA:'AU', NZL:'NZ', JPN:'JP', JAPAN:'JP',
    CHN:'CN', CHINA:'CN', KOR:'KR', IND:'IN', INDIA:'IN', SRB:'RS', SERBIA:'RS',
    CRO:'HR', HRV:'HR', CROATIA:'HR', SLO:'SI', SVN:'SI', SLOVENIA:'SI', SVK:'SK',
    SLOVAKIA:'SK', BUL:'BG', BGR:'BG', BULGARIA:'BG', GRE:'GR', GRC:'GR', GREECE:'GR',
    TUR:'TR', TURKEY:'TR', ISR:'IL', ISRAEL:'IL', TUN:'TN', TUNISIA:'TN', MAR:'MA',
    MOR:'MA', MOROCCO:'MA', RSA:'ZA', ZAF:'ZA', EGY:'EG', EGYPT:'EG', BLR:'BY',
    BELARUS:'BY', LTU:'LT', LITHUANIA:'LT', LAT:'LV', LVA:'LV', LATVIA:'LV', EST:'EE',
    ESTONIA:'EE', DEN:'DK', DNK:'DK', DENMARK:'DK', SWE:'SE', SWEDEN:'SE', NOR:'NO',
    NORWAY:'NO', FIN:'FI', FINLAND:'FI', IRL:'IE', IRELAND:'IE', HUN:'HU', HUNGARY:'HU',
    GEO:'GE', GEORGIA:'GE', ARM:'AM', ARMENIA:'AM', UZB:'UZ', UZBEKISTAN:'UZ',
    MDA:'MD', MOLDOVA:'MD', BIH:'BA', BOSNIA:'BA', MNE:'ME', MONTENEGRO:'ME',
    MKD:'MK', MACEDONIA:'MK', CYP:'CY', CYPRUS:'CY', THA:'TH', THAILAND:'TH', TPE:'TW'
  };

  // Fallback pour les affiches visibles au moment du correctif.
  const KNOWN_PLAYERS = {
    'ALEXANDER BUBLIK':'KZ',
    'QUENTIN HALYS':'FR',
    'TEREZA VALENTOVA':'CZ',
    'DARIA SNIGUR':'UA'
  };

  const SELECTORS = [
    '[data-country]','[data-country-code]','[data-nationality]','[data-ioc]',
    '.player-name','.player_name','.tennis-player','.tennis-player-name',
    '.competitor-name','.competitor','.match-player','.candidate-player',
    '.ef-player','.ef-player-name','.player','.participant-name',
    '[class*="player-name"]','[class*="competitor-name"]'
  ].join(',');

  const ATTRS = ['data-country','data-country-code','data-nationality','data-ioc','title','aria-label'];
  const countryHints = new Map(Object.entries(KNOWN_PLAYERS));

  function cleanText(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toUpperCase().replace(/\s+/g, ' ').trim();
  }

  function normalizeCountry(raw) {
    const clean = cleanText(raw).replace(/[._-]+/g, ' ');
    return ALIASES[clean] || (/^[A-Z]{2}$/.test(clean) ? clean : '');
  }

  function flagEmoji(iso) {
    return /^[A-Z]{2}$/.test(iso)
      ? String.fromCodePoint(...[...iso].map(c => 127397 + c.charCodeAt(0))) : '';
  }

  function readCountryFromNode(el) {
    let node = el;
    for (let depth = 0; node instanceof HTMLElement && depth < 4; depth++, node = node.parentElement) {
      for (const attr of ATTRS) {
        const iso = normalizeCountry(node.getAttribute(attr));
        if (iso) return iso;
      }
      const text = cleanText(node.textContent);
      for (const token of (text.match(/\b[A-Z]{2,3}\b/g) || [])) {
        const iso = normalizeCountry(token);
        if (iso) return iso;
      }
    }
    return '';
  }

  function learnHintsFromDom() {
    document.querySelectorAll('[data-country],[data-country-code],[data-nationality],[data-ioc]').forEach(el => {
      const iso = readCountryFromNode(el);
      const name = cleanText(el.getAttribute('data-player') || el.getAttribute('data-name') || el.textContent);
      if (iso && name && name.length < 80) countryHints.set(name, iso);
    });
  }

  function learnHintsFromJson() {
    const nameKeys = ['player','player_name','name','home_player','away_player','player1','player2'];
    const countryKeys = ['country','country_code','nationality','ioc','iso2','iso_code'];

    function walk(value) {
      if (!value || typeof value !== 'object') return;
      if (Array.isArray(value)) return value.forEach(walk);

      let name = '';
      let country = '';
      for (const key of nameKeys) if (!name && typeof value[key] === 'string') name = value[key];
      for (const key of countryKeys) if (!country && typeof value[key] === 'string') country = value[key];
      const iso = normalizeCountry(country);
      if (name && iso) countryHints.set(cleanText(name), iso);
      Object.values(value).forEach(walk);
    }

    document.querySelectorAll('script[type="application/json"],script[type="application/ld+json"]').forEach(script => {
      try { walk(JSON.parse(script.textContent || '')); } catch (_) {}
    });
  }

  function detectCountry(el) {
    const direct = readCountryFromNode(el);
    if (direct) return direct;

    const name = cleanText(el.textContent).replace(/\b[A-Z]{2,3}\b/g, '').trim();
    if (countryHints.has(name)) return countryHints.get(name);
    for (const [playerName, iso] of countryHints.entries()) {
      if (name.includes(playerName) || playerName.includes(name)) return iso;
    }
    return '';
  }

  function decorate(el) {
    if (!(el instanceof HTMLElement)) return;
    if (el.closest('#sidebar,.mobile-topbar,.admin-mob-tabs')) return;
    if (el.dataset.flagDecorated === '1' || el.querySelector(':scope > .tennis-country-flag')) return;

    const iso = detectCountry(el);
    const emoji = flagEmoji(iso);
    if (!emoji) return;

    const span = document.createElement('span');
    span.className = 'tennis-country-flag';
    span.textContent = emoji;
    span.title = iso;
    span.setAttribute('aria-label', `Pays ${iso}`);
    el.prepend(span);
    el.dataset.flagDecorated = '1';
  }

  function scan(root = document) {
    learnHintsFromDom();
    if (root instanceof HTMLElement && root.matches(SELECTORS)) decorate(root);
    root.querySelectorAll?.(SELECTORS).forEach(decorate);
  }

  const style = document.createElement('style');
  style.textContent = '.tennis-country-flag{display:inline-block;margin-right:.42em;font-size:.95em;line-height:1;vertical-align:-.04em;filter:drop-shadow(0 1px 2px rgba(0,0,0,.35))}';
  document.head.appendChild(style);

  const start = () => {
    learnHintsFromJson();
    scan();
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
      if (node instanceof HTMLElement) scan(node);
    }))).observe(document.body, {childList:true, subtree:true});
  };

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', start, {once:true})
    : start();
})();
