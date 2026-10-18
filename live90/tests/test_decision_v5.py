"""Decision desk: no outbound actions, conservative readings, retained evidence."""
import copy,datetime as dt,pathlib,unittest
from unittest.mock import patch
import test_v4 as legacy_fixture
import live_v4 as V
class DeskTests(unittest.TestCase):
 setUp=legacy_fixture.LiveV4Tests.setUp
 tearDown=legacy_fixture.LiveV4Tests.tearDown
 bundle=legacy_fixture.LiveV4Tests.bundle
 add=legacy_fixture.LiveV4Tests.add
 def prepare(self):
  self.r['stats'].update(corners={'h':0,'a':2},blocked_shots={'h':4,'a':1})
  self.r['ind10'].update(corners10={'h':0,'a':1},blocked_shots10={'h':2,'a':0})
  self.r['ind5'].update(corners5={'h':0,'a':0},fouls5={'h':2,'a':0})
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.before)
  V.import_bundle(self.c,'analyst',self.bundle('analyst'),self.before)
  self.add(dict(self.r,minute=29),self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
 def reading(self,kind='goals',team='h'):
  s=V.latest(self.c)[0];p,ctx=V.inputs(self.c,V.bind(self.c,V.sample_data(s)),self.now)
  return next(d for d in V.desk_advice(self.c,s,p,ctx,self.now) if d['market']==kind and d['team']==team)
 def test_production_entry_never_calls_senders_or_odds_and_keeps_evidence(self):
  self.prepare()
  with patch.object(V,'notify') as send,patch.object(V,'pulse_enrich') as odds,patch.object(V,'deliver_results') as result:
   V.run_dashboard(self.db,self.now);V.run_dashboard(self.db,self.now)
  send.assert_not_called();odds.assert_not_called();result.assert_not_called()
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_signals').fetchone()[0],0)
  events=V.desk_history(self.c);self.assertTrue(events);self.assertEqual(len(events),len({(o['match_id'],o['team'],o['market'],o['sample_id']) for o in events}))
  self.assertFalse(V.options(self.c)['telegram_enabled']);self.assertFalse(V.setting(self.c,'pulsescore_auto',True))
  self.assertEqual(events[0]['data']['kind'],'observation_not_bet');self.assertIn('profile',events[0]['data'])
  self.assertIn('run_dashboard(args.db)',(pathlib.Path(V.__file__).parent/'engine.py').read_text())
 def test_ingestion_is_not_locked_while_readings_are_computed(self):
  import sqlite3
  self.prepare();original=V.desk_advice
  def probe(*args,**kwargs):
   with sqlite3.connect(self.db,timeout=0) as other:
    other.execute("INSERT OR REPLACE INTO v4_settings VALUES('concurrent_ingest_probe','true')")
   return original(*args,**kwargs)
  with patch.object(V,'desk_advice',side_effect=probe):V.run_dashboard(self.db,self.now)
  self.assertTrue(V.setting(self.c,'concurrent_ingest_probe',False))
 def test_spike_is_not_confirmed_and_duplicate_timestamp_is_not_a_second_reading(self):
  self.prepare();self.c.execute('DELETE FROM samples');self.c.commit()
  quiet=copy.deepcopy(self.r);quiet['ind10']['sot10']['h']=0
  self.add(quiet,self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
  self.assertEqual(self.reading()['status'],'confirming')
  self.add(self.r,self.now);self.assertEqual(self.reading()['status'],'confirming')
 def test_corner_dynamics_work_without_inventing_baseline(self):
  self.prepare();self.assertNotIn('corners',V.DESK_MARKETS);self.assertEqual(len(V.board(self.c,self.now)['matches'][0]['advice']),4)
 def test_historical_ratio_alone_does_not_trigger(self):
  self.prepare();self.c.execute('DELETE FROM samples');self.c.commit()
  self.r['stats']['sot']['h']=8;self.r['ind10']['shots10']['h']=1;self.r['ind10']['sot10']['h']=0
  self.add(self.r,self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
  self.assertEqual(self.reading()['status'],'neutral');self.assertGreater(self.reading()['metrics']['sot_per_goal'],0)
 def test_cards_continue_after_existing_yellows_but_new_yellow_resets_confirmation(self):
  self.prepare();self.r['stats']['yellow_cards']['h']=3
  self.add(self.r,self.now-dt.timedelta(seconds=1));self.add(self.r,self.now+dt.timedelta(seconds=30));self.now+=dt.timedelta(seconds=30)
  self.assertEqual(self.reading('cards')['status'],'active')
  self.r['stats']['yellow_cards']['h']=4;self.now+=dt.timedelta(seconds=30);self.add(self.r,self.now)
  self.assertEqual(self.reading('cards')['status'],'confirming')
 def test_stale_expulsion_and_missing_data_never_highlight(self):
  self.prepare();s=V.latest(self.c)[0];r=V.sample_data(s)
  for state,change in [('stale',{'collected_at':V.iso(self.now-dt.timedelta(minutes=3))}),('waiting',{'state':'HT'}),('missing',{'quality_errors':['ambiguous']})]:
   d=V.desk_reading(dict(r,**change),self.profile,None,'h','goals',self.now,[]);self.assertEqual(d['status'],state)
  r['stats']['red_cards']['a']=1;self.assertEqual(V.desk_reading(r,self.profile,None,'h','corners',self.now,[])['status'],'caution')
 def test_recent_fouls_need_anchor_and_negative_correction_stays_unknown(self):
  self.prepare();r=copy.deepcopy(self.r);r['ind10'].pop('fouls10');r['ind5'].pop('fouls5')
  self.assertEqual(V.desk_reading(r,self.profile,None,'h','cards',self.now,[])['status'],'missing')
  old=copy.deepcopy(r);old['minute']=20;old['collected_at']=V.iso(self.now-dt.timedelta(minutes=10));old['stats']['fouls']['h']=20
  hist=[{'data':V.enc(old),'received_at':old['collected_at']}]
  self.assertIsNone(V.recent(r,hist,'fouls','h',10))
 def test_goal_resets_window(self):
  self.prepare();self.now+=dt.timedelta(seconds=30);self.r['score']['h']=1;self.add(self.r,self.now)
  self.assertEqual(self.reading()['status'],'missing')
 def test_sourced_corner_reference_validated_and_used(self):
  source={'id':'c','title':'Source de test','url':'https://example.org/corners','checked_at':V.iso(self.before)}
  self.ctx['sources']=[source];self.ctx['teams']['h']['corner_reference']={'matches':20,'sample_label':'Test','corners_for_ft':5.4,'corners_against_ft':4.1,'source_ids':['c']}
  self.prepare();V.validate_context(self.ctx,self.before);self.assertNotIn('corners',{x['market'] for x in V.board(self.c,self.now)['matches'][0]['advice']})
  self.ctx['teams']['h']['corner_reference']['source_ids']=['missing']
  with self.assertRaisesRegex(ValueError,'source corners'):V.validate_context(self.ctx,self.before)
 def test_actions_cannot_reenable_automatic_delivery(self):
  for payload in ({'action':'pulsescore_auto','enabled':True},{'action':'settings','settings':{'telegram_enabled':True,'min_odds':1.65}}):
   with self.assertRaisesRegex(ValueError,'Mode décision'):V.dispatch(self.c,payload,self.now)
 def test_export_observation_and_archived_history_remain_separate(self):
  self.prepare();V.run_dashboard(self.db,self.now)
  result=V.dispatch(self.c,{'action':'export_observations'},self.now)
  self.assertEqual(result['schema'],'stratedge.observations.v5');self.assertTrue(result['observations'])
  self.assertEqual(V.dispatch(self.c,{'action':'export_history'},self.now)['signals'],[])
 def test_missing_context_keeps_every_match_and_side(self):
  self.prepare();self.c.execute('DELETE FROM v4_contexts');self.c.commit()
  b=V.board(self.c,self.now);self.assertEqual(len(b['matches']),1);self.assertEqual(len(b['matches'][0]['advice']),4)
  self.assertEqual({d['team'] for d in b['matches'][0]['advice']},{'h','a'})
 def test_malformed_sample_does_not_break_other_markets(self):
  self.prepare();self.r['score']=[];self.add(self.r,self.now)
  self.assertTrue(all(d['status']=='missing' for d in V.board(self.c,self.now)['matches'][0]['advice']))
if __name__=='__main__':unittest.main()
