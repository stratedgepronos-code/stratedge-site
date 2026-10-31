"""Audit 30/31 October: integrity, not fitted predictive thresholds."""
import copy,datetime as dt,json,unittest,inspect
import test_decision_v5 as F
import test_card_momentum as C
import live_v4 as V

class Integrity(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 add=F.DeskTests.add
 bundle=F.DeskTests.bundle
 prepare=F.DeskTests.prepare
 reading=F.DeskTests.reading
 def guarded(self):return V.guarded_sample(self.c,V.latest(self.c)[0])
 def advance(self,minute,score,state='LIVE'):
  self.now+=dt.timedelta(seconds=30);self.r.update(minute=minute,score=score,state=state);self.add(self.r,self.now)
 def test_documented_rollbacks_stay_blocked_after_restart(self):
  for minute,first,second in [(45,{'h':1,'a':4},{'h':1,'a':2}),(13,{'h':2,'a':1},{'h':0,'a':0})]:
   with self.subTest(minute=minute):
    for t in ('samples','v5_stream_watermarks','v5_stream_incidents'):self.c.execute('DELETE FROM '+t)
    self.c.commit();self.advance(90,first);self.assertFalse(self.guarded()['continuity']['blocked'])
    self.now+=dt.timedelta(hours=6);self.advance(minute,second);self.assertTrue(self.guarded()['continuity']['blocked'])
    self.c.commit();self.c.close();self.c=V.connect(self.db)
    self.advance(minute+1,second);self.assertTrue(self.guarded()['continuity']['blocked'])
    p=dict(self.m,usable=True);ctx=dict(self.ctx,usable=True)
    ds=V.desk_advice(self.c,V.latest(self.c)[0],p,ctx,self.now)
    self.assertTrue(all(d['status'] not in ('active','candidate') for d in ds))
    self.assertEqual(self.c.execute('SELECT COUNT(*) FROM samples').fetchone()[0],3)
 def test_progression_extra_time_and_score_correction(self):
  for minute,state,score in [(44,'LIVE',0),(45,'LIVE',0),(45,'HT',0),(46,'LIVE',0),(55,'LIVE',1),(56,'LIVE',0),(90,'FT',0)]:
   self.advance(minute,{'h':score,'a':0},state)
   self.assertFalse(self.guarded()['continuity']['blocked'])
  incident=self.c.execute("SELECT data FROM v5_stream_incidents WHERE blocked=0").fetchone()
  self.assertEqual(V.dec(incident[0])['reason'],'score_correction_unattributed')
 def test_end_state_cannot_reopen_even_with_recent_capture(self):
  self.advance(90,{'h':1,'a':0},'FT');self.guarded()
  self.advance(90,{'h':1,'a':0});self.assertTrue(self.guarded()['continuity']['blocked'])
 def test_small_clock_correction_does_not_reset_eighty_cutoff(self):
  self.prepare();self.advance(80,{'h':0,'a':0});self.guarded()
  self.advance(79,{'h':0,'a':0});self.assertFalse(self.guarded()['continuity']['blocked'])
  self.assertEqual(self.reading()['status'],'waiting')
 def test_bounded_catchup_blocks_until_complete(self):
  for i in range(3):self.advance(20+i,{'h':0,'a':0})
  checked=V.prepare_stream(self.c,'123',limit=1)
  self.assertEqual(V.guarded_sample(self.c,V.latest(self.c)[0],checked)['continuity']['reason'],'continuity_catchup_pending')
  self.assertFalse(self.guarded()['continuity']['blocked'])
 def test_phase_regression_cannot_validate_an_old_ht_target(self):
  self.advance(35,{'h':0,'a':0});baseline=V.latest(self.c)[0]['id']
  self.advance(90,{'h':3,'a':1})
  self.advance(40,{'h':1,'a':0});self.advance(41,{'h':1,'a':0})
  event={'match_id':'123','sample_id':baseline,'team':'h','created_at':V.iso(self.now-dt.timedelta(minutes=3))}
  saved={'home':self.r['home'],'away':self.r['away'],'result_target':{'period':'HT','market':'goals','line':.5,'team':'h','baseline_sample_id':baseline}}
  self.assertIsNone(V.radar_result_verdict(self.c,event,saved,self.now))
 def test_six_historical_rapid_labels_repaired_without_changing_decision(self):
  for ident in (496,497,511,535,539,546):
   raw={'id':ident,'reading':{'window':'10 dernières minutes','trigger_path':'card_rapid_5m','metrics':{'comparison_window':5,'fouls10':None},'status':'active','observed':2}}
   fixed=V.audit_presentation(raw)
   self.assertEqual(fixed['reading']['window'],'5 dernières minutes');self.assertIsNone(fixed['reading']['metrics']['fouls10'])
   self.assertEqual(fixed['reading']['observed'],2);self.assertEqual(fixed['reading']['status'],'active');self.assertEqual(raw['reading']['window'],'10 dernières minutes')
 def test_revision_display_preserves_archive_without_claiming_success(self):
  for outcome in ('pending','unknown','not_observed','observed'):
   original={'radar_id':538,'revision':3,'outcome':outcome,'conclusion':'Résultat observé, cause non identifiée.'}
   fixed=V.review_presentation(original)
   self.assertEqual(fixed['revision'],3);self.assertEqual(fixed['archived_conclusion'],original['conclusion'])
   self.assertNotIn('Résultat observé',fixed['conclusion'])
   self.assertEqual(original['conclusion'],'Résultat observé, cause non identifiée.')
 def test_programme_dates_are_not_export_dates(self):
  now=V.date('2026-11-01T00:10:00Z')
  report=V.programme_dates([{'kickoff':'2026-10-31T20:00:00Z'},{'kickoff':'2026-10-31T23:30:00Z'}],now)
  self.assertEqual(report['fixture_dates'],{'2026-10-31':1,'2026-11-01':1});self.assertEqual(report['past_date_matches'],1)
 def test_coverage_does_not_claim_received_samples_were_evaluated(self):
  self.prepare();self.c.execute('INSERT INTO cycles VALUES(?,?,?,?)',('test',V.iso(self.now),V.iso(self.now),'{}'));self.c.commit()
  s=V.latest(self.c)[0];V.record_sample_audit(self.c,s,self.now,'evaluated',input_states=['context_absent'],reasons=['Profil absent']);self.c.commit()
  report=V.export_coverage(self.c,self.now.date().isoformat(),self.now);row=report['matches']['123']
  self.assertEqual((row['received'],row['evaluated'],row['received_not_processed']),(2,1,1))
  self.assertEqual(row['input_states']['context_absent'],1)
  self.assertIn('ne prouve pas',report['complete_means'])
 def test_coverage_plan_starts_with_cycles_not_all_sample_history(self):
  self.c.execute('CREATE UNIQUE INDEX test_cycle_samples ON samples(cycle_id,match_id)')
  query="SELECT s.id,s.match_id,s.received_at FROM cycles cy CROSS JOIN samples s ON s.cycle_id=cy.id WHERE cy.received_at>=? AND cy.received_at<? ORDER BY s.match_id,s.id"
  plan=[row[3] for row in self.c.execute('EXPLAIN QUERY PLAN '+query,('a','z'))]
  self.assertTrue(any('SEARCH s USING INDEX test_cycle_samples' in line for line in plan));self.assertFalse(any(line.startswith('SCAN s ') for line in plan))
  self.assertIn(query,inspect.getsource(V.export_coverage))
 def test_window_pages_are_bounded_and_have_explicit_scope(self):
  for i in range(3):self.c.execute('INSERT INTO v5_window_audits(sample_id,match_id,team,market,engine,created_at,data) VALUES(?,?,?,?,?,?,?)',(i,'123','h','goals','test',V.iso(self.now),'{}'))
  report=V.export_window_audit(self.c,{'day':self.now.date().isoformat(),'page_size':2},self.now)
  self.assertEqual(report['returned'],2);self.assertFalse(report['complete']);self.assertIn('pas surveillance continue',report['complete_means'])
  with self.assertRaises(ValueError):V.export_window_audit(self.c,{'page_size':501},self.now)

class CardLabel(unittest.TestCase):
 setUp=C.CardMomentum.setUp
 tearDown=C.CardMomentum.tearDown
 prepare=C.CardMomentum.prepare
 bundle=C.CardMomentum.bundle
 add=C.CardMomentum.add
 reading=C.CardMomentum.reading
 def test_five_minutes_label_and_missing_ten_remains_null(self):
  self.r['minute']=7;self.prepare();d=self.reading('cards')
  self.assertEqual(d['status'],'active');self.assertEqual(d['window'],'5 dernières minutes');self.assertIsNone(d['metrics']['fouls10'])
 def test_normal_card_path_remains_ten_minutes(self):
  self.r['ind5']['fouls5']['h']=2;self.prepare();d=self.reading('cards')
  self.assertEqual(d['window'],'10 dernières minutes');self.assertEqual(d['metrics']['comparison_window'],10)

if __name__=='__main__':unittest.main()
