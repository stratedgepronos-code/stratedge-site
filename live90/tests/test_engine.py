import sys, re
import copy, datetime as dt, importlib.util, json, pathlib, sqlite3, tempfile, unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'server'))
spec=importlib.util.spec_from_file_location('engine',ROOT/'tests/legacy_evaluate.py');E=importlib.util.module_from_spec(spec);spec.loader.exec_module(E)
class EngineTests(unittest.TestCase):
 def setUp(self):
  self.now=dt.datetime.now(dt.timezone.utc);self.ko=(self.now-dt.timedelta(minutes=60)).isoformat()
  self.b=dict(home='Alpha',away='Beta',kickoff=self.ko,recorded_at=(self.now-dt.timedelta(hours=2)).isoformat(),n_h=10,n_a=10,gf_h=1.5,gf_a=1.4,ga_h=1.3,ga_a=1.2,shots_h=10,shots_a=10,sot_h=4,sot_a=4)
  self.r=dict(packball_id='123',home='Alpha',away='Beta',kickoff_ts=self.ko,state='LIVE',minute=60,score={'h':0,'a':0},stats={'shots':{'h':11,'a':8},'sot':{'h':3,'a':2},'red_cards':{'h':0,'a':0},'second_yellow':{'h':0,'a':0}},ind10={'shots10':{'h':3,'a':2},'sot10':{'h':1,'a':1}},quotes=[{'market':'total_goals','period':'FT','side':'over','verified':True,'line':.5,'odds':1.9}])
 def evaluate(self,b=True,age=0):return E.evaluate(self.r,self.b if b else None,self.now,(self.now-dt.timedelta(seconds=age)).isoformat())[0]
 def test_candidate(self):self.assertEqual(self.evaluate(),'candidate')
 def test_missing_prematch(self):self.assertEqual(self.evaluate(False),'missing')
 def test_leakage(self):self.b['recorded_at']=self.now.isoformat();self.assertEqual(self.evaluate(),'missing')
 def test_wrong_match(self):self.b['home']='Another';self.assertEqual(self.evaluate(),'missing')
 def test_wrong_date(self):self.b['kickoff']=(self.now-dt.timedelta(days=1)).isoformat();self.assertEqual(self.evaluate(),'missing')
 def test_stale(self):self.assertEqual(self.evaluate(age=121),'stale')
 def test_zero_red_is_valid(self):self.assertEqual(self.evaluate(),'candidate')
 def test_red_missing(self):del self.r['stats']['red_cards'];self.assertEqual(self.evaluate(),'missing')
 def test_second_yellow(self):self.r['stats']['second_yellow']['h']=1;self.assertEqual(self.evaluate(),'excluded')
 def test_low_sample(self):self.b['n_a']=2;self.assertEqual(self.evaluate(),'missing')
 def test_wrong_line(self):self.r['quotes'][0]['line']=1.5;self.assertEqual(self.evaluate(),'price')
 def test_unverified_market(self):self.r['quotes'][0]['verified']=False;self.assertEqual(self.evaluate(),'price')
 def test_wrong_price(self):self.r['quotes'][0]['odds']=1.4;self.assertEqual(self.evaluate(),'price')
 def test_quiet_match(self):self.r['ind10']['shots10']={'h':1,'a':1};self.assertEqual(self.evaluate(),'watch')
 def test_no_sot(self):self.r['ind10']['sot10']=None;self.assertEqual(self.evaluate(),'missing')
 def test_incoherent_counters(self):self.r['stats']['sot']['h']=100;self.assertEqual(self.evaluate(),'missing')
 def test_malformed_stats(self):self.r['stats']=3;self.assertEqual(E.safe_evaluate(self.r,self.b,self.now,self.now.isoformat())[0],'missing')
 def test_nan(self):self.b['shots_h']=float('nan');self.assertEqual(self.evaluate(),'missing')
 def test_unknown_state(self):self.r['state']='OTHER';self.assertEqual(self.evaluate(),'waiting')
if __name__=='__main__':unittest.main()
