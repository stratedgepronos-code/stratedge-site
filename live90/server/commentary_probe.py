"""Read-only, one bulk request for the live programme. Does not send alerts."""
import argparse
from collections import Counter, defaultdict
import datetime as dt
import json
import os
from pathlib import Path
import re
import sqlite3
import sys
import time
import unicodedata
from urllib import request, error, parse
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

UTC = dt.timezone.utc
MAX_BODY = 8 * 1024 * 1024
URL = 'https://apiv3.apifootball.com/'
NAME = r"[^\W\d_]+(?:[ ’'\-][^\W\d_]+){1,4}"
FOUL = re.compile(rf"(?:foul by|faute de) ({NAME})\s*\(([^()]+)\)[.!]?", re.I)
WARNING = re.compile(rf"({NAME})\s*\(([^()]+)\) (?:receives a verbal warning from the referee|reçoit un avertissement verbal de l.arbitre)[.!]?", re.I)


def norm(value):
    return ' '.join(''.join(ch for ch in unicodedata.normalize('NFKD', value.casefold()) if not unicodedata.combining(ch)).split())


def utc(value):
    try:
        t = dt.datetime.fromisoformat(value.replace('Z', '+00:00'))
        return t.astimezone(UTC) if t.tzinfo else None
    except (AttributeError, ValueError):
        return None


def eligible(conn, now):
    program = conn.execute("SELECT value FROM v4_runtime WHERE key='active_packball_fixtures'").fetchone()
    if not program or not isinstance(keys := json.loads(program[0]), list) or not keys:
        return []
    sql = """SELECT f.fixture,f.home,f.away,f.kickoff,f.league,r.data,s.data,s.received_at
         FROM v4_fixtures f JOIN v5_readings r ON r.match_id=f.match_id AND r.market='cards'
         JOIN samples s ON s.id=r.sample_id AND s.match_id=r.match_id
         WHERE f.fixture IN (SELECT value FROM json_each(?))
         AND s.id=(SELECT s2.id FROM samples s2 WHERE s2.match_id=f.match_id ORDER BY s2.id DESC LIMIT 1)"""
    out = {}
    for fid, h, a, ko, lg, reading, raw, received in conn.execute(sql, (program[0],)):
        r, s = json.loads(reading), json.loads(raw)
        times = [utc(s.get('collected_at')), utc(received)]
        minute = s.get('minute')
        if (r.get('recommendation_allowed') is not True or r.get('status') == 'excluded' or
            s.get('state') != 'LIVE' or type(minute) not in (int, float) or not 0 <= minute < 80 or
            not all(t and 0 <= (now-t).total_seconds() <= 120 for t in times) or
            minute + (now-times[0]).total_seconds()/60 >= 80 or not utc(ko)):
            continue
        if not all(isinstance(x,str) and x.strip() for x in (h,a,lg)): continue
        out[fid] = (lg,h,a,utc(ko))
    return list(out.values())


def local_kickoff(row, tz):
    try:
        naive = dt.datetime.strptime(row['match_date']+' '+row['match_time'], '%Y-%m-%d %H:%M')
        points = {naive.replace(tzinfo=tz,fold=i).astimezone(UTC) for i in (0,1)}
        # Refuse ambiguous and nonexistent local times on daylight saving transitions.
        return next(iter(points)) if len(points)==1 else None
    except (KeyError, TypeError, ValueError):
        return None


def facts(comments,h,a):
    found, seen = Counter(), set()
    for event in comments:
        if not isinstance(event,dict): continue
        time, line = event.get('time'),event.get('text')
        if not (isinstance(line,str) and line.strip() and len(line)<=1024 and
                isinstance(time,str) and re.fullmatch(r"\d{1,3}(?:\+\d{1,2})?(?::[0-5]\d|['′])?",time)): continue
        line=' '.join(line.split())
        key=(time,norm(line))
        if key in seen: continue
        seen.add(key)
        for label,regex in [('fautes_nominatives',FOUL),('rappels_verbaux_explicites',WARNING)]:
            m=regex.fullmatch(line)
            if m and norm(m[2]) in (norm(h),norm(a)): found[label]+=1
    found['commentaires_horodates']=len(seen)
    return found


