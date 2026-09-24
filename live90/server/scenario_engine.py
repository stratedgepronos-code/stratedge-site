"""Local deterministic scenario execution. OpenAI is never contacted."""
import datetime as dt, json, math, sqlite3, os, unicodedata, urllib.error, urllib.request
from context_common import parse
UTC=dt.timezone.utc
VERSION='s90-scenarios-3'
def stamp():return dt.datetime.now(UTC).isoformat()
def num(x):return type(x) in (int,float) and math.isfinite(x)
def norm(x):return ''.join(c for c in unicodedata.normalize('NFKD',str(x).casefold()) if not unicodedata.combining(c)).strip()
def pair(r,g,k):
    p=r.get(g,{}).get(k)
    return p if isinstance(p,dict) and all(num(p.get(t)) and p[t]>=0 for t in ('h','a')) else None

def health(r,b,now,received):
    at=parse(received)
    if not at or not 0<=(now-at).total_seconds()<=120:return 'stale','Relevé périmé'
    if r.get('state')!='LIVE':return 'waiting','Match hors période live'
    ko=parse(b.get('kickoff'));recorded=parse(b.get('recorded_at'));liveko=parse(r.get('kickoff_ts'))
    if not ko or not recorded or recorded>=ko or now<ko or not liveko or abs((ko-liveko).total_seconds())>900:return 'missing','Horaires non concordants'
    if any(norm(r.get(k))!=norm(b.get(k)) for k in ('home','away')):return 'missing','Équipes non concordantes'
    if not num(r.get('minute')) or r.get('minute_extra',0)!=0:return 'waiting','Minute absente ou arrêts de jeu exclus'
    if r.get('quality_errors'):return 'missing','Colonnes ambiguës dans la collecte'
    sc=r.get('score')
    if not isinstance(sc,dict) or any(type(sc.get(t)) is not int or sc[t]<0 for t in ('h','a')):return 'missing','Score illisible'
    for k in ('red_cards','second_yellow','shots','sot'):
        if pair(r,'stats',k) is None:return 'missing','Statistique absente : '+k
    if any(sum(pair(r,'stats',k).values()) for k in ('red_cards','second_yellow')):return 'excluded','Expulsion : scénario annulé'
    for t in ('h','a'):
        if r['stats']['sot'][t]>r['stats']['shots'][t]:return 'missing','Compteurs incohérents'
    return None,None

def early_event(history,s):
    """Only a witnessed 0-0 -> 0-1 transition, never a guessed goal time."""
    team=s['team'];other='a' if team=='h' else 'h';found=None;previous=None
    for row in history:
        r=json.loads(row['data']);score=r.get('score');minute=r.get('minute');at=parse(row['received_at'])
        if r.get('state')!='LIVE' or not isinstance(score,dict) or not num(minute) or not at:previous=None;continue
        if previous:
            old,old_at=previous;gap=(at-old_at).total_seconds();old_score=old['score']
            if any(score.get(t,-1)<old_score.get(t,0) for t in ('h','a')):return None
            if 0<gap<=100 and 0<=minute-old['minute']<=2 and minute<=s['trigger']['before_minute'] and not r.get('minute_extra',0) and old_score=={'h':0,'a':0} and score.get(team)==0 and score.get(other)==1:
                found={'minute':minute,'received_at':row['received_at'],'sample_id':row['id']}
        previous=(r,at)
    return found

def metric(r,b,team,name):
    sides=('h','a') if team=='total' else (team,)
    if name in ('activity_ratio','sot_ratio'):
        key='shots' if name=='activity_ratio' else 'sot';p=pair(r,'stats',key)
        expected=sum(b[key+'_'+t] for t in sides)*r['minute']/90
        return sum(p[t] for t in sides)/expected if p and expected>0 else None
    win=10 if name.endswith('10') else 5 if name.endswith('5') else 0
    p=pair(r,'ind'+str(win) if win else 'stats',name)
    if p is None:return None
    if win:
        root='sot' if name.startswith('sot') else 'shots';cum=pair(r,'stats',root)
        shots=pair(r,'ind'+str(win),'shots'+str(win));sot=pair(r,'ind'+str(win),'sot'+str(win))
        if not cum or any(p[t]>cum[t] for t in ('h','a')) or (shots and sot and any(sot[t]>shots[t] for t in ('h','a'))):return None
    return sum(p[t] for t in sides)

