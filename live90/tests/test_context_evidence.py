"""Sourced home/away discipline and timing; no invented xG or league coverage."""
import copy
import test_decision_v5 as fixtures
import live_v4 as V
import unittest

class EvidenceTests(unittest.TestCase):
 setUp=fixtures.DeskTests.setUp
 tearDown=fixtures.DeskTests.tearDown
 bundle=fixtures.DeskTests.bundle
 add=fixtures.DeskTests.add
 prepare=fixtures.DeskTests.prepare
 reading=fixtures.DeskTests.reading
 def enrich(self):
  self.ctx['sources']=[{'id':'s','title':'Source de recette','url':'https://example.org/stats','checked_at':V.iso(self.before)}]
  self.ctx['referee']={'name':'Arbitre Test','appointment':'confirmed','appointment_source_ids':['s'],'note':'Test','stats':{'matches':20,'sample_label':'Saison test','yellow_per_match':4.7,'source_ids':['s']}}
  for side,scope in [('h','home'),('a','away')]:
   common={'matches':20,'sample_label':'Saison test','as_of':V.iso(self.before),'source_ids':['s']}
   self.ctx['teams'][side]['card_references']={x:dict(common,unit='yellow_cards',for_per_match=2.4 if x==scope else 1.8) for x in ('overall',scope,'h2h')}
   self.ctx['teams'][side]['goal_timing']={scope:dict(common,stoppage_policy='Temps additionnel dans les dernières tranches des mi-temps',bins=[{'start':i,'end':i+10,'scored':8 if i==20 else 1,'conceded':6 if i==20 else 1} for i in range(0,90,10)])}
 def test_roundtrip_context_and_message_with_both_teams_referee_h2h(self):
  self.enrich();self.prepare();d=self.reading('cards')
  self.assertEqual(d['baseline'],2.4);self.assertIn('domicile',d['baseline_origin'])
  r=V.sample_data(V.latest(self.c)[0]);msg=V.radar_message(r,d,{'quotes':[]})
  for text in ('Arbitre Test','4,7 jaunes','Équipe A','Équipe B','domicile 2,4','extérieur 2,4','H2H 1,8','n=20','Fautes au total : 9'):self.assertIn(text,msg)
  self.assertEqual(self.reading('goals')['context_evidence']['support'],'concordant')
 def test_bad_source_units_counts_cutoff_and_overlap_rejected(self):
  self.enrich();original=copy.deepcopy(self.ctx)
  for field,value in [('source_ids',['absent']),('as_of',V.iso(self.ko)),('unit','yellow_or_red'),('matches',0)]:
   self.ctx=copy.deepcopy(original);self.ctx['teams']['h']['card_references']['home'][field]=value
   with self.assertRaises(ValueError):V.validate_context(self.ctx,self.before)
  for change in ({'scored':.4},{'start':10},{'end':100}):
   self.ctx=copy.deepcopy(original);self.ctx['teams']['h']['goal_timing']['home']['bins'][2].update(change)
   with self.assertRaises(ValueError):V.validate_context(self.ctx,self.before)
 def test_timing_boundary_partial_small_overall_late_and_no_live_xg(self):
  self.enrich();ctx=dict(self.ctx,usable=True)
  self.assertEqual(V.context_evidence(ctx,'h','goals',30)['windows'][0]['start'],20)
  self.assertEqual(V.context_evidence(ctx,'h','goals',31)['windows'][0]['start'],30)
  for mode in ('small','partial','overall','late'):
   x=copy.deepcopy(ctx)
   if mode=='small':x['teams']['h']['goal_timing']['home']['matches']=3
   if mode=='partial':x['teams']['h']['goal_timing']['home']['bins'].pop()
   if mode=='overall':x['teams']['h']['goal_timing']['overall']=x['teams']['h']['goal_timing'].pop('home')
   if mode=='late':x['usable']=False
   self.assertNotEqual(V.context_evidence(x,'h','goals',30)['support'],'concordant',mode)
  self.prepare();self.assertIn('ExG Packball',str(self.reading('goals')['limits']))
 def test_history_alone_never_triggers(self):
  self.enrich();self.r['ind10']['shots10']['h']=1;self.r['ind10']['sot10']['h']=0;self.prepare()
  d=self.reading('goals');self.assertEqual(d['context_evidence']['support'],'concordant');self.assertEqual(d['status'],'neutral')
 def test_non_yellow_reference_not_used_as_yellow_baseline(self):
  self.enrich();self.ctx['teams']['h']['card_references']['home']['unit']='booking_points';self.prepare()
  self.assertIsNone(self.reading('cards')['baseline']);self.assertIn('points de sanction',str(self.reading('cards')['facts']))
 def test_cards_leagues_block_recommendations_but_keep_evidence_and_goals(self):
  self.enrich();self.prepare();d=self.reading('cards');self.assertEqual(d['status'],'excluded');self.assertFalse(d['telegram_allowed']);self.assertTrue(d['context_evidence']['lines'])
  self.assertEqual(self.reading('goals')['status'],'active')
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test women']},self.now)
  self.assertFalse(self.reading('cards')['telegram_allowed'])
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test']},self.now)
  self.assertTrue(self.reading('cards')['telegram_allowed'])
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':[]},self.now)
  self.assertFalse(self.reading('cards')['telegram_allowed'])
 def test_verified_current_team_market_can_authorize_without_league(self):
  self.prepare();r=V.sample_data(V.latest(self.c)[0])
  event={'event_id':'stake','home':r['home'],'away':r['away'],'score':r['score'],'markets':[{'name':r['home']+' Total Bookings','canonical':'OTHER','period':'FULL_TIME','active':True,'selections':[{'name':'Over 0.5','line':.5,'odds':1.9,'active':True}]}]}
  V.runtime(self.c,'pulsescore_test',{'ok':True,'at':V.iso(self.now),'events':[event]});self.c.commit()
  self.assertTrue(self.reading('cards')['telegram_allowed']);self.assertFalse(self.reading('cards','a')['telegram_allowed'])
  event['markets'][0]['name']='Total Bookings';V.runtime(self.c,'pulsescore_test',{'ok':True,'at':V.iso(self.now),'events':[event]});self.c.commit()
  self.assertFalse(self.reading('cards')['telegram_allowed'])

if __name__=='__main__':unittest.main()
