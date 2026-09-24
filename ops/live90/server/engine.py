#!/usr/bin/env python3
"""SEUIL90 Live / experimental observation engine. No fitted probabilities, no legacy rules."""
import argparse, datetime as dt, json, math, os, sqlite3, time, unicodedata, urllib.request
VERSION='s90-observation-1'
UTC=dt.timezone.utc

def stamp(): return dt.datetime.now(UTC).isoformat()
def parse(t):
    try:
        d=dt.datetime.fromisoformat(t.replace('Z','+00:00'))
        return d if d.tzinfo else None
    except (ValueError,TypeError,AttributeError): return None

def num(v): return isinstance(v,(int,float)) and not isinstance(v,bool) and math.isfinite(v)
def pair(r,group,key):
    p=r.get(group,{}).get(key)
    return p if isinstance(p,dict) and all(num(p.get(s)) for s in ('h','a')) else None

def normalize(s): return ''.join(c for c in unicodedata.normalize('NFKD',s.casefold()) if not unicodedata.combining(c)).strip()

def evaluate(r,b,now,received):
    """Return status, explicit reasons, comparison. All thresholds are hypotheses."""
    details={}; errors=[]
    at=parse(received)
    if not at or not 0 <= (now-at).total_seconds() <= 120: return 'stale',['Relevé périmé'],details
    if r.get('state')!='LIVE': return 'waiting',['Match hors jeu en direct'],details
    if not b: return 'missing',['Avant-match absent'],details
    ko=parse(b.get('kickoff')); recorded=parse(b.get('recorded_at')); liveko=parse(r.get('kickoff_ts'))
    if not ko or not recorded or recorded>=ko or now<ko: errors.append('Avant-match non horodaté avant le coup d’envoi')
    if not liveko or not ko or abs((liveko-ko).total_seconds())>900: errors.append('Horaire du match non concordant')
    if any(normalize(str(b.get(k,'')))!=normalize(str(r.get(k,''))) for k in ('home','away')): errors.append('Équipes non concordantes')
    for f in ('n_h','n_a','gf_h','gf_a','ga_h','ga_a','shots_h','shots_a','sot_h','sot_a'):
        if not num(b.get(f)) or b[f]<0: errors.append('Avant-match incomplet : '+f)
    if errors:return 'missing',errors,details
    if min(b['n_h'],b['n_a'])<8:return 'missing',['Échantillon inférieur à 8 matchs par équipe'],details
    if b['shots_h']+b['shots_a']<=0:return 'missing',['Moyenne de tirs absente'],details
    for g,k in [('stats','shots'),('stats','sot'),('stats','red_cards'),('stats','second_yellow'),('ind10','shots10'),('ind10','sot10')]:
        if pair(r,g,k) is None:errors.append('Statistique manquante : '+k)
    score=r.get('score'); minute=r.get('minute')
    if not isinstance(score,dict) or not all(type(score.get(s)) is int and score[s]>=0 for s in ('h','a')): errors.append('Score illisible')
    if not num(minute):errors.append('Minute illisible')
    if r.get('quality_errors'): errors+=r['quality_errors']
    if errors:return 'missing',errors,details
    if sum(pair(r,'stats','red_cards').values())+sum(pair(r,'stats','second_yellow').values())>0:return 'excluded',['Expulsion : scénario non couvert'],details
    if not 55<=minute<=75:return 'waiting',['Fenêtre étudiée : 55–75 minutes'],details
    if score['h']+score['a']>2 or abs(score['h']-score['a'])>1:return 'waiting',['Score hors scénario étudié'],details
    def total(g,k):return sum(pair(r,g,k)[s] for s in ('h','a'))
    shots=total('stats','shots'); sot=total('stats','sot'); shots10=total('ind10','shots10'); sot10=total('ind10','sot10')
    if min(shots,sot,shots10,sot10)<0 or sot>shots or sot10>shots10 or shots10>shots or sot10>sot:return 'missing',['Compteurs incohérents'],details
    expected=(b['shots_h']+b['shots_a'])*minute/90
    ratio=shots/expected
    profile=(b['gf_h']+b['ga_h']+b['gf_a']+b['ga_a'])/2
    details={'shots_expected':round(expected,1),'shots_actual':shots,'activity_ratio':round(ratio,2),'prematch_goal_environment':round(profile,2),'shots10':shots10,'sot10':sot10}
    if profile<2.5 or ratio<1.15 or shots10<4 or sot10<1 or sot<4:return 'watch',['Dynamique insuffisante pour le protocole expérimental'],details
    line=score['h']+score['a']+.5
    quotes=[q for q in r.get('quotes',[]) if q.get('market')=='total_goals' and q.get('period')=='FT' and q.get('side')=='over' and q.get('verified') is True and q.get('line')==line and num(q.get('odds')) and 1.70<=q['odds']<=2.40]
    if not quotes:return 'price',['Cote vérifiée entre 1,70 et 2,40 absente pour Over '+str(line)],details
    quote=max(quotes,key=lambda q:q['odds']);details['quote']=quote
    return 'candidate',['Avant-match compatible, activité supérieure au repère, accélération sur 10 minutes'],details

