#!/usr/bin/env python3
"""Mesure, en LECTURE SEULE, ce qui annonce réellement un but ou un jaune d'équipe.

N'affiche que des statistiques agrégées (journal public) : aucun secret, aucune
configuration, aucun identifiant de compte.

  1. Couverture des statistiques live par semaine, vérité mi-temps / fin de match.
  2. Taux de base par équipe : but avant la pause / avant la fin, jaune avant la
     pause / jaune supplémentaire avant la fin, selon la minute et le score.
  3. Ce que chaque statistique live ajoute au taux de base (dont la règle actuelle).
  4. Modèle simple testé sur les matchs les plus récents (séparation chronologique
     par match) : jusqu'où peut monter la réussite ?
  5. Références d'avant-match : le seuil 0,8 et les moyennes de jaunes trient-ils ?
  6. Alertes réellement envoyées et lectures sans alerte, contre le taux de base.
  7. Cotes « buts de l'équipe » archivées : réussite par tranche de cote.

Usage : sudo -u www-data python3 -I - /var/lib/stratedge/live90.sqlite < mesure_live90.py
"""
import collections, json, math, os, sqlite3, sys
from datetime import datetime

try: os.nice(10)          # ne pas gêner le moteur live qui tourne sur la même machine
except Exception: pass
sys.stdout.reconfigure(line_buffering=True)

DB = sys.argv[1] if len(sys.argv) > 1 else '/var/lib/stratedge/live90.sqlite'
c = sqlite3.connect(f'file:{DB}?mode=ro', uri=True)
tables = {r[0] for r in c.execute("select name from sqlite_master where type='table'")}

KEYS = ['shots', 'sot', 'shots_inside', 'shots_outside', 'shots_off_target', 'blocked_shots', 'da', 'corners',
        'fouls', 'tackles', 'possession', 'yellow_cards', 'red_cards', 'second_yellow', 'substitutions']
IND = [('ind10', 'shots10'), ('ind10', 'sot10'), ('ind10', 'da10'), ('ind10', 'corners10'),
       ('ind5', 'shots5'), ('ind5', 'sot5'), ('ind5', 'da5'), ('ind5', 'corners5')]
KI = {k: i for i, k in enumerate(KEYS)}
II = {k: i for i, (_, k) in enumerate(IND)}
BASE = 5
IBASE = BASE + 2 * len(KEYS)

def num(v): return isinstance(v, (int, float)) and not isinstance(v, bool) and math.isfinite(v)
def week(ts):
    try: return datetime.fromisoformat(ts.replace('Z', '+00:00')).strftime('%G-S%V')
    except Exception: return '?'
def pct(k, n): return f'{k / n:4.0%}' if n else '  – '
def val(rec, key, t): return rec[BASE + 2 * KI[key] + (t == 'a')]
def ind(rec, key, t): return rec[IBASE + 2 * II[key] + (t == 'a')]

def pack(r):
    sc = r.get('score') if isinstance(r.get('score'), dict) else {}
    sh, sa = sc.get('h'), sc.get('a')
    q = {'h': None, 'a': None}
    for x in r.get('quotes') or []:
        if (x.get('market') == 'team_goals' and x.get('period') == 'FT' and x.get('side') == 'over' and x.get('team') in q
                and num(x.get('odds')) and x['odds'] > 1 and num(x.get('line')) and num(sc.get(x['team']))
                and abs(x['line'] - sc[x['team']] - .5) < 1e-9):
            q[x['team']] = x['odds']
    out = [r.get('minute'), sh if num(sh) else None, sa if num(sa) else None, q['h'], q['a']]
    for g, keys in (('stats', KEYS),):
        d = r.get(g) if isinstance(r.get(g), dict) else {}
        for k in keys:
            p = d.get(k) if isinstance(d.get(k), dict) else {}
            out += [p.get('h') if num(p.get('h')) else None, p.get('a') if num(p.get('a')) else None]
    for g, k in IND:
        d = r.get(g) if isinstance(r.get(g), dict) else {}
        p = d.get(k) if isinstance(d.get(k), dict) else {}
        out += [p.get('h') if num(p.get('h')) else None, p.get('a') if num(p.get('a')) else None]
    return tuple(out)

# ───────────────────────── alertes : relevés à retrouver ─────────────────────────
alerts = []
if 'v5_radar_notifications' in tables:
    res = dict(c.execute('select radar_id,outcome from v5_radar_results').fetchall()) if 'v5_radar_results' in tables else {}
    for rid, mid, team, market, counter, created, sid, data, delivery in c.execute(
            'select id,match_id,team,market,counter,created_at,sample_id,data,delivery from v5_radar_notifications order by id'):
        try: d = json.loads(data or '{}')
        except Exception: d = {}
        tg = d.get('result_target') or {}
        period = tg.get('period') or ('HT' if (d.get('reading') or {}).get('period') == 'HT' and market == 'goals' else 'FT')
        alerts.append({'id': rid, 'mid': str(mid), 'team': team, 'market': market, 'counter': counter, 'created': created, 'sid': sid,
                       'delivery': delivery, 'period': period, 'outcome': res.get(rid, '(aucun)'), 'line': tg.get('line')})
need_sid = {a['sid'] for a in alerts}
sid_rec = {}

# ───────────────────────── chargement des relevés ─────────────────────────
M = {}
wk_n = collections.Counter(); wk_has = collections.defaultdict(collections.Counter)
n_live = 0
day_n = collections.Counter(); day_has = collections.defaultdict(collections.Counter); day_ver = collections.defaultdict(collections.Counter)
for sid, mid, rec, raw in c.execute(
        "select id,match_id,received_at,data from samples where data like '%\"LIVE\"%' or data like '%\"HT\"%' or data like '%\"FT\"%' order by id"):
    mid = str(mid)
    m = M.get(mid)
    if m is None: m = M[mid] = {'live': {}, 'ht': None, 'ft': [], 'late': None, 'first': sid, 'league': '', 'week': week(rec)}
    is_live = '"LIVE"' in raw
    if not is_live and len(m['ft']) >= 3 and m['ht'] is not None and sid not in need_sid: continue
    try: r = json.loads(raw)
    except Exception: continue
    st = r.get('state')
    if st not in ('LIVE', 'HT', 'FT'): continue
    if not m['league']: m['league'] = (r.get('league') or '')[:60]
    p = pack(r)
    if sid in need_sid: sid_rec[sid] = (st, p)
    if st == 'HT': m['ht'] = p
    elif st == 'FT':
        if len(m['ft']) < 3: m['ft'].append(p)
    else:
        minute = r.get('minute'); ex = r.get('minute_extra') or 0
        if not num(minute): continue
        n_live += 1
        if minute >= 90 and ex >= 3: m['late'] = p
        if ex or r.get('quality_errors') or minute in m['live']: continue
        m['live'][int(minute)] = p
        w = week(rec); wk_n[w] += 1
        dday = rec[:10]; day_n[dday] += 1
        for k in ('da', 'tackles', 'fouls', 'shots_inside', 'corners'):
            if val(p, k, 'h') is not None and val(p, k, 'a') is not None: day_has[dday][k] += 1
        day_ver[dday][str(r.get('collector_version') or 'ancien')] += 1
        for k in ('shots', 'sot', 'shots_inside', 'da', 'fouls', 'tackles', 'possession', 'yellow_cards'):
            if val(p, k, 'h') is not None and val(p, k, 'a') is not None: wk_has[w][k] += 1
        if ind(p, 'shots10', 'h') is not None: wk_has[w]['ind10'] += 1
        if ind(p, 'shots5', 'h') is not None: wk_has[w]['ind5'] += 1

