"""StratEdge Live V4 — deterministic observations, no LLM or bet placement."""
import argparse, datetime as dt, hashlib, json, math, os, re, sqlite3, sys, unicodedata
import urllib.error, urllib.request
from zoneinfo import ZoneInfo
UTC = dt.timezone.utc
VERSION = 'live4.0'
MARKETS = ('goal_ht', 'goal_ft', 'card_ft')
LABELS = {'goal_ht': 'But avant la mi-temps', 'goal_ft': 'But avant la fin du match', 'card_ft': 'Un carton supplémentaire · équipe'}
SCHEMA = '''
CREATE TABLE IF NOT EXISTS v4_imports(id INTEGER PRIMARY KEY,kind TEXT,digest TEXT UNIQUE,created_at TEXT,data TEXT);
CREATE TABLE IF NOT EXISTS v4_fixtures(fixture TEXT PRIMARY KEY,home TEXT,away TEXT,kickoff TEXT,league TEXT,match_id TEXT);
CREATE TABLE IF NOT EXISTS v4_profiles(fixture TEXT PRIMARY KEY,imported_at TEXT,data TEXT);
CREATE TABLE IF NOT EXISTS v4_contexts(fixture TEXT PRIMARY KEY,imported_at TEXT,generated_at TEXT,data TEXT);
CREATE TABLE IF NOT EXISTS v4_decisions(match_id TEXT,market TEXT,team TEXT,sample_id INTEGER,updated_at TEXT,status TEXT,data TEXT,PRIMARY KEY(match_id,market,team));
CREATE TABLE IF NOT EXISTS v4_signals(id INTEGER PRIMARY KEY,signal_key TEXT UNIQUE,match_id TEXT,fixture TEXT,market TEXT,team TEXT,line REAL,odds REAL,created_at TEXT,sample_id INTEGER,data TEXT,delivery TEXT,delivery_error TEXT,telegram_id INTEGER,outcome TEXT DEFAULT 'pending',settled_at TEXT);
CREATE TABLE IF NOT EXISTS v4_results(id INTEGER PRIMARY KEY,signal_id INTEGER,created_at TEXT,previous TEXT,outcome TEXT,source TEXT,data TEXT);
CREATE TABLE IF NOT EXISTS v4_result_notifications(result_id INTEGER PRIMARY KEY,signal_id INTEGER,created_at TEXT,message TEXT,delivery TEXT,delivery_error TEXT,telegram_id INTEGER,attempted_at TEXT);
CREATE TABLE IF NOT EXISTS v4_health_events(id INTEGER PRIMARY KEY,issue_key TEXT,opened_at TEXT,last_seen TEXT,resolved_at TEXT,data TEXT);
CREATE UNIQUE INDEX IF NOT EXISTS v4_health_open ON v4_health_events(issue_key) WHERE resolved_at IS NULL;
CREATE TABLE IF NOT EXISTS v4_settings(key TEXT PRIMARY KEY,value TEXT);
CREATE TABLE IF NOT EXISTS v4_runtime(key TEXT PRIMARY KEY,value TEXT);
CREATE TABLE IF NOT EXISTS v4_pulsescore_requests(id INTEGER PRIMARY KEY,attempted_at TEXT);
CREATE TABLE IF NOT EXISTS v4_pulsescore_auto(id INTEGER PRIMARY KEY,attempted_at TEXT);
CREATE INDEX IF NOT EXISTS v4_signal_created ON v4_signals(created_at);
'''
def now_utc(): return dt.datetime.now(UTC)
def iso(t=None): return (t or now_utc()).isoformat()
def enc(x): return json.dumps(x, ensure_ascii=False, allow_nan=False, separators=(',', ':'))
def dec(x, default=None):
    try: return json.loads(x)
    except (ValueError, TypeError): return default

def num(x): return type(x) in (int, float) and math.isfinite(x)
def date(x):
    try:
        t = dt.datetime.fromisoformat(x.replace('Z', '+00:00'))
        return t.astimezone(UTC) if t.tzinfo else None
    except (ValueError, TypeError, AttributeError): return None

def norm(x): return ' '.join(''.join(c for c in unicodedata.normalize('NFKD', str(x).casefold()) if not unicodedata.combining(c)).split())
def key(m): return hashlib.sha256(enc([norm(m['home']), norm(m['away']), iso(date(m['kickoff']))]).encode()).hexdigest()[:32]
def connect(path):
    c = sqlite3.connect(path, timeout=15); c.row_factory = sqlite3.Row
    c.execute('PRAGMA busy_timeout=15000'); c.execute('PRAGMA journal_mode=WAL'); c.executescript(SCHEMA)
    return c

def setting(c, name, default):
    row = c.execute('SELECT value FROM v4_settings WHERE key=?', (name,)).fetchone()
    return dec(row[0], default) if row else default

def options(c): return {'telegram_enabled': setting(c, 'telegram_enabled', True), 'min_odds': setting(c, 'min_odds', 1.65)}
def runtime(c, name, value): c.execute('INSERT OR REPLACE INTO v4_runtime VALUES(?,?)', (name, enc(value)))
def text_ok(v, limit=300): return isinstance(v, str) and 0 < len(v.strip()) <= limit

# One request per confirmed-signal batch, never on board refresh or ordinary observation.
PULSE_URL = 'https://api.pulsescore.net/api/stake/live-events?sport=soccer&page=1&limit=30'
class PulseNoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None

def pulse_market_reading(event, market):
    """Recognize only observed, explicit team totals. Provider enums alone are unsafe.

    Stake's Half Time team totals and Bands share the full-time canonical keys.
    Keep original fields and report a separate, conservative interpretation.
    This does not select a bet or claim that a line means one additional goal.
    """
    name=norm(market.get('name','')); period=market.get('period'); canonical=market.get('canonical')
    for team,field,expected in (('h','home','HOME_OVER_UNDER'),('a','away','AWAY_OVER_UNDER')):
        team_name=norm(event.get(field,''))
        if not team_name or norm(event.get('home',''))==norm(event.get('away','')):continue
        full=team_name+' total goals'
        ht=name in ('half time '+full,'half-time '+full)
        if not ht and name!=full:continue
        if canonical!=expected:return {'recognized':False,'reason':'Libellé et équipe normalisée contradictoires'}
        allowed=('FULL_TIME','FIRST_HALF') if ht else ('FULL_TIME',)
        if period not in allowed:return {'recognized':False,'reason':'Période non reconnue ou contradictoire'}
        mapped='HT' if ht else 'FT';selections=[]
        for s in market.get('selections',[]):
            line=s.get('line'); price=s.get('odds')
            # Require the literal Over line to agree with the numeric line.
            match=re.fullmatch(r'over\s+(\d+(?:\.\d+)?)',norm(s.get('name','')))
            if not match or not num(line) or float(match[1])!=line or not 0<=line<=20:continue
            half_line=abs(line%1-.5)<1e-9
            active=market.get('active') is True and s.get('active') is True
            selections.append({'line':line,'odds':price if num(price) and price>1 else None,
                'kind':'half_goal' if half_line else 'asian_or_integer','active_confirmed':active,
                'compatible_line':half_line,
                'note':('Total de buts de cette équipe sur la période ; comparer au score à l’instant du signal' if half_line
                        else 'Ligne asiatique ou entière : ne pas convertir en +0,5 but supplémentaire')})
        return {'recognized':True,'team':team,'period':mapped,'market':'goal_ht' if ht else 'goal_ft',
            'period_corrected':ht and period=='FULL_TIME','selections':selections}
    return {'recognized':False}

def pulse_day_used(c, now):
    start=now.astimezone(ZoneInfo('Europe/Paris')).replace(hour=0,minute=0,second=0,microsecond=0).astimezone(UTC)
    return c.execute('SELECT COUNT(*) FROM v4_pulsescore_auto WHERE attempted_at>=?',(iso(start),)).fetchone()[0]

def pulse_status(c, now):
    used=c.execute('SELECT COUNT(*) FROM v4_pulsescore_requests WHERE attempted_at>=?',(iso(now-dt.timedelta(days=31)),)).fetchone()[0]
    last=dec((c.execute("SELECT value FROM v4_runtime WHERE key='pulsescore_test'").fetchone() or [None])[0])
    # Reinterpret the saved test locally, including tests run before this fix.
    # No refresh request to the bookmaker and no mutation of the raw labels.
    if isinstance(last,dict) and last.get('ok'):
        for event in last.get('events',[]):
            for market in event.get('markets',[]):market['reading']=pulse_market_reading(event,market)
    return {'configured':bool(setting(c,'pulsescore_key',None)), 'attempts_31d':used, 'local_limit':500, 'last_test':last, 'auto_enabled':setting(c,'pulsescore_auto',True), 'auto_daily_limit':10, 'auto_today':pulse_day_used(c,now)}

def pulse_summary(payload, secret):
    if not isinstance(payload,dict) or not isinstance(payload.get('events'),list):
        raise ValueError('Format PulseScore non reconnu ; aucun marché interprété')
    def label(x): return x.replace(secret,'[masqué]')[:180] if isinstance(x,str) else ''
    events=[]
    for event in payload['events'][:30]:
        if not isinstance(event,dict) or not isinstance(event.get('markets'),list):
            raise ValueError('Structure des marchés PulseScore non reconnue')
        markets=[]
        for market in event['markets'][:200]:
            if not isinstance(market,dict):continue
            selections=[]
            for s in (market.get('selections') if isinstance(market.get('selections'),list) else [])[:6]:
                if not isinstance(s,dict):continue
                price=s.get('odds',s.get('decimal'));line=s.get('line')
                selections.append({'name':label(s.get('rawName') or s.get('name') or s.get('canonicalOutcome')),
                    'odds':price if num(price) and price>1 else None, 'line':line if num(line) else None,
                    'active':s.get('isActive') if type(s.get('isActive')) is bool else None})
            markets.append({'name':label(market.get('rawName') or market.get('canonicalMarket')),
                'canonical':label(market.get('canonicalMarket')), 'period':label(market.get('period')),
                'active':market.get('isActive') if type(market.get('isActive')) is bool else None,'selections':selections})
        events.append({'home':label(event.get('home')), 'away':label(event.get('away')),
            'event_id':label(event.get('eventId')), 'score':pulse_score(event.get('score')), 'market_count':len(event['markets']), 'markets':markets})
    total=payload.get('total')
    return {'events':events,'total':total if type(total) is int and total>=0 else None,
        'has_next_page':payload.get('hasNextPage') is True, 'returned':len(payload['events'])}

