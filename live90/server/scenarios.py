"""Strict imported playbooks. Data only: no eval, code execution or model calls."""
import datetime as dt, hashlib, json, math, re, sqlite3, sys, unicodedata
from context_common import parse, url_ok
UTC=dt.timezone.utc
METRICS={'shots','sot','shots5','sot5','shots10','sot10','activity_ratio','sot_ratio'}

def check(ok,message):
    if not ok:raise ValueError(message)
def numeric(x):return type(x) in (int,float) and math.isfinite(x)
def exact(x,keys,label):check(isinstance(x,dict) and set(x)==set(keys.split()),label+' : champs inattendus ou manquants')
def text(x,n=2000):return isinstance(x,str) and 0<len(x.strip())<=n

def validate(bundle,now,allow_pending=False):
    exact(bundle,'schema generated_at matches','Dossier')
    check(bundle['schema']=='seuil90.scenarios.v1','Schéma attendu : seuil90.scenarios.v1')
    generated=parse(bundle['generated_at']);check(generated and generated<=now and (now-generated).total_seconds()<86400,'Dossier daté de moins de 24 h requis')
    check(isinstance(bundle['matches'],list) and 1<=len(bundle['matches'])<=200,'Entre 1 et 200 matchs requis')
    ids=set()
    for m in bundle['matches']:
        exact(m,'match_id home away kickoff prematch context scenarios','Match')
        check((allow_pending and m['match_id'] is None) or (isinstance(m['match_id'],str) and re.fullmatch(r'\d{1,20}',m['match_id']) and m['match_id'] not in ids),'ID Packball absent ou dupliqué');ids.add(m['match_id'])
        check(text(m['home'],200) and text(m['away'],200),'Équipes invalides')
        ko=parse(m['kickoff']);check(ko and now<ko<=now+dt.timedelta(days=14),'Import et mises à jour uniquement avant le coup d’envoi')
        b=m['prematch'];exact(b,'n_h n_a gf_h gf_a ga_h ga_a shots_h shots_a sot_h sot_a','Statistiques')
        check(all(numeric(v) and 0<=v<=100 for v in b.values()),'Statistiques numériques complètes requises')
        check(all(type(b['n_'+side]) is int and b['n_'+side]>=8 and b['sot_'+side]<=b['shots_'+side] and b['shots_'+side]>0 for side in ('h','a')),'Échantillon de 8 matchs minimum et tirs cohérents requis')
        ctx=m['context'];exact(ctx,'summary unknowns sources','Contexte')
        check(text(ctx['summary']) and isinstance(ctx['unknowns'],list) and len(ctx['unknowns'])<=20 and all(text(x) for x in ctx['unknowns']),'Résumé ou incertitudes invalides')
        check(isinstance(ctx['sources'],list) and len(ctx['sources'])<=30,'Sources invalides')
        for src in ctx['sources']:
            exact(src,'url title checked_at','Source');at=parse(src['checked_at'])
            check(url_ok(src['url']) and text(src['title'],300) and at and at<=generated,'Source ou date de vérification invalide')
        check(isinstance(m['scenarios'],list) and len(m['scenarios'])<=4,'Maximum 4 scénarios par match')
        sids=set()
        for s in m['scenarios']:
            exact(s,'id title hypothesis evidence_status rationale team trigger window score conditions market','Scénario')
            check(isinstance(s['id'],str) and re.fullmatch(r'[a-z0-9_-]{1,48}',s['id']) and s['id'] not in sids,'Identifiant de scénario invalide ou dupliqué');sids.add(s['id'])
            check(all(text(s[k]) for k in ('title','hypothesis','rationale')),'Justification requise')
            check(s['evidence_status'] in ('hypothesis','documented'),'Statut de preuve invalide')
            if s['evidence_status']=='documented':check(bool(ctx['sources']),'Une observation documentée nécessite une source')
            check(s['team'] in ('h','a','total'),'Équipe attendue : h, a ou total')
            exact(s['trigger'],'kind before_minute','Déclencheur')
            check(s['trigger']['kind'] in ('pressure','conceded_early'),'Déclencheur inconnu')
            if s['trigger']['kind']=='pressure':check(s['trigger']['before_minute'] is None,'pressure : before_minute doit être null')
            else:check(s['team'] in ('h','a') and type(s['trigger']['before_minute']) is int and 1<=s['trigger']['before_minute']<=30,'But encaissé : équipe et minute limite requis')
            exact(s['window'],'from to period','Fenêtre')
            w=s['window'];check(w['period'] in ('HT','FT') and all(type(w[k]) is int for k in ('from','to')) and 1<=w['from']<w['to']<=(44 if w['period']=='HT' else 89),'Fenêtre invalide (hors arrêts de jeu)')
            exact(s['score'],'min_total max_total relation','Score')
            g=s['score'];check(all(type(g[k]) is int for k in ('min_total','max_total')) and 0<=g['min_total']<=g['max_total']<=10 and g['relation'] in ('any','drawing','trailing_one','not_leading'),'Condition de score invalide')
            check(s['team']!='total' or g['relation'] in ('any','drawing'),'Relation au score nécessite une équipe')
            if s['trigger']['kind']=='conceded_early':check(g['relation']=='trailing_one','La réaction exige une équipe encore menée d’un but')
            cond=s['conditions'];check(isinstance(cond,list) and 2<=len(cond)<=12,'Entre 2 et 12 conditions requises')
            seen=set()
            for x in cond:
                exact(x,'metric min','Condition')
                check(x['metric'] in METRICS and x['metric'] not in seen and numeric(x['min']) and 0<x['min']<=100,'Métrique ou seuil invalide');seen.add(x['metric'])
            check(bool(seen & {'sot5','sot10'}) and bool(seen & {'activity_ratio','sot_ratio'}),'Un seuil de cadrés récents et une comparaison avant-match sont obligatoires')
            exact(s['market'],'type period side line min_odds max_odds','Marché')
            q=s['market'];check(q['type'] in ('total_goals','team_goals') and q['period']==w['period'] and q['side']=='over' and q['line']=='next_half','Marché non pris en charge')
            check(q['type']!='team_goals' or s['team'] in ('h','a'),'Marché équipe nécessite h ou a')
            check(numeric(q['min_odds']) and numeric(q['max_odds']) and 1<q['min_odds']<=q['max_odds']<=20,'Bornes de cote invalides')
    return bundle

