#!/usr/bin/env python3
"""SEUIL90 Live / experimental observation engine. No fitted probabilities, no legacy rules."""
import argparse, datetime as dt, json, math, os, sqlite3, time, unicodedata, urllib.request
from context_common import gate as context_gate, live_key, quota_pause
VERSION='s90-observation-context-2'
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

