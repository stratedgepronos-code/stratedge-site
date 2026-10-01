(() => {
  'use strict';

  const ALIASES = {
    FRA:'FR', FRANCE:'FR', FRENCH:'FR', KAZ:'KZ', KAZAKHSTAN:'KZ',
    CZE:'CZ', CZECHIA:'CZ', 'CZECH REPUBLIC':'CZ', ROU:'RO', ROMANIA:'RO',
    UKR:'UA', UKRAINE:'UA', USA:'US', 'UNITED STATES':'US', AMERICAN:'US',
    GBR:'GB', GB:'GB', 'UNITED KINGDOM':'GB', BRITISH:'GB', ENGLAND:'GB',
    ESP:'ES', SPAIN:'ES', ITA:'IT', ITALY:'IT', GER:'DE', DEU:'DE', GERMANY:'DE',
    BEL:'BE', BELGIUM:'BE', NED:'NL', NLD:'NL', NETHERLANDS:'NL',
    SUI:'CH', CHE:'CH', SWITZERLAND:'CH', AUT:'AT', AUSTRIA:'AT',
    POL:'PL', POLAND:'PL', POR:'PT', PRT:'PT', PORTUGAL:'PT',
    BRA:'BR', BRAZIL:'BR', ARG:'AR', ARGENTINA:'AR', CHI:'CL', CHL:'CL', CHILE:'CL',
    COL:'CO', COLOMBIA:'CO', ECU:'EC', ECUADOR:'EC', MEX:'MX', MEXICO:'MX',
    CAN:'CA', CANADA:'CA', AUS:'AU', AUSTRALIA:'AU', NZL:'NZ', 'NEW ZEALAND':'NZ',
    JPN:'JP', JAPAN:'JP', CHN:'CN', CHINA:'CN', KOR:'KR', 'SOUTH KOREA':'KR',
    IND:'IN', INDIA:'IN', SRB:'RS', SERBIA:'RS', CRO:'HR', HRV:'HR', CROATIA:'HR',
    SLO:'SI', SVN:'SI', SLOVENIA:'SI', SVK:'SK', SLOVAKIA:'SK',
    BUL:'BG', BGR:'BG', BULGARIA:'BG', GRE:'GR', GRC:'GR', GREECE:'GR',
    TUR:'TR', TURKEY:'TR', TURKIYE:'TR', ISR:'IL', ISRAEL:'IL',
    TUN:'TN', TUNISIA:'TN', MAR:'MA', MOR:'MA', MOROCCO:'MA',
    RSA:'ZA', ZAF:'ZA', 'SOUTH AFRICA':'ZA', EGY:'EG', EGYPT:'EG',
    BLR:'BY', BELARUS:'BY', LTU:'LT', LITHUANIA:'LT', LAT:'LV', LVA:'LV', LATVIA:'LV',
    EST:'EE', ESTONIA:'EE', DEN:'DK', DNK:'DK', DENMARK:'DK', SWE:'SE', SWEDEN:'SE',
    NOR:'NO', NORWAY:'NO', FIN:'FI', FINLAND:'FI', IRL:'IE', IRELAND:'IE',
    HUN:'HU', HUNGARY:'HU', GEO:'GE', GEORGIA:'GE', ARM:'AM', ARMENIA:'AM',
    UZB:'UZ', UZBEKISTAN:'UZ', MDA:'MD', MOLDOVA:'MD', BIH:'BA', BOSNIA:'BA',
    MNE:'ME', MONTENEGRO:'ME', MKD:'MK', MACEDONIA:'MK', 'NORTH MACEDONIA':'MK',
    CYP:'CY', CYPRUS:'CY', THA:'TH', THAILAND:'TH', TPE:'TW', TAIWAN:'TW'
  };

  const KNOWN_PLAYERS = {
    'ALEXANDER BUBLIK':'KZ',
    'QUENTIN HALYS':'FR',
    'TEREZA VALENTOVA':'CZ',
    'DARIA SNIGUR':'UA'
  };

  const PLAYER_SELECTORS = [
    '[data-country]','[data-country-code]','[data-nationality]','[data-ioc]',
    '[data-player-country]','[data-player1-country]','[data-player2-country]',
    '.player-name','.player_name','.tennis-player','.tennis-player-name',
    '.competitor-name','.competitor','.match-player','.candidate-player',
    '.ef-player','.ef-player-name','.player','.participant-name',
    '[class*="player-name"]','[class*="competitor-name"]',
    'h1','h2','h3','h4','h5','h6','strong','b'
  ].join(',');

  const COUNTRY_ATTRS = [
    'data-country','data-country-code','data-nationality','data-ioc',
    'data-player-country','data-player1-country','data-player2-country',
    'title','aria-label'
  ];

  const PLAYER_ATTRS = [
    'data-player','data-player-name','data-name','data-player1','data-player2',
    'data-home-player','data-away-player'
  ];

  const countryHints = new Map(Object.entries(KNOWN_PLAYERS));
  const initializedDocuments = new WeakSet();
  const initializedFrames = new WeakSet();

  function isElement(node) {
    return Boolean(node && node.nodeType === 1);
  }

  function cleanText(value) {
    return String(value || '')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toUpperCase().replace(/\s+/g, ' ').trim();
  }

  function normalizeCountry(raw) {
    const clean = cleanText(raw).replace(/[._-]+/g, ' ');
    return ALIASES[clean] || (/^[A-Z]{2}$/.test(clean) ? clean : '');
  }

  function flagEmoji(iso) {
    return /^[A-Z]{2}$/.test(iso)
      ? String.fromCodePoint(...[...iso].map(char => 127397 + char.charCodeAt(0)))
      : '';
  }

  function exactKnownPlayer(text) {
    const cleaned = cleanText(text).replace(/^[\p{Regional_Indicator}\s]+/u, '').trim();
    for (const [name, iso] of Object.entries(KNOWN_PLAYERS)) {
      if (cleaned === name || (cleaned.length <= 65 && cleaned.includes(name))) return iso;
    }
    return '';
  }

  function readCountryFromNode(element) {
    let node = element;
    for (let depth = 0; isElement(node) && depth < 5; depth += 1, node = node.parentElement) {
      for (const attr of COUNTRY_ATTRS) {
        const iso = normalizeCountry(node.getAttribute?.(attr));
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

  function learnHintsFromDom(doc) {
    doc.querySelectorAll('[data-country],[data-country-code],[data-nationality],[data-ioc],[data-player-country],[data-player1-country],[data-player2-country]').forEach(element => {
      const iso = readCountryFromNode(element);
      if (!iso) return;

      let name = '';
      for (const attr of PLAYER_ATTRS) {
        if (!name) name = element.getAttribute?.(attr) || '';
      }
      if (!name) name = element.textContent || '';
      name = cleanText(name);
      if (name && name.length < 90) countryHints.set(name, iso);
    });
  }

  function learnHintsFromJson(doc) {
    const nameKeys = ['player','player_name','name','home_player','away_player','player1','player2'];
    const countryKeys = ['country','country_code','nationality','ioc','iso2','iso_code'];

    function walk(value) {
      if (!value || typeof value !== 'object') return;
      if (Array.isArray(value)) {
        value.forEach(walk);
        return;
      }

      let name = '';
      let country = '';
      for (const key of nameKeys) if (!name && typeof value[key] === 'string') name = value[key];
      for (const key of countryKeys) if (!country && typeof value[key] === 'string') country = value[key];
      const iso = normalizeCountry(country);
      if (name && iso) countryHints.set(cleanText(name), iso);
      Object.values(value).forEach(walk);
    }

    doc.querySelectorAll('script[type="application/json"],script[type="application/ld+json"]').forEach(script => {
      try { walk(JSON.parse(script.textContent || '')); } catch (_) {}
    });
  }

  function detectCountry(element) {
    const direct = readCountryFromNode(element);
    if (direct) return direct;

    const rawName = cleanText(element.textContent)
      .replace(/\b[A-Z]{2,3}\b/g, '')
      .replace(/^[^A-ZÀ-ÖØ-Þ]+/g, '')
      .trim();

    const known = exactKnownPlayer(rawName);
    if (known) return known;
    if (countryHints.has(rawName)) return countryHints.get(rawName);

    for (const [playerName, iso] of countryHints.entries()) {
      if (rawName.length <= 80 && (rawName.includes(playerName) || playerName.includes(rawName))) return iso;
    }
    return '';
  }

  function decorate(element) {
    if (!isElement(element)) return;
    if (element.closest?.('#sidebar,.mobile-topbar,.admin-mob-tabs')) return;
    if (element.dataset.flagDecorated === '1') return;
    if (element.querySelector?.(':scope > .tennis-country-flag')) return;

    const iso = detectCountry(element);
    const emoji = flagEmoji(iso);
    if (!emoji) return;

    const doc = element.ownerDocument;
    const span = doc.createElement('span');
    span.className = 'tennis-country-flag';
    span.textContent = emoji;
    span.title = iso;
    span.setAttribute('aria-label', `Pays ${iso}`);
    element.prepend(span);
    element.dataset.flagDecorated = '1';
  }

  function scan(doc, root = doc) {
    learnHintsFromDom(doc);
    learnHintsFromJson(doc);
    if (isElement(root) && root.matches?.(PLAYER_SELECTORS)) decorate(root);
    root.querySelectorAll?.(PLAYER_SELECTORS).forEach(decorate);
  }

  function installStyle(doc) {
    if (doc.getElementById('tennis-country-flags-style')) return;
    const style = doc.createElement('style');
    style.id = 'tennis-country-flags-style';
    style.textContent = '.tennis-country-flag{display:inline-block;margin-right:.42em;font-size:.95em;line-height:1;vertical-align:-.04em;filter:drop-shadow(0 1px 2px rgba(0,0,0,.35))}';
    doc.head?.appendChild(style);
  }

  function setupDocument(doc) {
    if (!doc || initializedDocuments.has(doc)) return;
    initializedDocuments.add(doc);

    const start = () => {
      installStyle(doc);
      scan(doc);
      if (!doc.body) return;
      new MutationObserver(records => {
        for (const record of records) {
          for (const node of record.addedNodes) {
            if (isElement(node)) scan(doc, node);
          }
        }
      }).observe(doc.body, {childList:true, subtree:true});
    };

    doc.readyState === 'loading'
      ? doc.addEventListener('DOMContentLoaded', start, {once:true})
      : start();
  }

  function setupFrame(frame) {
    if (!isElement(frame) || initializedFrames.has(frame)) return;
    initializedFrames.add(frame);

    const connect = () => {
      try {
        const frameDoc = frame.contentDocument || frame.contentWindow?.document;
        if (frameDoc) setupDocument(frameDoc);
      } catch (_) {
        // L'iframe est normalement same-origin. On ignore proprement sinon.
      }
    };

    frame.addEventListener('load', connect);
    connect();
  }

  function setupFrames(doc) {
    doc.querySelectorAll('iframe').forEach(setupFrame);
    if (!doc.body) return;
    new MutationObserver(records => {
      for (const record of records) {
        for (const node of record.addedNodes) {
          if (!isElement(node)) continue;
          if (node.matches?.('iframe')) setupFrame(node);
          node.querySelectorAll?.('iframe').forEach(setupFrame);
        }
      }
    }).observe(doc.body, {childList:true, subtree:true});
  }

  function boot() {
    setupDocument(document);
    setupFrames(document);
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', boot, {once:true})
    : boot();
})();