def import_bundle(c,bundle,now):
    validate(bundle,now)
    encoded=json.dumps(bundle,ensure_ascii=False,sort_keys=True);digest=hashlib.sha256(encoded.encode()).hexdigest()
    c.execute('BEGIN IMMEDIATE')
    try:
        if c.execute('SELECT 1 FROM playbook_imports WHERE digest=?',(digest,)).fetchone():c.rollback();return {'ok':True,'imported':0,'duplicate':True}
        at=now.isoformat()
        for m in bundle['matches']:
            b=dict(m['prematch'],match_id=m['match_id'],home=m['home'],away=m['away'],kickoff=m['kickoff'],recorded_at=at,source='Packball · dossier importé')
            pid=c.execute('INSERT INTO prematch(match_id,recorded_at,kickoff,data) VALUES(?,?,?,?)',(m['match_id'],at,m['kickoff'],json.dumps(b,ensure_ascii=False))).lastrowid
            c.execute('INSERT INTO playbooks(match_id,prematch_id,imported_at,generated_at,data,digest) VALUES(?,?,?,?,?,?)',(m['match_id'],pid,at,bundle['generated_at'],json.dumps(m,ensure_ascii=False),digest))
        c.execute('INSERT INTO playbook_imports(digest,imported_at) VALUES(?,?)',(digest,at));c.commit()
    except Exception:c.rollback();raise
    return {'ok':True,'imported':len(bundle['matches']),'duplicate':False}

def pending_schema(c):
    c.execute("CREATE TABLE IF NOT EXISTS pending_playbooks(fixture TEXT PRIMARY KEY, accepted_at TEXT NOT NULL, generated_at TEXT NOT NULL, data TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'waiting', match_id TEXT)")

def team_key(s):
    return ' '.join(''.join(x for x in unicodedata.normalize('NFKD',str(s).casefold()) if not unicodedata.combining(x)).split())

def sample_time(v):
    if type(v) in (int,float):
        try:return dt.datetime.fromtimestamp(v/1000 if v>1e12 else v,UTC)
        except (ValueError,OverflowError,OSError):return None
    return parse(v) if isinstance(v,str) else None

