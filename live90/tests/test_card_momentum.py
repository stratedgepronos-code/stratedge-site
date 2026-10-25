"""Measured foul bursts, honest contextual coverage and unchanged goal policy."""
import copy,datetime as dt,unittest
from unittest.mock import patch
import test_decision_v5 as F
import live_v4 as V

class CardMomentum(unittest.TestCase):
 def setUp(self):
  F.DeskTests.setUp(self)
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test']},self.now)
  self.r['ind5']['fouls5']={'h':3,'a':0}
  self.r['ind10']['fouls10']={'h':4,'a':1}
 tearDown=F.DeskTests.tearDown
 bundle=F.DeskTests.bundle
 add=F.DeskTests.add
 reading=F.DeskTests.reading
 def prepare(self):
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.before)
  V.import_bundle(self.c,'analyst',self.bundle('analyst'),self.before)
  self.add(dict(self.r,minute=self.r['minute']-1),self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
 def test_rapid_sequence_uses_five_minute_baseline_and_two_samples(self):
  self.prepare();d=self.reading('cards');r=V.sample_data(V.latest(self.c)[0])
  self.assertEqual(d['status'],'active');self.assertEqual(d['trigger_path'],'card_rapid_5m')
  self.assertEqual(d['metrics']['comparison_window'],5)
  self.assertTrue(any('Fautes équipe / 5′' in x for x in d['coherence']['lines']))
  self.assertEqual(d['metrics']['fouls_previous5'],1)
  self.assertEqual(d['situation']['trend'],'rising')
  msg=V.radar_message(r,d,{'quotes':[]})
  for text in ('Séquence rapide','3 fautes en 5 min','Arbitre Test','Possession cumulée','joueurs et motifs des fautes inconnus'):self.assertIn(text,msg)
  self.assertTrue(V.radar_eligible(r,d,self.now))
 def test_early_rapid_sequence_does_not_require_ten_minute_window(self):
  self.r['minute']=7;self.prepare();d=self.reading('cards')
  self.assertEqual(d['status'],'active');self.assertIsNone(d['metrics']['fouls10'])
  self.assertIsNone(d['metrics']['fouls_previous5']);self.assertEqual(d['situation']['trend'],'unknown')
  self.assertEqual(d['metrics']['comparison_window'],5)
 def test_rapid_does_not_bypass_pressure_or_missing_reference(self):
  self.r['stats']['possession']['h']=65;self.r['ind10']['shots10']['a']=0
  self.prepare();self.assertEqual(self.reading('cards')['status'],'neutral')
  self.ctx['referee']=None
  self.r['stats']['possession']['h']=40;self.prepare()
  self.assertEqual(self.reading('cards')['status'],'context_wait')
 def test_rapid_still_must_exceed_historical_foul_rate(self):
  self.ctx['teams']['h']['card_references']['home']['fouls_for_per_match']=60
  self.prepare();self.assertEqual(self.reading('cards')['status'],'context_conflict')
 def test_bad_nested_windows_are_blocked(self):
  self.r['ind5']['fouls5']['h']=5;self.prepare();d=self.reading('cards')
  self.assertEqual(d['status'],'missing');self.assertIn('incompatibles',d['reason'])
 def test_fractional_foul_count_is_not_an_event_count(self):
  self.r['ind5']['fouls5']['h']=3.5;self.prepare();self.assertEqual(self.reading('cards')['status'],'missing')
 def test_window_cannot_cross_half_or_start_before_five_minutes(self):
  for minute in (4,49):
   self.r['minute']=minute;self.prepare();self.assertEqual(self.reading('cards')['status'],'missing')
 def test_path_change_requires_fresh_confirmation(self):
  self.r['ind5']['fouls5']['h']=2;self.prepare()
  self.now+=dt.timedelta(seconds=30);self.r['ind5']['fouls5']['h']=3;self.add(self.r,self.now)
  self.assertEqual(self.reading('cards')['status'],'confirming')
  self.now+=dt.timedelta(seconds=30);self.add(self.r,self.now)
  self.assertEqual(self.reading('cards')['status'],'active')
 def test_old_card_queue_expires_and_does_not_send(self):
  self.prepare();d=self.reading('cards');r=V.sample_data(V.latest(self.c)[0])
  with patch.object(V,'radar_configured',return_value=True):V.queue_radar(self.c,r,d,self.now)
  row=self.c.execute('SELECT * FROM v5_radar_notifications').fetchone();saved=V.dec(row['data']);saved['reading'].pop('card_policy')
  self.c.execute('UPDATE v5_radar_notifications SET data=? WHERE id=?',(V.enc(saved),row['id']));self.c.commit()
  with patch.object(V,'radar_configured',return_value=True),patch.object(V,'notify') as send:
   V.deliver_radar(self.c,self.now);send.assert_not_called()
  self.assertEqual(self.c.execute('SELECT delivery FROM v5_radar_notifications').fetchone()[0],'expired')
 def test_context_is_sourced_descriptive_and_never_invents_player_events(self):
  self.m['packball']={'sample':{'venue_scope':'home_away'},'teams':{'h':{'possession_pct':55}}}
  flag={'kind':'weather','severity':'low','certainty':'reported','detail':'Prévision au stade à 20 h : pluie faible, bulletin émis à 08 h.','source_ids':['s']}
  self.ctx['teams']['h']['flags']=[flag];self.ctx['teams']['a']['flags']=[copy.deepcopy(flag)]
  self.prepare();d=self.reading('cards');context=d['card_context']
  self.assertEqual(d['status'],'active');self.assertEqual(len(context['weather']),1)
  self.assertTrue(context['coverage']['prematch_possession']);self.assertTrue(context['coverage']['prematch_weather'])
  for key in ('foul_type','repeat_player','live_weather'):self.assertFalse(context['coverage'][key])
  self.assertIn('55 %',context['possession_line'])
  msg=V.radar_message(V.sample_data(V.latest(self.c)[0]),d,{'quotes':[]})
  self.assertIn('non vérifiée en live',msg);self.assertIn('pluie faible',msg)
  unknown=copy.deepcopy(self.ctx);unknown['usable']=True
  for side in ('h','a'):unknown['teams'][side]['flags'][0]['certainty']='unknown'
  self.assertEqual(V.card_context(dict(self.m,usable=True),unknown,'h',d['metrics'])['weather'],[])
 def test_unknown_possession_scope_never_becomes_local_reference(self):
  p=dict(self.m,usable=True,packball={'sample':{'venue_scope':'unknown'},'teams':{'h':{'possession_pct':55}}})
  c=V.card_context(p,None,'h',{'possession':40})
  self.assertFalse(c['coverage']['prematch_possession']);self.assertNotIn('55',c['possession_line'])
 def test_cards_scope_cutoff_and_goal_threshold_unchanged(self):
  self.prepare();self.assertEqual(self.reading('goals')['status'],'active');self.assertEqual(V.GOAL_HALF_MIN_RATE,.8)
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':[],'disable_cards':True},self.now)
  self.assertEqual(self.reading('cards')['status'],'excluded')
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test']},self.now)
  self.r['minute']=80;self.add(self.r,self.now);self.assertEqual(self.reading('cards')['status'],'waiting')

if __name__=='__main__':unittest.main()