print('═══ 1 · COUVERTURE ═══')
print(f'  {len(M)} matchs vus en direct, à la pause ou terminés · {n_live} relevés en direct')
print('  part des minutes relevées où la statistique est présente pour les deux équipes :')
cols = ['shots', 'sot', 'shots_inside', 'da', 'fouls', 'tackles', 'possession', 'yellow_cards', 'ind10', 'ind5']
print('  ' + f"{'semaine':9}{'minutes':>8}" + ''.join(f'{k[:9]:>10}' for k in cols))
for w in sorted(wk_n):
    print('  ' + f'{w:9}{wk_n[w]:8}' + ''.join(f'{pct(wk_has[w][k], wk_n[w]):>10}' for k in cols))

print('  détail par jour, 16 derniers jours (colonnes susceptibles d’avoir disparu du filtre Packball) :')
for dday in sorted(day_n)[-16:]:
    print(f"    {dday} {day_n[dday]:6} min · att.dang. {pct(day_has[dday]['da'], day_n[dday])} · tacles {pct(day_has[dday]['tackles'], day_n[dday])} · fautes {pct(day_has[dday]['fouls'], day_n[dday])} · tirs surface {pct(day_has[dday]['shots_inside'], day_n[dday])} · corners {pct(day_has[dday]['corners'], day_n[dday])} · collecteur {dict(day_ver[dday].most_common(2))}")
# vérité de fin de période
def truth(m):
    ht = m['ht']; src_ht = 'HT' if ht else None
    if ht is None:
        for mm in (46, 47):
            if mm in m['live']: ht = m['live'][mm]; src_ht = '46-47'; break
    ft = m['ft'][-1] if m['ft'] else None; src_ft = 'FT' if ft else None
    if ft is None and m['late'] is not None: ft = m['late']; src_ft = '90+3'
    return ht, src_ht, ft, src_ft
src = collections.Counter()
for m in M.values():
    m['HT'], a, m['FT'], b = truth(m); src['mi-temps : ' + str(a)] += 1; src['fin : ' + str(b)] += 1
print('  vérité disponible :', dict(src))
ft_ok = [m for m in M.values() if m['FT'] is not None]
def mean(xs): xs = [x for x in xs if x is not None]; return sum(xs) / len(xs) if xs else float('nan')
print('  moyennes par équipe à la fin du match (contrôle de vraisemblance du flux) :')
for k in ('shots', 'sot', 'fouls', 'tackles', 'yellow_cards', 'corners'):
    vals = [val(m['FT'], k, t) for m in ft_ok for t in 'ha']
    have = sum(v is not None for v in vals)
    print(f'    {k:13} {mean(vals):6.2f}   (présent dans {pct(have, len(vals))} des fins de match)')
print(f"    buts          {mean([m['FT'][1 + (t == 'a')] for m in ft_ok for t in 'ha']):6.2f}")

# ───────────────────────── situations à minute fixe ─────────────────────────
G1 = (15, 20, 25, 30, 35, 40); G2 = (57, 60, 65, 70, 75)
order = sorted(M, key=lambda k: M[k]['first'])
rank = {mid: i for i, mid in enumerate(order)}

def delta(m, minute, w, key, t):
    cur = m['live'].get(minute)
    if cur is None: return None
    for am in (minute - w, minute - w - 1):
        if am < 1 or (minute > 45 and am < 46): continue
        a = m['live'].get(am)
        if a is not None:
            va, vc = val(a, key, t), val(cur, key, t)
            return vc - va if va is not None and vc is not None and vc >= va else None
    return None

def since_card(m, minute, t):
    cur = m['live'][minute]; y = val(cur, 'yellow_cards', t); f = val(cur, 'fouls', t)
    if y is None or f is None: return None
    if y == 0: return f
    best = None
    for mm in sorted(m['live']):
        if mm > minute: break
        r = m['live'][mm]
        if val(r, 'yellow_cards', t) == y and val(r, 'fouls', t) is not None: best = val(r, 'fouls', t); break
    return f - best if best is not None and f >= best else None

rows = []
for mid, m in M.items():
    for minute in G1 + G2:
        cur = m['live'].get(minute)
        if cur is None or cur[1] is None or cur[2] is None: continue
        half = 1 if minute <= 45 else 2
        end = m['HT'] if half == 1 else m['FT']
        for t in 'ha':
            o = 'a' if t == 'h' else 'h'; ti = 1 + (t == 'a'); oi = 1 + (o == 'a')
            x = {'mid': mid, 't': t, 'm': minute, 'half': half, 'rank': rank[mid], 'gap': cur[ti] - cur[oi]}
            # issues
            x['goal'] = None if end is None or end[ti] is None or end[ti] < cur[ti] else int(end[ti] > cur[ti])
            fin = m['FT']
            x['goal_ft'] = None if fin is None or fin[ti] is None or fin[ti] < cur[ti] else int(fin[ti] > cur[ti])
            yc, ye = val(cur, 'yellow_cards', t), (val(end, 'yellow_cards', t) if end is not None else None)
            x['card'] = None if yc is None or ye is None or ye < yc else int(ye > yc)
            yf = val(m['FT'], 'yellow_cards', t) if m['FT'] is not None else None
            x['card_ft'] = None if yc is None or yf is None or yf < yc else int(yf > yc)
            # cumulés
            for k in ('shots', 'sot', 'shots_inside', 'da', 'corners', 'fouls', 'tackles', 'possession', 'yellow_cards', 'red_cards'):
                x[k] = val(cur, k, t); x['o_' + k] = val(cur, k, o)
            # fenêtres recalculées sur les cumuls
            for k in ('shots', 'sot', 'shots_inside', 'da', 'corners', 'fouls', 'tackles'):
                for w in (5, 10):
                    x[f'{k}{w}'] = delta(m, minute, w, k, t); x[f'o_{k}{w}'] = delta(m, minute, w, k, o)
            for k in ('shots10', 'sot10', 'shots5'):
                x['p_' + k] = ind(cur, k, t)
            x['since_card'] = since_card(m, minute, t)
            x['odds'] = cur[3 + (t == 'a')]
            # règles actuelles du moteur (partie « direct » seulement)
            s10, t10, s5 = x['shots10'], x['sot10'], x['shots5']
            x['rule_goal'] = None if None in (s10, t10, s5) else int(((s10 >= 3 and t10 >= 1) or t10 >= 2) and s5 >= 1)
            ps10, pt10, ps5 = x['p_shots10'], x['p_sot10'], x['p_shots5']
            x['rule_goal_p'] = None if None in (ps10, pt10, ps5) else int(((ps10 >= 3 and pt10 >= 1) or pt10 >= 2) and ps5 >= 1)
            f10, f5, po, os10 = x['fouls10'], x['fouls5'], x['possession'], x['o_shots10']
            if None in (f10, f5) or (po is None and os10 is None): x['rule_card'] = None
            else:
                sup = (po is not None and po <= 45) or (os10 is not None and os10 >= 2)
                x['rule_card'] = int((f5 >= 3 or (f10 >= 3 and f5 >= 1)) and sup)
            rows.append(x)

for m in M.values(): m['live'] = None     # libère la mémoire : la suite n'utilise que les situations

def rate(rs, key):
    v = [r[key] for r in rs if r[key] is not None]
    return (sum(v), len(v))
def show(rs, key): k, n = rate(rs, key); return f'{pct(k, n)} (n={n})'
def state(g): return 'mené' if g < 0 else 'égalité' if g == 0 else 'mène'

print('\n═══ 2 · TAUX DE BASE PAR ÉQUIPE (une situation par équipe et par minute) ═══')
print('  1re mi-temps : « marque avant la pause » · « reçoit un jaune avant la pause »')
print('  2e  mi-temps : « marque encore avant la fin » · « reçoit encore un jaune avant la fin »')
print(f"  {'minute':7}{'BUT tous':>16}{'mené':>15}{'égalité':>15}{'mène':>15}   |{'JAUNE tous':>16}{'mené':>15}{'égalité':>15}{'mène':>15}")
base = {}
for minute in G1 + G2:
    rs = [r for r in rows if r['m'] == minute]
    line = f'  {minute:<7}'
    for key in ('goal', 'card'):
        line += f'{show(rs, key):>16}'
        for s in ('mené', 'égalité', 'mène'):
            sub = [r for r in rs if state(r['gap']) == s]
            line += f'{show(sub, key):>15}'; base[(key, minute, s)] = rate(sub, key)
        base[(key, minute, 'tous')] = rate(rs, key)
        if key == 'goal': line += '   |'
    print(line)