def evaluate(r,b,s,now,received,history):
    state,why=health(r,b,now,received)
    if state:return state,why,{}
    w=s['window'];minute=r['minute'];score=r['score'];team=s['team']
    if not w['from']<=minute<=w['to']:return 'waiting','Hors fenêtre du scénario',{}
    g=s['score'];total=sum(score.values());diff=score['h']-score['a'] if team!='a' else score['a']-score['h']
    if not g['min_total']<=total<=g['max_total'] or (g['relation']=='drawing' and diff!=0) or (g['relation']=='trailing_one' and diff!=-1) or (g['relation']=='not_leading' and diff>0):return 'watch','Score hors scénario',{}
    detail={'scenario_id':s['id'],'values':{},'trigger':None}
    wins=[10 if x['metric'].endswith('10') else 5 for x in s['conditions'] if x['metric'].endswith(('5','10'))]
    if any(minute<win or 45<minute<45+win for win in wins):return 'watch','Fenêtre récente non entièrement jouée dans cette mi-temps',detail
    if s['trigger']['kind']=='conceded_early':
        event=early_event(history,s)
        if not event:return 'watch','But précoce non observé avec certitude',detail
        detail['trigger']=event
        if minute-event['minute']<max(wins):return 'watch','Attente d’une fenêtre de tirs entièrement postérieure au but',detail
    for condition in s['conditions']:
        value=metric(r,b,team,condition['metric']);detail['values'][condition['metric']]=value
        if value is None:return 'missing','Mesure absente ou incohérente : '+condition['metric'],detail
        if value<condition['min']:return 'watch','Seuil non atteint : '+condition['metric'],detail
    market=s['market'];line=(total if market['type']=='total_goals' else score[team])+.5
    # HT lines refer to the first-half score only while minute <=44.
    if market['period']=='HT' and minute>=45:return 'waiting','Marché première mi-temps expiré',detail
    candidates=[]
    for q in r.get('quotes',[]):
        at=parse(q.get('observed_at'))
        if q.get('market')==market['type'] and q.get('period')==market['period'] and q.get('side')=='over' and q.get('line')==line and q.get('verified') is True and q.get('bookmaker')=='bet365' and (market['type']!='team_goals' or q.get('team')==team) and num(q.get('odds')) and market['min_odds']<=q['odds']<=market['max_odds'] and at and 0<=(now-at).total_seconds()<=120:candidates.append(q)
    if not candidates:return 'price','Marché exact ou cote vérifiée indisponible',detail
    detail['quote']=max(candidates,key=lambda q:q['odds'])
    return 'candidate','Toutes les conditions du scénario sont réunies',detail

def safe_evaluate(*args):
    try:return evaluate(*args)
    except (TypeError,ValueError,KeyError,AttributeError):return 'missing','Structure de données invalide',{}

def market_label(s,line,m):
    who='Total' if s['market']['type']=='total_goals' else m['home'] if s['team']=='h' else m['away']
    return f"{who} : plus de {line:g} buts · "+('1re mi-temps' if s['market']['period']=='HT' else 'match')

def run(db,send=False):
    c=sqlite3.connect(db,timeout=10);c.row_factory=sqlite3.Row;now=dt.datetime.now(UTC)
    for sample in c.execute('SELECT s.* FROM samples s JOIN (SELECT match_id,MAX(id) id FROM samples GROUP BY match_id) m ON s.id=m.id').fetchall():
        mid=sample['match_id'];r=json.loads(sample['data']);book=c.execute('SELECT * FROM playbooks WHERE match_id=? ORDER BY id DESC LIMIT 1',(mid,)).fetchone()
        outcomes=[]
        if not book:outcomes=[('missing','Dossier de scénarios à importer avant-match',{})]
        else:
            m=json.loads(book['data']);p=c.execute('SELECT * FROM prematch WHERE match_id=? ORDER BY id DESC LIMIT 1',(mid,)).fetchone()
            if not p or p['id']!=book['prematch_id']:outcomes=[('missing','Profil remplacé : réimporter un dossier cohérent avant-match',{})]
            else:
                b=json.loads(p['data']);history=c.execute('SELECT * FROM samples WHERE match_id=? AND id<=? ORDER BY id',(mid,sample['id'])).fetchall()
                prev=history[-2] if len(history)>1 else None
                for s in m['scenarios']:
                    st,why,detail=safe_evaluate(r,b,s,now,sample['received_at'],history)
                    if st=='candidate':
                        sustained=False
                        if prev:
                            old=json.loads(prev['data']);gap=(parse(sample['received_at'])-parse(prev['received_at'])).total_seconds()
                            ps,_,pd=safe_evaluate(old,b,s,now,prev['received_at'],history[:-1])
                            sustained=30<=gap<=100 and ps=='candidate' and old.get('score')==r.get('score') and 0<r['minute']-old.get('minute',999)<=2 and pd.get('quote',{}).get('line')==detail['quote']['line']
                        if not sustained:st,why='confirming','Attente de deux relevés concordants (30–100 s)'
                        else:
                            q=detail['quote'];label=market_label(s,q['line'],m)
                            context={'home':m['home'],'away':m['away'],'minute':r['minute'],'score':r['score'],'prematch':b,'comparison':detail,'scenario':s,'playbook_id':book['id'],'dossier':m['context'],'generated_at':book['generated_at'],'mode':'observation','market_label':label,'team':s['team'],'period':s['market']['period'],'analysis':{'review':{'summary':s['title']+' — conditions mesurées réunies. Exécution locale sans API.','reasons':[s['rationale']],'unknowns':m['context']['unknowns']}}}
                            # At most one signal per match/market/period/team, even after re-import or restart.
                            key=VERSION+':'+s['market']['type']+':'+s['market']['period']+':'+(s['team'] if s['market']['type']=='team_goals' else 'total')
                            c.execute('INSERT OR IGNORE INTO signals(match_id,sample_id,created_at,version,market,line,odds,context,delivery) VALUES(?,?,?,?,?,?,?,?,?)',(mid,sample['id'],stamp(),key,s['market']['type'],q['line'],q['odds'],json.dumps(context,ensure_ascii=False),'queued' if send else 'disabled'))
                            st,why='signal','Signal enregistré · '+s['title']
                    outcomes.append((st,why,detail))
        priority={'signal':10,'confirming':9,'price':8,'watch':7,'missing':6,'excluded':5,'stale':4,'waiting':1}
        st,why,detail=max(outcomes,key=lambda x:priority.get(x[0],0)) if outcomes else ('waiting','Aucun scénario retenu pour ce match',{})
        detail=dict(detail);detail['scenarios']=[{'status':o[0],'reason':o[1],'scenario_id':o[2].get('scenario_id'),'values':o[2].get('values',{})} for o in outcomes]
        c.execute('INSERT OR REPLACE INTO decisions VALUES(?,?,?,?,?,?)',(mid,sample['id'],stamp(),st,json.dumps([why],ensure_ascii=False),json.dumps(detail,ensure_ascii=False)))
    c.commit()
    if send:deliver(c,dt.datetime.now(UTC))
    c.close()

