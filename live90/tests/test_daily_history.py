"""Daily history reads frozen alerts/results, never the current slate."""
import copy, datetime as dt, unittest
import test_decision_v5 as F
import live_v4 as V

class DailyHistory(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 def insert(self,created='2026-10-24T22:30:00+00:00',outcome='pending',delivery='sent',market='goals',period='HT',line=.5):
  saved={'home':'Équipe A','away':'Équipe B','minute':35,'score':{'h':0,'a':0},'reading':{'reason':'Test','coherence':{'lines':['Tranche vérifiée']}},'result_target':{'team':'h','market':market,'period':period,'line':line,'unit':'goals' if market=='goals' else 'yellow_cards','quote':None,'mode':'event','baseline_sample_id':1,'baseline_at':created,'kickoff':V.iso(self.ko)}}
  rid=self.c.execute('INSERT INTO v5_radar_notifications(event_key,match_id,team,market,counter,created_at,sample_id,data,delivery) VALUES(?,?,?,?,?,?,?,?,?)',(str(self.c.total_changes),'123','h',market,0,created,1,V.enc(saved),delivery)).lastrowid
  if outcome is not None:self.c.execute('INSERT INTO v5_radar_results VALUES(?,?,?,?,?)',(rid,outcome,created,2,V.enc({'target':saved['result_target'],'current':1 if outcome=='observed' else 0})))
  self.c.commit();return rid
 def get(self,**kw):return V.dispatch(self.c,dict({'action':'daily_history','day':'2026-10-25'},**kw),self.now)
 def test_all_alerts_paginated_and_exported_beyond_two_hundred(self):
  for i in range(237):self.insert(outcome='observed' if i%2 else 'not_observed')
  keys=[]
  for page in (1,2,3):
   data=self.get(page=page,page_size=100);keys += [x['key'] for x in data['items']]
   self.assertEqual(data['total'],237);self.assertEqual(data['pages'],3)
  self.assertEqual(len(set(keys)),237)
  export=V.dispatch(self.c,{'action':'export_daily_history','day':'2026-10-25'},self.now)
  self.assertEqual(len(export['items']),237);self.assertTrue(export['export_complete'])
  self.assertEqual(export['summary']['observed']+export['summary']['not_observed'],237)
 def test_paris_midnight_and_dst_twenty_five_hour_day(self):
  for at in ('2026-10-24T21:59:59Z','2026-10-24T22:00:00Z','2026-10-25T00:30:00Z','2026-10-25T01:30:00Z','2026-10-25T22:59:59Z','2026-10-25T23:00:00Z'):self.insert(created=at)
  d=self.get();self.assertEqual(d['total'],4)
  self.assertEqual({x['day']:x['count'] for x in d['days']},{'2026-10-24':1,'2026-10-25':4,'2026-10-26':1})
 def test_result_revision_keeps_one_alert_and_original_day(self):
  rid=self.insert(outcome='pending')
  for old,new in [('pending','observed'),('observed','pending'),('pending','not_observed')]:
   self.c.execute('INSERT INTO v5_radar_result_events(radar_id,previous,outcome,created_at,data,delivery) VALUES(?,?,?,?,?,?)',(rid,old,new,'2026-10-26T02:00:00Z',V.enc({'current':0}),'sent'))
  self.c.execute('UPDATE v5_radar_results SET outcome=?,updated_at=? WHERE radar_id=?',('not_observed','2026-10-26T02:00:00Z',rid));self.c.commit()
  d=self.get();self.assertEqual(d['summary']['total'],1);self.assertEqual(d['summary']['not_observed'],1);self.assertEqual(len(d['items'][0]['revisions']),3)
  self.assertEqual(self.get(day='2026-10-26')['total'],0)
 def test_filters_unknown_and_unsent_are_not_losses(self):
  self.insert(outcome='observed',market='cards',period='FT',line=2.5)
  self.insert(outcome='unknown');self.insert(outcome=None);self.insert(outcome='observed',delivery='failed')
  d=self.get();self.assertEqual(d['summary'],{'observed':1,'not_observed':0,'pending':1,'unknown':1,'cancelled':0,'not_sent':0,'total':3,'sent':3})
  self.assertEqual(self.get(market='cards')['items'][0]['line'],2.5)
  self.assertEqual(self.get(outcome='unknown')['total'],1)
  d=self.get(include_unsent=True);self.assertEqual(d['summary']['not_sent'],1);self.assertEqual(d['summary']['observed'],1)
 def test_legacy_ids_never_collide_or_change_old_outcomes(self):
  rid=self.insert(outcome='observed')
  self.c.execute('INSERT INTO v4_signals(id,signal_key,market,team,line,odds,created_at,data,delivery,outcome) VALUES(?,?,?,?,?,?,?,?,?,?)',(rid,'legacy','card_ft','a',1.5,1.9,'2026-10-24T22:30:00Z',V.enc({'home':'A','away':'B','label':'Ancien signal'}),'sent','lost'));self.c.commit()
  d=self.get();self.assertEqual(len({x['key'] for x in d['items']}),2);self.assertEqual(d['summary']['not_observed'],1)
  old=self.get(generation='v4')['items'][0];self.assertEqual(old['period'],'FT');self.assertEqual(old['odds'],1.9);self.assertEqual(old['line'],1.5)
 def test_read_only_without_active_slate_or_profile(self):
  self.insert(outcome='observed');V.runtime(self.c,'active_fixtures',[]);self.c.commit()
  before=self.c.total_changes;d=self.get();self.assertEqual(d['total'],1);self.assertEqual(before,self.c.total_changes)
 def test_invalid_filters_rejected(self):
  for args in ({'day':'2026-02-30'},{'day':'20261025'},{'page':True},{'page_size':1000},{'outcome':'won'},{'include_unsent':'false'},{'generation':'xxx'}):
   with self.subTest(args=args),self.assertRaises(ValueError):self.get(**args)

if __name__=='__main__':unittest.main()