def safe_evaluate(r,b,now,received):
    try: return evaluate(r,b,now,received)
    except (TypeError,ValueError,KeyError,AttributeError): return 'missing',['Structure de données invalide'],{}

def run(db,send=False):
    c=sqlite3.connect(db,timeout=10);c.row_factory=sqlite3.Row
    rows=c.execute('SELECT s.* FROM samples s JOIN (SELECT match_id,MAX(id) id FROM samples GROUP BY match_id) m ON s.id=m.id').fetchall()
    now=dt.datetime.now(UTC)
    for sample in rows:
        r=json.loads(sample['data']); p=c.execute('SELECT data FROM prematch WHERE match_id=? ORDER BY id DESC LIMIT 1',(sample['match_id'],)).fetchone(); b=json.loads(p[0]) if p else None
        status,reasons,detail=safe_evaluate(r,b,now,sample['received_at'])
        if status=='candidate':
            prev=c.execute('SELECT * FROM samples WHERE match_id=? AND id<? ORDER BY id DESC LIMIT 1',(sample['match_id'],sample['id'])).fetchone()
            sustained=False
            if prev:
                old=json.loads(prev['data']);gap=(parse(sample['received_at'])-parse(prev['received_at'])).total_seconds()
                ps,_,_=safe_evaluate(old,b,now,prev['received_at'])
                sustained=30<=gap<=100 and ps=='candidate' and old.get('score')==r.get('score') and old.get('minute',999)<r['minute']
            if not sustained:status='confirming';reasons=['En attente de deux relevés concordants, avec progression de la minute (30–100 s)']
            else:
                quote=detail['quote'];context={'home':r.get('home'),'away':r.get('away'),'minute':r['minute'],'score':r['score'],'prematch':b,'comparison':detail,'reasons':reasons,'bookmaker':'bet365 / Packball','mode':'observation'}
                c.execute('INSERT OR IGNORE INTO signals(match_id,sample_id,created_at,version,market,line,odds,context,delivery) VALUES(?,?,?,?,?,?,?,?,?)',(sample['match_id'],sample['id'],stamp(),VERSION,'total_goals_over_FT',quote['line'],quote['odds'],json.dumps(context,ensure_ascii=False),'queued' if send else 'disabled'))
                status='signal'
        c.execute('INSERT OR REPLACE INTO decisions VALUES(?,?,?,?,?,?)',(sample['match_id'],sample['id'],stamp(),status,json.dumps(reasons,ensure_ascii=False),json.dumps(detail,ensure_ascii=False)))
    c.commit()
    if send:
        for s in c.execute("SELECT * FROM signals WHERE delivery='queued'").fetchall():
            # Claim before sending: ambiguous failures stay visible, never blind resend.
            c.execute("UPDATE signals SET delivery='sending' WHERE id=? AND delivery='queued'",(s['id'],));c.commit()
            context=json.loads(s['context']);age=(now-parse(s['created_at'])).total_seconds()
            if age>120:c.execute("UPDATE signals SET delivery='expired' WHERE id=?",(s['id'],));c.commit();continue
            try:
                token=os.environ['TELEGRAM_BOT_TOKEN'];chat=os.environ['TELEGRAM_CHAT_ID']
                msg=f"🧪 SEUIL 90 · OBSERVATION #{s['id']}\n{context['home']} — {context['away']} · {context['minute']}′\nOver {s['line']} buts FT · cote observée {s['odds']}\nSource : bet365 via Packball, pas une cote Stake.\nAvant-match + accélération live concordants.\nProtocole non validé · aucune probabilité ni rentabilité démontrée."
                req=urllib.request.Request('https://api.telegram.org/bot'+token+'/sendMessage',data=json.dumps({'chat_id':chat,'text':msg}).encode(),headers={'Content-Type':'application/json'})
                with urllib.request.urlopen(req,timeout=10) as response: result=json.load(response)
                state='sent' if result.get('ok') else 'failed'
            except Exception:state='uncertain'
            c.execute('UPDATE signals SET delivery=? WHERE id=?',(state,s['id']));c.commit()
    c.close()

if __name__=='__main__':
    ap=argparse.ArgumentParser();ap.add_argument('--once',action='store_true');ap.add_argument('--db',default=os.environ.get('SE90_DB','/var/lib/stratedge/live90.sqlite'));args=ap.parse_args()
    while True:
        try:run(args.db,os.environ.get('SE90_TELEGRAM','0')=='1')
        except Exception as e:print('Live90 cycle failed:',type(e).__name__,flush=True)
        if args.once:break
        time.sleep(30)
