#!/usr/bin/env python3
"""Read-only replay of archived decision anchors; no imports, engine loop or send."""
import argparse,json,pathlib,sqlite3,sys
sys.path.insert(0,str(pathlib.Path(__file__).resolve().parents[1]/'live90/server'))
import live_v4 as V

def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--db',required=True)
    parser.add_argument('--radar-ids',required=True)
    args=parser.parse_args()
    ids=[int(x) for x in args.radar_ids.split(',')]
    if not ids or len(ids)>100 or any(x<=0 for x in ids):raise ValueError('1 à 100 identifiants positifs requis')
    c=sqlite3.connect(pathlib.Path(args.db).resolve().as_uri()+'?mode=ro',uri=True)
    c.row_factory=sqlite3.Row;c.execute('PRAGMA query_only=ON');c.execute('BEGIN')
    try:
        rows=[V.dec(r['data'],{}) for r in c.execute('SELECT data FROM v5_learning_reviews WHERE radar_id IN ('+','.join('?' for _ in ids)+') ORDER BY radar_id',ids)]
        proofs=V.export_decision_proofs(c,rows)
        summaries=[]
        for review in rows:
            snapshot=review.get('known_at_signal') or {};reading=snapshot.get('reading') or {}
            signal=next((x for x in proofs['window_replays'] if x['radar_id']==review['radar_id'] and x['role']=='signal'),{})
            checks={key:{'status':check['status'],'issues':check['issues'],'anchor_id':check['anchor_id'],
                        'archived_count':check['archived_count'],'recomputed_count':check['recomputed_count'],
                        'elapsed_seconds':(check.get('timing') or {}).get('elapsed_seconds')} for key,check in signal.get('checks',{}).items()}
            # The comparison uses archived context only, never today's analyst import.
            shadow_reading=dict(reading,window_quality={k:dict(q,timing=(signal.get('checks',{}).get(k) or {}).get('timing')) for k,q in (reading.get('window_quality') or {}).items()})
            summaries.append({'radar_id':review['radar_id'],'outcome':review.get('outcome'),'engine':snapshot.get('engine'),
                              'sample_id':signal.get('sample_id'),'checks':checks,
                              'duration_shadow':V.foul_duration_shadow(shadow_reading,snapshot.get('context'),reading.get('team'))})
        print('LIVE90_WINDOW_REPLAY '+json.dumps({'read_only':True,'changes_notifications':False,'reviews_found':len(rows),
              'missing_review_ids':sorted(set(ids)-{r['radar_id'] for r in rows}),
              'referenced_samples':len(proofs['referenced_sample_ids']),'unavailable_sample_ids':proofs['unavailable_sample_ids'],
              'historical_readings_without_window_metadata':sum(not x['window_metadata_available'] for x in proofs['window_replays']),
              'results':summaries},ensure_ascii=False,separators=(',',':')))
    finally:c.rollback();c.close()

if __name__=='__main__':main()
