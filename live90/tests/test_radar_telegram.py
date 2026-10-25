import copy,datetime as dt,os,sqlite3,unittest,urllib.error
from unittest.mock import patch
import test_decision_v5 as fixtures
import live_v4 as V
class RadarTests(unittest.TestCase):
 bundle=fixtures.DeskTests.bundle
 add=fixtures.DeskTests.add
 prepare=fixtures.DeskTests.prepare
 reading=fixtures.DeskTests.reading
 def setUp(self):
  fixtures.DeskTests.setUp(self)
  self.env=patch.dict(os.environ,{'TELEGRAM_BOT_TOKEN':'test-token','TELEGRAM_CHAT_ID':'test-chat'});self.env.start()
  self.sender=patch.object(V,'notify',return_value=('sent',None,77));self.send=self.sender.start()
  self.sleep=patch.object(V.time,'sleep');self.sleep.start();self.prepare()
 def tearDown(self):
  self.sleep.stop();self.sender.stop();self.env.stop();fixtures.DeskTests.tearDown(self)
 def one(self):return V.sample_data(V.latest(self.c)[0]),self.reading('goals')
 def queue(self):
  r,d=self.one();V.queue_radar(self.c,r,d,self.now);self.c.commit();return r,d
 def rows(self):return self.c.execute('SELECT * FROM v5_radar_notifications ORDER BY id').fetchall()
 def test_once_across_cycles_no_legacy_bets_or_odds_calls(self):
  with patch.object(V,'pulse_enrich') as odds,patch.object(V,'deliver_results') as legacy:
   for _ in range(4):V.run_dashboard(self.db,self.now)
  self.assertGreater(self.send.call_count,0);self.assertEqual(self.send.call_count,len(self.rows()))
  self.assertTrue(all(x['delivery']=='sent' for x in self.rows()));odds.assert_not_called();legacy.assert_not_called()
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_signals').fetchone()[0],0)
  self.assertFalse(V.options(self.c)['telegram_enabled'])
  self.assertTrue(any(x['notification']['delivery']=='sent' for x in V.desk_history(self.c) if x['notification']))
 def test_only_confirmed_fresh_cards(self):
  r,d=self.one()
  for status in ('neutral','missing','confirming','waiting','caution','stale'):V.queue_radar(self.c,r,dict(d,status=status),self.now)
  for changes in ({'state':'HT'},{'state':'FT'},{'minute_extra':1},{'quality_errors':['mapping']},{'sample_id':-1},{'received_at':V.iso(self.now-dt.timedelta(seconds=101))}):V.queue_radar(self.c,dict(r,**changes),d,self.now)
  self.assertEqual(len(self.rows()),0)
 def test_counter_dedup_and_cooldown(self):
  r,d=self.queue();V.queue_radar(self.c,r,dict(d,observed=1),self.now+dt.timedelta(minutes=1));self.assertEqual(len(self.rows()),1)
  later=self.now+dt.timedelta(minutes=11);r.update(received_at=V.iso(later),collected_at=V.iso(later))
  V.queue_radar(self.c,r,d,later);self.assertEqual(len(self.rows()),1)
  V.queue_radar(self.c,r,dict(d,observed=1),later);self.assertEqual(len(self.rows()),2)
 def test_stop_at_80_for_both_markets_including_old_active_readings(self):
  r,d=self.one()
  for market in ('goals','cards'):
   reading=dict(d,market=market,period='FT',telegram_allowed=True,card_policy=V.CARD_MOMENTUM_POLICY)
   self.assertTrue(V.radar_eligible(dict(r,minute=79),reading,self.now))
   for minute in (80,81,90,95):
    V.queue_radar(self.c,dict(r,minute=minute),reading,self.now)
  self.assertEqual(len(self.rows()),0)
 def test_queued_alert_expires_at_cutoff_even_without_new_sample(self):
  for latest_minute,delay in ((80,1),(79,60)):
   self.c.execute('DELETE FROM v5_radar_notifications');self.c.commit()
   self.r['minute']=79;self.add(self.r,self.now-dt.timedelta(seconds=30));self.add(self.r,self.now);r,d=self.one()
   V.queue_radar(self.c,r,d,self.now);self.c.commit()
   if latest_minute==80:
    self.r['minute']=80;self.add(self.r,self.now+dt.timedelta(seconds=1))
   V.deliver_radar(self.c,self.now+dt.timedelta(seconds=delay))
   self.assertEqual(self.rows()[0]['delivery'],'expired')
  self.send.assert_not_called()
 def test_expire_if_finished_score_counter_or_freshness_changes(self):
  original=copy.deepcopy(self.r)
  for change in ('finished','score','counter','stale'):
   self.c.execute('DELETE FROM v5_radar_notifications');self.c.commit();self.r=copy.deepcopy(original);self.add(self.r,self.now-dt.timedelta(seconds=30));self.add(self.r,self.now);self.queue()
   if change=='finished':self.r['state']='FT'
   elif change=='score':self.r['score']['a']=1
   elif change=='counter':self.r['score']['h']=1
   self.add(self.r,self.now+dt.timedelta(seconds=1));V.deliver_radar(self.c,self.now+dt.timedelta(seconds=101 if change=='stale' else 1))
   self.assertEqual(self.rows()[0]['delivery'],'expired',change)
  self.send.assert_not_called()
 def test_missing_quote_does_not_block(self):
  self.queue();V.deliver_radar(self.c,self.now);msg=self.send.call_args.args[0]
  for text in ('But à surveiller','Équipe A','Cote à vérifier — miser uniquement à partir de 1,50','pas un pari validé'):self.assertIn(text,msg)
 def test_exact_team_cut_and_required_events(self):
  self.queue();event={'event_id':'stake','home':'Équipe A','away':'Équipe B','score':{'h':0,'a':0},'markets':[{'name':'Équipe A Total Goals','canonical':'HOME_OVER_UNDER','period':'FULL_TIME','active':True,'selections':[{'name':'Over 2.5','line':2.5,'odds':1.9,'active':True}]}]}
  V.runtime(self.c,'pulsescore_test',{'ok':True,'at':V.iso(self.now),'events':[event]});self.c.commit()
  V.deliver_radar(self.c,self.now);msg=self.send.call_args.args[0]
  self.assertNotIn('Équipe A +2,5 buts',msg)
  self.assertIn('objectif statistique',msg)
  self.assertEqual(V.dec(self.rows()[0]['data'])['result_target']['line'],.5)
 def test_stale_quote_removed(self):
  self.queue();V.runtime(self.c,'pulsescore_test',{'ok':True,'at':V.iso(self.now-dt.timedelta(minutes=3)),'events':[]});self.c.commit()
  V.deliver_radar(self.c,self.now);self.assertIn('Cote à vérifier — miser uniquement à partir de 1,50',self.send.call_args.args[0])
 def test_disable_and_missing_credentials(self):
  with patch.dict(os.environ,{'TELEGRAM_BOT_TOKEN':''}):self.queue()
  self.assertEqual(len(self.rows()),0);self.queue();V.dispatch(self.c,{'action':'radar_telegram','enabled':False},self.now)
  V.deliver_radar(self.c,self.now);self.send.assert_not_called();self.assertEqual(self.rows()[0]['delivery'],'disabled');self.assertFalse(V.radar_status(self.c)['enabled'])
 def test_failed_uncertain_never_resent(self):
  for state in ('failed','uncertain'):
   self.c.execute('DELETE FROM v5_radar_notifications');self.c.commit();self.queue();self.send.return_value=(state,'Erreur',None)
   before=self.send.call_count;V.deliver_radar(self.c,self.now);V.deliver_radar(self.c,self.now)
   self.assertEqual(self.send.call_count,before+1);self.assertEqual(self.rows()[0]['delivery'],state)
 def test_crash_recovery_no_duplicate(self):
  self.queue();self.c.execute("UPDATE v5_radar_notifications SET delivery='sending',attempted_at=?",(V.iso(self.now-dt.timedelta(minutes=4)),));self.c.commit()
  V.deliver_radar(self.c,self.now);self.send.assert_not_called();self.assertEqual(self.rows()[0]['delivery'],'uncertain')
 def test_network_does_not_lock_ingestion_board_never_sends(self):
  self.queue()
  def send(msg):
   with sqlite3.connect(self.db,timeout=0) as other:other.execute("INSERT OR REPLACE INTO v4_settings VALUES('during_notify','true')")
   return 'sent',None,123
  self.send.side_effect=send;V.deliver_radar(self.c,self.now);self.assertTrue(V.setting(self.c,'during_notify',False));self.send.reset_mock()
  b=V.board(self.c,self.now);self.send.assert_not_called();self.assertTrue(b['radar_telegram']['enabled'])
if __name__=='__main__':unittest.main()

