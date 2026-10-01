"""Observed Azerbaijan/Liechtenstein labels; active flags and score are synthetic.

The user's text does not supply the live score, cards or active flags. Tests set
those explicitly, without treating the pasted prices as currently available.
"""
import copy
import datetime as dt
import pathlib
import sys
import unittest
sys.path.insert(0,str(pathlib.Path(__file__).resolve().parents[1]/'server'))
import live_v4 as V

class BookingMarkets(unittest.TestCase):
 def setUp(self):
  self.now=dt.datetime(2026,10,1,17,20,tzinfo=V.UTC)
  self.event={'home':'Azerbaijan','away':'Liechtenstein','event_id':'test-event',
              'score':{'h':0,'a':0},'markets':[]}
  for name,canonical,line,odds in [('Azerbaijan Total Bookings','OTHER',1.5,1.5),('Liechtenstein Total Bookings','OTHER',3.5,1.4),('Azerbaijan Total','HOME_OVER_UNDER',.5,1.3),('Liechtenstein Total','AWAY_OVER_UNDER',.5,6.25)]:
   self.event['markets'].append({'name':name,'canonical':canonical,'period':'FULL_TIME','active':True,
       'selections':[{'name':f'Over {line}','line':line,'odds':odds,'active':True}]})
  self.saved={'home':self.event['home'],'away':self.event['away'],'score':{'h':0,'a':0},
      'decision':{'metrics':{'yellow_cards':1}},
      'discipline':{'red_cards':{'h':0,'a':0},'second_yellow':{'h':0,'a':0}}}
  self.signal={'market':'card_ft','team':'h','line':1.5}
 def quote(self):
  return V.pulse_signal_quote({'ok':True,'at':V.iso(self.now),'events':[self.event]},self.signal,self.saved,self.now)[0]
 def test_both_teams_with_their_current_card_counts(self):
  q=self.quote();self.assertEqual(q['odds'],1.5);self.assertEqual(q['unit'],'stake_bookings');self.assertIn('rouge = 2',q['settlement_note'])
  self.signal.update(team='a',line=3.5);self.saved['decision']['metrics']['yellow_cards']=3
  self.assertEqual(self.quote()['odds'],1.4)
  self.saved['decision']['metrics']['yellow_cards']=2;self.assertIsNone(self.quote())
  self.signal['line']=2.5;self.assertIsNone(self.quote()) # needs TWO more, not one
 def test_short_goal_names_keep_goal_prices_and_team_mapping(self):
  self.signal.update(market='goal_ft',line=.5)
  self.assertEqual(self.quote()['odds'],1.3)
  self.signal['team']='a';self.assertEqual(self.quote()['odds'],6.25)
 def test_match_totals_intervals_points_corners_and_winner_are_excluded(self):
  for name in ('Total Bookings','Booking 1x2','15 Minutes - Booking 1x2 from 61 to 75',
      '15 Minutes - Total Bookings from 61 to 75','Azerbaijan Total Bookings from 61 to 75',
      'Azerbaijan Total Booking Points','Azerbaijan Total Corners','Sending Off','Half Time Azerbaijan Total Bookings'):
   m=copy.deepcopy(self.event['markets'][0]);m['name']=name
   self.assertFalse(V.pulse_market_reading(self.event,m)['recognized'],name)
 def test_missing_discipline_or_expulsion_never_gets_booking_price(self):
  original=copy.deepcopy(self.saved)
  for mutation in ('missing','unknown_red','red','second_yellow','missing_yellow'):
   self.saved=copy.deepcopy(original)
   if mutation=='missing':self.saved.pop('discipline')
   elif mutation=='unknown_red':self.saved['discipline']['red_cards']['a']=None
   elif mutation=='red':self.saved['discipline']['red_cards']['a']=1
   elif mutation=='second_yellow':self.saved['discipline']['second_yellow']['h']=1
   else:self.saved['decision']['metrics']['yellow_cards']=None
   self.assertIsNone(self.quote(),mutation)
 def test_wrong_period_enum_suspension_and_non_half_lines(self):
  original=copy.deepcopy(self.event)
  for mutation in ('period','canonical','market_closed','selection_closed','quarter','integer','line_disagrees'):
   self.event=copy.deepcopy(original);m=self.event['markets'][0];s=m['selections'][0]
   if mutation=='period':m['period']='FIRST_HALF'
   elif mutation=='canonical':m['canonical']='HOME_OVER_UNDER'
   elif mutation=='market_closed':m['active']=False
   elif mutation=='selection_closed':s['active']=False
   elif mutation=='quarter':s.update(name='Over 1.75',line=1.75)
   elif mutation=='integer':s.update(name='Over 1',line=1)
   else:s['name']='Over 2.5'
   self.assertIsNone(self.quote(),mutation)

if __name__=='__main__':unittest.main()