def pulse_score(value):
    if not isinstance(value,dict):return None
    result={}
    for src,dst in (('home','h'),('away','a')):
        n=value.get(src)
        if type(n) is int and 0<=n<=99:result[dst]=n
        elif isinstance(n,str) and re.fullmatch(r'\d{1,2}',n):result[dst]=int(n)
        else:return None
    return result

def pulse_test(c, now, automatic=False):
    # Reserve under a write lock, then release it BEFORE the network operation.
    # Failed attempts consume the local budget too; never retry or auto-paginate.
    with c:
        c.execute('BEGIN IMMEDIATE')
        if automatic and not setting(c,'pulsescore_auto',True):raise ValueError('Consultation automatique désactivée')
        if automatic and pulse_day_used(c,now)>=10:raise ValueError('Budget quotidien atteint : 10 consultations automatiques, heure de Paris')
        secret=setting(c,'pulsescore_key',None)
        if not secret:raise ValueError('Enregistre ta clé PulseScore avant le test')
        prev=c.execute('SELECT attempted_at FROM v4_pulsescore_requests ORDER BY id DESC LIMIT 1').fetchone()
        if prev and date(prev[0]) and (now-date(prev[0])).total_seconds()<60:
            raise ValueError('Attends une minute entre deux tests PulseScore')
        if pulse_status(c,now)['attempts_31d']>=500:raise ValueError('Limite locale atteinte : 500 tentatives sur 31 jours glissants')
        c.execute('INSERT INTO v4_pulsescore_requests(attempted_at) VALUES(?)',(iso(now),))
        if automatic:c.execute('INSERT INTO v4_pulsescore_auto(attempted_at) VALUES(?)',(iso(now),))
    result={'ok':False,'at':iso(now),'error':'Connexion PulseScore indisponible ; aucun nouvel essai automatique'}
    try:
        req=urllib.request.Request(PULSE_URL,headers={'X-Secret':secret,'Accept':'application/json','Accept-Encoding':'identity'})
        with urllib.request.build_opener(PulseNoRedirect()).open(req,timeout=3 if automatic else 8) as response:
            raw=response.read(4*1024*1024+1)
            if len(raw)>4*1024*1024:raise ValueError('Réponse PulseScore trop volumineuse ; test interrompu')
        try:payload=json.loads(raw)
        except (ValueError,UnicodeError):raise ValueError('Réponse PulseScore non JSON ; aucun marché interprété') from None
        result={'ok':True,'at':iso(now),**pulse_summary(payload,secret)}
    except urllib.error.HTTPError as e:
        errors={401:'Clé refusée par PulseScore',403:'Accès refusé : vérifier les droits Stake du forfait',429:'Quota ou fréquence PulseScore dépassé'}
        result['error']=errors.get(e.code,'Réponse HTTP PulseScore refusée')+' (HTTP '+str(e.code)+')'
        e.close()
    except ValueError as e:result['error']=str(e)
    except Exception:pass  # Never expose provider bodies, headers or exception text containing credentials.
    with c:runtime(c,'pulsescore_auto_last' if automatic else 'pulsescore_test',result)
    return result if automatic else {'ok':result['ok'],'pulsescore':pulse_status(c,now)}

def pulse_signal_quote(result, signal, saved, now):
    stamp=date(result.get('at'))
    if not result.get('ok') or not stamp or not 0<=(now-stamp).total_seconds()<=15:
        return None,'Réponse absente ou trop ancienne'
    events=[e for e in result.get('events',[]) if norm(e.get('home',''))==norm(saved.get('home','')) and norm(e.get('away',''))==norm(saved.get('away',''))]
    if len(events)!=1:return None,'Match absent de la page reçue ou identification ambiguë'
    event=events[0]
    if not event.get('event_id') or event.get('score') is None or event['score']!=saved.get('score'):
        return None,'Identifiant ou score fournisseur absent / différent de Packball'
    if signal['market'] not in ('goal_ht','goal_ft'):return None,'Marché cartons non couvert par ce raccordement'
    if signal['line']!=saved['score'][signal['team']]+.5:return None,'Ligne incompatible avec un but supplémentaire'
    choices=[]
    for m in event.get('markets',[]):
        r=pulse_market_reading(event,m)
        if not r.get('recognized') or r['market']!=signal['market'] or r['team']!=signal['team']:continue
        for selection in r['selections']:
            if selection['compatible_line'] and selection['active_confirmed'] and selection['line']==signal['line'] and num(selection['odds']) and selection['odds']>1:
                choices.append({'odds':selection['odds'],'line':selection['line'],'period':r['period'],'team':r['team'],
                    'market_name':m['name'],'period_corrected':r['period_corrected'],'event_id':event['event_id'],
                    'score':event['score'],'received_at':result['at'],'source':'Stake via PulseScore',
                    'matching':'noms exacts normalisés et score identique ; match unique dans la page live'})
    if not choices:return None,'Marché exact absent, suspendu, asiatique ou disponibilité non confirmée'
    if len({q['odds'] for q in choices})!=1:return None,'Plusieurs cotes contradictoires pour le même marché'
    return choices[0],None

def pulse_enrich(c, now):
    pending=[]
    # Claim each enrichment once. A crash leaves the signal available for statistical delivery.
    with c:
        c.execute('BEGIN IMMEDIATE')
        for signal in c.execute("SELECT * FROM v4_signals WHERE delivery='queued' AND created_at>=? ORDER BY id LIMIT 10",(iso(now-dt.timedelta(seconds=90)),)).fetchall():
            saved=dec(signal['data'],{})
            if 'stake_lookup' in saved:continue
            reason=None
            if signal['market']=='card_ft':reason='Cartons : alerte statistique sans cote'
            elif not setting(c,'pulsescore_auto',True):reason='Consultation automatique désactivée'
            elif not setting(c,'pulsescore_key',None):reason='Clé PulseScore non configurée'
            saved['stake_lookup']={'state':'skipped' if reason else 'checking','at':iso(now),'reason':reason}
            c.execute('UPDATE v4_signals SET data=? WHERE id=?',(enc(saved),signal['id']))
            if not reason:pending.append((signal,saved))
    if not pending:return
    cached=dec((c.execute("SELECT value FROM v4_runtime WHERE key='pulsescore_auto_last'").fetchone() or [None])[0],{})
    at=date(cached.get('at'));result=cached if cached.get('ok') and at and 0<=(now-at).total_seconds()<=15 else None
    reason=None
    if result is None and at and not cached.get('ok') and 0<=(now-at).total_seconds()<900:
        result=cached;reason='Pause API de 15 minutes après erreur : '+cached.get('error','PulseScore indisponible')
    if result is None:
        try:
            result=pulse_test(c,now,automatic=True)
            if not result.get('ok'):reason=result.get('error','PulseScore indisponible')
        except ValueError as e:reason=str(e)
        except Exception:reason='PulseScore indisponible ; alerte statistique conservée'
    with c:
        for signal,saved in pending:
            quote,why=pulse_signal_quote(result,signal,saved,now) if result and result.get('ok') else (None,reason)
            saved['stake_lookup']={'state':'found' if quote else 'unavailable','at':iso(now),'reason':why}
            if quote:saved['stake_quote']=quote
            c.execute("UPDATE v4_signals SET data=? WHERE id=? AND delivery='queued'",(enc(saved),signal['id']))

def pulse_message(saved, now):
    quote=saved.get('stake_quote');stamp=date(quote.get('received_at')) if isinstance(quote,dict) else None
    if quote and stamp and 0<=(now-stamp).total_seconds()<=30:
        return 'Cote indicative Stake via PulseScore : '+str(quote['odds'])+' · '+quote['market_name']+' · Over '+str(quote['line'])+' · relevée à '+stamp.astimezone(ZoneInfo('Europe/Paris')).strftime('%H:%M:%S')+' (Paris). À vérifier sur stake.bet ; fraîcheur du flux source non garantie.'
    return 'Cote Stake non disponible : '+(saved.get('stake_lookup',{}).get('reason') or 'relevé trop ancien ou consultation inachevée')+'. Vérification manuelle.'

def identity(m):
    if not isinstance(m, dict) or not all(text_ok(m.get(k), 160) for k in ('home','away')) or norm(m['home']) == norm(m['away']): raise ValueError('Noms des équipes invalides')
    if not date(m.get('kickoff')): raise ValueError('Heure ISO avec fuseau requise ; le site utilise Europe/Paris')
    mid = m.get('match_id')
    if mid is not None and (not isinstance(mid, str) or not re.fullmatch(r'\d{1,20}', mid)): raise ValueError('ID Packball invalide : conserver null s’il manque')
    return key(m)

