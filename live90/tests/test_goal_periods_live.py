"""New HT horizon, period-aware outbox and measurable interval concordance."""
import copy, datetime as dt, unittest
import test_decision_v5 as D
import test_radar_results as R
import live_v4 as V

class HalfResults(unittest.TestCase):
 bundle=R.RadarResults.bundle
 add=R.RadarResults.add
 prepare=R.RadarResults.prepare
 reading=R.RadarResults.reading
 emit=R.RadarResults.emit
 advance=R.RadarResults.advance
 twice=R.RadarResults.twice
 result=R.RadarResults.result
 events=R.RadarResults.events
 tearDown=R.RadarResults.tearDown
 def setUp(self):
  R.RadarResults.setUp(self)
  self.c.execute('DELETE FROM samples');self.c.commit()
  self.r['minute']=35
  self.add(dict(self.r,minute=34),self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
 def test_ht_loss_does_not_turn_into_win_after_second_half_goal(self):
  t=self.emit();self.assertEqual(t['period'],'HT')
  self.twice(state='HT');self.assertEqual(self.result()['outcome'],'not_observed')
  self.assertIn('AVANT LA PAUSE',self.send.call_args.args[0])
  self.r['minute']=65;self.twice(value=1);self.assertEqual(self.result()['outcome'],'not_observed')
  self.twice(value=2,state='FT');self.assertEqual(self.result()['outcome'],'not_observed')
 def test_second_half_score_never_substitutes_for_missing_halftime(self):
  self.emit();self.r['minute']=65;self.twice(value=2)
  self.assertEqual(self.result()['outcome'],'pending')
  self.now+=dt.timedelta(hours=7);V.settle_radar_results(self.c,self.now)
  self.assertEqual(self.result()['outcome'],'unknown')
 def test_stoppage_goal_and_correction_are_tracked_in_first_half(self):
  self.emit();self.r.update(minute=45,minute_extra=2);self.twice(value=1)
  self.assertEqual(self.result()['outcome'],'observed')
  self.twice(value=0,state='HT');self.assertEqual(self.result()['outcome'],'not_observed')
 def test_ht_and_ft_same_counter_are_distinct_but_each_deduplicated(self):
  self.emit();self.twice(state='HT')
  self.now+=dt.timedelta(minutes=25);self.r.update(state='LIVE',minute=65)
  self.add(dict(self.r,minute=64),self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
  t=self.emit();self.assertEqual(t['period'],'FT')
  r=V.sample_data(V.latest(self.c)[0]);d=self.reading()
  V.queue_radar(self.c,r,d,self.now);self.c.commit()
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_radar_notifications').fetchone()[0],2)
 def test_queued_ht_expires_if_half_changes_even_with_same_score(self):
  r=V.sample_data(V.latest(self.c)[0]);V.queue_radar(self.c,r,self.reading(),self.now);self.c.commit()
  self.r['minute']=55;self.now+=dt.timedelta(seconds=30)
  self.add(dict(self.r,minute=54),self.now-dt.timedelta(seconds=20));self.add(self.r,self.now)
  V.deliver_radar(self.c,self.now)
  self.assertEqual(self.c.execute('SELECT delivery FROM v5_radar_notifications').fetchone()[0],'expired')
  self.send.assert_not_called()
 def test_cut_cannot_cross_periods_or_change_additional_goal_count(self):
  self.r['score']['h']=1;self.c.execute('DELETE FROM samples');self.c.commit()
  self.add(self.r,self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
  d=self.reading();r=V.sample_data(V.latest(self.c)[0])
  q={'team':'h','kind':'goals','period':'FT','half_line':True,'needed':1,'line':1.5,'current':1,'odds':1.9}
  self.assertIsNone(V.radar_quote(d,{'quotes':[q]}))
  q['period']='HT';self.assertIsNotNone(V.radar_quote(d,{'quotes':[q]}))
  t=V.radar_target(r,d,{'quotes':[]});self.assertEqual((t['period'],t['line']),('HT',1.5))

class Intervals(unittest.TestCase):
 setUp=D.DeskTests.setUp
 tearDown=D.DeskTests.tearDown
 bundle=D.DeskTests.bundle
 add=D.DeskTests.add
 prepare=D.DeskTests.prepare
 reading=D.DeskTests.reading
 def intervals(self,key='31-45'):
  for side,scope in [('h','home'),('a','away')]:
   ref=self.ctx['teams'][side]['goal_halves'][scope]
   ref['intervals']={k:{'scored_per_match':.1,'conceded_per_match':.1} for k in V.GOAL_INTERVALS}
   ref['intervals'][key]={'scored_per_match':.6,'conceded_per_match':.6}
 def test_two_sot_in_matching_interval_can_confirm_on_two_readings(self):
  self.intervals();self.r['minute']=36
  self.r['ind10'].update(shots10={'h':2,'a':1},sot10={'h':2,'a':0})
  self.r['ind5'].update(shots5={'h':2,'a':1},sot5={'h':2,'a':0})
  self.prepare();d=self.reading()
  self.assertEqual(d['status'],'active');self.assertEqual(d['trigger_path'],'interval_5m')
  self.assertEqual(d['metrics']['comparison_window'],5)
  self.assertEqual(d['interval_evidence']['interval'],'31-45')
  message=V.radar_message(V.sample_data(V.latest(self.c)[0]),d,{'quotes':[]})
  self.assertIn('5 dernières minutes',message);self.assertIn('31-45',message)
  self.assertIn('avant la pause',message)
 def test_rolling_window_crossing_interval_cannot_claim_interval_confirmation(self):
  self.intervals();self.r['minute']=34
  self.r['ind10'].update(shots10={'h':2,'a':1},sot10={'h':2,'a':0})
  self.r['ind5'].update(shots5={'h':2,'a':1},sot5={'h':2,'a':0})
  self.prepare();self.assertEqual(self.reading()['status'],'neutral')
 def test_unknown_scope_cannot_be_guessed_from_pairs_or_analyst_notes(self):
  self.m['packball']={'sample':{'venue_scope':'unknown'},'teams':{'h':{'goal_intervals':{k:{'scored':.6,'conceded':.5} for k in V.GOAL_INTERVALS}}}}
  self.ctx['teams']['h']['note']='Tranche 31-45 favorable'
  self.prepare();self.assertEqual(self.reading()['interval_evidence']['status'],'unknown')
 def test_matching_half_is_required_even_with_interval_and_live_spike(self):
  self.intervals();self.ctx['teams']['h']['goal_halves']['home'].pop('first_half')
  self.r['minute']=36;self.r['ind5']['sot5']['h']=2
  self.prepare();self.assertEqual(self.reading()['status'],'context_wait')
 def test_missing_zero_wrong_scope_and_malformed_intervals_never_invent_evidence(self):
  self.intervals();self.r['minute']=35
  ref=self.ctx['teams']['h']['goal_halves']['home']
  ref['intervals']['31-45']['scored_per_match']=0
  e=V.goal_interval_evidence(self.r,dict(self.m,usable=True),dict(self.ctx,usable=True),'h')
  self.assertEqual(e['status'],'not_elevated')
  ref['intervals'].pop('0-15');self.assertEqual(V.goal_interval_evidence(self.r,self.m,self.ctx,'h')['status'],'unknown')
  for value in (-1,True,'0.6',float('nan'),31):
   ref['intervals']['31-45']['scored_per_match']=value
   with self.assertRaises(ValueError):V.validate_context(self.ctx,self.before)
 def test_half_path_cannot_bypass_documented_weak_defensive_interval(self):
  self.intervals('16-30');self.r['minute']=18
  self.ctx['teams']['a']['goal_halves']['away']['intervals']['16-30']['conceded_per_match']=0
  self.prepare();d=self.reading()
  self.assertEqual(d['trigger_path'],'half_10m')
  self.assertEqual(d['status'],'context_conflict')
  self.assertEqual(d['coherence']['interval_status'],'not_elevated')
  self.assertIn('16-30',d['reason'])
  r=V.sample_data(V.latest(self.c)[0]);self.assertFalse(V.radar_eligible(r,d,self.now))
  V.queue_radar(self.c,r,d,self.now);self.c.commit()
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_radar_notifications').fetchone()[0],0)
 def test_half_path_accepts_matching_interval_and_labels_missing_interval(self):
  self.intervals('16-30');self.r['minute']=18
  self.prepare();d=self.reading()
  self.assertEqual(d['trigger_path'],'half_10m');self.assertEqual(d['status'],'active')
  msg=V.radar_message(V.sample_data(V.latest(self.c)[0]),d,{'quotes':[]})
  self.assertIn('mi-temps et tranche 16-30',msg)
  for side,scope in [('h','home'),('a','away')]:self.ctx['teams'][side]['goal_halves'][scope].pop('intervals')
  self.prepare();d=self.reading();self.assertEqual(d['status'],'active')
  msg=V.radar_message(V.sample_data(V.latest(self.c)[0]),d,{'quotes':[]})
  self.assertIn('Tranche de 15 min non confirmée',msg)
 def test_negative_interval_blocks_both_halves_without_affecting_cards(self):
  self.intervals('61-75');self.r['minute']=65
  self.ctx['teams']['a']['goal_halves']['away']['intervals']['61-75']['conceded_per_match']=.01
  self.prepare();self.assertEqual(self.reading()['status'],'context_conflict')
  d=self.reading('cards');self.assertNotIn('interval_gate',d.get('coherence',{}))
 def test_second_half_references_and_cutoff(self):
  self.r['minute']=65;self.prepare();d=self.reading()
  self.assertEqual(d['period'],'FT');self.assertEqual(d['context_evidence']['period'],'second_half')
  self.assertIn('avant la fin',V.radar_message(V.sample_data(V.latest(self.c)[0]),d,{'quotes':[]}))
  self.r['minute']=80;self.add(self.r,self.now);self.assertEqual(self.reading()['status'],'waiting')
 def test_early_second_half_short_window_does_not_require_cross_half_ten_minutes(self):
  self.intervals('46-60');self.r['minute']=51
  self.r['ind5'].update(shots5={'h':2,'a':1},sot5={'h':2,'a':0})
  self.prepare();d=self.reading()
  self.assertEqual(d['status'],'active');self.assertEqual(d['trigger_path'],'interval_5m')
  self.assertIsNone(d['metrics']['sot10']);self.assertEqual(d['period'],'FT')
  self.assertEqual(d['context_evidence']['period'],'second_half')

if __name__=='__main__':unittest.main()
