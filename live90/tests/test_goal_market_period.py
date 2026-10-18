"""Goal alert horizon must match its historical references and result target."""
import copy
import unittest
import test_decision_v5 as F
import live_v4 as V

class GoalMarketPeriod(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 add=F.DeskTests.add
 reading=F.DeskTests.reading
 bundle=F.DeskTests.bundle
 def full_time_only(self):
  for side,scope in [('h','home'),('a','away')]:
   ref=self.ctx['teams'][side]['goal_halves'][scope]
   ref['full_time']={'scored_per_match':1.7,'conceded_per_match':1.3}
   ref.pop('first_half');ref.pop('second_half')
 def test_ft_only_import_alert_and_telegram_without_halves(self):
  self.full_time_only();self.prepare();d=self.reading()
  self.assertEqual(d['status'],'active')
  self.assertEqual(d['coherence']['policy'],'prematch_live_period_v3')
  msg=V.radar_message(V.sample_data(V.latest(self.c)[0]),d,{'quotes':[]})
  self.assertIn('1,7 buts marqués/m',msg);self.assertIn('1,3 buts encaissés/m',msg)
  self.assertIn('match entier',msg);self.assertNotIn('mi-temps',msg)
 def test_ft_reference_does_not_change_at_halftime(self):
  self.full_time_only();ctx=dict(self.ctx,usable=True)
  self.assertEqual(V.context_evidence(ctx,'h','goals',30),V.context_evidence(ctx,'h','goals',60))
  self.assertEqual(V.context_evidence(ctx,'h','goals',30,period='first_half')['halves'],[])
 def test_explicit_ft_precedes_halves_and_zero_is_not_replaced(self):
  ref=self.ctx['teams']['h']['goal_halves']['home']
  ref['full_time']={'scored_per_match':0,'conceded_per_match':1}
  self.prepare();d=self.reading()
  self.assertEqual(d['status'],'context_conflict')
  self.assertEqual(d['context_evidence']['halves'][0]['rate'],0)
 def test_missing_ft_is_never_inferred_from_only_one_half(self):
  ref=self.ctx['teams']['h']['goal_halves']['home'];ref.pop('second_half')
  ctx=dict(self.ctx,usable=True)
  self.assertEqual(len(V.context_evidence(ctx,'h','goals',30)['halves']),1)
  self.assertEqual(len(V.context_evidence(ctx,'h','goals',30,period='first_half')['halves']),2)
 def test_ft_only_validation_rejects_invalid_numbers(self):
  self.full_time_only();original=copy.deepcopy(self.ctx)
  for value in (-1,31,'1.4',True,float('nan')):
   self.ctx=copy.deepcopy(original)
   self.ctx['teams']['h']['goal_halves']['home']['full_time']['scored_per_match']=value
   with self.assertRaises(ValueError):V.validate_context(self.ctx,self.before)
 def test_old_halves_policy_cannot_send_stored_active_reading(self):
  self.prepare();d=self.reading();d['coherence']['policy']='prematch_live_halves_v2'
  self.assertFalse(V.radar_eligible(V.sample_data(V.latest(self.c)[0]),d,self.now))

if __name__=='__main__':unittest.main()
