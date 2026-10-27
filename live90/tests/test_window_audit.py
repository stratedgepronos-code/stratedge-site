import copy,datetime as dt,unittest
from unittest.mock import patch
import test_decision_v5 as fixtures
import live_v4 as V

class WindowAuditTests(unittest.TestCase):
 setUp=fixtures.DeskTests.setUp
 tearDown=fixtures.DeskTests.tearDown
 bundle=fixtures.DeskTests.bundle
 add=fixtures.DeskTests.add
 prepare=fixtures.DeskTests.prepare
 reading=fixtures.DeskTests.reading

 def test_optional_quality_is_neutralized_without_changing_goal_trigger(self):
  self.r['stats']['shots_inside']={'h':6,'a':0}
  self.r['ind10']['shots_inside10']={'h':2.5,'a':0}
  self.prepare();d=self.reading()
  self.assertEqual(d['status'],'active');self.assertIsNone(d['metrics']['shots_inside10'])
  q=d['shot_quality']['windows']['10']['shots_inside']
  self.assertEqual(q['value'],2.5);self.assertFalse(q['usable'])
  self.assertEqual(d['shot_quality']['provider_synchronization'],'unknown')

 def test_subwindows_nesting_and_surface_sum(self):
  for k in ('shots_inside','shots_outside'):self.r['stats'][k]={'h':10,'a':0}
  self.r['ind10']['shots_inside10']={'h':2,'a':0};self.r['ind5']['shots_inside5']={'h':3,'a':0}
  q=V.shot_quality_audit(self.r,[],'h')
  self.assertFalse(q['windows']['5']['shots_inside']['usable']);self.assertFalse(q['windows']['10']['shots_inside']['usable'])
  self.r['ind5'].pop('shots_inside5');self.r['ind10']['shots_inside10']['h']=4
  self.r['ind10']['shots_outside10']={'h':4,'a':0}
  q=V.shot_quality_audit(self.r,[],'h')
  self.assertFalse(q['windows']['10']['shots_inside']['usable']);self.assertFalse(q['windows']['10']['shots_outside']['usable'])

 def test_derived_and_provider_windows_not_silently_compared(self):
  self.r['stats']['shots_inside']={'h':5,'a':0}
  old=copy.deepcopy(self.r);old.update(minute=20,collected_at=V.iso(self.now-dt.timedelta(minutes=10)))
  old['stats']['shots_inside']['h']=2;old['stats']['shots']['h']=4
  history=[{'id':17,'data':V.enc(old),'received_at':old['collected_at']}]
  q=V.shot_quality_audit(self.r,history,'h')['windows']['10']['shots_inside']
  self.assertEqual(q['value'],3);self.assertEqual(q['anchor_id'],17);self.assertFalse(q['usable'])
  self.r['ind10'].pop('shots10')
  self.assertTrue(V.shot_quality_audit(self.r,history,'h')['windows']['10']['shots_inside']['usable'])
  history[0]['received_at']=V.iso(self.now+dt.timedelta(seconds=1))
  self.assertIsNone(V.recent(self.r,history,'shots_inside','h',10))

 def test_core_shots_and_sot_with_different_origins_do_not_confirm(self):
  old=copy.deepcopy(self.r);old.update(minute=20,collected_at=V.iso(self.now-dt.timedelta(minutes=10)))
  old['stats']['sot']['h']=1
  self.r['ind10'].pop('sot10')
  self.add(old,self.now-dt.timedelta(minutes=10));self.prepare()
  d=self.reading()
  self.assertEqual(d['status'],'missing')
  self.assertIn('non synchronisées',d['reason'])

 def test_two_confirmation_samples_frozen(self):
  self.prepare();d=self.reading();samples=d['confirmation']['samples']
  self.assertEqual(d['status'],'active');self.assertEqual(len(samples),2)
  self.assertLess(samples[0]['sample']['sample_id'],samples[1]['sample']['sample_id'])
  self.assertEqual(d['confirmation']['gap_seconds'],30)
  self.assertIn('shot_quality',samples[0]['reading'])
  self.assertNotIn('confirmation',samples[1]['reading']) # No recursive payload.
  with patch.object(V,'radar_configured',return_value=True),patch.object(V,'radar_enabled',return_value=True):
   r=V.sample_data(V.latest(self.c)[0]);V.queue_radar(self.c,r,d,self.now)
  saved=V.dec(self.c.execute('SELECT data FROM v5_radar_notifications').fetchone()[0])
  self.assertEqual(saved['initial_confirmation'],d['confirmation'])
  snap=V.learning_snapshot(r,d,self.profile,self.ctx,self.now,saved['initial_confirmation'])
  self.assertEqual(snap['initial_confirmation']['samples'],samples)

 def test_every_evaluated_sample_including_rejections_and_immutable_inputs(self):
  self.prepare()
  with patch.object(V,'notify') as send:
   V.run_dashboard(self.db,self.now);V.run_dashboard(self.db,self.now)
  send.assert_not_called()
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_window_audits').fetchone()[0],4)
  self.now+=dt.timedelta(seconds=30);self.r['ind10'].pop('shots10');self.r['ind5'].pop('shots5');self.add(self.r,self.now)
  V.run_dashboard(self.db,self.now)
  x=V.export_window_audit(self.c,{'day':self.now.date().isoformat()},self.now)
  self.assertEqual(x['total'],8);self.assertTrue(x['complete'])
  self.assertTrue(any(row['data']['status']=='missing' for row in x['readings']))
  self.assertTrue(any(row['data']['status']=='active' for row in x['readings']))
  self.assertTrue(x['inputs']);self.assertTrue(x['samples'])
  self.c.execute("UPDATE v4_profiles SET data='{}'")
  again=V.export_window_audit(self.c,{'day':self.now.date().isoformat()},self.now)
  self.assertEqual(again['inputs'],x['inputs'])

 def test_pagination_stable_ceiling_and_paris_day(self):
  at='2026-10-25T23:30:00+00:00'
  for i in range(501):self.c.execute('INSERT INTO v5_window_audits(sample_id,match_id,team,market,engine,created_at,data) VALUES(?,?,?,?,?,?,?)',(i,'123','h','goals','test',at,'{}'))
  first=V.export_window_audit(self.c,{'day':'2026-10-26'},self.now)
  self.assertEqual(first['returned'],500);self.assertFalse(first['complete']);self.assertIsNotNone(first['next_cursor'])
  self.c.execute('INSERT INTO v5_window_audits(sample_id,match_id,team,market,engine,created_at,data) VALUES(?,?,?,?,?,?,?)',(999,'123','h','goals','test',at,'{}'))
  last=V.export_window_audit(self.c,{'day':'2026-10-26','cursor':first['next_cursor'],'ceiling':first['ceiling']},self.now)
  self.assertEqual(last['returned'],1);self.assertEqual(last['total'],501);self.assertIsNone(last['next_cursor'])
  self.assertEqual(V.export_window_audit(self.c,{'day':'2026-10-25'},self.now)['total'],0)
  self.assertTrue(all(x is None for x in last['samples'].values())) # No fabricated raw snapshot.

if __name__=='__main__':unittest.main()