def validate_context(m, generated):
    if m.get('watch') is not True: raise ValueError('Chaque match doit conserver watch:true')
    if not text_ok(m.get('summary'), 2500): raise ValueError('Résumé du contexte requis')
    sources = m.get('sources')
    if not isinstance(sources, list) or len(sources)>30: raise ValueError('Sources invalides')
    ids = set()
    for s in sources:
        if not isinstance(s,dict) or not text_ok(s.get('id'),40) or s['id'] in ids or not text_ok(s.get('title'),250) or not re.match(r'^https://[^\s/]+(?:/[^\s]*)?$',s.get('url','')) or len(s['url'])>1200 or not date(s.get('checked_at')) or date(s['checked_at'])>generated: raise ValueError('Source invalide ou date de consultation future')
        ids.add(s['id'])
    if not isinstance(m.get('unknowns'),list) or len(m['unknowns'])>30 or not all(text_ok(v,500) for v in m['unknowns']): raise ValueError('Liste des inconnues invalide')
    referee=m.get('referee')
    if referee is not None:
        if not isinstance(referee,dict) or not text_ok(referee.get('name'),160) or referee.get('appointment') not in ('confirmed','reported','unknown') or not text_ok(referee.get('note'),1000): raise ValueError('Arbitre invalide')
        refs=referee.get('appointment_source_ids')
        if not isinstance(refs,list) or any(v not in ids for v in refs) or (referee['appointment']!='unknown' and not refs): raise ValueError('Source de désignation de l’arbitre requise')
        stats=referee.get('stats')
        if stats is not None:
            if not isinstance(stats,dict) or not text_ok(stats.get('sample_label'),300): raise ValueError('Échantillon arbitre requis')
            n=stats.get('matches')
            if n is not None and (type(n) is not int or not 1<=n<=10000): raise ValueError('Nombre de matchs arbitre invalide')
            for field in ('yellow_per_match','red_per_match','fouls_per_match'):
                v=stats.get(field)
                if v is not None and (not num(v) or not 0<=v<=100): raise ValueError('Statistique arbitre invalide : '+field)
            refs=stats.get('source_ids')
            if not isinstance(refs,list) or not refs or any(v not in ids for v in refs): raise ValueError('Sources statistiques arbitre requises')
    teams=m.get('teams')
    if not isinstance(teams,dict) or set(teams)!={'h','a'}: raise ValueError('Contexte h/a requis')
    for t in teams.values():
        if not isinstance(t,dict) or not text_ok(t.get('note'),1800) or not isinstance(t.get('flags'),list) or len(t['flags'])>12: raise ValueError('Note et facteurs par équipe requis')
        for f in t['flags']:
            if not isinstance(f,dict) or f.get('kind') not in ('attack_absences','defence_absences','fatigue','rotation','schedule','discipline','weather','other') or f.get('severity') not in ('low','medium','high') or f.get('certainty') not in ('confirmed','reported','unknown') or not text_ok(f.get('detail'),1000) or not isinstance(f.get('source_ids'),list) or any(v not in ids for v in f['source_ids']): raise ValueError('Facteur contextuel invalide')
            if f['certainty']!='unknown' and not f['source_ids']: raise ValueError('Un fait confirmé/rapporté exige une source')

STATS=('n_h','n_a','gf_h','gf_a','ga_h','ga_a','shots_h','shots_a','sot_h','sot_a')
def import_bundle(c, kind, bundle, now=None):
    now=now or now_utc()
    expected='stratedge.context.v4' if kind=='analyst' else 'stratedge.packball.v4'
    if not isinstance(bundle,dict) or bundle.get('schema')!=expected or bundle.get('timezone')!='Europe/Paris': raise ValueError('Schéma attendu : '+expected+' ; timezone Europe/Paris')
    matches=bundle.get('matches'); generated=date(bundle.get('generated_at' if kind=='analyst' else 'exported_at'))
    if not generated or generated>now+dt.timedelta(seconds=60) or generated<now-dt.timedelta(days=7): raise ValueError('Date du dossier absente, future ou vieille de plus de 7 jours')
    if not isinstance(matches,list) or not 1<=len(matches)<=1000: raise ValueError('Dossier attendu : de 1 à 1000 matchs, sans sélection préalable')
    digest=hashlib.sha256((kind+enc(bundle)).encode()).hexdigest()
    if c.execute('SELECT 1 FROM v4_imports WHERE digest=?',(digest,)).fetchone():
        if kind=='packball':
            with c:runtime(c,'active_packball_fixtures',[key(m) for m in matches])
        return {'ok':True,'duplicate':True,'imported':0}
    seen=set(); prepared=[]
    for m in matches:
        fk=identity(m)
        if fk in seen: raise ValueError('Match dupliqué dans le dossier : '+m['home'])
        seen.add(fk)
        if not now-dt.timedelta(days=7)<=date(m['kickoff'])<=now+dt.timedelta(days=14): raise ValueError('Rencontre hors plage de dates (−7 / +14 jours)')
        if kind=='analyst': validate_context(m,generated)
        else:
            p=m.get('prematch')
            if not isinstance(p,dict): raise ValueError('Statistiques Packball requises')
            for k in STATS:
                v=p.get(k)
                if v is not None and (not num(v) or not 0<=v<=200): raise ValueError('Statistique invalide : '+k)
                if k.startswith('n_') and v is not None and int(v)!=v: raise ValueError('Échantillon non entier')
            for t in ('h','a'):
                if num(p.get('sot_'+t)) and num(p.get('shots_'+t)) and p['sot_'+t]>p['shots_'+t]: raise ValueError('Cadrés supérieurs aux tirs')
        prepared.append((fk,m))
    kept=0
    with c:
        if kind=='packball':runtime(c,'active_packball_fixtures',[fk for fk,_ in prepared])
        c.execute('INSERT INTO v4_imports(kind,digest,created_at,data) VALUES(?,?,?,?)',(kind,digest,iso(now),enc(bundle)))
        for fk,m in prepared:
            c.execute('INSERT OR IGNORE INTO v4_fixtures VALUES(?,?,?,?,?,?)',(fk,m['home'],m['away'],iso(date(m['kickoff'])),m.get('league',''),None))
            table='v4_contexts' if kind=='analyst' else 'v4_profiles'
            old=c.execute('SELECT * FROM '+table+' WHERE fixture=?',(fk,)).fetchone()
            # Freeze a valid prematch version once kickoff has passed; late data can
            # still be viewed when nothing was imported, never backdated for signals.
            if old and date(m['kickoff'])<=now: kept+=1; continue
            if kind=='analyst': c.execute('INSERT OR REPLACE INTO v4_contexts VALUES(?,?,?,?)',(fk,iso(now),iso(generated),enc(m)))
            else: c.execute('INSERT OR REPLACE INTO v4_profiles VALUES(?,?,?)',(fk,iso(now),enc(m)))
    return {'ok':True,'imported':len(matches)-kept,'preserved':kept,'late':sum(date(m['kickoff'])<=now for _,m in prepared)}

def active_fixtures(c):
    saved=c.execute("SELECT value FROM v4_runtime WHERE key='active_packball_fixtures'").fetchone()
    if saved:return set(dec(saved['value'],[]))
    last=c.execute("SELECT data FROM v4_imports WHERE kind='packball' ORDER BY id DESC LIMIT 1").fetchone()
    if last:return {key(m) for m in dec(last['data'],{}).get('matches',[])}
    return None

def current_cycle_ids(c):
    row=c.execute('SELECT payload FROM cycles ORDER BY rowid DESC LIMIT 1').fetchone()
    if not row:return None
    return {str(r.get('packball_id')) for r in dec(row['payload'],{}).get('rows',[]) if isinstance(r,dict)}

def bind(c,r):
    ko=date(r.get('kickoff_ts'))
    if not ko: return None
    rows=[f for f in c.execute('SELECT * FROM v4_fixtures') if norm(f['home'])==norm(r.get('home')) and norm(f['away'])==norm(r.get('away')) and abs((date(f['kickoff'])-ko).total_seconds())<=60]
    if len(rows)!=1: return None
    f=rows[0]; mid=str(r.get('packball_id',''))
    conflict=c.execute('SELECT fixture FROM v4_fixtures WHERE match_id=? AND fixture!=?',(mid,f['fixture'])).fetchone()
    if conflict or f['match_id'] not in (None,mid): return None
    c.execute('UPDATE v4_fixtures SET match_id=? WHERE fixture=?',(mid,f['fixture']))
    return dict(f,match_id=mid)

def inputs(c,f,now):
    if not f: return None,None
    p=c.execute('SELECT * FROM v4_profiles WHERE fixture=?',(f['fixture'],)).fetchone()
    ctx=c.execute('SELECT * FROM v4_contexts WHERE fixture=?',(f['fixture'],)).fetchone()
    profile=dec(p['data']) if p else None; context=dec(ctx['data']) if ctx else None
    if profile: profile['usable']=date(p['imported_at'])<date(f['kickoff']); profile['imported_at']=p['imported_at']
    if context: context['usable']=date(ctx['imported_at'])<date(f['kickoff']) and date(ctx['generated_at'])>=date(f['kickoff'])-dt.timedelta(hours=48); context['generated_at']=ctx['generated_at']
    return profile,context

def stat(r,k,t,group='stats'):
    values=r.get(group);p=values.get(k) if isinstance(values,dict) else None
    v=p.get(t) if isinstance(p,dict) else None
    return v if num(v) and v>=0 else None

def dismissal(r): return any((stat(r,k,t) or 0)>0 for k in ('red_cards','second_yellow') for t in ('h','a'))
def recent(r,hist,k,t,minutes):
    minute=r.get('minute')
    if not num(minute) or minute<minutes or 45<minute<45+minutes: return None
    # After a goal, require a full new window. This avoids celebrating a
    # completed attack as evidence that the team is about to score again.
    sample_time=date(r.get('collected_at'))
    if not sample_time: return None
    window_start=sample_time-dt.timedelta(minutes=minutes)
    usable=[]
    for h in hist:
        old=dec(h['data'],{}); at=date(old.get('collected_at') or h['received_at'])
        if not at or old.get('state')!='LIVE' or not num(old.get('minute')): continue
        if (old['minute']<=45)!=(minute<=45): continue
        if window_start<=at<=sample_time and old.get('score')!=r.get('score'): return None
        if at<=window_start and 0<=(window_start-at).total_seconds()<=100 and minutes-.5<=minute-old['minute']<=minutes+2: usable.append((at,old))
    supplied=stat(r,k+str(minutes),t,'ind'+str(minutes))
    total=stat(r,k,t)
    if supplied is not None:
        if total is None or supplied>total: return None
        return supplied
    if not usable or total is None: return None
    old=max(usable,key=lambda z:z[0])[1]; before=stat(old,k,t)
    return total-before if before is not None and total>=before else None

