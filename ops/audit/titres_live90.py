#!/usr/bin/env python3
"""LECTURE SEULE. Intitulés exacts des colonnes Packball déjà vues (statistiques et cotes),
pour reconstruire le filtre live sans rien inventer. N'affiche que des intitulés et des compteurs."""
import collections, json, sqlite3, sys
from datetime import datetime
DB = sys.argv[1] if len(sys.argv) > 1 else '/var/lib/stratedge/live90.sqlite'
c = sqlite3.connect(f'file:{DB}?mode=ro', uri=True)
def week(ts):
    try: return datetime.fromisoformat(ts.replace('Z', '+00:00')).strftime('S%V')
    except Exception: return '?'
stats = collections.Counter(); sw = collections.defaultdict(set); quotes = collections.Counter(); qw = collections.defaultdict(set); qmap = {}
n = 0
for sid, rec, raw in c.execute("select id,received_at,data from samples where id % 40 = 0 and data like '%\"LIVE\"%'"):
    try: r = json.loads(raw)
    except Exception: continue
    if r.get('state') != 'LIVE': continue
    n += 1; w = week(rec)
    for x in r.get('raw_cells') or []:
        if isinstance(x, dict) and x.get('title'): stats[(x['title'][:70], x.get('target') or 'non reconnue')] += 1; sw[x['title'][:70]].add(w)
    for q in r.get('quotes') or []:
        t = (q.get('title') or '?')[:80]; quotes[t] += 1; qw[t].add(w)
        qmap[t] = f"{q.get('market')}/{q.get('period')}/{q.get('team')}/{q.get('side')}"
print(f'═══ {n} relevés en direct échantillonnés (1 sur 40) ═══')
print('  COLONNES STATISTIQUES vues (intitulé Packball → champ) :')
for (t, target), k in sorted(stats.items(), key=lambda x: -x[1]): print(f"    {k:6}  {t:62} → {target:24} semaines {' '.join(sorted(sw[t]))}")
print('  COLONNES DE COTES vues (intitulé Packball → classement) :')
for t, k in quotes.most_common(40): print(f"    {k:6}  {t:70} → {qmap[t]:36} semaines {' '.join(sorted(qw[t]))}")
