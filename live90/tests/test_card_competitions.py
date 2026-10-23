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
 def test_selected_eleven_country_and_league(self):
  cases=[('United States','Major League Soccer','USA · MLS'),('England','Premier League','Angleterre · Premier League'),('Spain','La Liga','Espagne · La Liga'),('Italy','Serie A','Italie · Serie A'),('Germany','Bundesliga','Allemagne · Bundesliga'),('France','Ligue 1','France · Ligue 1'),('Saudi Arabia','Pro League','Arabie saoudite · Saudi Pro League')]
  V.install_card_selection(self.c)
  for country,league,wanted in cases:
   p={'league':league,'packball':{'export_a':[country]}}
   self.assertEqual(V.selected_card_competition({'league':league},p),wanted)
   self.assertTrue(V.card_market_allowed(self.c,{'league':league},'h',self.now,p=p))
  self.assertEqual(len(V.radar_status(self.c)['card_leagues']),11)
 def test_uefa_region_aliases_and_both_teams(self):
  V.install_card_selection(self.c)
  for country in ('EUR','Europe','UEFA'):
   for league,wanted in [('UEFA Champions League','UEFA · Champions League'),('Champions League','UEFA · Champions League'),('Ligue des champions','UEFA · Champions League'),('UEFA Europa League','UEFA · Europa League'),('Europa League','UEFA · Europa League'),('Ligue Europa','UEFA · Europa League')]:
    p={'league':league,'packball':{'export_a':[country]}}
    self.assertEqual(V.selected_card_competition({},p),wanted)
    for team in ('h','a'):
     self.assertTrue(V.card_market_allowed(self.c,{},team,self.now,p=p),(country,league,team))
 def test_other_continents_cups_and_ambiguous_names_remain_excluded(self):
  V.install_card_selection(self.c)
  for country,league in [('Africa','Champions League'),('Asia','Champions League'),('','Champions League'),('Asia','Conference League'),('', 'Conference League'),('EUR','Conference League Women'),('EUR','UEFA Conference League U19'),('EUR',"UEFA Women's Champions League"),('EUR','UEFA Youth League'),('EUR','Champions League Women'),('EUR','UEFA Nations League'),('EUR','UEFA Champions League U19')]:
   self.assertFalse(V.card_market_allowed(self.c,{'country':country,'league':league},'h',self.now),(country,league))
 def test_upgrade_adds_only_uefa_and_does_not_reset_owner_settings(self):
  for k,v in [('card_selection_20261018',True),('card_leagues',['France · Ligue 1','Custom League']),('card_league_policy','strict_allowlist'),('radar_telegram_enabled',False)]:
   self.c.execute('INSERT OR REPLACE INTO v4_settings VALUES(?,?)',(k,V.enc(v)))
  self.c.commit()
  V.install_card_selection(self.c)
  self.assertEqual(V.setting(self.c,'card_leagues',[]),['France · Ligue 1','Custom League',*V.UEFA_CARD_COMPETITIONS,'UEFA · Conference League','Australie · A-League Men'])
  self.assertFalse(V.radar_enabled(self.c))
  V.dispatch(self.c,{'action':'radar_telegram','enabled':False,'card_leagues':['France · Ligue 1']},self.now)
  V.install_card_selection(self.c)
  self.assertEqual(V.setting(self.c,'card_leagues',[]),['France · Ligue 1'])
  self.assertEqual(V.setting(self.c,'card_league_policy',''),'strict_allowlist')
 def test_uefa_allowed_in_live_and_scheduled_board(self):
  self.m['league']='UEFA Champions League';self.m['packball']={'export_a':['Europe']}
  self.prepare();V.install_card_selection(self.c)
  self.assertTrue(self.reading('cards')['telegram_allowed'])
  self.assertNotEqual(self.reading('cards')['status'],'excluded')
  self.c.execute('DELETE FROM samples');self.c.commit()
  for d in V.board(self.c,self.now)['matches'][0]['advice']:
   if d['market']=='cards':self.assertNotEqual(d['status'],'excluded')
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
  self.assertEqual(self.reading('goals')['status'],'context_wait')
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
   ds=V.desk_advice(self.c,s,p,dict(self.ctx,usable=True),self.now)
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
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':[],'disable_cards':True},self.now)
  self.assertEqual(self.reading('cards')['status'],'excluded')
  self.assertEqual(self.reading('goals')['status'],'active')

 def test_empty_recovery_once_preserves_later_disable(self):
  V.install_card_selection(self.c)
  self.c.execute('INSERT OR REPLACE INTO v4_settings VALUES(?,?)',('card_leagues','[]'));self.c.commit()
  V.repair_empty_card_selection(self.c)
  self.assertEqual(V.radar_status(self.c)['card_leagues'],list(V.CARD_COMPETITIONS))
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':[],'disable_cards':True},self.now)
  V.repair_empty_card_selection(self.c)
  self.assertEqual(V.radar_status(self.c)['card_leagues'],[])
 def test_empty_recovery_preserves_nonempty_selection_and_telegram(self):
  V.install_card_selection(self.c)
  V.dispatch(self.c,{'action':'radar_telegram','enabled':False,'card_leagues':['France · Ligue 1']},self.now)
  V.repair_empty_card_selection(self.c)
  self.assertEqual(V.radar_status(self.c)['card_leagues'],['France · Ligue 1'])
  self.assertFalse(V.radar_enabled(self.c))
 def test_empty_save_requires_explicit_disable(self):
  V.install_card_selection(self.c)
  with self.assertRaisesRegex(ValueError,'confirme explicitement'):
   V.dispatch(self.c,{'action':'radar_telegram','enabled':False,'card_leagues':[]},self.now)
  self.assertEqual(V.radar_status(self.c)['card_leagues'],list(V.CARD_COMPETITIONS))
  self.assertTrue(V.radar_enabled(self.c))
 def test_stale_form_cannot_erase_newer_settings(self):
  V.install_card_selection(self.c)
  previous=V.radar_status(self.c)['card_leagues']
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['France · Ligue 1'],'expected_card_leagues':previous},self.now)
  with self.assertRaisesRegex(ValueError,'sélection a changé'):
   V.dispatch(self.c,{'action':'radar_telegram','enabled':False,'card_leagues':previous,'expected_card_leagues':previous},self.now)
  self.assertEqual(V.radar_status(self.c)['card_leagues'],['France · Ligue 1'])
  self.assertTrue(V.radar_enabled(self.c))

 def test_uefa_fixture_accepts_documented_domestic_references(self):
  self.m['league']='Champions League';self.m['packball']={'export_a':['Europe']}
  self.ctx['league']='Champions League'
  for side,scope,label in [('h','home','Ligue 1, domicile, dix matchs officiels synthétiques'),('a','away','Premier League, extérieur, dix matchs officiels synthétiques')]:
   for field in ('card_references','goal_halves'):
    self.ctx['teams'][side][field][scope].update(matches=10,sample_label=label)
  self.prepare();V.install_card_selection(self.c)
  for market in ('cards','goals'):
   d=self.reading(market)
   self.assertEqual(d['status'],'active')
   self.assertEqual(d['coherence']['status'],'confirmed')
 def test_domestic_analyst_reference_does_not_inflate_packball_sample(self):
  self.m['league']='Champions League';self.m['packball']={'export_a':['Europe']}
  self.m['prematch']['n_h']=4;self.m['prematch']['n_a']=7
  self.prepare();V.install_card_selection(self.c)
  d=self.reading('goals')
  self.assertEqual(d['status'],'active')
  self.assertEqual(self.m['prematch']['n_h'],4)
  self.assertEqual(self.m['prematch']['n_a'],7)
  self.assertTrue(any('Packball domicile n=4' in reason and 'extérieur n=7' in reason for reason in d['coherence']['cautions']))


 def test_conference_aliases_both_teams_and_live_board(self):
  V.install_card_selection(self.c)
  for country in ('EUR','Europe','UEFA'):
   for league in ('Europa Conference League','UEFA Europa Conference League','UEFA Conference League','Conference League','Ligue Conférence','Ligue Europa Conference'):
    p={'country':country,'league':league}
    self.assertEqual(V.selected_card_competition({},p),'UEFA · Conference League')
    for team in ('h','a'):
     self.assertTrue(V.card_market_allowed(self.c,{},team,self.now,p=p))
  self.m['league']=self.ctx['league']='Europa Conference League'
  self.m['packball']={'export_a':['EUR']}
  self.prepare()
  d=self.reading('cards')
  self.assertEqual(d['status'],'active')
  self.assertTrue(d['telegram_allowed'])
  self.assertTrue(V.radar_eligible(V.sample_data(V.latest(self.c)[0]),d,self.now))

 def test_existing_install_adds_conference_once_without_resetting_selection(self):
  V.install_card_selection(self.c)
  self.c.execute("DELETE FROM v4_settings WHERE key='card_selection_conference_20261022'")
  for k,v in [('card_leagues',['France · Ligue 1','Custom League']),('radar_telegram_enabled',False),('radar_priced_only',True)]:
   self.c.execute('INSERT OR REPLACE INTO v4_settings VALUES(?,?)',(k,V.enc(v)))
  self.c.commit()
  V.install_card_selection(self.c)
  self.assertEqual(V.setting(self.c,'card_leagues',[]),['France · Ligue 1','Custom League','UEFA · Conference League'])
  self.assertFalse(V.radar_enabled(self.c))
  self.assertTrue(V.setting(self.c,'radar_priced_only',False))
  V.dispatch(self.c,{'action':'radar_telegram','enabled':False,'card_leagues':['France · Ligue 1']},self.now)
  V.install_card_selection(self.c)
  self.assertEqual(V.setting(self.c,'card_leagues',[]),['France · Ligue 1'])

 def test_australia_aliases_and_both_team_cards_allowed(self):
  V.install_card_selection(self.c)
  for country in ('Australia','AUS','Australie','New Zealand'):
   for league in ('A-League Men','A-League','Isuzu UTE A-League Men'):
    p={'league':league,'packball':{'export_a':[country]}}
    self.assertEqual(V.selected_card_competition({},p),'Australie · A-League Men')
    for team in ('h','a'):
     self.assertTrue(V.card_market_allowed(self.c,{},team,self.now,p=p))
  self.m['league']=self.ctx['league']='A-League Men'
  self.m['packball']={'export_a':['Australia','AUS','A-League Men']}
  self.prepare()
  d=self.reading('cards')
  self.assertEqual(d['status'],'active')
  self.assertTrue(d['telegram_allowed'])
  self.assertTrue(V.radar_eligible(V.sample_data(V.latest(self.c)[0]),d,self.now))

 def test_australia_excludes_women_youth_other_divisions_and_cups(self):
  V.install_card_selection(self.c)
  for country,league in [('AUS','A-League Women'),('AUS','W-League'),('AUS','A-League Youth'),('AUS','A-League Men U23'),('AUS','National Premier Leagues'),('AUS','Australia Cup'),('AUS','Australian Championship'),('','A-League Men'),('England','A-League Men')]:
   self.assertFalse(V.card_market_allowed(self.c,{'country':country,'league':league},'h',self.now),(country,league))

 def test_australia_upgrade_additive_once_preserves_owner_controls(self):
  V.install_card_selection(self.c)
  self.c.execute("DELETE FROM v4_settings WHERE key='card_selection_australia_20261023'")
  for k,v in [('card_leagues',['France · Ligue 1','Custom League']),('radar_telegram_enabled',True),('radar_priced_only',False),('radar_odds_auto',False)]:
   self.c.execute('INSERT OR REPLACE INTO v4_settings VALUES(?,?)',(k,V.enc(v)))
  self.c.commit()
  V.install_card_selection(self.c)
  self.assertEqual(V.setting(self.c,'card_leagues',[]),['France · Ligue 1','Custom League','Australie · A-League Men'])
  self.assertTrue(V.radar_enabled(self.c))
  self.assertFalse(V.setting(self.c,'radar_priced_only',True))
  self.assertFalse(V.setting(self.c,'radar_odds_auto',True))
  V.dispatch(self.c,{'action':'radar_telegram','enabled':False,'card_leagues':['France · Ligue 1']},self.now)
  V.install_card_selection(self.c)
  self.assertEqual(V.setting(self.c,'card_leagues',[]),['France · Ligue 1'])
  self.assertFalse(V.radar_enabled(self.c))

if __name__=='__main__':unittest.main()

