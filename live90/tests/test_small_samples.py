"""Available samples do not bypass live coherence, freshness or missing references."""
import copy, unittest
import test_decision_v5 as F
import live_v4 as V

class SmallSamples(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 add=F.DeskTests.add
 reading=F.DeskTests.reading
 bundle=F.DeskTests.bundle
 def configure(self,n):
  self.m['league']=self.ctx['league']='Champions League'
  self.m['packball']={'export_a':['Europe']}
  self.m['prematch'].update(n_h=n,n_a=n)
  for side,scope in [('h','home'),('a','away')]:
   for field in ('goal_halves','card_references'):
    self.ctx['teams'][side][field][scope]['matches']=n
  self.ctx['referee']['stats']['matches']=n
 def test_one_match_refs_and_live_can_activate_uefa_cards_and_goals(self):
  self.configure(1);self.prepare();V.install_card_selection(self.c)
  for market in ('goals','cards'):
   d=self.reading(market)
   self.assertEqual(d['status'],'active')
   self.assertEqual(d['coherence']['sample_policy'],'available_positive_samples_v1')
   self.assertIn('Échantillon limité',d['coherence']['cautions'][0])
   r=V.sample_data(V.latest(self.c)[0])
   self.assertTrue(V.radar_eligible(r,d,self.now))
   msg=V.radar_message(r,d,{'quotes':[]})
   self.assertIn('Échantillon limité',msg);self.assertIn('n=1',msg)
 def test_three_matches_do_not_override_quiet_live(self):
  self.configure(3);self.r['ind10']['shots10']['h']=1;self.r['ind10']['sot10']['h']=0
  self.r['ind10']['fouls10']['h']=2
  self.prepare();V.install_card_selection(self.c)
  for market in ('goals','cards'):
   d=self.reading(market);self.assertEqual(d['status'],'neutral')
   self.assertFalse(V.radar_eligible(V.sample_data(V.latest(self.c)[0]),d,self.now))
 def test_nine_matches_still_require_two_distinct_samples(self):
  self.configure(9);self.prepare();V.install_card_selection(self.c)
  last=V.latest(self.c)[0]['id'];self.c.execute('DELETE FROM samples WHERE id<>?',(last,));self.c.commit()
  for market in ('goals','cards'):self.assertEqual(self.reading(market)['status'],'confirming')
 def test_missing_packball_count_does_not_become_one(self):
  self.configure(3);self.prepare();r=V.sample_data(V.latest(self.c)[0])
  for n in (None,0):
   p=copy.deepcopy(self.m);p['usable']=True;p['prematch']['n_h']=n
   ctx=dict(self.ctx,usable=True)
   e=V.context_evidence(ctx,'h','goals',30)
   c=V.prematch_live_coherence(r,p,ctx,'h','goals',{'shots10':6,'sot10':2},e)
   self.assertEqual(c['status'],'missing');self.assertTrue(any('effectif historique Packball' in x for x in c['missing']))
 def test_ten_matches_no_small_sample_warning(self):
  self.configure(10);self.prepare();V.install_card_selection(self.c)
  for market in ('goals','cards'):
   d=self.reading(market);self.assertEqual(d['status'],'active')
   self.assertFalse(any('Échantillon limité' in x for x in d['coherence']['cautions']))

if __name__=='__main__':unittest.main()