print('  jaune avant la FIN DU MATCH, vu depuis la 1re mi-temps (cible actuelle « cartons FT ») :')
print('   ' + ' · '.join(f"{minute}′ {show([r for r in rows if r['m'] == minute], 'card_ft')}" for minute in G1))
print('  jaune selon le nombre de jaunes déjà reçus par l’équipe :')
for minute in (25, 35, 65, 75):
    rs = [r for r in rows if r['m'] == minute]
    print(f"   {minute}′  " + ' · '.join(f"{lab} : {show([r for r in rs if r['yellow_cards'] is not None and f(r['yellow_cards'])], 'card')}"
                                        for lab, f in (('0 jaune', lambda v: v == 0), ('1 jaune', lambda v: v == 1), ('2 et +', lambda v: v >= 2))))

print('  jaune SUPPLÉMENTAIRE avant la fin du match, vu en 1re mi-temps, selon les jaunes déjà reçus :')
for minute in (25, 35, 40):
    rs = [r for r in rows if r['m'] == minute]
    print(f"   {minute}′  " + ' · '.join(f"{lab} : {show([r for r in rs if r['yellow_cards'] is not None and f(r['yellow_cards'])], 'card_ft')}"
                                        for lab, f in (('0 jaune', lambda v: v == 0), ('1 jaune', lambda v: v == 1), ('2 et +', lambda v: v >= 2))))
# ───────────────────────── apport de chaque statistique ─────────────────────────
def lift(rs, key, label, feat, bins):
    parts = []
    for lab, f in bins:
        sub = [r for r in rs if r.get(feat) is not None and f(r[feat])]
        k, n = rate(sub, key)
        if n: parts.append(f'{lab} → {pct(k, n).strip()} (n={n})')
    print(f'   {label:34} ' + ' | '.join(parts))
def B(*edges):
    out = []
    for i, e in enumerate(edges):
        if i + 1 < len(edges):
            hi = edges[i + 1]
            out.append((f'{e}' if hi == e + 1 else f'{e}–{hi - 1}', (lambda lo, hi: lambda v: lo <= v < hi)(e, hi)))
        else: out.append((f'{e}+', (lambda lo: lambda v: v >= lo)(e)))
    return out
YN = [('non', lambda v: v == 0), ('OUI', lambda v: v == 1)]
ST = [('mené', lambda v: v < 0), ('égalité', lambda v: v == 0), ('mène', lambda v: v > 0)]

print('\n═══ 3 · CE QUE CHAQUE STATISTIQUE LIVE AJOUTE ═══')
for minute, title in ((25, 'BUT avant la pause, vu à la 25′'), (35, 'BUT avant la pause, vu à la 35′'), (65, 'BUT avant la fin, vu à la 65′')):
    rs = [r for r in rows if r['m'] == minute]
    print(f"  ── {title} · taux de base {show(rs, 'goal')}")
    lift(rs, 'goal', 'règle actuelle (cumuls)', 'rule_goal', YN)
    lift(rs, 'goal', 'règle actuelle (fenêtres Packball)', 'rule_goal_p', YN)
    lift(rs, 'goal', 'tirs / 10 min', 'shots10', B(0, 1, 2, 3, 4))
    lift(rs, 'goal', 'cadrés / 10 min', 'sot10', B(0, 1, 2))
    lift(rs, 'goal', 'tirs surface / 10 min', 'shots_inside10', B(0, 1, 2, 3))
    lift(rs, 'goal', 'attaques dangereuses / 10 min', 'da10', B(0, 5, 10, 15, 20))
    lift(rs, 'goal', 'corners / 10 min', 'corners10', B(0, 1, 2))
    lift(rs, 'goal', 'cadrés cumulés', 'sot', B(0, 1, 2, 3, 5))
    lift(rs, 'goal', 'tirs surface cumulés', 'shots_inside', B(0, 2, 4, 6, 9))
    lift(rs, 'goal', 'att. dangereuses cumulées', 'da', B(0, 15, 30, 45, 60))
    lift(rs, 'goal', 'score', 'gap', ST)
for minute, title in ((25, 'JAUNE avant la pause, vu à la 25′'), (35, 'JAUNE avant la pause, vu à la 35′'), (65, 'JAUNE supplémentaire avant la fin, vu à la 65′')):
    rs = [r for r in rows if r['m'] == minute]
    print(f"  ── {title} · taux de base {show(rs, 'card')}")
    lift(rs, 'card', 'règle actuelle', 'rule_card', YN)
    lift(rs, 'card', 'fautes équipe / 10 min', 'fouls10', B(0, 1, 2, 3, 4, 5))
    lift(rs, 'card', 'fautes équipe / 5 min', 'fouls5', B(0, 1, 2, 3))
    lift(rs, 'card', 'fautes équipe cumulées', 'fouls', B(0, 3, 5, 7, 9, 12))
    lift(rs, 'card', 'fautes depuis son dernier jaune', 'since_card', B(0, 3, 5, 7, 9, 12))
    lift(rs, 'card', 'fautes adverses cumulées', 'o_fouls', B(0, 3, 5, 7, 9, 12))
    lift(rs, 'card', 'jaunes déjà reçus (équipe)', 'yellow_cards', B(0, 1, 2))
    lift(rs, 'card', 'jaunes déjà reçus (adversaire)', 'o_yellow_cards', B(0, 1, 2))
    lift(rs, 'card', 'tacles équipe / 10 min', 'tackles10', B(0, 2, 4, 6))
    lift(rs, 'card', 'att. dangereuses adverses / 10', 'o_da10', B(0, 5, 10, 15, 20))
    lift(rs, 'card', 'possession', 'possession', [('<40', lambda v: v < 40), ('40–49', lambda v: 40 <= v < 50), ('50–59', lambda v: 50 <= v < 60), ('60+', lambda v: v >= 60)])
    lift(rs, 'card', 'score', 'gap', ST)

# ───────────────────────── modèle simple, test chronologique ─────────────────────────
def solve(A, b):
    n = len(b); A = [row[:] + [b[i]] for i, row in enumerate(A)]
    for i in range(n):
        p = max(range(i, n), key=lambda r: abs(A[r][i])); A[i], A[p] = A[p], A[i]
        if abs(A[i][i]) < 1e-12: continue
        for r in range(i + 1, n):
            f = A[r][i] / A[i][i]
            if f:
                for k in range(i, n + 1): A[r][k] -= f * A[i][k]
    x = [0.0] * n
    for i in range(n - 1, -1, -1):
        s = A[i][n] - sum(A[i][k] * x[k] for k in range(i + 1, n))
        x[i] = s / A[i][i] if abs(A[i][i]) > 1e-12 else 0.0
    return x
def sig(z): return 1 / (1 + math.exp(-max(-30, min(30, z))))
def fit(X, y, l2=1.0, iters=12):
    k = len(X[0]); w = [0.0] * k
    for _ in range(iters):
        g = [0.0] * k; H = [[0.0] * k for _ in range(k)]
        for xi, yi in zip(X, y):
            p = sig(sum(a * b for a, b in zip(w, xi))); d = p - yi; s = p * (1 - p)
            for i in range(k):
                g[i] += d * xi[i]; xs = s * xi[i]; Hi = H[i]
                for j in range(i, k): Hi[j] += xs * xi[j]
        for i in range(k):
            for j in range(i): H[i][j] = H[j][i]
            if i: H[i][i] += l2; g[i] += l2 * w[i]
        step = solve(H, g); w = [a - b for a, b in zip(w, step)]
        if max(abs(s) for s in step) < 1e-5: break
    return w
