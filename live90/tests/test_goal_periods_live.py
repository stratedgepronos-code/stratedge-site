"""HT/FT settlement and localized half references with live momentum."""
import copy, datetime as dt, unittest
from unittest.mock import patch
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
 def test_sent_result_keeps_settling_without_interval_context(self):
  self.emit();self.c.execute('DELETE FROM v4_contexts');self.c.commit()
  self.twice(value=1)
  self.assertEqual(self.result()['outcome'],'observed');self.send.assert_called_once()
 def test_pre_upgrade_queued_goal_is_expired_without_sending(self):
  r=V.sample_data(V.latest(self.c)[0]);V.queue_radar(self.c,r,self.reading(),self.now)
  event=self.c.execute('SELECT * FROM v5_radar_notifications').fetchone()
  saved=V.dec(event['data']);saved['reading']['coherence'].update(interval_gate='required_concordant_interval_v2',interval_status='concordant')
  self.c.execute('UPDATE v5_radar_notifications SET data=? WHERE id=?',(V.enc(saved),event['id']));self.c.commit()
  V.deliver_radar(self.c,self.now)
  self.assertEqual(self.c.execute('SELECT delivery FROM v5_radar_notifications').fetchone()[0],'expired');self.send.assert_not_called()
 def test_cut_cannot_cross_periods_or_change_additional_goal_count(self):
  self.r['score']['h']=1;self.c.execute('DELETE FROM samples');self.c.commit()
  self.add(self.r,self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
  d=self.reading();r=V.sample_data(V.latest(self.c)[0])
  q={'team':'h','kind':'goals','period':'FT','half_line':True,'needed':1,'line':1.5,'current':1,'odds':1.9}
  self.assertIsNone(V.radar_quote(d,{'quotes':[q]}))
  q['period']='HT';self.assertIsNotNone(V.radar_quote(d,{'quotes':[q]}))
  t=V.radar_target(r,d,{'quotes':[]});self.assertEqual((t['period'],t['line']),('HT',1.5))

class HalfMomentum(unittest.TestCase):
 def setUp(self):
  D.DeskTests.setUp(self)
  for side,scope in [('h','home'),('a','away')]:self.ctx['teams'][side]['goal_halves'][scope].pop('intervals')
 tearDown=D.DeskTests.tearDown
 bundle=D.DeskTests.bundle
 add=D.DeskTests.add
 def prepare(self):
  self.c.execute('DELETE FROM v4_contexts');self.c.execute('DELETE FROM v4_imports');self.c.commit()
  D.DeskTests.prepare(self)
 reading=D.DeskTests.reading
 def test_user_example_confirms_and_queues_without_intervals(self):
  self.r['minute']=25
  self.ctx['teams']['h']['goal_halves']['home']['first_half']['scored_per_match']=1.3
  self.ctx['teams']['a']['goal_halves']['away']['first_half']['conceded_per_match']=1.1
  self.prepare();d=self.reading();r=V.sample_data(V.latest(self.c)[0])
  self.assertEqual(d['status'],'active');self.assertEqual(d['period'],'HT')
  self.assertEqual(d['coherence']['interval_status'],'not_used')
  self.assertTrue(V.radar_eligible(r,d,self.now))
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True},self.now)
  with patch.object(V,'radar_configured',return_value=True):V.queue_radar(self.c,r,d,self.now)
  self.c.commit()
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_radar_notifications').fetchone()[0],1)
  msg=V.radar_message(r,d,{'quotes':[]})
  self.assertIn('1re mi-temps',msg);self.assertIn('avant la pause',msg)
  self.assertNotIn('tranche',msg.lower());self.assertNotIn('15 min',msg)
 def test_intervals_never_change_decision_for_either_team_or_half(self):
  original=copy.deepcopy(self.ctx)
  for minute in (25,65):
   for team in ('h','a'):
    for mode in ('absent','zero','partial','flat','strong'):
     with self.subTest(minute=minute,team=team,mode=mode):
      self.ctx=copy.deepcopy(original);self.r['minute']=minute
      for side,scope in [('h','home'),('a','away')]:
       ref=self.ctx['teams'][side]['goal_halves'][scope]
       if mode!='absent':
        ref['intervals']={k:{'scored_per_match':0 if mode=='zero' else .2,'conceded_per_match':0 if mode=='zero' else .2} for k in V.GOAL_INTERVALS}
        if mode=='partial':ref['intervals']['16-30']['scored_per_match']=None
        if mode=='strong':ref['intervals']['16-30']={'scored_per_match':1,'conceded_per_match':1}
      self.r['ind10'].update(shots10={'h':6,'a':6},sot10={'h':3,'a':3})
      self.r['ind5'].update(shots5={'h':3,'a':3},sot5={'h':1,'a':1})
      self.prepare();d=self.reading(team=team)
      self.assertEqual(d['status'],'active');self.assertEqual(d['trigger_path'],'half_10m')
      self.assertEqual(d['context_evidence']['period'],'first_half' if minute<45 else 'second_half')
      self.assertTrue(V.radar_eligible(V.sample_data(V.latest(self.c)[0]),d,self.now))
 def test_missing_or_weak_current_half_still_blocks(self):
  original=copy.deepcopy(self.ctx)
  for minute,half in ((25,'first_half'),(65,'second_half')):
   for mode in ('missing','weak','wrong_scope'):
    with self.subTest(minute=minute,mode=mode):
     self.ctx=copy.deepcopy(original);self.r['minute']=minute
     ref=self.ctx['teams']['a']['goal_halves']['away']
     if mode=='missing':ref.pop(half)
     elif mode=='weak':ref[half]['conceded_per_match']=.1
     else:self.ctx['teams']['a']['goal_halves']['overall']=self.ctx['teams']['a']['goal_halves'].pop('away')
     self.prepare();d=self.reading()
     self.assertEqual(d['status'],'context_conflict' if mode=='weak' else 'context_wait')
     self.assertFalse(V.radar_eligible(V.sample_data(V.latest(self.c)[0]),d,self.now))
 def test_first_half_cannot_support_second_half_and_reverse(self):
  for minute,bad,good in ((65,'second_half','first_half'),(25,'first_half','second_half')):
   self.r['minute']=minute;ref=self.ctx['teams']['a']['goal_halves']['away']
   ref[good]['conceded_per_match']=2;ref[bad]['conceded_per_match']=.1
   self.prepare();self.assertEqual(self.reading()['status'],'context_conflict')
 def test_two_shot_shortcut_is_retired_even_with_strong_intervals(self):
  self.r['minute']=36;self.r['ind10'].update(shots10={'h':2,'a':1},sot10={'h':2,'a':0})
  self.r['ind5'].update(shots5={'h':2,'a':1},sot5={'h':2,'a':0})
  for side,scope in [('h','home'),('a','away')]:
   ref=self.ctx['teams'][side]['goal_halves'][scope]
   ref['intervals']={k:{'scored_per_match':.1,'conceded_per_match':.1} for k in V.GOAL_INTERVALS}
   ref['intervals']['31-45']={'scored_per_match':1,'conceded_per_match':1}
  self.prepare();self.assertEqual(self.reading()['status'],'neutral')
 def test_incomplete_cross_half_window_does_not_trigger(self):
  self.r['minute']=51;self.r['ind5'].update(shots5={'h':2,'a':1},sot5={'h':2,'a':0})
  self.prepare();self.assertEqual(self.reading()['status'],'missing')
 def test_old_active_policy_cannot_notify(self):
  self.r['minute']=25;self.prepare();d=self.reading();r=V.sample_data(V.latest(self.c)[0])
  self.assertTrue(V.radar_eligible(r,d,self.now))
  for policy in ('required_concordant_interval_v2','known_interval_veto_v1'):
   old=copy.deepcopy(d);old['coherence'].update(interval_gate=policy,interval_status='concordant')
   self.assertFalse(V.radar_eligible(r,old,self.now))
 def test_second_half_target_and_cutoff_unchanged(self):
  self.r['minute']=65;self.prepare();d=self.reading()
  self.assertEqual(d['period'],'FT');self.assertEqual(d['context_evidence']['period'],'second_half')
  msg=V.radar_message(V.sample_data(V.latest(self.c)[0]),d,{'quotes':[]})
  self.assertIn('2e mi-temps',msg);self.assertIn('avant la fin',msg);self.assertNotIn('tranche',msg)
  self.r['minute']=80;self.add(self.r,self.now);self.assertEqual(self.reading()['status'],'waiting')
 def test_export_tells_analyst_halves_not_intervals(self):
  self.prepare();out=V.export_analysis(self.c,self.now)
  self.assertIn('25/10/2026',out['instructions']);self.assertIn('ne sont plus utilisées',out['instructions'])
  self.assertNotIn('tranche actuelle de 15 minutes',out['instructions'])

if __name__=='__main__':unittest.main()