def base_decision(market,team): return {'market':market,'team':team,'status':'watch','reason':'Observation en cours','intensity':0,'metrics':{},'context_notes':[],'quote':None,'line':None,'threshold':70}
def evaluate(r,profile,context,market,team,now,hist,settings):
    d=base_decision(market,team)
    def finish(state,reason): d.update(status=state,reason=reason); return d
    at=date(r.get('collected_at')); received=date(r.get('received_at'))
    if not at or not received or not 0<=(now-at).total_seconds()<=100 or not 0<=(now-received).total_seconds()<=100: return finish('stale','Collecte interrompue ou relevé de plus de 100 secondes')
    if r.get('state')!='LIVE': return finish('waiting','À venir, pause ou rencontre terminée')
    minute=r.get('minute'); score=r.get('score')
    if not num(minute) or not 0<minute<=90 or r.get('minute_extra',0): return finish('waiting','Minute absente ou temps additionnel non modélisé')
    if not isinstance(score,dict) or any(type(score.get(t)) is not int or not 0<=score[t]<=30 for t in ('h','a')): return finish('missing','Score non vérifié')
    if r.get('quality_errors'): return finish('missing','Colonnes de collecte ambiguës')
    if dismissal(r): return finish('suspended','Expulsion : reprise après vérification, modèle à 11 contre 11')
    if any(stat(r,'red_cards',t) is None for t in ('h','a')): return finish('missing','Compteur des expulsions absent')
    other='a' if team=='h' else 'h'
    p=profile.get('prematch',{}) if profile and profile.get('usable') else {}
    if context and context.get('usable'):
        flags=context.get('teams',{}).get(team,{}).get('flags',[])
        d['context_notes']=[f['detail'] for f in flags]
        # Context makes verification stricter, never excludes a fixture or
        # manufactures a probability. Strong live evidence can still qualify.
        penalty=sum((6 if f['severity']=='high' else 3) for f in flags if f['kind'] in ('attack_absences','fatigue','rotation','weather') and f['certainty']!='unknown') if market!='card_ft' else 0
        d['threshold']+=min(penalty,12)
    else: d['context_notes']=['Contexte GPT absent, tardif ou trop ancien : lecture neutre']
    if market=='card_ft':
        yc=stat(r,'yellow_cards',team); fouls=stat(r,'fouls',team); poss=stat(r,'possession',team)
        f10=recent(r,hist,'fouls',team,10); oppshots=recent(r,hist,'shots',other,10)
        d['metrics']={'yellow_cards':yc,'fouls':fouls,'fouls10':f10,'possession':poss,'opponent_shots10':oppshots}
        if yc is None or int(yc)!=yc: return finish('missing','Compteur entier des cartons jaunes de l’équipe requis')
        d['line']=yc+.5
        # Refresh discipline evidence after a booking; old sanctioned fouls
        # cannot trigger a second bet on a higher line immediately.
        for h in hist:
            old=dec(h['data'],{}); old_at=date(old.get('collected_at') or h['received_at'])
            if old_at and at-dt.timedelta(minutes=10)<=old_at<=at and stat(old,'yellow_cards',team) is not None and stat(old,'yellow_cards',team)!=yc:
                return finish('watch','Carton ou correction récente : reconstruire la fenêtre de fautes sur 10 minutes pour le carton suivant')
        referee=context.get('referee') if context and context.get('usable') else None
        if isinstance(referee,dict):
            rs=referee.get('stats') or {}
            note='Arbitre : '+referee['name']+' · désignation '+referee['appointment']
            if referee['appointment']=='confirmed' and rs:
                note+=' · '+str(rs.get('yellow_per_match','?'))+' jaunes/match · '+str(rs.get('matches','?'))+' matchs · '+rs['sample_label']
                d['metrics'].update(referee_yellow_per_match=rs.get('yellow_per_match'),referee_fouls_per_match=rs.get('fouls_per_match'),referee_sample=rs.get('matches'))
            else: note+=' · statistiques à confirmer'
            d['context_notes'].insert(0,note)
        else: d['context_notes'].insert(0,'Arbitre non documenté : aucune statistique supposée')
        if not 15<=minute<=78: return finish('waiting','Fenêtre cartons : 15e à 78e minute')
        if fouls is None or f10 is None or poss is None or not 0<=poss<=100: return finish('missing','Fautes, fenêtre de 10 minutes ou possession manquantes')
        d['intensity']=min(100,round(min(f10/4,1)*40+min(fouls/9,1)*20+(15 if poss<=45 else 5 if poss<=52 else 0)+(15 if (oppshots or 0)>=3 else 0)+(10 if score[team]<=score[other] else 0)))
        if fouls<5 or f10<3 or not (poss<=48 or (oppshots or 0)>=3): return finish('watch','Attendre fautes répétées et pression subie confirmée')
    else:
        d['line']=score[team]+.5
        if not (12<=minute<=42 if market=='goal_ht' else 15<=minute<=82): return finish('waiting','Hors fenêtre de recherche de but')
        shots=stat(r,'shots',team); sot=stat(r,'sot',team)
        sh10=recent(r,hist,'shots',team,10); so10=recent(r,hist,'sot',team,10)
        sh5=recent(r,hist,'shots',team,5); so5=recent(r,hist,'sot',team,5)
        expected=p.get('shots_'+team); sample=p.get('n_'+team)
        ratio=shots/(expected*minute/90) if num(shots) and num(expected) and expected>0 and num(sample) and sample>=1 else None
        d['metrics']={'shots':shots,'sot':sot,'shots10':sh10,'sot10':so10,'shots5':sh5,'sot5':so5,'activity_ratio':round(ratio,3) if ratio is not None else None,'baseline_shots':expected,'sample':sample}
        if shots is None or sot is None or sot>shots: return finish('missing','Tirs cumulés absents ou incohérents')
        if sh10 is None or so10 is None or so10>sh10 or so10>sot: return finish('missing','Fenêtre tirs/cadrés de 10 minutes indisponible ou interrompue par un but')
        if ratio is None: return finish('missing','Profil Packball avant-match nécessaire pour comparer le rythme de tirs')
        d['intensity']=min(100,round(min(sh10/6,1)*25+min(so10/3,1)*30+min(ratio/1.8,1)*25+(10 if (so5 or 0)>=1 else 0)+(10 if score[team]<=score[other] else 0)))
        if sh10<4 or so10<2 or ratio<1.15: return finish('watch','Attendre tirs cadrés répétés et rythme supérieur à l’avant-match')
    if d['intensity']<d['threshold']: return finish('watch','Intensité encore insuffisante compte tenu du contexte')
    d['mode']='statistical_no_odds'
    return finish('candidate','Dynamique '+('disciplinaire' if market=='card_ft' else 'offensive')+' concordante · alerte statistique sans cote')

def safe_evaluate(*args):
    try: return evaluate(*args)
    except (ValueError,TypeError,KeyError,AttributeError,OverflowError):
        d=base_decision(args[3],args[4]);d.update(status='missing',reason='Structure de collecte invalide');return d

def label(r,d):
    name=r['home'] if d['team']=='h' else r['away']
    if d['market']=='card_ft': return name+' · plus de '+str(d['line']).replace('.',',')+' jaunes dans le match (un carton supplémentaire · suivi Packball)'
    return name+' · plus de '+str(d['line']).replace('.',',')+' but(s) '+('en première mi-temps' if d['market']=='goal_ht' else 'dans le match')

def latest(c):
    return c.execute('SELECT s.* FROM samples s JOIN (SELECT match_id,MAX(id) id FROM samples GROUP BY match_id) m ON s.id=m.id ORDER BY s.id DESC LIMIT 1500').fetchall()
def sample_data(s):
    r=dec(s['data'],{}); r['received_at']=s['received_at'];r.setdefault('collected_at',s['received_at']);r['sample_id']=s['id'];return r

def notify(message):
    token=os.environ.get('TELEGRAM_BOT_TOKEN'); chat=os.environ.get('TELEGRAM_CHAT_ID')
    if not token or not chat: return 'failed','Bot ou destinataire Telegram non configuré',None
    try:
        req=urllib.request.Request('https://api.telegram.org/bot'+token+'/sendMessage',data=enc({'chat_id':chat,'text':message[:4000]}).encode(),headers={'Content-Type':'application/json'})
        with urllib.request.urlopen(req,timeout=8) as response: x=json.load(response)
        if isinstance(x,dict) and x.get('ok') is True: return 'sent',None,x.get('result',{}).get('message_id')
        if isinstance(x,dict) and x.get('ok') is False: return 'failed','Refus Telegram',None
        return 'uncertain','Réponse Telegram illisible ; aucun renvoi automatique',None
    except urllib.error.HTTPError as e: return 'failed','Refus HTTP '+str(e.code),None
    except Exception: return 'uncertain','Connexion interrompue ; livraison inconnue, aucun renvoi automatique',None