def auc(p, y):
    z = sorted(zip(p, y)); n1 = sum(y); n0 = len(y) - n1
    if not n1 or not n0: return float('nan')
    r = 0.0; i = 0
    while i < len(z):
        j = i
        while j < len(z) and z[j][0] == z[i][0]: j += 1
        r += sum(v for _, v in z[i:j]) * ((i + j + 1) / 2); i = j
    return (r - n1 * (n1 + 1) / 2) / (n1 * n0)

def model(title, key, minutes, feats, rule):
    rs = [r for r in rows if r['m'] in minutes and r[key] is not None and all(f(r) is not None for _, f in feats)]
    if len(rs) < 600: print(f'  ── {title} : trop peu de situations complètes ({len(rs)})'); return
    cut = sorted({r['rank'] for r in rs}); cut = cut[int(len(cut) * .7)]
    tr = [r for r in rs if r['rank'] < cut]; te = [r for r in rs if r['rank'] >= cut]
    mu = [mean([f(r) for r in tr]) for _, f in feats]
    sd = [max(1e-9, math.sqrt(mean([(f(r) - mu[i]) ** 2 for r in tr]))) for i, (_, f) in enumerate(feats)]
    def vec(r): return [1.0] + [(f(r) - mu[i]) / sd[i] for i, (_, f) in enumerate(feats)]
    w = fit([vec(r) for r in tr], [r[key] for r in tr])
    p = [sig(sum(a * b for a, b in zip(w, vec(r)))) for r in te]; y = [r[key] for r in te]
    b0 = mean([r[key] for r in tr]); brier = mean([(a - b) ** 2 for a, b in zip(p, y)]); brier0 = mean([(b0 - b) ** 2 for b in y])
    print(f'  ── {title}')
    print(f"     apprentissage : {len(tr)} situations / {len({r['mid'] for r in tr})} matchs · test (matchs les plus récents) : {len(te)} situations / {len({r['mid'] for r in te})} matchs")
    print(f'     taux de base en test {mean(y):.0%} · AUC {auc(p, y):.3f} (0,5 = hasard) · Brier {brier:.4f} contre {brier0:.4f} sans modèle')
    z = sorted(zip(p, y, te), key=lambda t: -t[0]); n = len(z)
    for lab, a, b in (('top 10 %', 0, .1), ('top 20 %', 0, .2), ('20–40 %', .2, .4), ('40–60 %', .4, .6), ('60–80 %', .6, .8), ('bas 20 %', .8, 1)):
        sl = z[int(n * a):int(n * b)]
        if sl: print(f"       {lab:9} annoncé {mean([s[0] for s in sl]):4.0%} · réalisé {mean([s[1] for s in sl]):4.0%} · n={len(sl)} · {len({s[2]['mid'] for s in sl})} matchs")
    sub = [r for r in te if r.get(rule) == 1]
    if sub: print(f"       règle actuelle vraie, mêmes matchs de test : réalisé {show(sub, key)}")
    ww = sorted(zip([n for n, _ in feats], w[1:]), key=lambda t: -abs(t[1]))
    print('     poids (variables centrées-réduites) : ' + ' · '.join(f'{n} {v:+.2f}' for n, v in ww))

def per_min(k): return lambda r: None if r[k] is None else r[k] / r['m']
def raw(k): return lambda r: r[k]
gf = [('minute', raw('m')), ('mené', lambda r: float(r['gap'] < 0)), ('mène', lambda r: float(r['gap'] > 0)),
      ('tirs/min cumul', per_min('shots')), ('cadrés/min cumul', per_min('sot')), ('surface/min cumul', per_min('shots_inside')),
      ('att.dang./min cumul', per_min('da')), ('corners/min cumul', per_min('corners')),
      ('tirs 10′', raw('shots10')), ('cadrés 10′', raw('sot10')), ('surface 10′', raw('shots_inside10')), ('att.dang. 10′', raw('da10')),
      ('att.dang. adverses/min', per_min('o_da'))]
cf = [('minute', raw('m')), ('mené', lambda r: float(r['gap'] < 0)), ('mène', lambda r: float(r['gap'] > 0)),
      ('fautes/min cumul', per_min('fouls')), ('fautes 10′', raw('fouls10')), ('fautes adverses/min', per_min('o_fouls')),
      ('jaunes équipe', raw('yellow_cards')), ('jaunes adversaire', raw('o_yellow_cards')),
      ('fautes depuis jaune', raw('since_card')), ('att.dang. adverses 10′', raw('o_da10')), ('possession', raw('possession'))]
print('\n═══ 4 · MODÈLE SIMPLE, TESTÉ SUR LES MATCHS LES PLUS RÉCENTS ═══')
print('  (régression logistique sur les seules statistiques du direct ; 70 % des matchs les plus anciens pour apprendre)')
model('BUT de l’équipe avant la pause (situations 20′ à 40′)', 'goal', (20, 25, 30, 35, 40), gf, 'rule_goal')
model('BUT de l’équipe avant la fin (situations 57′ à 75′)', 'goal', G2, gf, 'rule_goal')
model('JAUNE de l’équipe avant la pause (situations 20′ à 40′)', 'card', (20, 25, 30, 35, 40), cf, 'rule_card')
model('JAUNE supplémentaire avant la fin (situations 57′ à 75′)', 'card', G2, cf, 'rule_card')
model('JAUNE avant la fin du match, vu en 1re mi-temps (20′ à 40′)', 'card_ft', (20, 25, 30, 35, 40), cf, 'rule_card')

# ───────────────────────── avant-match ─────────────────────────
print('\n═══ 5 · RÉFÉRENCES D’AVANT-MATCH (une ligne par équipe et par match) ═══')
fx = {}
if 'v4_fixtures' in tables:
    for fixture, mid in c.execute('select fixture,match_id from v4_fixtures where match_id is not null'): fx[fixture] = str(mid)
prof = {}; ctxs = {}
if 'v4_profiles' in tables:
    for fixture, data in c.execute('select fixture,data from v4_profiles'):
        if fixture in fx:
            try: prof[fx[fixture]] = json.loads(data)
            except Exception: pass
if 'v4_contexts' in tables:
    for fixture, data in c.execute('select fixture,data from v4_contexts'):
        if fixture in fx:
            try: ctxs[fx[fixture]] = json.loads(data)
            except Exception: pass