def resolve_pending(c,now):
    pending_schema(c);c.commit();c.execute('BEGIN IMMEDIATE');resolved=0
    try:
        samples=[]
        for mid,raw in c.execute('SELECT s.match_id,s.data FROM samples s JOIN (SELECT match_id,MAX(id) id FROM samples GROUP BY match_id) x ON s.id=x.id'):
            try:samples.append((mid,json.loads(raw)))
            except (ValueError,TypeError):continue
        for key,accepted,generated,raw in c.execute("SELECT fixture,accepted_at,generated_at,data FROM pending_playbooks WHERE state IN ('waiting','ambiguous')").fetchall():
            m=json.loads(raw);ko=parse(m['kickoff']);ids=set()
            if now>ko+dt.timedelta(hours=4):
                c.execute("UPDATE pending_playbooks SET state='expired' WHERE fixture=?",(key,));continue
            for mid,row in samples:
                kick=sample_time(row.get('kickoff_ts'))
                if kick and abs((kick-ko).total_seconds())<60 and team_key(row.get('home'))==team_key(m['home']) and team_key(row.get('away'))==team_key(m['away']):
                    ids.add(str(mid))
            if len(ids)!=1:
                c.execute('UPDATE pending_playbooks SET state=? WHERE fixture=?',('ambiguous' if len(ids)>1 else 'waiting',key));continue
            mid=next(iter(ids))
            if m['match_id'] is not None and m['match_id']!=mid:
                c.execute("UPDATE pending_playbooks SET state='ambiguous' WHERE fixture=?",(key,));continue
            m['match_id']=mid
            # The dossier was validated and frozen before kickoff; late binding changes only its ID.
            b=dict(m['prematch'],match_id=mid,home=m['home'],away=m['away'],kickoff=m['kickoff'],recorded_at=accepted,source='Packball · association automatique')
            latest=c.execute('SELECT generated_at FROM playbooks WHERE match_id=? ORDER BY id DESC LIMIT 1',(mid,)).fetchone()
            if not latest or parse(latest[0])<=parse(generated):
                pid=c.execute('INSERT INTO prematch(match_id,recorded_at,kickoff,data) VALUES(?,?,?,?)',(mid,accepted,m['kickoff'],json.dumps(b,ensure_ascii=False))).lastrowid
                c.execute('INSERT INTO playbooks(match_id,prematch_id,imported_at,generated_at,data,digest) VALUES(?,?,?,?,?,?)',(mid,pid,accepted,generated,json.dumps(m,ensure_ascii=False),key));resolved+=1
            c.execute("UPDATE pending_playbooks SET state='linked',match_id=? WHERE fixture=?",(mid,key))
        c.commit()
    except Exception:c.rollback();raise
    return resolved

def accept_pending(c,bundle,now):
    validate(bundle,now,allow_pending=True);pending_schema(c);c.commit()
    digest=hashlib.sha256(json.dumps(bundle,sort_keys=True,ensure_ascii=False).encode()).hexdigest()
    c.execute('BEGIN IMMEDIATE')
    try:
        if c.execute('SELECT 1 FROM playbook_imports WHERE digest=?',(digest,)).fetchone():
            c.rollback();return {'ok':True,'imported':0,'duplicate':True}
        keys=set()
        for m in bundle['matches']:
            key=hashlib.sha256(json.dumps([team_key(m['home']),team_key(m['away']),parse(m['kickoff']).isoformat()]).encode()).hexdigest()
            check(key not in keys,'Match dupliqué dans le dossier');keys.add(key)
            old=c.execute('SELECT generated_at FROM pending_playbooks WHERE fixture=?',(key,)).fetchone()
            if old and parse(old[0])>parse(bundle['generated_at']):continue
            c.execute("INSERT OR REPLACE INTO pending_playbooks VALUES(?,?,?,?,'waiting',NULL)",(key,now.isoformat(),bundle['generated_at'],json.dumps(m,ensure_ascii=False)))
        c.execute('INSERT INTO playbook_imports VALUES(?,?)',(digest,now.isoformat()));c.commit()
    except Exception:c.rollback();raise
    linked=resolve_pending(c,now)
    pending=sum(c.execute("SELECT state FROM pending_playbooks WHERE fixture=?",(key,)).fetchone()[0]!='linked' for key in keys)
    return {'ok':True,'imported':len(keys),'linked':linked,'pending':pending,'duplicate':False}

if __name__=='__main__':
    try:
        raw=sys.stdin.buffer.read(2097153) if '--resolve' not in sys.argv else b'{}';check(len(raw)<=2097152,'Dossier limité à 2 Mo')
        c=sqlite3.connect(sys.argv[1],timeout=10)
        result={'ok':True,'linked':resolve_pending(c,dt.datetime.now(UTC))} if '--resolve' in sys.argv else accept_pending(c,json.loads(raw),dt.datetime.now(UTC))
        print(json.dumps(result,ensure_ascii=False));c.close()
    except (ValueError,TypeError,KeyError) as e:print(json.dumps({'ok':False,'error':str(e)},ensure_ascii=False));sys.exit(2)
    except Exception:print(json.dumps({'ok':False,'error':'Import indisponible : consulter le journal serveur'}));sys.exit(3)
