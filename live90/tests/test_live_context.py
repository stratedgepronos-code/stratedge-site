"""Observed live context, optional new columns, research isolation and target scope."""
import copy,datetime as dt,unittest
from unittest.mock import patch
import test_decision_v5 as F
import live_v4 as V

class LiveContext(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 add=F.DeskTests.add
 reading=F.DeskTests.reading
 bundle=F.DeskTests.bundle
 def quality(self,inside=3):
  self.r['stats']['shots_inside']={'h':5,'a':2}
  self.r['ind10']['shots_inside10']={'h':inside,'a':1}
 def test_optional_columns_enrich_without_changing_current_goal_trigger(self):
  self.quality();self.prepare();d=self.reading()
  self.assertEqual(d['status'],'active');self.assertEqual(d['situation']['quality'],'inside_observed')
  self.assertEqual(d['metrics']['shots_previous5'],3)
  self.assertTrue(d['research']['candidate']);self.assertEqual(d['research']['mode'],'shadow')
  self.assertEqual(d['situation']['remaining_regulation'],60)
 def test_old_collector_stays_compatible_without_inventing_quality(self):
  self.prepare();d=self.reading()
  self.assertEqual(d['status'],'active');self.assertEqual(d['situation']['quality'],'unknown')
  self.assertIsNone(d['research']['candidate']);self.assertIn('non exploitables',d['situation']['lines'][0])
 def test_shadow_reject_does_not_silently_change_live_decision(self):
  self.quality(0);self.prepare();d=self.reading()
  self.assertEqual(d['status'],'active');self.assertFalse(d['research']['candidate'])
  self.assertEqual(d['situation']['quality'],'no_inside_shots')
 def test_inconsistent_surface_count_not_used_as_quality(self):
  self.quality(5);self.r['ind10']['shots10']['h']=4;self.prepare();d=self.reading()
  self.assertEqual(d['situation']['quality'],'unknown');self.assertIsNone(d['research']['candidate'])
  self.assertTrue(any('supérieurs' in x for x in d['situation']['warnings']))
 def test_five_minute_windows_do_not_double_count(self):
  self.quality();self.r['ind5']['shots5']['h']=2;self.prepare();d=self.reading()
  self.assertEqual(d['metrics']['shots_previous5'],4);self.assertEqual(d['situation']['trend'],'falling')
  self.assertFalse(d['research']['candidate'])
 def test_cumulative_surface_deltas_need_real_anchor_and_ignore_negative_correction(self):
  r=copy.deepcopy(self.r);r['stats']['shots_inside']={'h':5,'a':2}
  old=copy.deepcopy(r);old['minute']=20;old['collected_at']=V.iso(self.now-dt.timedelta(minutes=10));old['stats']['shots_inside']['h']=2
  hist=[{'data':V.enc(old),'received_at':old['collected_at']}]
  self.assertEqual(V.recent(r,hist,'shots_inside','h',10),3)
  old['stats']['shots_inside']['h']=6;hist[0]['data']=V.enc(old)
  self.assertIsNone(V.recent(r,hist,'shots_inside','h',10))
  self.assertIsNone(V.recent(r,[],'shots_inside','h',10))
 def test_score_remaining_time_are_factual_not_probability(self):
  self.r['minute']=85;self.r['score']={'h':0,'a':2};self.prepare();d=self.reading()
  self.assertEqual(d['situation']['score_gap'],-2);self.assertEqual(d['situation']['remaining_regulation'],5)
  self.assertIn('retard de 2',d['situation']['clock_line']);self.assertNotIn('probability',d)
 def test_substitutions_do_not_invent_attacking_intent(self):
  self.r['stats']['substitutions']={'h':3,'a':1};self.r['ind10']['substitutions10']={'h':2,'a':0}
  self.prepare();d=self.reading();self.assertTrue(any('effet tactique non identifié' in x for x in d['situation']['lines']))
 def test_card_referee_cautions_are_visible_and_old_rule_is_recorded(self):
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test']},self.now)
  self.ctx['referee']['stats']['yellow_per_match']=2;self.prepare();d=self.reading('cards')
  self.assertEqual(d['status'],'active');self.assertFalse(d['research']['legacy_candidate'])
  r=V.sample_data(V.latest(self.c)[0]);msg=V.radar_message(r,d,{'quotes':[]})
  self.assertIn('À nuancer',msg);self.assertIn('aucun veto automatique',msg)
 def test_research_includes_non_candidates_once_without_sending(self):
  self.r['ind10']['shots10']['h']=1;self.r['ind10']['sot10']['h']=0;self.r['ind5']['shots5']['h']=1
  self.prepare()
  with patch.object(V,'notify') as notify:
   V.run_dashboard(self.db,self.now);V.run_dashboard(self.db,self.now)
  notify.assert_not_called()
  x=V.dispatch(self.c,{'action':'export_observations'},self.now)['research']
  rows=[r for r in x['readings'] if r['team']=='h' and r['market']=='goals']
  self.assertEqual(len(rows),1);self.assertFalse(rows[0]['data']['research']['baseline_candidate'])
  first=rows[0]['sample_id'];self.add(self.r,self.now+dt.timedelta(seconds=30));V.run_dashboard(self.db,self.now+dt.timedelta(seconds=30))
  row=self.c.execute("SELECT sample_id FROM v5_shadow_readings WHERE team='h' AND market='goals'").fetchone()
  self.assertEqual(row[0],first)
 def test_quote_for_multiple_events_cannot_become_result_target(self):
  self.prepare();d=self.reading();r=V.sample_data(V.latest(self.c)[0])
  quote={'team':'h','kind':'goals','period':'FT','half_line':True,'needed':3,'line':2.5,'odds':1.9,'current':0,'indicative_counter':False}
  cuts={'quotes':[quote]};t=V.radar_target(r,d,cuts)
  self.assertEqual(t['line'],.5);self.assertEqual(t['mode'],'event');self.assertIsNone(t['quote'])
  msg=V.radar_message(r,d,cuts);self.assertIn('Marché distinct, non soutenu',msg)
  self.assertIn('plus de 0,5 buts',msg)
 def test_old_policy_and_shadow_alone_cannot_send(self):
  self.prepare();d=self.reading();r=V.sample_data(V.latest(self.c)[0])
  d['coherence']['policy']='prematch_live_period_v3';self.assertFalse(V.radar_eligible(r,d,self.now))
  d['coherence']['policy']='prematch_live_context_v4';d['status']='neutral';d['research']['candidate']=True
  self.assertFalse(V.radar_eligible(r,d,self.now))

 def test_foul_windows_survive_goal_without_inventing_missing_samples(self):
  r=copy.deepcopy(self.r);r['minute']=69;r['score']={'h':2,'a':4}
  r['ind10'].pop('fouls10',None);r['ind5'].pop('fouls5',None)
  r['stats']['fouls']['h']=12
  hist=[]
  for minutes,total in [(10,8),(5,10),(2,11)]:
   old=copy.deepcopy(r);old['minute']=69-minutes;old['collected_at']=V.iso(self.now-dt.timedelta(minutes=minutes))
   old['score']={'h':2,'a':3};old['stats']['fouls']['h']=total
   hist.append({'data':V.enc(old),'received_at':old['collected_at']})
  self.assertEqual(V.recent(r,hist,'fouls','h',10),4)
  self.assertEqual(V.recent(r,hist,'fouls','h',5),2)
  self.assertIsNone(V.recent(r,hist,'shots','h',10))
  self.assertIsNone(V.recent(r,[],'fouls','h',10))
  r['stats']['fouls']['h']=7
  self.assertIsNone(V.recent(r,hist,'fouls','h',10))
  r['minute']=49
  self.assertIsNone(V.recent(r,hist,'fouls','h',10))
 def test_goal_still_requires_fresh_card_confirmation_after_score_change(self):
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test']},self.now)
  self.prepare();self.now+=dt.timedelta(seconds=30);self.r['score']['h']=1;self.add(self.r,self.now)
  self.assertEqual(self.reading('goals')['status'],'missing')
  self.assertEqual(self.reading('cards')['status'],'confirming')
  self.now+=dt.timedelta(seconds=30);self.add(self.r,self.now)
  self.assertEqual(self.reading('cards')['status'],'active')

if __name__=='__main__':unittest.main()
