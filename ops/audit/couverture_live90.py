#!/usr/bin/env python3
"""Mesure, en LECTURE SEULE, ce que live90.sqlite conserve réellement.

Répond à trois questions, chiffres à l'appui :
  1. Les relevés sans alerte sont-ils gardés, sur quelle période, à quelle cadence ?
  2. Quelles cotes live sont présentes, pour quel marché / période / équipe, et sur
     quelle part des relevés en direct ?
  3. Les alertes portent-elles une cote, et laquelle ?

Ouvre la base en mode ro : aucune écriture possible.
Usage : sudo -u www-data python3 couverture_live90.py [/var/lib/stratedge/live90.sqlite]
"""
import collections, json, sqlite3, statistics, sys

DB = sys.argv[1] if len(sys.argv) > 1 else '/var/lib/stratedge/live90.sqlite'
c = sqlite3.connect(f'file:{DB}?mode=ro', uri=True)
tables = {r[0] for r in c.execute("select name from sqlite_master where type='table'")}

print('═══ 1 · RELEVÉS CONSERVÉS ═══')
n, t0, t1, nm = c.execute('select count(*),min(received_at),max(received_at),count(distinct match_id) from samples').fetchone()
print(f'  {n} relevés · {nm} matchs · du {t0} au {t1} (heure serveur, UTC)')
print(f"  cycles bruts conservés : {c.execute('select count(*) from cycles').fetchone()[0]}")

live = 0; per_match = collections.Counter(); versions = collections.Counter()
gaps = []; last = {}
quotes = collections.Counter(); unmapped = collections.Counter(); with_any = 0
matches_with = collections.defaultdict(set)
for sid, mid, rec, raw in c.execute('select id,match_id,received_at,data from samples order by id'):
    try: r = json.loads(raw)
    except Exception: continue
    versions[r.get('collector_version') or '(ancien)'] += 1
    if r.get('state') != 'LIVE': continue
    live += 1; per_match[mid] += 1
    if mid in last:
        from datetime import datetime
        try:
            a = datetime.fromisoformat(last[mid].replace('Z', '+00:00')); b = datetime.fromisoformat(rec.replace('Z', '+00:00'))
            g = (b - a).total_seconds()
            if 0 < g < 600: gaps.append(g)
        except Exception: pass
    last[mid] = rec
    seen = set()
    for q in r.get('quotes') or []:
        if not isinstance(q.get('odds'), (int, float)) or q['odds'] <= 1: continue
        if q.get('verified'):
            k = (q.get('market'), q.get('period'), q.get('team') or 'match', q.get('side'))
            seen.add(k); matches_with[k].add(mid)
        else:
            unmapped[(q.get('title') or '?')[:60]] += 1
    for k in seen: quotes[k] += 1
    if seen: with_any += 1

print(f'  dont {live} relevés EN DIRECT · {len(per_match)} matchs suivis en direct')
if per_match: print(f'  relevés en direct par match : médiane {statistics.median(per_match.values()):.0f}')
if gaps: print(f'  intervalle entre deux relevés d’un même match : médiane {statistics.median(gaps):.0f} s')
print('  versions du collecteur :', dict(versions.most_common(6)))

print('\n═══ 2 · COTES LIVE PRÉSENTES DANS LES RELEVÉS EN DIRECT ═══')
print(f'  relevés en direct avec au moins une cote classée : {with_any} / {live}' + (f' ({with_any/live:.0%})' if live else ''))
print(f"  {'marché':12} {'période':7} {'équipe':6} {'sens':5} {'relevés':>8} {'part':>6} {'matchs':>7}")
for (m, p, t, s), k in sorted(quotes.items(), key=lambda x: -x[1]):
    print(f'  {str(m):12} {str(p):7} {str(t):6} {str(s):5} {k:8} {k/live:6.0%} {len(matches_with[(m,p,t,s)]):7}')
print('  colonnes de cotes NON classées (conservées brutes, titre Packball) :')
for t, k in unmapped.most_common(10): print(f'    {k:7}  {t}')

print('\n═══ 3 · ALERTES ═══')
if 'v4_signals' in tables:
    tot, odd = c.execute('select count(*),sum(odds is not null) from v4_signals').fetchone()
    stake = sum(1 for (d,) in c.execute('select data from v4_signals') if '"stake_quote"' in (d or ''))
    print(f'  {tot} alertes · avec une cote enregistrée dans la colonne odds : {odd or 0} · avec une cote Stake (PulseScore) : {stake}')
    print('  par marché :', dict(c.execute('select market,count(*) from v4_signals group by market').fetchall()))
    print(f"  décisions conservées (dernier état seulement, par match/marché/équipe) : {c.execute('select count(*) from v4_decisions').fetchone()[0]}")
if 'v4_imports' in tables:
    print('  imports d’avant-match versionnés :', dict(c.execute('select kind,count(*) from v4_imports group by kind').fetchall()))

print('\n═══ EXEMPLE BRUT · un relevé en direct, cotes comprises ═══')
row = None
for sid, rec, raw in c.execute('select id,received_at,data from samples order by id desc limit 5000'):
    try: r = json.loads(raw)
    except Exception: continue
    if r.get('state') == 'LIVE' and any(q.get('verified') for q in r.get('quotes') or []):
        row = (sid, rec); break
if row:
    keep = {k: r.get(k) for k in ('packball_id', 'home', 'away', 'league', 'kickoff_ts', 'state', 'minute', 'score', 'collected_at', 'collector_version')}
    keep['received_at'] = row[1]; keep['sample_id'] = row[0]
    keep['quotes'] = [{k: q.get(k) for k in ('market', 'period', 'team', 'side', 'line', 'odds', 'column', 'title', 'observed_at', 'verified')} for q in (r.get('quotes') or [])][:8]
    print(json.dumps(keep, ensure_ascii=False, indent=1))
else:
    print('  aucun relevé en direct avec cote classée')