def deliver(c, now, realtime=False):
    # Recover ambiguous sends after a process crash; never resend blindly.
    c.execute("UPDATE v4_signals SET delivery='uncertain',delivery_error='Processus interrompu pendant l’envoi' WHERE delivery='sending' AND created_at<?",(iso(now-dt.timedelta(minutes=3)),));c.commit()
    for s in c.execute("SELECT * FROM v4_signals WHERE delivery='queued' ORDER BY id LIMIT 10").fetchall():
        if realtime:now=now_utc()
        if not c.execute("UPDATE v4_signals SET delivery='sending' WHERE id=? AND delivery='queued'",(s['id'],)).rowcount: continue
        c.commit(); saved=dec(s['data'],{}); row=c.execute('SELECT * FROM samples WHERE match_id=? ORDER BY id DESC LIMIT 1',(s['match_id'],)).fetchone()
        valid=bool(row and 0<=(now-date(s['created_at'])).total_seconds()<=90)
        if valid:
            r=sample_data(row); f=bind(c,r);p,ctx=inputs(c,f,now);hist=c.execute('SELECT * FROM samples WHERE match_id=? AND id<=? ORDER BY id DESC LIMIT 80',(s['match_id'],row['id'])).fetchall()
            d=safe_evaluate(r,p,ctx,s['market'],s['team'],now,hist,options(c));valid=d['status']=='candidate' and r.get('score')==saved.get('score') and d['line']==s['line'] and (d.get('quote') or {}).get('odds')==s['odds']
        if not valid: state,error,mid='expired','Conditions ou cote modifiées avant envoi',None
        else:
            lines=['STRATEDGE · LIVE #'+str(s['id']),saved['home']+' — '+saved['away'],str(saved['minute'])+'′ · '+str(saved['score']['h'])+'–'+str(saved['score']['a']),saved['label'],('ALERTE STATISTIQUE'+(' · cote indicative conservée' if saved.get('stake_quote') else ' SANS COTE')+' · aucun avantage de prix évalué' if s['odds'] is None else 'Cote observée '+str(s['odds'])+' · bet365 via Packball'),'Intensité '+str(saved['decision']['intensity'])+'/100 (pas une probabilité)']
            if s['odds'] is None:lines.append(pulse_message(saved,now))
            lines += [k+' : '+str(v) for k,v in saved['decision']['metrics'].items() if v is not None]
            lines += saved['decision']['context_notes'][:2]
            lines += ['Vérifier la cote et le règlement chez ton bookmaker.','Signal en observation · aucune mise automatique.']
            state,error,mid=notify('\n'.join(lines))
        c.execute('UPDATE v4_signals SET delivery=?,delivery_error=?,telegram_id=? WHERE id=?',(state,error,mid,s['id']));c.commit()

def signal_review(c,s,outcome,now):
    saved=s['data'] if isinstance(s['data'],dict) else dec(s['data'],{});decision=saved.get('decision',{});facts=[];limits=[]
    ctx=saved.get('context') or {};profile=saved.get('profile') or {}
    if not ctx.get('usable'):limits.append('Contexte analyste absent ou non exploitable au déclenchement.')
    if not profile.get('usable'):limits.append('Profil avant-match absent ou non exploitable au déclenchement.')
    if s['market']=='card_ft' and not (ctx.get('referee') or {}).get('stats'):limits.append('Statistiques arbitre non documentées au déclenchement.')
    if s['odds'] is None:limits.append('Alerte statistique sans cote : aucun gain, rendement ou avantage de prix calculable.')
    limits.extend(str(x) for x in ctx.get('unknowns',[])[:8])
    rows=c.execute('SELECT * FROM samples WHERE match_id=? AND id>=? ORDER BY id LIMIT 600',(s['match_id'],s['sample_id'])).fetchall()
    observations=[]
    for row in rows:
        r=sample_data(row);at=date(r.get('collected_at'));received=date(r.get('received_at'))
        if not at or not received or at>now or received>now:continue
        if s['market']=='goal_ht' and not (r.get('state')=='HT' or r.get('state')=='LIVE' and num(r.get('minute')) and r['minute']<=45):continue
        observations.append({'sample_id':row['id'],'at':iso(at),'received_at':iso(received),'state':r.get('state'),'minute':r.get('minute'),'score':r.get('score'),'shots':stat(r,'shots',s['team']),'sot':stat(r,'sot',s['team']),'fouls':stat(r,'fouls',s['team']),'yellow_cards':stat(r,'yellow_cards',s['team']),'red_h':stat(r,'red_cards','h'),'red_a':stat(r,'red_cards','a'),'quality_errors':r.get('quality_errors',[])})
    if observations:
        first,last=observations[0],observations[-1]
        gaps=[(date(b['received_at'])-date(a['received_at'])).total_seconds() for a,b in zip(observations,observations[1:])]
        max_gap=max(gaps,default=0)
        if max_gap>100:limits.append('Trou de collecte observé après le signal : '+str(round(max_gap))+' secondes entre deux relevés.')
        if any(x['quality_errors'] for x in observations):limits.append('Au moins un relevé présente des colonnes ambiguës après le signal.')
        if any((x['red_h'] or 0)>0 or (x['red_a'] or 0)>0 for x in observations):facts.append('Une expulsion apparaît dans les relevés suivant le signal.')
        for field,title in [('shots','Tirs'),('sot','Cadrés'),('fouls','Fautes'),('yellow_cards','Jaunes')]:
            a,b=first[field],last[field]
            if num(a) and num(b) and b>=a:facts.append(title+' équipe ciblée : '+str(a)+' → '+str(b)+' sur la portion de match collectée.')
            elif num(a) and num(b):limits.append(title+' : compteur corrigé à la baisse, variation non interprétée.')
        if len(observations)<2:limits.append('Un seul relevé exploitable : évolution du match non mesurable.')
        if len(rows)==600:limits.append('Relecture limitée aux 600 premiers relevés après le signal.')
    else:limits.append('Aucun relevé exploitable conservé pour reconstituer la suite du signal.')
    required=('fouls10','fouls','possession') if s['market']=='card_ft' else ('shots10','sot10','activity_ratio')
    for field in required:
        if decision.get('metrics',{}).get(field) is None:limits.append('Repère absent dans le signal : '+field+'.')
    if outcome=='lost':facts.insert(0,'Événement non observé avant la fin du match, selon Packball.' if s['odds'] is None else 'Le résultat enregistré est perdant : la condition du pari n’a pas été atteinte selon sa source de règlement.')
    elif outcome=='won':facts.insert(0,'Événement observé dans Packball ; ce constat ne représente pas un gain financier.' if s['odds'] is None else 'Le résultat enregistré est gagnant ; conserver ce cas pour comparaison avec les pertes.')
    else:facts.insert(0,'Résultat '+outcome+' : ne pas le traiter comme une perte.')
    return {'schema':'stratedge.review.v1','generated_at':iso(now),'outcome':outcome,'signal_metrics':decision.get('metrics',{}),'facts':facts,'limits':list(dict.fromkeys(limits)),'observations':observations,'conclusion':'Ces constats ne prouvent pas la cause du résultat. Sans modèle de probabilités calibré, on ne peut pas conclure que la cote était avantageuse.','next_steps':['Comparer aux paris gagnants du même marché et aux mêmes plages de minute/cote.','Vérifier les inconnues datées disponibles avant le signal, sans utiliser le résultat comme justification rétrospective.','Tester toute modification sur une période ultérieure séparée ; aucun seuil n’est modifié automatiquement.']}

def feed_health(c,now):
    active=active_fixtures(c);cycle_ids=current_cycle_ids(c);issues=[];rows=[];seen=set()
    def issue(code,detail,mid=None):issues.append({'key':code+':'+str(mid or 'global'),'code':code,'match_id':mid,'detail':detail})
    for sample in latest(c):
        r=sample_data(sample);f=bind(c,r)
        if active is not None and (not f or f['fixture'] not in active):continue
        if active is None and cycle_ids is not None and str(r.get('packball_id')) not in cycle_ids:continue
        if f:seen.add(f['fixture'])
        rows.append(r);mid=r.get('packball_id');label=r.get('home','?')+' — '+r.get('away','?')
        if r.get('state') not in ('LIVE','HT'):continue
        at=date(r.get('received_at'));collected=date(r.get('collected_at'))
        if not at or not collected or not 0<=(now-at).total_seconds()<=100 or not 0<=(now-collected).total_seconds()<=100:issue('stale',label+' : relevé absent, trop ancien ou horloge incohérente.',mid);continue
        if r.get('quality_errors'):issue('columns',label+' : '+str(r['quality_errors'])[:400],mid)
        if not isinstance(r.get('score'),dict) or any(not num(r['score'].get(t)) for t in ('h','a')):issue('score',label+' : score non lisible.',mid)
        if not num(r.get('minute')):issue('clock',label+' : minute non lisible.',mid)
        missing=[k+' '+t for k in ('shots','sot','fouls','possession','yellow_cards','red_cards') for t in ('h','a') if stat(r,k,t) is None]
        if missing:issue('stats',label+' : données absentes du relevé : '+', '.join(missing),mid)
    # A schedule is not proof that a match is live. Report the verification gap.
    if active:
        for f in c.execute('SELECT * FROM v4_fixtures'):
            if f['fixture'] in active and f['fixture'] not in seen and now-dt.timedelta(hours=3)<=date(f['kickoff'])<=now-dt.timedelta(minutes=10):issue('unmatched',f['home']+' — '+f['away']+' : horaire dépassé, aucun relevé live associé ; statut à vérifier.',f['match_id'] or f['fixture'])
    cycle=c.execute('SELECT payload FROM cycles ORDER BY rowid DESC LIMIT 1').fetchone()
    payload=dec(cycle['payload'],{}) if cycle else {}
    if cycle and any(r.get('state') in ('LIVE','HT') for r in rows) and not payload.get('stat_headers'):
        issue('layout','Aucune colonne statistique live reçue : vérifier l’onglet Statistiques en direct et le groupe de colonnes affiché dans Packball.')
    heartbeat=dec((c.execute("SELECT value FROM v4_runtime WHERE key='engine'").fetchone() or [None])[0],{})
    if any(r.get('state') in ('LIVE','HT') for r in rows) and (not date(heartbeat.get('at')) or (now-date(heartbeat['at'])).total_seconds()>65):issue('engine','Aucun cycle moteur récent.')
    try:
        rejected=c.execute('SELECT received_at FROM collection_rejections ORDER BY id DESC LIMIT 1').fetchone()
        if rejected and date(rejected['received_at']) and 0<=(now-date(rejected['received_at'])).total_seconds()<=100:
            issue('rejected','Un collecteur envoie un tableau sans colonnes live. Envoi refusé : vérifier les onglets Packball et les scripts actifs. Les anciens relevés ne sont pas rafraîchis artificiellement.')
    except sqlite3.OperationalError:pass
    history_count=c.execute('SELECT COUNT(*) FROM v4_health_events WHERE last_seen>=?',(iso(now-dt.timedelta(hours=24)),)).fetchone()[0]
    return {'checked_at':iso(now),'status':'issues' if issues else 'ok','active_matches':len(rows),'issues':issues,'incidents_24h':history_count,'note':'État actuel uniquement : consulter les incidents et la relecture des matchs pour la nuit. Une cote absente est une limite de couverture, pas nécessairement une panne. Une statistique constante ne prouve pas que le flux est bloqué.'}