print(f'  matchs rattachés : {len(fx)} · avec profil Packball {len(prof)} · avec dossier analyste {len(ctxs)}')
ko = []
for mid, m in M.items():
    ht, ft = m['HT'], m['FT']
    if ht is None and ft is None: continue
    p = prof.get(mid) or {}; cx = ctxs.get(mid) or {}
    pre = p.get('prematch') or {}; pk = ((p.get('packball') or {}).get('teams') or {})
    ref = ((cx.get('referee') or {}).get('stats') or {})
    for t in 'ha':
        o = 'a' if t == 'h' else 'h'; ti = 1 + (t == 'a')
        x = {'mid': mid, 'league': m['league'], 'has_p': bool(p), 'has_c': bool(cx)}
        x['g1'] = None if ht is None or ht[ti] is None else int(ht[ti] > 0)
        x['g2'] = None if ht is None or ft is None or ht[ti] is None or ft[ti] is None or ft[ti] < ht[ti] else int(ft[ti] > ht[ti])
        yh = val(ht, 'yellow_cards', t) if ht is not None else None; yf = val(ft, 'yellow_cards', t) if ft is not None else None
        x['y1'] = None if yh is None else int(yh > 0)
        x['y2'] = None if yh is None or yf is None or yf < yh else int(yf > yh)
        x['yft'] = yf; x['yht'] = yh
        x['gf'] = pre.get('gf_' + t); x['ga_o'] = pre.get('ga_' + o)
        T = pk.get(t) or {}; O = pk.get(o) or {}
        x['pk_g1'] = T.get('goals_for_ht'); x['pk_c1_o'] = O.get('goals_against_ht')
        x['pk_g2'] = T.get('goals_for_2h'); x['pk_c2_o'] = O.get('goals_against_2h')
        x['pk_y'] = T.get('yellow_for_ft')
        scope = 'home' if t == 'h' else 'away'; oscope = 'away' if t == 'h' else 'home'
        gh = (((cx.get('teams') or {}).get(t) or {}).get('goal_halves') or {}).get(scope) or {}
        go = (((cx.get('teams') or {}).get(o) or {}).get('goal_halves') or {}).get(oscope) or {}
        x['a_g1'] = (gh.get('first_half') or {}).get('scored_per_match'); x['a_c1_o'] = (go.get('first_half') or {}).get('conceded_per_match')
        x['a_g2'] = (gh.get('second_half') or {}).get('scored_per_match'); x['a_c2_o'] = (go.get('second_half') or {}).get('conceded_per_match')
        cr = (((cx.get('teams') or {}).get(t) or {}).get('card_references') or {}).get(scope) or {}
        x['a_y'] = cr.get('for_per_match') if cr.get('unit') == 'yellow_cards' else None
        x['a_f'] = cr.get('fouls_for_per_match')
        x['ref_y'] = ref.get('yellow_per_match'); x['ref_f'] = ref.get('fouls_per_match')
        x['gate1'] = None if not num(x['a_g1']) or not num(x['a_c1_o']) else int(x['a_g1'] >= .8 and x['a_c1_o'] >= .8)
        x['gate2'] = None if not num(x['a_g2']) or not num(x['a_c2_o']) else int(x['a_g2'] >= .8 and x['a_c2_o'] >= .8)
        x['sum1'] = x['a_g1'] + x['a_c1_o'] if num(x['a_g1']) and num(x['a_c1_o']) else None
        x['sum2'] = x['a_g2'] + x['a_c2_o'] if num(x['a_g2']) and num(x['a_c2_o']) else None
        x['pksum1'] = x['pk_g1'] + x['pk_c1_o'] if num(x['pk_g1']) and num(x['pk_c1_o']) else None
        x['gsum'] = x['gf'] + x['ga_o'] if num(x['gf']) and num(x['ga_o']) else None
        for k in ('a_y', 'pk_y', 'ref_y', 'ref_f', 'a_f'):
            if not num(x[k]): x[k] = None
        ko.append(x)
print(f"  tous matchs : l’équipe marque en 1re MT {show(ko, 'g1')} · en 2e MT {show(ko, 'g2')} · jaune en 1re MT {show(ko, 'y1')} · jaune en 2e MT {show(ko, 'y2')}")
FB = lambda *e: [(f'<{e[0]}', (lambda hi: lambda v: v < hi)(e[0]))] + [(f'{e[i]}–{e[i + 1]}', (lambda lo, hi: lambda v: lo <= v < hi)(e[i], e[i + 1])) for i in range(len(e) - 1)] + [(f'≥{e[-1]}', (lambda lo: lambda v: v >= lo)(e[-1]))]
lift(ko, 'g1', 'seuil 0,8 / 0,8 (1re MT, analyste)', 'gate1', YN)
lift(ko, 'g2', 'seuil 0,8 / 0,8 (2e MT, analyste)', 'gate2', YN)
lift(ko, 'g1', 'marqués + encaissés adv. 1re MT', 'sum1', FB(1.0, 1.4, 1.8, 2.2))
lift(ko, 'g2', 'marqués + encaissés adv. 2e MT', 'sum2', FB(1.2, 1.6, 2.0, 2.6))
lift(ko, 'g1', 'idem 1re MT (profil Packball)', 'pksum1', FB(1.0, 1.4, 1.8, 2.2))
lift(ko, 'g1', 'marqués + encaissés adv. FT → 1re MT', 'gsum', FB(2.0, 2.6, 3.2, 3.8))
lift(ko, 'g2', 'marqués + encaissés adv. FT → 2e MT', 'gsum', FB(2.0, 2.6, 3.2, 3.8))
lift(ko, 'y1', 'jaunes/m équipe (analyste) → 1re MT', 'a_y', FB(1.5, 2.0, 2.5, 3.0))
lift(ko, 'y2', 'jaunes/m équipe (analyste) → 2e MT', 'a_y', FB(1.5, 2.0, 2.5, 3.0))
lift(ko, 'y1', 'jaunes/m équipe (Packball) → 1re MT', 'pk_y', FB(1.5, 2.0, 2.5, 3.0))
lift(ko, 'y2', 'jaunes/m équipe (Packball) → 2e MT', 'pk_y', FB(1.5, 2.0, 2.5, 3.0))
lift(ko, 'y1', 'jaunes/m arbitre → 1re MT', 'ref_y', FB(3.5, 4.5, 5.5))
lift(ko, 'y2', 'jaunes/m arbitre → 2e MT', 'ref_y', FB(3.5, 4.5, 5.5))
lift(ko, 'y1', 'fautes/m équipe (analyste) → 1re MT', 'a_f', FB(10, 12, 14))
print('  jaunes par ligue (ligues les plus représentées) : jaune en 1re MT · en 2e MT · moyenne FT par équipe')
lg = collections.defaultdict(list)
for x in ko: lg[x['league']].append(x)
for name, xs in sorted(lg.items(), key=lambda t: -len(t[1]))[:22]:
    if sum(x['y1'] is not None for x in xs) < 40: continue
    print(f"    {name[:44]:44} {show(xs, 'y1'):>14} · {show(xs, 'y2'):>14} · {mean([x['yft'] for x in xs]):.2f}")

# ───────────────────────── alertes et lectures sans alerte ─────────────────────────
print('\n═══ 6 · ALERTES ENVOYÉES, CONTRE LE TAUX DE BASE ═══')
print('  notifications enregistrées par état d’envoi :', dict(collections.Counter(a['delivery'] for a in alerts)))
sent = [a for a in alerts if a['delivery'] == 'sent']
print('  alertes envoyées par semaine :', dict(sorted(collections.Counter(week(a['created']) for a in sent).items())))
def nearest(minute):
    grid = G1 if minute <= 45 else G2
    g = min(grid, key=lambda v: abs(v - minute))
    return g if abs(g - minute) <= 6 else None
seen = set(); tab = collections.defaultdict(lambda: collections.Counter())
agree = collections.Counter(); mins = collections.defaultdict(list)
def base_rate(key, g, st, y=None):
    rs = [r for r in rows if r['m'] == g and state(r['gap']) == st and (y is None or (r['yellow_cards'] is not None and min(r['yellow_cards'], 2) == y))]
    return rate(rs, key)
bcache = {}
for a in sent:
    m = M.get(a['mid']); sp = sid_rec.get(a['sid'])
    minute = sp[1][0] if sp else None
    half = '?' if not num(minute) else '1re MT' if minute <= 45 else '2e MT'
    mk = (a['market'], a['period'], half)
    tab[mk]['toutes'] += 1; tab[mk][a['outcome']] += 1
    if num(minute): mins[mk].append(minute)
    unit = (a['mid'], a['team'], a['market'], a['period'])
    first = unit not in seen; seen.add(unit)
    mine = None; exp = None
    if m and sp and num(a['line']):
        st, p = sp; ti = 1 + (a['team'] == 'a'); oi = 3 - ti
        end = m['HT'] if a['period'] == 'HT' else m['FT']
        if end is not None:
            v = end[ti] if a['market'] == 'goals' else val(end, 'yellow_cards', a['team'])
            if v is not None: mine = int(v > a['line'])
        if num(minute) and p[1] is not None and p[2] is not None and a['market'] in ('goals', 'cards'):
            g = nearest(minute)
            if a['market'] == 'goals': key = 'goal' if (a['period'] == 'HT' or minute > 45) else 'goal_ft'; y = None
            else:
                key = 'card' if minute > 45 else 'card_ft'; y = val(p, 'yellow_cards', a['team']); y = None if y is None else int(min(y, 2))
            if g is not None:
                ck = (key, g, state(p[ti] - p[oi]), y)
                if ck not in bcache: bcache[ck] = base_rate(*ck)
                k, n = bcache[ck]
                if n >= 30: exp = k / n
    if a['outcome'] in ('observed', 'not_observed') and mine is not None:
        agree['accord' if (a['outcome'] == 'observed') == bool(mine) else 'désaccord'] += 1
    if first:
        tab[mk]['1re alerte'] += 1
        if mine is not None:
            tab[mk]['1re réglée'] += 1; tab[mk]['1re réussie'] += mine
            if exp is not None: tab[mk]['1re avec base'] += 1; tab[mk]['1re réussie (avec base)'] += mine; tab[mk]['base'] += exp
