#!/usr/bin/env python3
"""Deuxième contrôle, en LECTURE SEULE, de live90.sqlite.

  A. Les cotes « buts de l'équipe » portent-elles la ligne visée par une alerte
     (buts déjà marqués par l'équipe + 0,5) ou une autre ligne ?
  B. Pour chacune des alertes, le relevé du déclenchement contient-il la cote
     exacte (même équipe, même période, même ligne) ? Rattachement rétroactif.
  C. « Plus Cartes - Ligne suivante » : quelle ligne, par rapport aux jaunes déjà
     distribués dans le match ?
  D. Couverture semaine par semaine, pour voir quand les colonnes ont été ajoutées.
  E. Deux exemples bruts : un relevé avec cote d'équipe, une alerte et son relevé.

Usage : sudo -u www-data python3 -I - < couverture_live90_b.py
"""
import collections, json, sqlite3, sys
from datetime import datetime

DB = sys.argv[1] if len(sys.argv) > 1 else '/var/lib/stratedge/live90.sqlite'
c = sqlite3.connect(f'file:{DB}?mode=ro', uri=True)

def num(v): return isinstance(v, (int, float)) and not isinstance(v, bool)
def pair(r, g, k):
    p = (r.get(g) or {}).get(k)
    return p if isinstance(p, dict) and num(p.get('h')) and num(p.get('a')) else None
def week(ts):
    try: return datetime.fromisoformat(ts.replace('Z', '+00:00')).strftime('%G-S%V')
    except Exception: return '?'

ecart_but = collections.Counter(); ecart_cartes = collections.Counter()
sem_live = collections.Counter(); sem_but = collections.Counter(); sem_cartes = collections.Counter()
ex_but = None

for sid, rec, raw in c.execute("select id,received_at,data from samples where data like '%\"LIVE\"%'"):
    try: r = json.loads(raw)
    except Exception: continue
    if r.get('state') != 'LIVE': continue
    w = week(rec); sem_live[w] += 1
    sc = r.get('score') if isinstance(r.get('score'), dict) else None
    yc = pair(r, 'stats', 'yellow_cards')
    has_b = has_c = False
    for q in r.get('quotes') or []:
        if not num(q.get('odds')) or q['odds'] <= 1 or not num(q.get('line')): continue
        if q.get('market') == 'team_goals' and q.get('side') == 'over' and q.get('team') in ('h', 'a') and sc and num(sc.get(q['team'])):
            ecart_but[(q.get('period'), round(q['line'] - sc[q['team']], 1))] += 1; has_b = True
            if ex_but is None: ex_but = (sid, rec, r)
        if 'cartes' in (q.get('title') or '').lower() and 'suivante' in (q.get('title') or '').lower():
            ecart_cartes['jaunes inconnus' if not yc else round(q['line'] - (yc['h'] + yc['a']), 1)] += 1; has_c = True
    if has_b: sem_but[w] += 1
    if has_c: sem_cartes[w] += 1

print('═══ A · LIGNE DES COTES « BUTS DE L’ÉQUIPE » (ligne − buts déjà marqués par l’équipe) ═══')
print('  0,5 = exactement la cible d’une alerte « l’équipe marque encore »')
for (p, e), k in sorted(ecart_but.items(), key=lambda x: -x[1])[:10]:
    print(f'  période {p} · écart {e:+.1f} : {k} relevés')

print('\n═══ B · LES ALERTES ONT-ELLES LEUR COTE EXACTE DANS LEUR RELEVÉ ? ═══')
tot = collections.Counter(); ok = collections.Counter(); autre_ligne = collections.Counter()
ex_alerte = None
for sid_sig, market, team, line, sample_id, created, data in c.execute('select id,market,team,line,sample_id,created_at,data from v4_signals order by id'):
    tot[market] += 1
    row = c.execute('select data from samples where id=?', (sample_id,)).fetchone()
    if not row: continue
    r = json.loads(row[0])
    want = {'goal_ft': ('team_goals', 'FT'), 'goal_ht': ('team_goals', 'HT')}.get(market)
    found = None; near = False
    for q in r.get('quotes') or []:
        if want and q.get('market') == want[0] and q.get('period') == want[1] and q.get('team') == team and q.get('side') == 'over' and num(q.get('odds')):
            if num(line) and num(q.get('line')) and abs(q['line'] - line) < 1e-9: found = q
            else: near = True
    if found: ok[market] += 1
    elif near: autre_ligne[market] += 1
    if ex_alerte is None and market in ('goal_ft', 'goal_ht'):
        ex_alerte = {'signal_id': sid_sig, 'market': market, 'team': team, 'line': line, 'created_at': created,
                     'sample_id': sample_id, 'minute': r.get('minute'), 'score': r.get('score'),
                     'quotes_du_releve': [{k: q.get(k) for k in ('market', 'period', 'team', 'side', 'line', 'odds', 'title', 'observed_at')} for q in (r.get('quotes') or [])][:6]}
for m in tot:
    print(f'  {m:8} : {tot[m]} alertes · cote exacte présente : {ok[m]} · cote de l’équipe mais autre ligne : {autre_ligne[m]}')
print('  (card_ft : aucune cote de cartons par équipe n’est collectée, voir C)')

print('\n═══ C · « PLUS CARTES - LIGNE SUIVANTE » (ligne − jaunes déjà distribués dans le match) ═══')
for e, k in ecart_cartes.most_common(8): print(f'  écart {e} : {k} relevés')

print('\n═══ D · COUVERTURE PAR SEMAINE (relevés en direct) ═══')
print(f"  {'semaine':10} {'en direct':>10} {'avec but équipe':>16} {'avec cartes':>12}")
for w in sorted(sem_live):
    n = sem_live[w]
    print(f'  {w:10} {n:10} {sem_but[w]:9} ({sem_but[w]/n:4.0%}) {sem_cartes[w]:6} ({sem_cartes[w]/n:4.0%})')

print('\n═══ E · EXEMPLES BRUTS ═══')
if ex_but:
    sid, rec, r = ex_but
    print('  relevé avec cote « buts de l’équipe » :')
    print(json.dumps({'sample_id': sid, 'received_at': rec, **{k: r.get(k) for k in ('packball_id', 'home', 'away', 'minute', 'score', 'collected_at', 'collector_version')},
                      'quotes': [{k: q.get(k) for k in ('market', 'period', 'team', 'side', 'line', 'odds', 'title', 'column', 'observed_at')} for q in (r.get('quotes') or [])][:6]},
                     ensure_ascii=False, indent=1))
if ex_alerte:
    print('  alerte buts et son relevé de déclenchement :')
    print(json.dumps(ex_alerte, ensure_ascii=False, indent=1))
