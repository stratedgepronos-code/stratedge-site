"""Stake prices qualify real team cuts without upgrading one event to several."""
import copy,datetime as dt,unittest,sqlite3
from unittest.mock import patch,MagicMock
import test_radar_telegram as F
import live_v4 as V

class RadarPrices(unittest.TestCase):
 bundle=F.RadarTests.bundle
 add=F.RadarTests.add
 prepare=F.RadarTests.prepare
 reading=F.RadarTests.reading
 one=F.RadarTests.one
 queue=F.RadarTests.queue
 rows=F.RadarTests.rows
 tearDown=F.RadarTests.tearDown
 def setUp(self):
  F.RadarTests.setUp(self)
  self.c.execute("INSERT OR REPLACE INTO v4_settings VALUES('radar_priced_only','true')");self.c.commit()
 def cache(self,odds=1.8,line=.5,age=0,score=None,key='pulsescore_test'):
  event={'event_id':'stake','home':'Équipe A','away':'Équipe B','score':score or {'h':0,'a':0},'markets':[{'name':'Équipe A Total Goals','canonical':'HOME_OVER_UNDER','period':'FULL_TIME','active':True,'selections':[{'name':f'Over {line}','line':line,'odds':odds,'active':True}]}]}
  V.runtime(self.c,key,{'ok':True,'at':V.iso(self.now-dt.timedelta(seconds=age)),'events':[event]});self.c.commit()
 def test_no_price_stays_on_site_without_telegram(self):
  self.queue();self.assertEqual(len(self.rows()),0)
  V.run_dashboard(self.db,self.now);self.send.assert_not_called()
  b=V.board(self.c,self.now);d=next(d for d in b['matches'][0]['advice'] if d['market']=='goals' and d['team']=='h')
  self.assertEqual(d['status'],'active');self.assertFalse(d['stake_gate']['eligible'])
 def test_real_plus_half_at_112_is_not_sent(self):
  self.cache(1.12);self.queue();V.deliver_radar(self.c,self.now)
  self.send.assert_not_called();self.assertEqual(len(self.rows()),0)
 def test_minimum_150_inclusive_preserves_exact_target_and_price(self):
  self.cache(1.5);self.queue();V.deliver_radar(self.c,self.now)
  self.send.assert_called_once();saved=V.dec(self.rows()[0]['data'])
  self.assertEqual(saved['result_target']['line'],.5);self.assertEqual(saved['result_target']['quote']['odds'],1.5)
  self.assertIn('FT · cote 1,5',self.send.call_args.args[0])
 def test_does_not_upgrade_to_two_goals_for_higher_price(self):
  self.cache(1.8,line=1.5);self.queue();self.send.assert_not_called();self.assertEqual(len(self.rows()),0)
 def test_price_rise_can_qualify_same_counter_later(self):
  self.cache(1.12);self.queue();self.cache(1.65);self.queue();V.deliver_radar(self.c,self.now)
  self.send.assert_called_once();self.assertEqual(len(self.rows()),1)
 def test_stale_score_mismatch_or_unknown_price_rejected(self):
  for opts in ({'age':31},{'score':{'h':1,'a':0}},{'odds':None}):
   self.cache(**opts);self.queue();self.assertEqual(len(self.rows()),0)
 def test_delivery_rechecks_price_then_can_requeue_only_unsent_wait(self):
  self.cache(1.8);self.queue();self.cache(1.2);V.deliver_radar(self.c,self.now)
  self.send.assert_not_called();self.assertEqual(self.rows()[0]['delivery'],'price_wait')
  self.cache(1.8);self.queue();V.deliver_radar(self.c,self.now)
  self.send.assert_called_once();self.assertEqual(self.rows()[0]['delivery'],'sent')
  self.queue();V.deliver_radar(self.c,self.now);self.send.assert_called_once()
 def enable_auto(self):
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'priced_only':True,'odds_auto':True},self.now)
  V.dispatch(self.c,{'action':'pulsescore_key','key':'private-test-key'},self.now)
 def payload(self,odds=1.8):
  return {'events':[{'eventId':'stake','home':'Équipe A','away':'Équipe B','score':{'home':0,'away':0},'markets':[{'rawName':'Équipe A Total Goals','canonicalMarket':'HOME_OVER_UNDER','period':'FULL_TIME','isActive':True,'selections':[{'rawName':'Over 0.5','line':.5,'odds':odds,'isActive':True}]}]}]}
 def opener(self,payload):
  net=MagicMock();net.open.return_value.__enter__.return_value.read.return_value=V.enc(payload).encode();return net
 def test_auto_fetch_once_on_confirmation_and_reuses_batch(self):
  self.enable_auto();net=self.opener(self.payload())
  with patch.object(V.urllib.request,'build_opener',return_value=net):
   V.run_dashboard(self.db,self.now);V.run_dashboard(self.db,self.now)
  net.open.assert_called_once();self.send.assert_called_once()
  self.assertEqual(V.pulse_day_used(self.c,self.now),1)
  self.assertEqual(V.pulse_status(self.c,self.now)['attempts_31d'],1)
 def test_no_api_from_board_or_without_confirmed_candidates(self):
  self.enable_auto()
  with patch.object(V.urllib.request,'build_opener') as net:
   V.board(self.c,self.now);V.refresh_radar_prices(self.c,self.now,[])
   r,d=self.one();V.refresh_radar_prices(self.c,self.now,[(r,dict(d,status='confirming'))]);net.assert_not_called()
 def test_daily_budget_cannot_be_bypassed_and_no_unpriced_fallback(self):
  self.enable_auto()
  self.c.executemany('INSERT INTO v4_pulsescore_auto(attempted_at) VALUES(?)',[(V.iso(self.now),)]*10);self.c.commit()
  with patch.object(V.urllib.request,'build_opener') as net:V.run_dashboard(self.db,self.now);net.assert_not_called()
  self.send.assert_not_called();self.assertIn('Budget quotidien',V.dec(self.c.execute("SELECT value FROM v4_runtime WHERE key='radar_price_lookup'").fetchone()[0])['reason'])
 def test_rolling_500_budget_includes_manual_requests(self):
  self.enable_auto();self.c.executemany('INSERT INTO v4_pulsescore_requests(attempted_at) VALUES(?)',[(V.iso(self.now-dt.timedelta(minutes=2)),)]*500);self.c.commit()
  with patch.object(V.urllib.request,'build_opener') as net:V.run_dashboard(self.db,self.now);net.assert_not_called()
  self.send.assert_not_called()
 def test_api_failure_pauses_and_never_falls_back_to_old_quote(self):
  self.enable_auto();net=MagicMock();net.open.side_effect=TimeoutError('private-test-key')
  with patch.object(V.urllib.request,'build_opener',return_value=net):
   V.run_dashboard(self.db,self.now)
   r,d=self.one();later=self.now+dt.timedelta(seconds=70);r.update(collected_at=V.iso(later),received_at=V.iso(later))
   V.refresh_radar_prices(self.c,later,[(r,d)])
  net.open.assert_called_once();self.send.assert_not_called()
  self.assertNotIn('private-test-key',V.enc(V.radar_price_cache(self.c)))
 def test_budget_protects_successful_low_price_from_tight_polling(self):
  self.enable_auto();net=self.opener(self.payload(1.12))
  with patch.object(V.urllib.request,'build_opener',return_value=net):
   V.run_dashboard(self.db,self.now)
   r,d=self.one();later=self.now+dt.timedelta(seconds=70);r.update(collected_at=V.iso(later),received_at=V.iso(later))
   V.refresh_radar_prices(self.c,later,[(r,d)])
  net.open.assert_called_once();self.send.assert_not_called()
 def test_settings_boolean_validation_and_production_default_strict(self):
  self.c.execute("DELETE FROM v4_settings WHERE key IN ('radar_priced_only','radar_odds_auto')");self.c.commit()
  settings=V.radar_status(self.c);self.assertTrue(settings['priced_only']);self.assertTrue(settings['odds_auto'])
  for name in ('priced_only','odds_auto'):
   with self.assertRaises(ValueError):V.dispatch(self.c,{'action':'radar_telegram','enabled':True,name:'false'},self.now)
 def test_auto_network_releases_database_write_lock(self):
  self.enable_auto();net=self.opener(self.payload())
  def open_check(*args,**kwargs):
   with sqlite3.connect(self.db,timeout=0) as other:other.execute("INSERT OR REPLACE INTO v4_settings VALUES('during_price_fetch','true')")
   return net.open.return_value
  net.open.side_effect=open_check
  with patch.object(V.urllib.request,'build_opener',return_value=net):V.run_dashboard(self.db,self.now)
  self.assertTrue(V.setting(self.c,'during_price_fetch',False));self.send.assert_called_once()

if __name__=='__main__':unittest.main()