def deliver(c,now):
    for s in c.execute("SELECT * FROM signals WHERE delivery='queued' AND version LIKE ?",(VERSION+':%',)).fetchall():
        claimed=c.execute("UPDATE signals SET delivery='sending' WHERE id=? AND delivery='queued'",(s['id'],)).rowcount;c.commit()
        if not claimed:continue
        ctx=json.loads(s['context']);latest=c.execute('SELECT * FROM samples WHERE match_id=? ORDER BY id DESC LIMIT 1',(s['match_id'],)).fetchone()
        valid=latest and 0<=(now-parse(s['created_at'])).total_seconds()<=120
        if valid:
            r=json.loads(latest['data']);hist=c.execute('SELECT * FROM samples WHERE match_id=? ORDER BY id',(s['match_id'],)).fetchall()
            st,_,_=safe_evaluate(r,ctx['prematch'],ctx['scenario'],now,latest['received_at'],hist)
            valid=st=='candidate' and r.get('score')==ctx['score']
        if not valid:c.execute("UPDATE signals SET delivery='expired' WHERE id=?",(s['id'],));c.commit();continue
        msg=f"🧪 STRATEDGE · SCÉNARIO #{s['id']}\n{ctx['home']} — {ctx['away']} · {ctx['minute']}′\n{ctx['market_label']} · cote observée {s['odds']}\n{ctx['scenario']['title']}\nMesures : "+', '.join(f'{k}={v:.2f}' for k,v in ctx['comparison']['values'].items())+'\nExécution locale sans API · bet365 via Packball, pas une cote Stake.\nObservation : aucune rentabilité démontrée.'
        # Trois issues, et une seule est ambiguë :
        #   sent       Telegram a répondu ok:true
        #   failed     réponse CERTAINE qu'aucun message n'est parti : erreur
        #              HTTP (400 chat introuvable, 401 bot invalide…), JSON
        #              ok:false, ou configuration absente (rien n'a été tenté)
        #   uncertain  timeout, rupture réseau, réponse illisible : le message
        #              a pu partir. Jamais renvoyé automatiquement, puisque
        #              deliver() ne reprend que les signaux 'queued' — un renvoi
        #              aveugle risquerait un doublon sur le canal.
        token=os.environ.get('TELEGRAM_BOT_TOKEN');chat=os.environ.get('TELEGRAM_CHAT_ID')
        if not token or not chat:state='failed'
        else:
            try:
                req=urllib.request.Request('https://api.telegram.org/bot'+token+'/sendMessage',data=json.dumps({'chat_id':chat,'text':msg[:4000]}).encode(),headers={'Content-Type':'application/json'})
                with urllib.request.urlopen(req,timeout=10) as response:result=json.load(response)
                ok=result.get('ok') if isinstance(result,dict) else None
                state='sent' if ok is True else 'failed' if ok is False else 'uncertain'
            except urllib.error.HTTPError:state='failed'
            except Exception:state='uncertain'
        c.execute('UPDATE signals SET delivery=? WHERE id=?',(state,s['id']));c.commit()
