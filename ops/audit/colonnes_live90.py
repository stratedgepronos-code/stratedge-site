#!/usr/bin/env python3
"""LECTURE SEULE. Quelles colonnes Packball chaque version du collecteur reçoit-elle ?

Explique pourquoi « attaques dangereuses » et « tacles » manquent dans une partie
des relevés : colonne absente de l'écran, ou colonne présente mais non reconnue.
N'affiche que des intitulés de colonnes et des compteurs.
"""
import collections, json, sqlite3, sys
DB = sys.argv[1] if len(sys.argv) > 1 else '/var/lib/stratedge/live90.sqlite'
c = sqlite3.connect(f'file:{DB}?mode=ro', uri=True)
last = c.execute('select max(id) from samples').fetchone()[0]
seen = {}; clients = collections.defaultdict(collections.Counter)
for sid, rec, raw in c.execute("select id,received_at,data from samples where id>? and data like '%\"LIVE\"%' order by id desc", (last - 400000,)):
    try: r = json.loads(raw)
    except Exception: continue
    if r.get('state') != 'LIVE': continue
    v = str(r.get('collector_version') or 'ancien')
    clients[v][rec[:10]] += 1
    if v in seen: continue
    cells = r.get('raw_cells') or []
    seen[v] = (rec, [(x.get('title'), x.get('target')) for x in cells if isinstance(x, dict)],
               sorted((r.get('stats') or {}).keys()), sorted((r.get('ind10') or {}).keys()), sorted((r.get('ind5') or {}).keys()),
               sorted({(q.get('title') or '?')[:70] for q in r.get('quotes') or []}), r.get('quality_errors'))
for v, (rec, cells, st, i10, i5, quotes, qe) in sorted(seen.items()):
    print(f'═══ collecteur {v} · dernier relevé en direct reçu le {rec} ═══')
    days = sorted(clients[v].items())
    print('  relevés en direct par jour (10 derniers) :', dict(days[-10:]))
    print(f'  colonnes statistiques à l’écran : {len(cells)}')
    for title, target in cells: print(f"    {'✓' if target else '·'} {str(title)[:60]:60} → {target or 'non reconnue'}")
    if not cells: print('    (intitulés non conservés dans ce relevé) clés reçues :', st, i10, i5)
    print('  colonnes de cotes à l’écran :', quotes or 'aucune')
    if qe: print('  erreurs de qualité :', qe)