def audit_matches(c,now):
    """Read-only replay, including fixtures with no signals; never backfill alerts."""
    out=[];active=active_fixtures(c);settings=options(c)
    for f in c.execute('SELECT * FROM v4_fixtures ORDER BY kickoff'):
        if not f['match_id'] or active is not None and f['fixture'] not in active:continue
        rows=list(reversed(c.execute('SELECT * FROM samples WHERE match_id=? AND received_at>=? AND received_at<=? ORDER BY id DESC LIMIT 2000',(f['match_id'],iso(now-dt.timedelta(hours=24)),iso(now))).fetchall()))
        if not any(dec(x['data'],{}).get('state')=='LIVE' for x in rows):continue
        profile,ctx=inputs(c,f,now);cr=c.execute('SELECT imported_at FROM v4_contexts WHERE fixture=?',(f['fixture'],)).fetchone();hist=[];markets={};live=empty=0;states={};changes=[];last_signature=None
        for row in rows:
            r=sample_data(row);at=date(r.get('received_at'))
            hist.insert(0,row);hist=hist[:80]
            states[r.get('state','?')]=states.get(r.get('state','?'),0)+1
            if r.get('state')!='LIVE' or not at:continue
            live+=1
            blank=all(stat(r,k,t) is None for k in ('shots','sot','fouls','possession','yellow_cards','red_cards') for t in ('h','a'))
            empty+=int(blank)
            signature=(blank,enc(r.get('score')),enc(r.get('stats',{}).get('red_cards') if isinstance(r.get('stats'),dict) else None))
            if signature!=last_signature:
                changes.append({'at':r['received_at'],'minute':r.get('minute'),'score':r.get('score'),'stats_empty':blank,'red_cards':r.get('stats',{}).get('red_cards') if isinstance(r.get('stats'),dict) else None,'raw_cells_count':len(r.get('raw_cells') or [])})
                last_signature=signature
            # Only inputs already imported at this sample time are allowed.
            p=profile if profile and date(profile['imported_at'])<=at else None
            context=ctx if ctx and cr and date(cr['imported_at'])<=at else None
            for market in MARKETS:
                for team in ('h','a'):
                    d=safe_evaluate(r,p,context,market,team,at+dt.timedelta(seconds=1),hist,settings)
                    group=markets.setdefault(market+':'+team,{'states':{},'reasons':{},'candidate_samples':0,'confirmed_pairs':0,'candidate_examples':[],'max_intensity':0,'best':None})
                    group['states'][d['status']]=group['states'].get(d['status'],0)+1
                    group['reasons'][d['reason']]=group['reasons'].get(d['reason'],0)+1
                    group['candidate_samples']+=int(d['status']=='candidate')
                    if d['status']=='candidate':
                        if len(group['candidate_examples'])<10:group['candidate_examples'].append({'at':r['received_at'],'minute':r.get('minute'),'score':r.get('score'),'decision':d})
                        if len(hist)>1:
                            old=sample_data(hist[1]);gap=(at-date(old['received_at'])).total_seconds()
                            pd=safe_evaluate(old,p,context,market,team,at+dt.timedelta(seconds=1),hist[1:],settings)
                            if 20<=gap<=100 and pd['status']=='candidate' and old.get('score')==r.get('score') and 0<=r['minute']-old.get('minute',999)<=2 and pd['line']==d['line']:group['confirmed_pairs']+=1
                    if d['intensity']>group['max_intensity'] or group['best'] is None:
                        group['max_intensity']=d['intensity'];group['best']={'at':r['received_at'],'minute':r.get('minute'),'score':r.get('score'),'decision':d,'quotes':r.get('quotes',[])}
        out.append({'match_id':f['match_id'],'home':f['home'],'away':f['away'],'kickoff':f['kickoff'],'samples':len(rows),'live_samples':live,'empty_live_samples':empty,'states':states,'first_received_at':rows[0]['received_at'],'last_received_at':rows[-1]['received_at'],'profile_usable':bool(profile and profile.get('usable')),'profile_imported_at':profile.get('imported_at') if profile else None,'context_usable':bool(ctx and ctx.get('usable')),'markets':markets,'changes':changes[-120:],'limits':'Relecture avec le code et les paramètres actuels, une seconde après l’heure de réception de chaque relevé (anciens horodatages serveur tronqués à la seconde) ; pas une preuve des décisions effectivement exécutées. 24 h, 2000 relevés/match et 120 changements maximum. Les trous ne sont pas reconstruits ; aucune alerte rétroactive.'})
    return out

def record_health(c,report,now):
    current={x['key'] for x in report['issues']}
    for row in c.execute('SELECT id,issue_key FROM v4_health_events WHERE resolved_at IS NULL').fetchall():
        if row['issue_key'] not in current:c.execute('UPDATE v4_health_events SET resolved_at=? WHERE id=?',(iso(now),row['id']))
    for issue in report['issues']:
        row=c.execute('SELECT id FROM v4_health_events WHERE issue_key=? AND resolved_at IS NULL',(issue['key'],)).fetchone()
        if row:c.execute('UPDATE v4_health_events SET last_seen=?,data=? WHERE id=?',(iso(now),enc(issue),row['id']))
        else:c.execute('INSERT INTO v4_health_events(issue_key,opened_at,last_seen,data) VALUES(?,?,?,?)',(issue['key'],iso(now),iso(now),enc(issue)))

def result_message(s, previous, outcome, source, automatic=False):
    z=dec(s['data'],{});names={'won':'✅ GAGNANT','lost':'❌ PERDANT','void':'⚪ ANNULÉ / REMBOURSÉ','pending':'⏳ À VÉRIFIER — validation retirée'}
    if s['odds'] is None:names={'won':'✅ ÉVÉNEMENT OBSERVÉ','lost':'❌ NON OBSERVÉ AVANT LA FIN','void':'⚪ OBSERVATION ANNULÉE','pending':'⏳ À VÉRIFIER — validation retirée'}
    correction=previous!='pending'
    title='STRATEDGE · '+('CORRECTION DU RÉSULTAT' if correction else 'RÉSULTAT PACKBALL' if automatic else 'RÉSULTAT CONFIRMÉ')+' · LIVE #'+str(s['id'])
    lines=[title,names[outcome],z.get('home','?')+' — '+z.get('away','?'),z.get('label',LABELS.get(s['market'],s['market'])),('Alerte statistique sans cote · aucun bilan financier' if s['odds'] is None else 'Cote du signal : '+str(s['odds']))]
    if correction: lines.append('Ancien résultat : '+names[previous])
    profit=profit_units(s['odds'],outcome)
    if profit is not None: lines.append('Bilan simulé pour 1 unité : '+format(profit,'+.2f')+' u')
    review=z.get('review') or {}
    if outcome=='lost':lines.extend(['Bilan automatique :']+(review.get('facts',[])[1:3])+review.get('limits',[])[:2]+['Cause non démontrée ; bilan complet dans l’historique.'])
    lines.extend(['Résultat automatique d’après Packball ; corrigé si le flux change.' if automatic else 'Confirmation dans l’historique StratEdge.','Source : '+source,'Aucune mise automatique ; règlement du bookmaker distinct.'])
    return '\n'.join(lines)

def deliver_results(c,now,enabled=True):
    # Result events are persisted atomically with settlement. No historical
    # backfill and no blind retry after an ambiguous Telegram response.
    c.execute("UPDATE v4_result_notifications SET delivery='uncertain',delivery_error='Processus interrompu pendant l’envoi ; aucun renvoi automatique' WHERE delivery='sending' AND attempted_at<?",(iso(now-dt.timedelta(minutes=3)),))
    if not enabled or not options(c)['telegram_enabled']:
        c.execute("UPDATE v4_result_notifications SET delivery='disabled',delivery_error='Envoi désactivé' WHERE delivery='queued'");c.commit();return
    c.commit()
    for event in c.execute("SELECT * FROM v4_result_notifications WHERE delivery='queued' ORDER BY result_id LIMIT 10").fetchall():
        claimed=c.execute("UPDATE v4_result_notifications SET delivery='sending',attempted_at=? WHERE result_id=? AND delivery='queued'",(iso(now),event['result_id'])).rowcount
        c.commit()
        if not claimed:continue
        state,error,mid=notify(event['message'])
        c.execute('UPDATE v4_result_notifications SET delivery=?,delivery_error=?,telegram_id=? WHERE result_id=?',(state,error,mid,event['result_id']));c.commit()

def packball_verdict(c,s,r):
    """A feed verdict, not a bookmaker settlement. Never infer FT from minute 90."""
    market=s['market'];team=s['team'];state=r.get('state');value=None;ended=False
    if state not in ('LIVE','HT','FT'):
        return 'pending','Statut Packball non conclusif : interruption ou état à vérifier'
    if market=='card_ft':
        reds=[stat(r,k,t) for k in ('red_cards','second_yellow') for t in ('h','a')]
        if any(stat(r,'red_cards',t) is None for t in ('h','a')) or any(v is not None and v>0 for v in reds):
            return 'pending','Cartons : expulsion ou compteur incomplet, règlement à vérifier'
        value=stat(r,'yellow_cards',team);ended=state=='FT'
        detail='cartons jaunes équipe '+team
    elif market=='goal_ft':
        value=r.get('score',{}).get(team) if isinstance(r.get('score'),dict) else None;ended=state=='FT';detail='score équipe '+team+' · match'
    elif market=='goal_ht':
        if state=='HT' or state=='LIVE' and num(r.get('minute')) and r['minute']<=45:
            value=r.get('score',{}).get(team) if isinstance(r.get('score'),dict) else None;ended=state=='HT'
        else:
            # Use the latest actual halftime observation, never a second-half score.
            rows=c.execute('SELECT * FROM samples WHERE match_id=? AND id>? AND id<=? ORDER BY id DESC LIMIT 1500',(s['match_id'],s['sample_id'],r['sample_id'])).fetchall()
            for row in rows:
                hr=dec(row['data'],{})
                if hr.get('state')=='HT' and isinstance(hr.get('score'),dict):value=hr['score'].get(team);ended=True;break
            if value is None:return 'pending','Score de première mi-temps non collecté : aucun score FT substitué'
        detail='score équipe '+team+' · première mi-temps'
    else:return None
    if not num(value) or int(value)!=value or not 0<=value<=100:return 'pending','Compteur Packball absent ou incohérent'
    verdict='won' if value>s['line'] else 'lost' if ended else 'pending'
    return verdict,detail+' = '+str(value)+' · ligne '+str(s['line'])+(' · période terminée' if ended else ' · relevé en cours, correction possible')

