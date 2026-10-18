"""Frozen radar targets, feed evidence, corrections and durable Telegram outbox."""
import copy,datetime as dt,os,unittest,sqlite3
from unittest.mock import patch
import test_decision_v5 as F
import live_v4 as V

class RadarResults(unittest.TestCase):
 bundle=F.DeskTests.bundle
 add=F.DeskTests.add
 prepare=F.DeskTests.prepare
 reading=F.DeskTests.reading
 def setUp(self):
  F.DeskTests.setUp(self)
  self.env=patch.dict(os.environ,{'TELEGRAM_BOT_TOKEN':'test','TELEGRAM_CHAT_ID':'test'});self.env.start()
  self.sender=patch.object(V,'notify',return_value=('sent',None,123));self.send=self.sender.start()
  self.sleep=patch.object(V.time,'sleep');self.sleep.start()
  V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':['Test']},self.now)
  self.prepare()
 def tearDown(self):
  self.sleep.stop();self.sender.stop();self.env.stop();F.DeskTests.tearDown(self)
 def emit(self,market='goals',line=None):
  r=V.sample_data(V.latest(self.c)[0]);d=self.reading(market)
  cuts={'quotes':[]}
  if line is not None:cuts['quotes']=[{'team':'h','kind':market,'period':'FT','half_line':True,'line':line,'needed':int(line)+1,'current':0,'indicative_counter':market=='cards','odds':1.8}]
  V.queue_radar(self.c,r,d,self.now);self.c.commit()
  with patch.object(V,'desk_cuts',return_value=cuts):V.deliver_radar(self.c,self.now)
  self.event=dict(self.c.execute('SELECT * FROM v5_radar_notifications ORDER BY id DESC LIMIT 1').fetchone())
  self.assertEqual(self.event['delivery'],'sent');self.send.reset_mock()
  V.settle_radar_results(self.c,self.now)
  return V.dec(self.event['data'])['result_target']
 def advance(self,value=0,state='LIVE',seconds=30,market='goals',send=True):
  self.now+=dt.timedelta(seconds=seconds)
  self.r['state']=state
  if market=='goals':self.r['score']['h']=value
  else:self.r['stats']['yellow_cards']['h']=value
  self.add(self.r,self.now);V.settle_radar_results(self.c,self.now)
  if send:V.deliver_radar_results(self.c,self.now)
 def twice(self,**kwargs):self.advance(**kwargs);self.advance(**kwargs)
 def result(self):return V.radar_result_status(self.c,self.event['id'])
 def events(self):return self.c.execute('SELECT * FROM v5_radar_result_events ORDER BY id').fetchall()
 def test_goal_event_confirmed_once_replied_and_retained_in_history(self):
  target=self.emit();self.assertEqual(target['line'],.5);self.assertEqual(target['period'],'FT')
  self.advance(1);self.assertEqual(self.result()['outcome'],'pending');self.send.assert_not_called()
  self.advance(1);self.assertEqual(self.result()['outcome'],'observed');self.assertEqual(self.send.call_count,1)
  self.assertEqual(self.send.call_args.kwargs,{'reply_to':123});self.assertIn('ÉVÉNEMENT OBSERVÉ',self.send.call_args.args[0])
  for _ in range(3):V.settle_radar_results(self.c,self.now);V.deliver_radar_results(self.c,self.now)
  self.assertEqual(self.send.call_count,1)
  V.run_dashboard(self.db,self.now)
  tracked=[o for o in V.desk_history(self.c) if o.get('result') and o['result']['radar_id']==self.event['id']]
  # This fixture queued directly, but a dashboard-created observation has the same sample.
  self.assertTrue(tracked);self.assertEqual(tracked[0]['result']['outcome'],'observed')
 def test_cut_requires_exact_total_and_is_never_replaced_by_new_quote(self):
  self.emit(line=1.5);self.twice(value=1);self.assertEqual(self.result()['outcome'],'pending')
  V.runtime(self.c,'pulsescore_test',{'ok':True,'at':V.iso(self.now),'events':[]});self.c.commit()
  self.twice(value=2);self.assertEqual(self.result()['outcome'],'observed')
  self.assertEqual(self.result()['data']['target']['line'],1.5)
 def test_bookings_cut_is_only_indicative_yellow_result(self):
  self.emit('cards',2.5);self.twice(value=1,market='cards');self.assertEqual(self.result()['outcome'],'pending')
  self.twice(value=3,market='cards');self.assertEqual(self.result()['outcome'],'observed')
  message=self.send.call_args.args[0];self.assertIn('EN JAUNES PACKBALL',message);self.assertIn('ne valide pas',message);self.assertNotIn('GAGNANT',message)
 def test_ft_required_never_halftime_clock_90_or_disappearance(self):
  self.emit();self.twice(state='HT');self.assertEqual(self.result()['outcome'],'pending')
  self.r['minute']=90;self.twice();self.assertEqual(self.result()['outcome'],'pending')
  V.runtime(self.c,'active_fixtures',[]);self.c.commit()
  self.advance(state='FT');self.send.assert_not_called()
  self.advance(state='FT');self.assertEqual(self.result()['outcome'],'not_observed');self.assertEqual(self.send.call_count,1)
 def test_correction_retracts_then_final_loss_then_corrected_success(self):
  self.emit();self.twice(value=1);self.twice(value=0)
  self.assertEqual(self.result()['outcome'],'pending');self.assertIn('VALIDATION RETIRÉE',self.send.call_args.args[0])
  self.twice(state='FT');self.assertEqual(self.result()['outcome'],'not_observed')
  self.twice(value=1,state='FT');self.assertEqual(self.result()['outcome'],'observed')
  self.assertIn('CORRECTION',self.send.call_args.args[0]);self.assertEqual(len(self.events()),4)
 def test_missing_final_is_unknown_never_lost_and_later_resolves(self):
  self.emit();self.now+=dt.timedelta(hours=7);V.settle_radar_results(self.c,self.now);V.deliver_radar_results(self.c,self.now)
  self.assertEqual(self.result()['outcome'],'unknown');self.assertIn('Aucun résultat perdu',self.send.call_args.args[0])
  self.twice(state='FT');self.assertEqual(self.result()['outcome'],'not_observed')
 def test_duplicate_timestamps_or_malformed_or_other_match_cannot_settle(self):
  self.emit();self.advance(value=1);self.advance(value=1,seconds=0);self.send.assert_not_called()
  self.r['quality_errors']=['ambiguous'];self.twice(value=1);self.send.assert_not_called()
  self.r.pop('quality_errors');self.r['home']='Autre match';self.twice(value=1);self.send.assert_not_called()
  self.r['home']=self.m['home'];self.r['kickoff_ts']=V.iso(self.ko+dt.timedelta(days=1));self.twice(value=1);self.send.assert_not_called()
 def test_missing_counter_at_ft_cannot_be_a_loss(self):
  self.emit('cards');self.twice(value=None,state='FT',market='cards');self.assertEqual(self.result()['outcome'],'pending');self.send.assert_not_called()
 def test_away_goal_does_not_validate_home(self):
  self.emit();self.r['score']['a']=2;self.twice();self.assertEqual(self.result()['outcome'],'pending');self.send.assert_not_called()
 def test_league_removed_does_not_cancel_results_of_sent_alert(self):
  self.emit('cards');V.dispatch(self.c,{'action':'radar_telegram','enabled':True,'card_leagues':[]},self.now)
  self.twice(value=1,market='cards');self.assertEqual(self.result()['outcome'],'observed');self.send.assert_called_once()
 def test_disabled_telegram_keeps_result_without_sending(self):
  self.emit();V.dispatch(self.c,{'action':'radar_telegram','enabled':False},self.now)
  self.twice(value=1);self.assertEqual(self.result()['outcome'],'observed');self.send.assert_not_called();self.assertEqual(self.events()[0]['delivery'],'disabled')
 def test_unsent_or_legacy_without_frozen_target_never_get_result(self):
  self.emit();self.c.execute("UPDATE v5_radar_notifications SET delivery='failed'");self.c.commit();self.twice(value=1);self.send.assert_not_called()
  self.c.execute("UPDATE v5_radar_notifications SET delivery='sent',data=?",(V.enc({'home':self.m['home'],'away':self.m['away']}),));self.c.commit()
  V.settle_radar_results(self.c,self.now);self.assertEqual(len(self.events()),0)
 def test_failed_uncertain_no_retry_and_network_does_not_lock_ingestion(self):
  self.emit();self.twice(value=1,send=False)
  def send(message,reply_to=None):
   with sqlite3.connect(self.db,timeout=0) as other:other.execute("INSERT OR REPLACE INTO v4_settings VALUES('result_probe','true')")
   return 'uncertain','Network timeout',None
  self.send.side_effect=send;V.deliver_radar_results(self.c,self.now);V.deliver_radar_results(self.c,self.now)
  self.send.assert_called_once();self.assertEqual(self.events()[0]['delivery'],'uncertain')
 def test_pending_success_superseded_before_delivery(self):
  self.emit();self.twice(value=1,send=False);self.twice(value=0,send=False)
  self.assertEqual(self.events()[0]['delivery'],'superseded')
  V.deliver_radar_results(self.c,self.now);self.send.assert_called_once();self.assertIn('VALIDATION RETIRÉE',self.send.call_args.args[0])
 def test_crash_recovery_marks_uncertain_no_duplicate(self):
  self.emit();self.twice(value=1,send=False)
  self.c.execute("UPDATE v5_radar_result_events SET delivery='sending',attempted_at=?",(V.iso(self.now-dt.timedelta(minutes=4)),));self.c.commit()
  V.deliver_radar_results(self.c,self.now);self.send.assert_not_called();self.assertEqual(self.events()[0]['delivery'],'uncertain')

if __name__=='__main__':unittest.main()
