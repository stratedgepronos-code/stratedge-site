#!/usr/bin/env python3
"""Read-only linkage audit: no raw payloads, credentials, or notifications."""
import collections,datetime as dt,json,pathlib,sqlite3,sys,unicodedata
from zoneinfo import ZoneInfo

def norm(x):return ' '.join(''.join(c for c in unicodedata.normalize('NFKD',str(x).casefold()) if not unicodedata.combining(c)).split())
def parse(x):
 try:return dt.datetime.fromisoformat(x.replace('Z','+00:00'))
 except (ValueError,AttributeError):return None

def audit(path):
 c=sqlite3.connect(pathlib.Path(path).resolve().as_uri()+'?mode=ro',uri=True);c.row_factory=sqlite3.Row
 c.execute('PRAGMA query_only=ON')
 now=dt.datetime.now(dt.timezone.utc);day=now.astimezone(ZoneInfo('Europe/Paris')).date().isoformat()
 row=c.execute("SELECT value FROM v4_runtime WHERE key='active_packball_fixtures'").fetchone()
 active=set(json.loads(row[0])) if row else None
 fixtures=[dict(x) for x in c.execute('SELECT f.*,p.imported_at profile_at,x.imported_at context_at,x.generated_at context_generated FROM v4_fixtures f LEFT JOIN v4_profiles p USING(fixture) LEFT JOIN v4_contexts x USING(fixture)')]
 by_names=collections.defaultdict(list)
 for f in fixtures:by_names[(norm(f['home']),norm(f['away']))].append(f)
 imports=[]
 for row in c.execute('SELECT kind,created_at,data FROM v4_imports ORDER BY id DESC LIMIT 8'):
  bundle=json.loads(row['data']);matches=bundle.get('matches',[])
  dates=collections.Counter(str(m.get('kickoff',''))[:10] for m in matches)
  imports.append({'kind':row['kind'],'created_at':row['created_at'],'matches':len(matches),'kickoff_dates':dict(dates),'auxerre':[{'kickoff':m.get('kickoff'),'fixture':m.get('fixture')} for m in matches if norm(m.get('home'))=='auxerre']})
 latest=c.execute('SELECT s.match_id,s.received_at,s.data FROM samples s JOIN (SELECT match_id,MAX(id) id FROM samples GROUP BY match_id) m ON s.id=m.id ORDER BY s.id DESC LIMIT 1500')
 details=[];collectors=collections.Counter()
 for row in c.execute('SELECT received_at,payload FROM cycles ORDER BY rowid DESC LIMIT 6'):
  payload=json.loads(row['payload']);collectors[str(payload.get('collector_version'))]+=1
 for row in latest:
  r=json.loads(row['data']);ko=parse(r.get('kickoff_ts'))
  if not ko or ko.astimezone(ZoneInfo('Europe/Paris')).date().isoformat()!=day:continue
  names=(norm(r.get('home')),norm(r.get('away')))
  nearby=[]
  for f in by_names.get(names,[]):
   fko=parse(f['kickoff'])
   if fko and abs((fko-ko).total_seconds())<=172800:
    nearby.append({k:f.get(k) for k in ('fixture','kickoff','match_id','profile_at','context_at','context_generated')}|{'active':active is None or f['fixture'] in active,'offset_seconds':(fko-ko).total_seconds()})
  details.append({'match_id':row['match_id'],'home':r.get('home'),'away':r.get('away'),'kickoff_ts':r.get('kickoff_ts'),'state':r.get('state'),'received_at':row['received_at'],'candidates':nearby})
 c.close()
 return {'at':now.isoformat(),'day':day,'active_count':None if active is None else len(active),'fixture_count':len(fixtures),'profile_count':sum(bool(f['profile_at']) for f in fixtures),'imports':imports,'recent_collectors':dict(collectors),'matches':details[:100]}
if __name__=='__main__':print(json.dumps(audit(sys.argv[1]),ensure_ascii=False,separators=(',',':')))