def auto_results(c,now):
    for s in c.execute('SELECT * FROM v4_signals WHERE created_at>=?',(iso(now-dt.timedelta(days=3)),)).fetchall():
        saved=dec(s['data'],{})
        if saved.get('resolution',{}).get('mode')=='manual':continue
        previous=c.execute('SELECT data FROM v4_results WHERE signal_id=? ORDER BY id DESC LIMIT 1',(s['id'],)).fetchone()
        if previous and not dec(previous['data'],{}).get('automatic'):continue
        row=c.execute('SELECT * FROM samples WHERE match_id=? ORDER BY id DESC LIMIT 1',(s['match_id'],)).fetchone()
        if not row or row['id']<=s['sample_id']:continue
        r=sample_data(row);at=date(r.get('collected_at'));received=date(r.get('received_at'))
        if not at or not received or not 0<=(now-at).total_seconds()<=100 or not 0<=(now-received).total_seconds()<=100 or at<date(s['created_at']):continue
        if r.get('quality_errors'):continue
        ko=date(r.get('kickoff_ts'));original=date(saved.get('kickoff'))
        if not ko or not original or abs((ko-original).total_seconds())>60:continue
        if norm(r.get('home'))!=norm(saved.get('home')) or norm(r.get('away'))!=norm(saved.get('away')):continue
        verdict=packball_verdict(c,s,r)
        if verdict is None:continue
        outcome,detail=verdict
        if outcome==s['outcome']:continue
        source='Packball automatique · '+iso(at)+' · '+detail
        settle(c,{'id':s['id'],'outcome':outcome,'source':source},now,automatic=True,evidence={'sample_id':row['id'],'observed_at':iso(at),'detail':detail})

def run(path,send=True,now=None):
    realtime=now is None
    now=now or now_utc(); c=connect(path);settings=options(c);active=active_fixtures(c);cycle_ids=current_cycle_ids(c)
    for sample in latest(c):
        r=sample_data(sample)
        if not isinstance(r.get('packball_id'),str): continue
        f=bind(c,r)
        if active is not None and (not f or f['fixture'] not in active):continue
        if active is None and cycle_ids is not None and r['packball_id'] not in cycle_ids:continue
        p,ctx=inputs(c,f,now)
        hist=c.execute('SELECT * FROM samples WHERE match_id=? AND id<=? ORDER BY id DESC LIMIT 80',(sample['match_id'],sample['id'])).fetchall()
        previous=hist[1] if len(hist)>1 else None
        for market in MARKETS:
            for team in ('h','a'):
                d=safe_evaluate(r,p,ctx,market,team,now,hist,settings)
                if d['status']=='candidate':
                    sustained=False
                    if previous:
                        old=sample_data(previous);gap=(date(r['received_at'])-date(old['received_at'])).total_seconds()
                        pd=safe_evaluate(old,p,ctx,market,team,now,hist[1:],settings)
                        sustained=20<=gap<=100 and pd['status']=='candidate' and old.get('score')==r.get('score') and 0<=r['minute']-old.get('minute',999)<=2 and pd['line']==d['line']
                    d['status']='confirming';d['reason']='Attente de deux relevés concordants espacés de 20 à 100 secondes'
                    if sustained:
                        # One team/market/line signal per fixture; FT/HT overlap remains
                        # visible as correlated exposure, never a multiplied stake.
                        sk=enc([str(r['packball_id']),market,team,d['line']])
                        data={'home':r['home'],'away':r['away'],'kickoff':r.get('kickoff_ts'),'minute':r['minute'],'score':r['score'],'label':label(r,d),'decision':d.copy(),'profile':p,'context':ctx,'version':VERSION,'mode':'statistical_no_odds'}
                        c.execute('INSERT OR IGNORE INTO v4_signals(signal_key,match_id,fixture,market,team,line,odds,created_at,sample_id,data,delivery) VALUES(?,?,?,?,?,?,?,?,?,?,?)',(sk,r['packball_id'],f['fixture'] if f else None,market,team,d['line'],(d.get('quote') or {}).get('odds'),iso(now),sample['id'],enc(data),'queued' if send and settings['telegram_enabled'] else 'disabled'))
                        d.update(status='signal',reason='Signal conservé dans l’historique')
                c.execute('INSERT OR REPLACE INTO v4_decisions VALUES(?,?,?,?,?,?,?)',(r['packball_id'],market,team,sample['id'],iso(now),d['status'],enc(d)))
    auto_results(c,now);runtime(c,'engine',{'at':iso(now),'version':VERSION,'telegram_active':bool(send and settings['telegram_enabled'])});c.commit()
    if send and settings['telegram_enabled']:
        try:pulse_enrich(c,now)
        except Exception:
            c.rollback() # Odds enrichment must never prevent statistical delivery.
        deliver(c,now,realtime=realtime)
    else:
        c.execute("UPDATE v4_signals SET delivery='disabled',delivery_error='Envoi désactivé' WHERE delivery='queued'");c.commit()
    deliver_results(c,now,send and settings['telegram_enabled'])
    record_health(c,feed_health(c,now),now);c.commit()
    c.close()

def settle(c,x,now,automatic=False,evidence=None):
    sid=x.get('id'); outcome=x.get('outcome');source=x.get('source')
    if type(sid) is not int or outcome not in ('won','lost','void','pending') or not text_ok(source,600): raise ValueError('Résultat et source de vérification requis')
    # Serialize concurrent confirmation clicks before reading the old verdict.
    with c:
        if not c.in_transaction:c.execute('BEGIN IMMEDIATE')
        s=c.execute('SELECT * FROM v4_signals WHERE id=?',(sid,)).fetchone()
        if not s: raise ValueError('Signal introuvable')
        data=dec(s['data'],{})
        if automatic:
            # Manual override takes precedence even if it raced the feed worker.
            last=c.execute('SELECT data FROM v4_results WHERE signal_id=? ORDER BY id DESC LIMIT 1',(sid,)).fetchone()
            if data.get('resolution',{}).get('mode')=='manual' or last and not dec(last['data'],{}).get('automatic'):return {'ok':True,'manual_override':True}
        if s['outcome']!=outcome:data['review']=signal_review(c,s,outcome,now)
        data.pop('suggested_outcome',None);data.pop('suggested_source',None)
        data['resolution']={'mode':'automatic' if automatic else 'manual','at':iso(now),'source':source,**(evidence or {})}
        c.execute('UPDATE v4_signals SET data=? WHERE id=?',(enc(data),sid))
        if s['outcome']==outcome:return {'ok':True,'unchanged':True}
        event=c.execute('INSERT INTO v4_results(signal_id,created_at,previous,outcome,source,data) VALUES(?,?,?,?,?,?)',(sid,iso(now),s['outcome'],outcome,source,enc({'market':s['market'],'team':s['team'],'line':s['line'],'automatic':automatic,'evidence':evidence,'review':data.get('review')})))
        c.execute('UPDATE v4_signals SET outcome=?,settled_at=? WHERE id=?',(outcome,iso(now) if outcome!='pending' else None,sid))
        # A correction before delivery supersedes only unsent queued events.
        c.execute("UPDATE v4_result_notifications SET delivery='superseded',delivery_error='Résultat corrigé avant envoi' WHERE signal_id=? AND delivery='queued'",(sid,))
        state='queued' if options(c)['telegram_enabled'] else 'disabled'
        c.execute('INSERT INTO v4_result_notifications(result_id,signal_id,created_at,message,delivery) VALUES(?,?,?,?,?)',(event.lastrowid,sid,iso(now),result_message(dict(s,data=enc(data)),s['outcome'],outcome,source,automatic),state))
    return {'ok':True,'result_delivery':state}

def profit_units(odds,outcome):
    if not num(odds) or odds<=1:return None
    return odds-1 if outcome=='won' else -1 if outcome=='lost' else 0 if outcome=='void' else None

def history(c,limit=100,offset=0):
    out=[]
    for s in c.execute('SELECT * FROM v4_signals ORDER BY id DESC LIMIT ? OFFSET ?',(limit,offset)):
        row=dict(s);row['data']=dec(s['data'],{});row['profit_units']=profit_units(s['odds'],s['outcome']);event=c.execute('SELECT delivery,delivery_error,telegram_id,created_at FROM v4_result_notifications WHERE signal_id=? ORDER BY result_id DESC LIMIT 1',(s['id'],)).fetchone();row['result_notification']=dict(event) if event else None;out.append(row)
    return out

