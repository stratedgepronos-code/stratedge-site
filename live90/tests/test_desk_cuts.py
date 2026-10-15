"""Only explicit, current team markets may explain a cut; no network calls."""
import copy,datetime as dt,pathlib,sys,unittest
from unittest.mock import patch
sys.path.insert(0,str(pathlib.Path(__file__).resolve().parents[1]/'server'))
import live_v4 as V
class CutTests(unittest.TestCase):
 def setUp(self):
  self.now=dt.datetime(2026,10,15,20,tzinfo=V.UTC)
  self.r={'home':'A','away':'B','state':'LIVE','minute':60,'score':{'h':0,'a':1},'received_at':V.iso(self.now),'stats':{'yellow_cards':{'h':0,'a':1},'red_cards':{'h':0,'a':0},'corners':{'h':3,'a':2}}}
  self.event={'home':'A','away':'B','event_id':'stake-event','score':{'h':0,'a':1},'markets':[]}
  self.result={'ok':True,'at':V.iso(self.now),'events':[self.event]}
 def market(self,name,canonical,line,period='FULL_TIME',odds=1.8):
  m={'name':name,'canonical':canonical,'period':period,'active':True,'selections':[{'name':f'Over {line}','line':line,'odds':odds,'active':True}]};self.event['markets'].append(m);return m
 def read(self):return V.desk_cuts(self.r,self.result,self.now)
 def test_each_team_cut_counts_required_events_and_keeps_bookings_conditional(self):
  self.market('A Total Bookings','OTHER',2.5)
  self.market('A Total Corners','CORNERS_OVER_UNDER',5.5)
  self.market('B Total Goals','AWAY_OVER_UNDER',1.5)
  with patch.object(V.urllib.request,'build_opener') as network:q=self.read()['quotes'];network.assert_not_called()
  cards,corners,goals=q
  self.assertEqual((cards['current'],cards['needed'],cards['unit']),(0,3,'stake_bookings'));self.assertTrue(cards['indicative_counter'])
  self.assertEqual((corners['team'],corners['current'],corners['needed']),('h',3,3));self.assertFalse(corners['indicative_counter'])
  self.assertEqual((goals['team'],goals['current'],goals['needed']),('a',1,1))
 def test_match_total_next_corner_and_timed_markets_are_never_team_cuts(self):
  for name in ['Total Corners','6th Corner','10 Minutes - A Total Corners from 61 to 70','A Total Corners (Bands)','A Total Goals After 75 Minutes']:
   self.market(name,'CORNERS_OVER_UNDER',5.5)
  self.assertEqual(self.read()['quotes'],[])
 def test_missing_unknown_and_suspended_quotes_are_not_offered(self):
  m=self.market('A Total Corners','CORNERS_OVER_UNDER',5.5)
  for value in (None,False):
   m['active']=value;self.assertEqual(self.read()['quotes'],[])
  m['active']=True;m['selections'][0]['active']=None;self.assertEqual(self.read()['quotes'],[])
  m['selections'][0]['active']=True;m['canonical']='HOME_OVER_UNDER';self.assertEqual(self.read()['quotes'],[])
 def test_identity_score_freshness_and_duplicate_event_checks(self):
  self.market('A Total Corners','CORNERS_OVER_UNDER',5.5)
  original=copy.deepcopy(self.result)
  for mutate in [lambda:self.event.update(score={'h':1,'a':1}),lambda:self.event.update(home='A Women'),lambda:self.event.update(event_id=''),lambda:self.result['events'].append(copy.deepcopy(self.event)),lambda:self.result.update(at=V.iso(self.now-dt.timedelta(seconds=61))),lambda:self.result.update(at=V.iso(self.now+dt.timedelta(seconds=1)))]:
   self.result=copy.deepcopy(original);self.event=self.result['events'][0];mutate();self.assertEqual(self.read()['quotes'],[])
 def test_red_cards_missing_yellows_and_asian_line_have_no_simple_gap(self):
  self.market('A Total Bookings','OTHER',2.5);self.r['stats']['red_cards']['a']=1
  self.assertIsNone(self.read()['quotes'][0]['needed'])
  self.r['stats']['red_cards']['a']=0;self.r['stats']['yellow_cards']['h']=None
  self.assertIsNone(self.read()['quotes'][0]['needed'])
  self.event['markets']=[];self.market('B Total Goals','AWAY_OVER_UNDER',1.25)
  q=self.read()['quotes'][0];self.assertIsNone(q['needed']);self.assertFalse(q['half_line'])
 def test_already_passed_line_is_not_the_next_event(self):
  self.market('A Total Corners','CORNERS_OVER_UNDER',2.5)
  self.assertEqual(self.read()['quotes'][0]['needed'],0)
 def test_contradictory_prices_are_rejected(self):
  self.market('A Total Corners','CORNERS_OVER_UNDER',5.5,odds=1.8)
  self.market('A Total Corners','CORNERS_OVER_UNDER',5.5,odds=2)
  self.assertEqual(self.read()['quotes'],[])
 def test_halftime_goals_are_separate_and_removed_in_second_half(self):
  self.market('Half Time A Total Goals','HOME_OVER_UNDER',.5)
  self.assertEqual(self.read()['quotes'],[]);self.r['minute']=30
  self.assertEqual(self.read()['quotes'][0]['period'],'HT')
 def test_stopped_or_ambiguous_packball_feed_does_not_attach_prices(self):
  self.market('A Total Corners','CORNERS_OVER_UNDER',5.5)
  for changes in ({'state':'HT'},{'received_at':V.iso(self.now-dt.timedelta(seconds=101))},{'quality_errors':['ambiguous']}):
   r=dict(self.r,**changes);self.assertEqual(V.desk_cuts(r,self.result,self.now)['quotes'],[])
if __name__=='__main__':unittest.main()