print('  (taux de base apparié : même cible, même minute à ±6, même état du score ; pour les cartons, même nombre de jaunes déjà reçus)')
for mk, t in sorted(tab.items()):
    md = sorted(mins[mk])[len(mins[mk]) // 2] if mins[mk] else '?'
    print(f"  {mk[0]:7} cible {mk[1]:2} · signal en {mk[2]:6} : {t['toutes']} envoyées (minute médiane {md}) · verdict moteur observé {t['observed']} / non observé {t['not_observed']} / autre {t['toutes'] - t['observed'] - t['not_observed']}")
    if t['1re réglée']:
        print(f"             première alerte par match-équipe : {t['1re alerte']} · réglées ici {t['1re réglée']} · réussies {pct(t['1re réussie'], t['1re réglée']).strip()}")
    if t['1re avec base']:
        print(f"             comparaison appariée : alertes {pct(t['1re réussie (avec base)'], t['1re avec base']).strip()} contre taux de base {t['base'] / t['1re avec base']:.0%} · n={t['1re avec base']}")
print('  contrôle de mon règlement contre celui du moteur :', dict(agree))

if 'v5_shadow_readings' in tables:
    print('  lectures internes sans alerte (v5_shadow_readings), une par match-équipe-marché-minute :')
    sh = collections.defaultdict(lambda: collections.Counter()); bk = collections.defaultdict(lambda: {'c': {}, 'n': {}})
    for mid, team, market, minute, policy, data in c.execute('select match_id,team,market,minute,policy,data from v5_shadow_readings order by match_id,team,market,minute'):
        try: d = json.loads(data)
        except Exception: continue
        m = M.get(str(mid))
        if not m: continue
        period = d.get('target_period') or 'FT'; line = d.get('target_line')
        end = m['HT'] if period == 'HT' else m['FT']
        if end is None or not num(line): continue
        v = end[1 + (team == 'a')] if market == 'goals' else val(end, 'yellow_cards', team)
        if v is None: continue
        win = int(v > line); rsch = d.get('research') or {}
        bc = rsch.get('baseline_candidate'); cd = rsch.get('candidate')
        lab = 'candidat' if bc else 'non candidat'
        sh[(market, period)][lab + ' n'] += 1; sh[(market, period)][lab + ' ok'] += win
        if market == 'goals' and bc:
            l2 = 'hypothèse qualité vraie' if cd else 'hypothèse qualité fausse' if cd is False else 'hypothèse non évaluée'
            sh[(market, period)][l2 + ' n'] += 1; sh[(market, period)][l2 + ' ok'] += win
        cell = bk[(market, period)]['c' if bc else 'n'].setdefault(int(minute) // 5, [0, 0]); cell[0] += 1; cell[1] += win
    for mk, t in sorted(sh.items()):
        parts = [f"{lab} {pct(t[lab + ' ok'], t[lab + ' n']).strip()} (n={t[lab + ' n']})" for lab in ('candidat', 'non candidat', 'hypothèse qualité vraie', 'hypothèse qualité fausse') if t[lab + ' n']]
        print(f'    {mk[0]:6} {mk[1]:3} : ' + ' · '.join(parts))
    print('    à minute égale (tranches de 5 min) : candidats contre non-candidats de la même tranche')
    for mk in sorted(sh):
        tot = k = 0; expd = 0.0
        for b, (cn, cw) in sorted(bk[mk]['c'].items()):
            nn, nw = bk[mk]['n'].get(b, (0, 0))
            if nn >= 30: tot += cn; k += cw; expd += cn * nw / nn
        if tot: print(f'    {mk[0]:6} {mk[1]:3} : candidats {pct(k, tot).strip()} contre {expd / tot:.0%} attendus sans signal · n={tot} lectures')

# ───────────────────────── cotes archivées ─────────────────────────
print('\n═══ 7 · COTES « BUTS DE L’ÉQUIPE » ARCHIVÉES (ligne = buts déjà marqués + 0,5, match entier) ═══')
OB = [('<1,20', lambda v: v < 1.2), ('1,20–1,34', lambda v: 1.2 <= v < 1.35), ('1,35–1,49', lambda v: 1.35 <= v < 1.5), ('1,50–1,74', lambda v: 1.5 <= v < 1.75),
      ('1,75–2,09', lambda v: 1.75 <= v < 2.1), ('2,10–2,99', lambda v: 2.1 <= v < 3), ('3,00+', lambda v: v >= 3)]
ftrows = [{'m': r['m'], 'odds': r['odds'], 'win': r['goal_ft'], 'imp': 1 / r['odds']} for r in rows if r['m'] in (25, 35, 60, 70) and r['odds'] is not None and r['goal_ft'] is not None]
for minute in (25, 35, 60, 70):
    rs = [r for r in ftrows if r['m'] == minute]
    if not rs: continue
    print(f"  vu à la {minute}′ · {len(rs)} situations avec cote · réussite {pct(sum(r['win'] for r in rs), len(rs)).strip()} · probabilité implicite moyenne (1/cote, marge incluse) {mean([r['imp'] for r in rs]):.0%}")
    for lab, f in OB:
        sub = [r for r in rs if f(r['odds'])]
        if len(sub) >= 15: print(f"      cote {lab:10} n={len(sub):4} · réussite {pct(sum(r['win'] for r in sub), len(sub)).strip()} · implicite {mean([r['imp'] for r in sub]):.0%} · gain moyen par unité misée {mean([r['win'] * r['odds'] - 1 for r in sub]):+.1%}")

# ───────────────────────── avant-match + direct, test chronologique ─────────────────────────
print('\n═══ 8 · AVANT-MATCH SEUL, PUIS AVANT-MATCH + DIRECT (test sur les matchs les plus récents) ═══')
by_mid = collections.defaultdict(dict)
seen_mid = collections.Counter()
for x in ko:
    x['t'] = 'h' if seen_mid[x['mid']] == 0 else 'a'; seen_mid[x['mid']] += 1
    by_mid[x['mid']][x['t']] = x; x['rank'] = rank[x['mid']]; x['m'] = 0; x['home'] = float(x['t'] == 'h')
# propension de la ligue, calculée uniquement sur les matchs ANTÉRIEURS (aucune fuite du futur)
acc = collections.defaultdict(lambda: [0, 0, 0, 0, 0.0, 0])
for mid in order:
    pair_ = by_mid.get(mid)
    if not pair_: continue
    lgk = next(iter(pair_.values()))['league']; a = acc[lgk]
    for x in pair_.values():
        x['lg_y1'] = (a[1] + 20 * .5) / (a[0] + 20); x['lg_y2'] = (a[3] + 20 * .7) / (a[2] + 20); x['lg_yft'] = (a[4] + 20 * 2.0) / (a[5] + 20)
    for x in pair_.values():
        if x['y1'] is not None: a[0] += 1; a[1] += x['y1']
        if x['y2'] is not None: a[2] += 1; a[3] += x['y2']
        if x['yft'] is not None: a[5] += 1; a[4] += x['yft']
for x in ko:
    o = by_mid[x['mid']].get('a' if x['t'] == 'h' else 'h') or {}
    x['pk_y_o'] = o.get('pk_y')
for r in rows:
    x = by_mid.get(r['mid'], {}).get(r['t'])
    for k in ('lg_y1', 'lg_y2', 'lg_yft', 'pk_y', 'pk_y_o', 'gsum', 'pksum1', 'a_f', 'ref_y'):
        r[k] = x.get(k) if x else None
        if r[k] is not None and not num(r[k]): r[k] = None

def model2(title, key, rs_all, feats, rule=None):
    rs = [r for r in rs_all if r[key] is not None and all(f(r) is not None for _, f in feats)]
    if len(rs) < 500: print(f'  ── {title} : trop peu de lignes complètes ({len(rs)})'); return
    cut = sorted({r['rank'] for r in rs}); cut = cut[int(len(cut) * .7)]
    tr = [r for r in rs if r['rank'] < cut]; te = [r for r in rs if r['rank'] >= cut]
    mu = [mean([f(r) for r in tr]) for _, f in feats]
    sd = [max(1e-9, math.sqrt(mean([(f(r) - mu[i]) ** 2 for r in tr]))) for i, (_, f) in enumerate(feats)]
    def vec(r): return [1.0] + [(f(r) - mu[i]) / sd[i] for i, (_, f) in enumerate(feats)]
    w = fit([vec(r) for r in tr], [r[key] for r in tr])
    p = [sig(sum(a * b for a, b in zip(w, vec(r)))) for r in te]; y = [r[key] for r in te]
    print(f'  ── {title}')
    print(f"     apprentissage {len(tr)} lignes / {len({r['mid'] for r in tr})} matchs · test {len(te)} lignes / {len({r['mid'] for r in te})} matchs · taux de base en test {mean(y):.0%} · AUC {auc(p, y):.3f}")
    z = sorted(zip(p, y, te), key=lambda t: -t[0]); n = len(z)
    print('       ' + ' | '.join(f"{lab} : annoncé {mean([s_[0] for s_ in z[int(n * a):int(n * b)]]):.0%}, réalisé {mean([s_[1] for s_ in z[int(n * a):int(n * b)]]):.0%} (n={int(n * b) - int(n * a)}, {len({s_[2]['mid'] for s_ in z[int(n * a):int(n * b)]})} matchs)"
                                 for lab, a, b in (('top 10 %', 0, .1), ('top 20 %', 0, .2), ('milieu', .4, .6), ('bas 20 %', .8, 1))))
    if rule:
        sub = [r for r in te if r.get(rule) == 1]
        if sub: print(f'       règle actuelle vraie, mêmes matchs : réalisé {show(sub, key)}')
    print('     poids : ' + ' · '.join(f'{n_} {v:+.2f}' for n_, v in sorted(zip([n_ for n_, _ in feats], w[1:]), key=lambda t: -abs(t[1]))))

P = lambda k: (lambda r: r[k])
print('  A. Décision au COUP D’ENVOI (aucune statistique du direct)')
model2('JAUNE de l’équipe en 1re mi-temps · ligue + moyenne de jaunes des deux équipes (Packball)', 'y1', ko,
       [('ligue (passé)', P('lg_y1')), ('jaunes/m équipe', P('pk_y')), ('jaunes/m adversaire', P('pk_y_o')), ('domicile', P('home'))])
model2('JAUNE de l’équipe en 1re mi-temps · + fautes/m de l’équipe et jaunes/m de l’arbitre (dossier analyste)', 'y1', ko,
       [('ligue (passé)', P('lg_y1')), ('jaunes/m équipe', P('pk_y')), ('fautes/m équipe', P('a_f')), ('jaunes/m arbitre', P('ref_y')), ('domicile', P('home'))])
model2('BUT de l’équipe en 1re mi-temps · marqués + encaissés adverses (FT et 1re MT, Packball)', 'g1', ko,
       [('marqués+encaissés FT', P('gsum')), ('marqués+encaissés 1re MT', P('pksum1')), ('domicile', P('home'))])
model2('BUT de l’équipe en 2e mi-temps · marqués + encaissés adverses FT (Packball)', 'g2', ko,
       [('marqués+encaissés FT', P('gsum')), ('domicile', P('home'))])
print('  B. Décision PENDANT le match (direct + avant-match)')
early = [r for r in rows if r['m'] in (15, 20, 25)]
model2('JAUNE avant la pause, vu entre la 15′ et la 25′', 'card', early,
       [('minute', P('m')), ('mené', lambda r: float(r['gap'] < 0)), ('fautes/min cumul', per_min('fouls')), ('fautes depuis jaune', P('since_card')),
        ('jaunes adversaire', P('o_yellow_cards')), ('jaunes équipe', P('yellow_cards')), ('ligue (passé)', P('lg_y1')), ('jaunes/m équipe', P('pk_y'))], 'rule_card')
model2('JAUNE supplémentaire avant la fin, vu entre la 57′ et la 75′', 'card', [r for r in rows if r['m'] in G2],
       [('minute', P('m')), ('mené', lambda r: float(r['gap'] < 0)), ('mène', lambda r: float(r['gap'] > 0)), ('fautes/min cumul', per_min('fouls')),
        ('jaunes équipe', P('yellow_cards')), ('jaunes adversaire', P('o_yellow_cards')), ('ligue (passé)', P('lg_y2')), ('jaunes/m équipe', P('pk_y'))], 'rule_card')
model2('BUT avant la pause, vu entre la 15′ et la 25′ (sans attaques dangereuses)', 'goal', early,
       [('minute', P('m')), ('mène', lambda r: float(r['gap'] > 0)), ('cadrés/min cumul', per_min('sot')), ('tirs/min cumul', per_min('shots')),
        ('tirs 10′', P('shots10')), ('cadrés 10′', P('sot10')), ('marqués+encaissés FT', P('gsum'))], 'rule_goal')
model2('BUT avant la fin, vu entre la 57′ et la 75′ (sans attaques dangereuses)', 'goal', [r for r in rows if r['m'] in G2],
       [('minute', P('m')), ('mène', lambda r: float(r['gap'] > 0)), ('mené', lambda r: float(r['gap'] < 0)), ('cadrés/min cumul', per_min('sot')), ('tirs/min cumul', per_min('shots')),
        ('tirs 10′', P('shots10')), ('cadrés 10′', P('sot10')), ('marqués+encaissés FT', P('gsum'))], 'rule_goal')
model2('BUT avant la fin, vu entre la 57′ et la 75′ (avec attaques dangereuses)', 'goal', [r for r in rows if r['m'] in G2],
       [('minute', P('m')), ('mène', lambda r: float(r['gap'] > 0)), ('mené', lambda r: float(r['gap'] < 0)), ('cadrés/min cumul', per_min('sot')),
        ('att.dang./min cumul', per_min('da')), ('att.dang. adverses/min', per_min('o_da')), ('tirs 10′', P('shots10')), ('marqués+encaissés FT', P('gsum'))], 'rule_goal')

# ───────────────────────── règles simples candidates ─────────────────────────
print('\n═══ 9 · RÈGLES SIMPLES CANDIDATES (seuils fixés sur les matchs anciens, vérifiés sur les récents) ═══')
print('  « haut » = au-dessus du 67e centile des matchs d’apprentissage. Une ligne par match-équipe : premier instant où la règle est vraie.')
def quant(vs, q): vs = sorted(v for v in vs if v is not None); return vs[int(len(vs) * q)] if vs else None
def rules(title, key, rs_all, hi, fixed, combos):
    rs = [r for r in rs_all if r[key] is not None]
    if len(rs) < 500: print(f'  ── {title} : trop peu de lignes'); return
    cut = sorted({r['rank'] for r in rs}); cut = cut[int(len(cut) * .7)]
    tr = [r for r in rs if r['rank'] < cut]; te = [r for r in rs if r['rank'] >= cut]
    thr = {n_: quant([f(r) for r in tr], .67) for n_, f in hi.items()}
    def ok(r, n_):
        if n_ in hi:
            v = hi[n_](r); return None if v is None or thr[n_] is None else v >= thr[n_]
        return fixed[n_](r)
    print(f"  ── {title} · taux de base : apprentissage {show(tr, key)} · test {show(te, key)}")
    print('     seuils « haut » : ' + ' · '.join(f'{n_} ≥ {thr[n_]:.2f}' for n_ in hi if thr[n_] is not None))
    for combo in [()] + list(combos):
        out = []
        for part in (tr, te):
            first = {}
            for r in sorted(part, key=lambda r: r['m']):
                u = (r['mid'], r['t'])
                if u in first: continue
                if all(ok(r, n_) is True for n_ in combo): first[u] = r[key]
            out.append((sum(first.values()), len(first)))
        (k1, n1), (k2, n2) = out
        print(f"     {(' + '.join(combo) or 'AUCUNE CONDITION (premier instant relevé) = référence'):78} apprentissage {pct(k1, n1).strip():>4} (n={n1:4}) · TEST {pct(k2, n2).strip():>4} (n={n2})")
F = lambda k: (lambda r: r[k])
cur_g = lambda r: None if r['rule_goal'] is None else bool(r['rule_goal'])
cur_c = lambda r: None if r['rule_card'] is None else bool(r['rule_card'])
second = [r for r in rows if r['m'] in G2]
rules('BUT avant la fin, 57′–75′', 'goal', second,
      {'att.dang./min haut': per_min('da'), 'tirs/min haut': per_min('shots'), 'cadrés/min haut': per_min('sot'), 'avant-match haut': F('gsum')},
      {'règle actuelle': cur_g, 'ne mène pas de 2+': lambda r: r['gap'] <= 1},
      [('règle actuelle',), ('att.dang./min haut',), ('tirs/min haut',), ('avant-match haut',), ('règle actuelle', 'avant-match haut'), ('règle actuelle', 'att.dang./min haut'),
       ('tirs/min haut', 'avant-match haut'), ('att.dang./min haut', 'avant-match haut'), ('règle actuelle', 'tirs/min haut', 'avant-match haut'), ('règle actuelle', 'att.dang./min haut', 'avant-match haut')])
rules('BUT avant la pause, 15′–25′', 'goal', early,
      {'tirs/min haut': per_min('shots'), 'cadrés/min haut': per_min('sot'), 'avant-match haut': F('gsum'), 'att.dang./min haut': per_min('da')},
      {'règle actuelle': cur_g},
      [('règle actuelle',), ('tirs/min haut',), ('avant-match haut',), ('att.dang./min haut',), ('règle actuelle', 'avant-match haut'), ('tirs/min haut', 'avant-match haut'), ('cadrés/min haut', 'avant-match haut'),
       ('règle actuelle', 'tirs/min haut', 'avant-match haut')])
rules('JAUNE avant la pause, 15′–25′', 'card', early,
      {'fautes/min haut': per_min('fouls'), 'jaunes/m équipe haut': F('pk_y'), 'ligue haute': F('lg_y1'), 'fautes depuis jaune haut': F('since_card')},
      {'règle actuelle': cur_c, 'adversaire déjà averti': lambda r: None if r['o_yellow_cards'] is None else r['o_yellow_cards'] >= 1, 'mené': lambda r: r['gap'] < 0,
       'équipe pas encore avertie': lambda r: None if r['yellow_cards'] is None else r['yellow_cards'] == 0},
      [('règle actuelle',), ('fautes/min haut',), ('jaunes/m équipe haut',), ('ligue haute',), ('adversaire déjà averti',), ('mené',), ('fautes/min haut', 'ligue haute'), ('fautes/min haut', 'jaunes/m équipe haut'),
       ('fautes/min haut', 'adversaire déjà averti'), ('ligue haute', 'jaunes/m équipe haut'), ('fautes/min haut', 'ligue haute', 'jaunes/m équipe haut'), ('fautes depuis jaune haut', 'ligue haute'),
       ('fautes/min haut', 'mené')])
rules('JAUNE supplémentaire avant la fin, 57′–75′', 'card', second,
      {'fautes/min haut': per_min('fouls'), 'jaunes/m équipe haut': F('pk_y'), 'ligue haute': F('lg_y2'), 'fautes depuis jaune haut': F('since_card')},
      {'règle actuelle': cur_c, 'au plus 1 jaune': lambda r: None if r['yellow_cards'] is None else r['yellow_cards'] <= 1, 'ne mène pas': lambda r: r['gap'] <= 0, 'égalité': lambda r: r['gap'] == 0},
      [('règle actuelle',), ('fautes/min haut',), ('jaunes/m équipe haut',), ('ligue haute',), ('au plus 1 jaune',), ('ne mène pas',), ('égalité',), ('fautes/min haut', 'au plus 1 jaune'),
       ('fautes/min haut', 'ne mène pas'), ('fautes/min haut', 'jaunes/m équipe haut'), ('fautes/min haut', 'au plus 1 jaune', 'ne mène pas'), ('fautes/min haut', 'jaunes/m équipe haut', 'au plus 1 jaune'),
       ('fautes depuis jaune haut', 'au plus 1 jaune')])
first_half_ft = [r for r in rows if r['m'] in (20, 25, 30, 35)]
rules('JAUNE avant la FIN DU MATCH, vu entre la 20′ et la 35′', 'card_ft', first_half_ft,
      {'fautes/min haut': per_min('fouls'), 'jaunes/m équipe haut': F('pk_y'), 'ligue haute': F('lg_yft')},
      {'règle actuelle': cur_c, 'équipe pas encore avertie': lambda r: None if r['yellow_cards'] is None else r['yellow_cards'] == 0, 'ne mène pas': lambda r: r['gap'] <= 0},
      [('règle actuelle',), ('fautes/min haut',), ('jaunes/m équipe haut',), ('ligue haute',), ('équipe pas encore avertie',), ('fautes/min haut', 'équipe pas encore avertie'),
       ('fautes/min haut', 'jaunes/m équipe haut'), ('fautes/min haut', 'ligue haute'), ('fautes/min haut', 'jaunes/m équipe haut', 'équipe pas encore avertie')])
print('  Décision au coup d’envoi (une ligne par équipe)')
rules('JAUNE de l’équipe en 1re mi-temps', 'y1', ko,
      {'jaunes/m équipe haut': F('pk_y'), 'ligue haute': F('lg_y1'), 'jaunes/m adversaire haut': F('pk_y_o'), 'fautes/m équipe haut': F('a_f'), 'arbitre haut': F('ref_y')},
      {'extérieur': lambda r: r['home'] == 0},
      [('jaunes/m équipe haut',), ('ligue haute',), ('fautes/m équipe haut',), ('arbitre haut',), ('extérieur',), ('ligue haute', 'jaunes/m équipe haut'), ('ligue haute', 'jaunes/m équipe haut', 'jaunes/m adversaire haut'),
       ('fautes/m équipe haut', 'arbitre haut'), ('fautes/m équipe haut', 'ligue haute'), ('ligue haute', 'jaunes/m équipe haut', 'extérieur')])
rules('BUT de l’équipe en 1re mi-temps', 'g1', ko,
      {'marqués+encaissés FT haut': F('gsum'), 'marqués+encaissés 1re MT haut': F('pksum1')}, {'domicile': lambda r: r['home'] == 1},
      [('marqués+encaissés FT haut',), ('marqués+encaissés 1re MT haut',), ('domicile',), ('marqués+encaissés FT haut', 'marqués+encaissés 1re MT haut'), ('marqués+encaissés FT haut', 'domicile'),
       ('marqués+encaissés FT haut', 'marqués+encaissés 1re MT haut', 'domicile')])
rules('BUT de l’équipe en 2e mi-temps', 'g2', ko,
      {'marqués+encaissés FT haut': F('gsum')}, {'domicile': lambda r: r['home'] == 1},
      [('marqués+encaissés FT haut',), ('domicile',), ('marqués+encaissés FT haut', 'domicile')])
