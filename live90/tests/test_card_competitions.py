import copy, unittest
import test_decision_v5 as F
import live_v4 as V
class Competitions(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 bundle=F.DeskTests.bundle
 prepare=F.DeskTests.prepare
 reading=F.DeskTests.reading
 add=F.DeskTests.add
 def test_selected_seven_country_and_league(self):
  cases=[('United States','Major League Soccer','USA · MLS'),('England','Premier League','Angleterre · Premier League'),('Spain','La Liga','Espagne · La Liga'),('Italy','Serie A','Italie · Serie A'),('Germany','Bundesliga','Allemagne · Bundesliga'),('France','Ligue 1','France · Ligue 1'),('Saudi Arabia','Pro League','Arabie saoudite · Saudi Pro League')]
  V.install_card_selection(self.c)
  for country,league,wanted in cases:
   p={'league':league,'packball':{'export_a':[country]}}
   self.assertEqual(V.selected_card_competition({'league':league},p),wanted)
   self.assertTrue(V.card_market_allowed(self.c,{'league':league},'h',self.now,p=p))
  self.assertEqual(len(V.radar_status(self.c)['card_leagues']),7)
 def test_no_other_countries_divisions_cups_youth_women_or_missing_country(self):
  V.install_card_selection(self.c)
  for country,league in [('Brazil','Serie A'),('Kenya','Premier League'),('Austria','Bundesliga'),('France','Ligue 2'),('England','Championship'),('Spain','La Liga 2'),('Saudi Arabia','Division 1'),('USA','MLS Next Pro'),('England','Premier League U21'),('France','Ligue 1 Women'),('England','FA Cup'),('','Premier League')]:
   p={'league':league,'packball':{'export_a':[country]}}
   self.assertFalse(V.card_market_allowed(self.c,{'league':league},'h',self.now,p=p),(country,league))
 def test_install_once_and_manual_removal_preserved(self):
  V.install_card_selection(self.c)
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['France · Ligue 1']},self.now)
  V.install_card_selection(self.c)
  self.assertEqual(V.radar_status(self.c)['card_leagues'],['France · Ligue 1'])
  self.assertEqual(V.radar_status(self.c)['card_policy'],'strict_allowlist')
 def test_real_import_country_used_without_context_or_extra_column(self):
  self.m['league']='Premier League';self.m['packball']={'export_a':['England','ENG','Premier League']}
  self.prepare();V.install_card_selection(self.c)
  self.c.execute('DELETE FROM v4_contexts');self.c.commit()
  self.assertTrue(self.reading('cards')['telegram_allowed'])
  self.assertEqual(self.reading('goals')['status'],'active')
 def test_stake_quote_cannot_bypass_owner_leagues(self):
  self.prepare();V.install_card_selection(self.c);r=V.sample_data(V.latest(self.c)[0])
  event={'event_id':'s','home':r['home'],'away':r['away'],'score':r['score'],'markets':[{'name':r['home']+' Total Bookings','canonical':'OTHER','period':'FULL_TIME','active':True,'selections':[{'name':'Over 0.5','line':.5,'odds':1.9,'active':True}]}]}
  V.runtime(self.c,'pulsescore_test',{'ok':True,'at':V.iso(self.now),'events':[event]});self.c.commit()
  self.assertFalse(self.reading('cards')['telegram_allowed']);self.assertEqual(self.reading('cards')['status'],'excluded')
 def test_turkey_netherlands_no_card_suggestions_or_new_observations(self):
  self.prepare();V.install_card_selection(self.c)
  for country,league in [('Turkey','Super Lig'),('Netherlands','Eredivisie')]:
   p=copy.deepcopy(self.m);p.update(league=league,usable=True,packball={'export_a':[country]})
   s=V.latest(self.c)[0];r=V.sample_data(s)
   ds=V.desk_advice(self.c,s,p,None,self.now)
   for d in ds:
    if d['market']=='cards':
     self.assertEqual(d['status'],'excluded');self.assertFalse(d['recommendation_allowed'])
     self.assertFalse(V.radar_eligible(r,d,self.now));self.assertIsNotNone(d['observed'])
   self.assertEqual(next(d for d in ds if d['market']=='goals' and d['team']=='h')['status'],'active')
  V.run_dashboard(self.db,self.now)
  self.assertEqual(self.c.execute("SELECT COUNT(*) FROM v5_observations WHERE market='cards'").fetchone()[0],0)
  self.assertGreater(self.c.execute("SELECT COUNT(*) FROM v5_observations WHERE market='goals'").fetchone()[0],0)
 def test_scheduled_match_and_live_match_share_card_scope(self):
  self.prepare();V.install_card_selection(self.c)
  self.c.execute('DELETE FROM samples');self.c.commit()
  b=V.board(self.c,self.now)
  self.assertTrue(b['matches'])
  self.assertTrue(all(d['status']=='excluded' for d in b['matches'][0]['advice'] if d['market']=='cards'))
 def test_removing_allowed_league_immediately_disables_cards_only(self):
  self.m['league']='Ligue 1';self.m['packball']={'export_a':['France']}
  self.prepare();V.install_card_selection(self.c)
  self.assertEqual(self.reading('cards')['status'],'active')
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':[]},self.now)
  self.assertEqual(self.reading('cards')['status'],'excluded')
  self.assertEqual(self.reading('goals')['status'],'active')

if __name__=='__main__':unittest.main()