def board(c,now):
    records=[];seen=set();active=active_fixtures(c);cycle_ids=current_cycle_ids(c)
    for s in latest(c):
        r=sample_data(s)
        ko=date(r.get('kickoff_ts'))
        f=bind(c,r)
        if active is not None and (not f or f['fixture'] not in active):continue
        if active is None and cycle_ids is not None and str(r.get('packball_id')) not in cycle_ids:continue
        if active is None and cycle_ids is None and ko and ko<now-dt.timedelta(days=1):continue
        p,ctx=inputs(c,f,now)
        if f: seen.add(f['fixture'])
        r.update(profile=p,analyst=ctx,fixture=f['fixture'] if f else None,decisions=[])
        for d in c.execute('SELECT * FROM v4_decisions WHERE match_id=?',(s['match_id'],)):
            detail=dec(d['data'],{});detail['updated_at']=d['updated_at'];detail['sample_id']=d['sample_id'];r['decisions'].append(detail)
        # Do not leak raw HTML or entire historical payloads into the UI.
        r.pop('raw_cells',None);records.append(r)
    for f in c.execute('SELECT * FROM v4_fixtures ORDER BY kickoff'):
        if f['fixture'] in seen or active is None or f['fixture'] not in active:continue
        p,ctx=inputs(c,f,now);records.append({'fixture':f['fixture'],'packball_id':f['match_id'],'home':f['home'],'away':f['away'],'league':f['league'],'kickoff_ts':f['kickoff'],'state':'NS','profile':p,'analyst':ctx,'decisions':[]})
    cycle=c.execute('SELECT received_at,payload FROM cycles ORDER BY rowid DESC LIMIT 1').fetchone()
    feed=dec(cycle['payload'],{}) if cycle else {}
    totals=dict(c.execute("SELECT COUNT(*) total, SUM(outcome='won') won, SUM(outcome='lost') lost, SUM(outcome='void') void, SUM(outcome='pending') pending, COALESCE(SUM(CASE WHEN odds>1 THEN CASE outcome WHEN 'won' THEN odds-1 WHEN 'lost' THEN -1 ELSE 0 END ELSE 0 END),0) units, SUM(odds>1) priced_total, SUM(odds>1 AND outcome='won') priced_won, SUM(odds>1 AND outcome='lost') priced_lost, SUM(odds IS NULL) statistical_total, SUM(odds IS NULL AND outcome='won') statistical_won, SUM(odds IS NULL AND outcome='lost') statistical_lost FROM v4_signals").fetchone())
    totals={k:(0 if v is None else v) for k,v in totals.items()}
    legacy=[]
    try:
        for s in c.execute('SELECT * FROM signals ORDER BY id DESC LIMIT 200'):
            z=dict(s);z['context']=dec(z['context'],{});legacy.append(z)
    except sqlite3.OperationalError:pass
    c.commit()
    return {'ok':True,'version':VERSION,'server_time':iso(now),'health':feed_health(c,now),'matches':records,'signals':history(c),'history_total':totals['total'],'totals':totals,'legacy':legacy,'feed':{'received_at':cycle['received_at'] if cycle else None,'rows':feed.get('page_rows'), 'collector_version':feed.get('collector_version'),'headers':feed.get('stat_headers',[]),'odds_meta':feed.get('odds_meta',{})},'engine':dec((c.execute("SELECT value FROM v4_runtime WHERE key='engine'").fetchone() or [None])[0]),'settings':options(c),'pulsescore':pulse_status(c,now),'telegram':{'configured':bool(os.environ.get('TELEGRAM_BOT_TOKEN') and os.environ.get('TELEGRAM_CHAT_ID'))}}

def export_analysis(c,now):
    matches=[];active=active_fixtures(c)
    for f in c.execute('SELECT * FROM v4_fixtures ORDER BY kickoff'):
        if active is None or f['fixture'] not in active:continue
        p,_=inputs(c,f,now)
        m={k:f[k] for k in ('fixture','match_id','home','away','kickoff','league')}
        m.update(prematch=p.get('prematch',{}) if p else {},packball=p.get('packball') if p else None,
                 state=p.get('state') if p else None,data_issues=p.get('issues',[]) if p else [],
                 prematch_imported_at=p.get('imported_at') if p else None,prematch_usable=p.get('usable',False) if p else False)
        matches.append(m)
    return {'schema':'stratedge.research.v4','exported_at':iso(now),'timezone':'Europe/Paris','matches':matches,'instructions':'Tous les matchs doivent figurer dans le résultat stratedge.context.v4 avec watch:true. Aucun scénario éliminatoire. Utiliser PROMPT_ANALYSTE_V4.md.'}

def dispatch(c,x,now):
    action=x.get('action','board')
    if action=='board': return board(c,now)
    if action in ('analyst','packball'): return import_bundle(c,action,x.get('bundle'),now)
    if action=='export_analysis': return export_analysis(c,now)
    if action=='history':
        offset=x.get('offset',0)
        if type(offset) is not int or not 0<=offset<=1000000:raise ValueError('Pagination invalide')
        return {'ok':True,'signals':history(c,100,offset)}
    if action=='export_history': return {'schema':'stratedge.history.v4','exported_at':iso(now),'signals':history(c,100000)}
    if action=='export_review':
        signals=history(c,100);all_count=c.execute('SELECT COUNT(*) FROM v4_signals').fetchone()[0]
        incidents=[dict(row,data=dec(row['data'],{})) for row in c.execute('SELECT * FROM v4_health_events ORDER BY id DESC LIMIT 200')]
        for signal in signals:
            if not signal['data'].get('review'):signal['data']['review']=signal_review(c,signal,signal['outcome'],now)
        groups=[dict(row) for row in c.execute("SELECT market,outcome,COUNT(*) n FROM v4_signals GROUP BY market,outcome")]
        return {'schema':'stratedge.audit.v4','exported_at':iso(now),'timezone':'Europe/Paris','coverage':{'exported_signals':len(signals),'total_signals':all_count,'scope':'100 derniers signaux ; 200 derniers incidents'},'health':feed_health(c,now),'incidents':incidents,'all_history_counts':groups,'match_audits':audit_matches(c,now),'signals':signals,'instructions':'Analyser les faits, les problèmes de collecte, les limites du modèle et les hypothèses séparément. Comparer gagnants et perdants, pas seulement les pertes. Respecter les informations disponibles au moment du signal ; les relevés ultérieurs décrivent le déroulement, pas une information prédictive connue. Un résultat perdu ne prouve ni erreur ni cause précise. Ne pas inventer xG, arbitre ou absence. Les signaux sont corrélés par match et les cotes observées ne prouvent aucun avantage. Proposer des tests hors échantillon avant toute modification des seuils. Aucun changement automatique du moteur.'}
    if action=='settle': return settle(c,x,now)
    if action=='settings':
        s=x.get('settings',{})
        if type(s.get('telegram_enabled')) is not bool or not num(s.get('min_odds')) or not 1.2<=s['min_odds']<=3: raise ValueError('Paramètres invalides')
        with c:
            for k in ('telegram_enabled','min_odds'):c.execute('INSERT OR REPLACE INTO v4_settings VALUES(?,?)',(k,enc(s[k])))
        return {'ok':True}
    if action=='pulsescore_auto':
        if type(x.get('enabled')) is not bool:raise ValueError('Activation invalide')
        with c:c.execute('INSERT OR REPLACE INTO v4_settings VALUES(?,?)',('pulsescore_auto',enc(x['enabled'])))
        return {'ok':True,'pulsescore':pulse_status(c,now)}
    if action=='pulsescore_key':
        secret=x.get('key')
        if not isinstance(secret,str) or not re.fullmatch(r'[!-~]{8,512}',secret):raise ValueError('Clé invalide : 8 à 512 caractères sans espace')
        with c:
            c.execute('INSERT OR REPLACE INTO v4_settings VALUES(?,?)',('pulsescore_key',enc(secret)))
            c.execute("DELETE FROM v4_runtime WHERE key IN ('pulsescore_test','pulsescore_auto_last')")
        return {'ok':True,'pulsescore':pulse_status(c,now)}
    if action=='pulsescore_remove':
        with c:
            c.execute("DELETE FROM v4_settings WHERE key='pulsescore_key'")
            c.execute("DELETE FROM v4_runtime WHERE key IN ('pulsescore_test','pulsescore_auto_last')")
        return {'ok':True,'pulsescore':pulse_status(c,now)}
    if action=='pulsescore_test':return pulse_test(c,now)
    if action=='telegram_test':
        prev=dec((c.execute("SELECT value FROM v4_runtime WHERE key='telegram_test'").fetchone() or [None])[0],{})
        if date(prev.get('at')) and (now-date(prev['at'])).total_seconds()<60:raise ValueError('Attendre une minute entre deux tests Telegram')
        state,error,mid=notify('STRATEDGE LIVE V4 · Test de connexion\nLe canal reçoit les messages. Ceci n’est pas un pari.')
        with c: runtime(c,'telegram_test',{'at':iso(now),'state':state})
        return {'ok':state=='sent','state':state,'error':error,'message_id':mid}
    raise ValueError('Action inconnue')

if __name__=='__main__':
    ap=argparse.ArgumentParser();ap.add_argument('db');ap.add_argument('--init',action='store_true');ap.add_argument('--diagnostic',action='store_true');args=ap.parse_args()
    try:
        c=connect(args.db)
        if args.init: result={'ok':True,'version':VERSION}
        elif args.diagnostic:
            b=board(c,now_utc());result={k:b[k] for k in ('version','feed','engine','telegram','totals')};result['feed']=dict(result['feed']);result['feed'].pop('headers',None);result['feed'].pop('odds_meta',None);result['list']={'matches':len(b['matches']),'live':sum(r.get('state')=='LIVE' for r in b['matches']),'halftime':sum(r.get('state')=='HT' for r in b['matches'])};result['collection_columns']=b['feed'].get('headers',[]);result['collection_odds']=b['feed'].get('odds_meta',{});result['health']=b['health'];result['recent_rows']=[{k:r.get(k) for k in ('packball_id','home','away','state','status_raw','minute','received_at','stats','ind5','ind10','quotes','quality_errors')} for r in [sample_data(x) for x in latest(c)] if r.get('state') in ('LIVE','HT')][:6]
            result['match_audits']=audit_matches(c,now_utc())
            result['pulsescore']={k:b['pulsescore'][k] for k in ('configured','auto_enabled','auto_today','auto_daily_limit','attempts_31d')}
        else: result=dispatch(c,json.load(sys.stdin),now_utc())
        print(enc(result));c.close()
    except (ValueError,TypeError,KeyError) as e: print(enc({'error':str(e)}));sys.exit(2)
    except Exception as e: print(enc({'error':'Service Live V4 indisponible','type':type(e).__name__}));sys.exit(1)
