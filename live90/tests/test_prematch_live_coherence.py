"""No live-only alerts: independent before-match/live agreement is mandatory."""
import copy,datetime as dt,os,unittest
from unittest.mock import patch
import test_decision_v5 as F
import live_v4 as V
class Coherence(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 add=F.DeskTests.add
 reading=F.DeskTests.reading
 bundle=F.DeskTests.bundle
 def test_complete_goal_and_cards_references_pass(self):
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test']},self.now)
  self.prepare()
  for market in ('goals','cards'):
   d=self.reading(market);self.assertEqual(d['status'],'active');self.assertEqual(d['coherence']['status'],'confirmed')
   r=V.sample_data(V.latest(self.c)[0]);self.assertTrue(V.radar_eligible(r,d,self.now))
   text=V.radar_message(r,d,{'quotes':[]})
   self.assertIn('Avant-match + live concordants',text);self.assertIn('Pourquoi les données concordent',text)
 def test_live_only_never_notifies_and_match_stays_visible(self):
  self.prepare();self.c.execute('DELETE FROM v4_contexts');self.c.commit()
  b=V.board(self.c,self.now);self.assertEqual(len(b['matches']),1)
  self.assertEqual(self.reading('goals')['status'],'context_wait')
  with patch.dict(os.environ,{'TELEGRAM_BOT_TOKEN':'test','TELEGRAM_CHAT_ID':'test'}),patch.object(V,'notify') as send:
   V.run_dashboard(self.db,self.now)
  send.assert_not_called();self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_radar_notifications').fetchone()[0],0)
 def test_old_active_reading_without_coherence_cannot_bypass_gate(self):
  self.prepare();d=self.reading('goals');d.pop('coherence');r=V.sample_data(V.latest(self.c)[0])
  self.assertFalse(V.radar_eligible(r,d,self.now))
 def test_flat_live_never_triggers_even_with_strong_prematch(self):
  self.r['ind10']['shots10']['h']=1;self.r['ind10']['sot10']['h']=0;self.prepare()
  self.assertEqual(self.reading('goals')['status'],'neutral')
 def test_goal_weak_half_blocks_first_half(self):
  for side,scope in [('h','home'),('a','away')]:
   self.ctx['teams'][side]['goal_halves'][scope]['first_half'].update(scored_per_match=.2,conceded_per_match=.2)
  self.prepare();d=self.reading('goals');self.assertEqual(d['status'],'context_conflict');self.assertEqual(d['context_evidence']['period'],'first_half')
 def test_goal_overall_does_not_replace_localization(self):
  t=self.ctx['teams']['a']['goal_halves'];t['overall']=t.pop('away');self.prepare()
  d=self.reading('goals');self.assertEqual(d['status'],'context_wait');self.assertIn('domicile/extérieur',d['reason'])
 def test_goals_high_historical_shooting_requires_matching_live_pace(self):
  self.m['prematch']['shots_h']=90;self.m['prematch']['sot_h']=36;self.prepare()
  d=self.reading('goals');self.assertEqual(d['status'],'context_conflict');self.assertIn('rythme historique',d['reason'])
 def test_both_confirmation_samples_must_be_coherent(self):
  self.m['prematch']['shots_h']=54;self.prepare();self.c.execute('DELETE FROM samples');self.c.commit()
  old=copy.deepcopy(self.r);old['ind10']['shots10']['h']=5
  self.add(old,self.now-dt.timedelta(seconds=30));self.r['ind10']['shots10']['h']=7;self.add(self.r,self.now)
  self.assertEqual(self.reading('goals')['status'],'confirming')
  self.now+=dt.timedelta(seconds=30);self.add(self.r,self.now);self.assertEqual(self.reading('goals')['status'],'active')
 def test_cards_missing_fouls_referee_or_split_block(self):
  self.prepare();sample=V.sample_data(V.latest(self.c)[0]);p=dict(self.m,usable=True)
  for change in ('fouls','appointment','referee_fouls','referee_n','team_n','split'):
   ctx=copy.deepcopy(self.ctx);ctx['usable']=True
   if change=='fouls':ctx['teams']['h']['card_references']['home'].pop('fouls_for_per_match')
   if change=='appointment':ctx['referee']['appointment']='reported'
   if change=='referee_fouls':ctx['referee']['stats'].pop('fouls_per_match')
   if change=='referee_n':ctx['referee']['stats']['matches']=0
   if change=='team_n':ctx['teams']['h']['card_references']['home']['matches']=0
   if change=='split':ctx['teams']['h']['card_references']['h2h']=ctx['teams']['h']['card_references'].pop('home')
   d=V.desk_reading(sample,p,ctx,'h','cards',self.now,[])
   self.assertEqual(d['status'],'context_wait',change);self.assertTrue(d['coherence']['missing'])
 def test_cards_referee_and_foul_pace_can_disagree(self):
  self.prepare();sample=V.sample_data(V.latest(self.c)[0]);p=dict(self.m,usable=True)
  for change in ('referee_yellows','team_fouls','referee_fouls'):
   ctx=copy.deepcopy(self.ctx);ctx['usable']=True
   if change=='referee_yellows':ctx['referee']['stats']['yellow_per_match']=2
   if change=='team_fouls':ctx['teams']['h']['card_references']['home']['fouls_for_per_match']=45
   if change=='referee_fouls':ctx['referee']['stats']['fouls_per_match']=54
   d=V.desk_reading(sample,p,ctx,'h','cards',self.now,[])
   if change=='team_fouls':self.assertEqual(d['status'],'context_conflict',change)
   else:
    self.assertEqual(d['status'],'candidate',change)
    self.assertTrue(d['coherence']['cautions']);self.assertTrue(d['coherence']['legacy_conflicts'])
 def test_existing_yellows_do_not_create_debt_or_disable_new_card(self):
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test']},self.now)
  self.r['stats']['yellow_cards']['h']=4;self.prepare();self.assertEqual(self.reading('cards')['status'],'active')
 def test_new_fouls_field_optional_but_validated(self):
  original=copy.deepcopy(self.ctx)
  for value in (-1,101,'12',float('nan')):
   self.ctx=copy.deepcopy(original);self.ctx['teams']['h']['card_references']['home']['fouls_for_per_match']=value
   with self.assertRaisesRegex(ValueError,'fautes'):V.validate_context(self.ctx,self.before)
  self.ctx=copy.deepcopy(original);self.ctx['teams']['h']['card_references']['home'].pop('fouls_for_per_match')
  V.validate_context(self.ctx,self.before)
 def test_profile_late_blocks_even_if_context_exists(self):
  self.prepare();sample=V.sample_data(V.latest(self.c)[0]);d=V.desk_reading(sample,dict(self.m,usable=False),dict(self.ctx,usable=True),'h','goals',self.now,[])
  self.assertEqual(d['status'],'context_wait');self.assertIn('Profil Packball',d['reason'])
if __name__=='__main__':unittest.main()
