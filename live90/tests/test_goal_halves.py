"""Historical half/scope references; recent live windows remain independent."""
import copy
import datetime as dt
import unittest
import test_decision_v5 as F
import live_v4 as V

class GoalHalvesTests(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 add=F.DeskTests.add
 reading=F.DeskTests.reading
 bundle=F.DeskTests.bundle
 def test_half_and_target_localizations_are_exact(self):
  ctx=dict(self.ctx,usable=True)
  h=ctx['teams']['h']['goal_halves']['home'];a=ctx['teams']['a']['goal_halves']['away']
  h['first_half']={'scored_per_match':1.1,'conceded_per_match':.6}
  a['first_half']={'scored_per_match':.7,'conceded_per_match':1.3}
  ctx['teams']['h']['goal_halves']['away']=dict(h,first_half={'scored_per_match':9,'conceded_per_match':9})
  e=V.context_evidence(ctx,'h','goals',30,period='first_half')
  self.assertEqual([x['rate'] for x in e['halves']],[1.1,1.3])
  self.assertEqual(e,V.context_evidence(ctx,'h','goals',40,period='first_half'))
  self.assertEqual([x['rate'] for x in V.context_evidence(ctx,'a','goals',30,period='first_half')['halves']],[.7,.6])
  self.assertEqual(V.context_evidence(ctx,'h','goals',45,period='first_half')['half'],'first_half')
  self.assertEqual([x['rate'] for x in V.context_evidence(ctx,'h','goals',46,period='second_half')['halves']],[1.2,.9])
 def test_missing_second_half_only_blocks_second_half(self):
  self.ctx['teams']['h']['goal_halves']['home']['second_half']=None
  self.prepare();self.assertEqual(self.reading()['status'],'active')
  self.r['minute']=60;self.now+=dt.timedelta(seconds=30);self.add(self.r,self.now)
  self.assertEqual(self.reading()['status'],'context_wait')
 def test_half_switch_restarts_confirmation(self):
  for side,scope in [('h','home'),('a','away')]:
   self.ctx['teams'][side]['goal_halves'][scope]['intervals']['46-60']={'scored_per_match':.5,'conceded_per_match':.5}
  self.prepare();self.c.execute('DELETE FROM samples');self.c.commit()
  self.add(dict(self.r,minute=45),self.now-dt.timedelta(seconds=30))
  self.add(dict(self.r,minute=46),self.now)
  self.assertEqual(self.reading()['status'],'missing') # New live window must fill after the pause.
  self.now+=dt.timedelta(minutes=9);self.add(dict(self.r,minute=55),self.now)
  self.assertEqual(self.reading()['status'],'confirming')
  self.now+=dt.timedelta(seconds=30);self.add(dict(self.r,minute=56),self.now)
  self.assertEqual(self.reading()['status'],'active')
 def test_message_uses_first_half_and_roundtrip_keeps_halves(self):
  self.prepare();d=self.reading();r=V.sample_data(V.latest(self.c)[0]);msg=V.radar_message(r,d,{'quotes':[]})
  self.assertIn('1re mi-temps',msg);self.assertIn('première mi-temps',msg);self.assertIn('0,8 buts marqués/m',msg);self.assertIn('0,6 buts encaissés/m',msg)
  self.assertNotIn('créneau',msg);self.assertNotIn('windows',d['context_evidence'])
  p,ctx=V.inputs(self.c,V.bind(self.c,r),self.now)
  self.assertEqual(ctx['teams']['h']['goal_halves']['home']['first_half']['scored_per_match'],.8)
 def test_legacy_complete_15_minute_bins_aggregate_without_interpolation(self):
  ctx=copy.deepcopy(self.ctx);ctx['usable']=True
  for side,scope in [('h','home'),('a','away')]:
   ref=ctx['teams'][side].pop('goal_halves')[scope]
   ref.pop('first_half');ref.pop('second_half')
   ref['bins']=[{'start':i,'end':i+15,'scored':4,'conceded':5} for i in range(0,90,15)]
   ctx['teams'][side]['goal_timing']={scope:ref}
  e=V.context_evidence(ctx,'h','goals',30,period='first_half')
  self.assertEqual(e['support'],'concordant');self.assertEqual([x['rate'] for x in e['halves']],[.6,.75])
  ctx['teams']['h']['goal_halves']={'home':None}
  self.assertNotEqual(V.context_evidence(ctx,'h','goals',30,period='first_half')['support'],'concordant')
 def test_legacy_cross_half_or_incomplete_bins_never_imputed(self):
  ctx=copy.deepcopy(self.ctx);ctx['usable']=True
  for side,scope in [('h','home'),('a','away')]:
   ref=ctx['teams'][side].pop('goal_halves')[scope]
   ref.pop('first_half');ref.pop('second_half')
   ref['bins']=[{'start':i,'end':i+10,'scored':9,'conceded':9} for i in range(0,90,10)]
   ctx['teams'][side]['goal_timing']={scope:ref}
  self.assertEqual(V.context_evidence(ctx,'h','goals',30,period='first_half')['halves'],[])
  ref=ctx['teams']['h']['goal_timing']['home']
  ref['bins']=[{'start':i,'end':i+15,'scored':9,'conceded':9} for i in (0,30,45,60,75)]
  self.assertNotIn('first_half',V.legacy_goal_halves({'home':ref})['home'])
 def test_no_alias_or_unsupported_scope_and_no_old_policy_alert(self):
  self.ctx['teams']['h']['goal_halves']['domicile']=self.ctx['teams']['h']['goal_halves'].pop('home')
  with self.assertRaisesRegex(ValueError,'goal_halves'):V.validate_context(self.ctx,self.before)
  self.ctx['teams']['h']['goal_halves']['home']=self.ctx['teams']['h']['goal_halves'].pop('domicile')
  self.prepare();d=self.reading();d['coherence']['policy']='prematch_live_v1'
  self.assertFalse(V.radar_eligible(V.sample_data(V.latest(self.c)[0]),d,self.now))

if __name__=='__main__':unittest.main()