def report(fixtures, payload, tz):
    if payload == []: payload={}
    if not isinstance(payload,dict) or any(k in payload for k in ('error','message','result')):
        raise ValueError('réponse fournisseur inattendue')
    feed = defaultdict(list)
    for item in payload.values():
        if not isinstance(item,dict) or not item.get('match_id'):
            raise ValueError('format match fournisseur inattendu')
        h,a = item.get('match_hometeam_name'),item.get('match_awayteam_name')
        if isinstance(h,str) and isinstance(a,str):
            feed[norm(h),norm(a),local_kickoff(item,tz)].append(item)
    counts = defaultdict(Counter)
    local_ids = Counter((norm(h),norm(a),ko) for _,h,a,ko in fixtures)
    for league,h,a,ko in fixtures:
        c=counts[league]; c['matchs_radar']+=1
        key=(norm(h),norm(a),ko);rows=feed.get(key,[])
        if len(rows)!=1 or local_ids[key]!=1: c['non_apparies']+=1; continue
        c['apparies']+=1
        comments=rows[0].get('live_comments')
        if not isinstance(comments,list) or not comments: c['sans_texte']+=1; continue
        f=facts(comments,h,a)
        if f['commentaires_horodates']==0: c['sans_commentaire_horodate_lisible']+=1; continue
        c['avec_texte']+=1
        c['matchs_avec_fautif_nomme']+=int(bool(f['fautes_nominatives']))
        c['matchs_avec_rappel_explicite']+=int(bool(f['rappels_verbaux_explicites']))
        c.update(f)
    return dict(counts)


class NoRedirect(request.HTTPRedirectHandler):
    def redirect_request(self,*_): return None


def fetch(key):
    data=parse.urlencode({'action':'get_live_odds_commnets','APIkey':key})
    req=request.Request(URL+'?'+data, headers={'Accept':'application/json','User-Agent':'StratEdge/coverage-1'})
    with request.build_opener(NoRedirect).open(req, timeout=12) as response:
        raw=response.read(MAX_BODY+1)
    if len(raw)>MAX_BODY: raise ValueError('réponse trop volumineuse')
    return json.loads(raw)


def main():
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--db', default=os.getenv('SE90_DB','/var/lib/stratedge/live90.sqlite'))
    ap.add_argument('--timezone',default=os.getenv('SE90_COMMENTARY_TZ'),help='fuseau confirmé des coups d’envoi renvoyés par la source')
    ap.add_argument('--cycles',type=int,default=1,help='1 à 90 passages espacés de 60 secondes ; arrêt à la première erreur')
    args=ap.parse_args()
    if not 1 <= args.cycles <= 90: ap.error('--cycles : de 1 à 90')
    for index in range(args.cycles):
        if index: time.sleep(60)
        once(args)


def once(args):
    now=dt.datetime.now(UTC)
    try:
        with sqlite3.connect(Path(args.db).resolve().as_uri()+'?mode=ro',uri=True,timeout=2) as c:
            fixtures=eligible(c,now)
        if fixtures:
            key=os.getenv('SE90_APIFOOTBALL_COM_KEY')
            if not key or not args.timezone: sys.exit('Configurer la clé APIfootball.com et le fuseau horaire fournisseur sur le serveur.')
            tz=ZoneInfo(args.timezone)
            data=report(fixtures,fetch(key),tz)
        else: data={}
        print(json.dumps({'at':now.isoformat(),'mode':'observation','appels':int(bool(fixtures)),'ligues':data},ensure_ascii=False),flush=True)
    except error.HTTPError as e:
        sys.exit(f'HTTP {e.code} du fournisseur ; aucun nouvel essai dans ce passage.')
    except (error.URLError,TimeoutError,OSError,ValueError,sqlite3.Error,ZoneInfoNotFoundError):
        sys.exit('Lecture impossible : source, schéma, horloge ou connexion à vérifier. Aucun envoi.')
if __name__=='__main__': main()
