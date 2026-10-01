"""Integration with the real signal lifecycle; all provider/Telegram calls mocked."""
import copy,datetime as dt,json,unittest
from unittest.mock import patch,MagicMock
import live_v4 as V
import test_v4
class PulseAutoTests(unittest.TestCase):
 def setUp(self):
  self.base=test_v4.LiveV4Tests();self.base.setUp();self.c=self.base.c;self.now=self.base.now
  V.dispatch(self.c,{'action':'pulsescore_key','key':'test-only-private-key'},self.now)
 def tearDown(self):self.base.tearDown()
 def prepare(self,cards=False):
  self.base.prepared(cards=cards,both_goals=not cards)
  with patch.object(V,'deliver'),patch.object(V,'pulse_enrich'):
   V.run(self.base.db,send=True,now=self.now)
 def payload(self,score=None):
  return {'events':[{'eventId':'stake-123','home':'Équipe A','away':'Équipe B','score':score or {'home':'0','away':'0'},'markets':[
   {'rawName':name,'canonicalMarket':'HOME_OVER_UNDER','period':'FULL_TIME','isActive':True,'selections':[{'rawName':'Over 0.5','line':.5,'odds':odds,'isActive':True}]}
   for name,odds in [('Équipe A Total Goals',1.53),('Half Time Équipe A Total Goals',5.8)]]}]}
 def opener(self,payload):
  m=MagicMock();m.open.return_value.__enter__.return_value.read.return_value=V.enc(payload).encode();return m
 def test_two_periods_one_request_telegram_and_history_keep_reference_prices(self):
  self.base.prepared(both_goals=True);network=self.opener(self.payload())
  with patch.object(V.urllib.request,'build_opener',return_value=network),patch.object(V,'notify',return_value=('sent',None,1)) as send:
   V.run(self.base.db,send=True,now=self.now)
  network.open.assert_called_once();self.assertEqual(network.open.call_args.kwargs['timeout'],3);self.assertEqual(send.call_count,2)
  text='\n'.join(x.args[0] for x in send.call_args_list);self.assertIn('1,53',text);self.assertIn('5,80',text);self.assertIn('indicative',text)
  signals=V.history(self.c);self.assertEqual(len(signals),2);self.assertTrue(all(s['odds'] is None for s in signals))
  self.assertEqual({s['data']['stake_quote']['period'] for s in signals},{'HT','FT'})
  V.settle(self.c,{'id':signals[0]['id'],'outcome':'won','source':'Test'},self.now)
  self.assertEqual(V.board(self.c,self.now)['totals']['units'],0)
 def test_failure_and_repeat_never_block_or_retry_signals(self):
  self.prepare();network=MagicMock();network.open.side_effect=TimeoutError('no secret output')
  with patch.object(V.urllib.request,'build_opener',return_value=network),patch.object(V,'notify',return_value=('sent',None,1)) as send:
   V.pulse_enrich(self.c,self.now);V.pulse_enrich(self.c,self.now+dt.timedelta(seconds=70));V.deliver(self.c,self.now)
  network.open.assert_called_once();send.assert_not_called()
  self.assertTrue(all(s["delivery"]=="filtered" for s in V.history(self.c)))
 def test_cards_fetch_bookings_price_and_send_above_minimum(self):
  self.prepare(cards=True)
  payload=self.payload();m=payload['events'][0]['markets'][0];m.update(rawName='Équipe A Total Bookings',canonicalMarket='OTHER')
  network=self.opener(payload)
  with patch.object(V.urllib.request,'build_opener',return_value=network),patch.object(V,'notify',return_value=('sent',None,1)) as send:
   V.pulse_enrich(self.c,self.now);V.deliver(self.c,self.now)
  network.open.assert_called_once();self.assertEqual(send.call_count,1)
  self.assertIn('1,53 · Stake Bookings',send.call_args.args[0]);self.assertIn('cartons (Bookings)',send.call_args.args[0])
  s=V.history(self.c)[0];self.assertEqual(s['data']['stake_quote']['unit'],'stake_bookings');self.assertIsNone(s['odds'])
  self.assertEqual(V.pulse_status(self.c,self.now)['auto_today'],1)
 def test_cards_provider_failure_keeps_history_without_telegram(self):
  self.prepare(cards=True);network=MagicMock();network.open.side_effect=TimeoutError()
  with patch.object(V.urllib.request,'build_opener',return_value=network),patch.object(V,'notify',return_value=('sent',None,1)) as send:
   V.pulse_enrich(self.c,self.now);V.deliver(self.c,self.now)
  network.open.assert_called_once();send.assert_not_called()
  self.assertTrue(all(s["delivery"]=="filtered" for s in V.history(self.c)))
 def test_unconfirmed_observation_does_not_request_quotes(self):
  self.base.prepared();self.base.r['ind10']['sot10']['h']=0;self.base.add(self.base.r,self.now)
  with patch.object(V.urllib.request,'build_opener') as network,patch.object(V,'notify'):
   V.run(self.base.db,send=True,now=self.now)
  network.assert_not_called()
 def test_daily_budget_keeps_history_without_telegram(self):
  self.prepare()
  with self.c:self.c.executemany('INSERT INTO v4_pulsescore_auto(attempted_at) VALUES(?)',[(V.iso(self.now-dt.timedelta(hours=1)),)]*10)
  with patch.object(V.urllib.request,'build_opener') as network,patch.object(V,'notify',return_value=('sent',None,1)) as send:
   V.pulse_enrich(self.c,self.now);V.deliver(self.c,self.now)
  network.assert_not_called();send.assert_not_called()
  self.assertIn('Budget quotidien',V.history(self.c)[0]['data']['stake_lookup']['reason'])
  self.assertEqual(V.pulse_day_used(self.c,self.now.replace(hour=22)),0) # midnight Paris
  self.assertTrue(all(s["delivery"]=="filtered" for s in V.history(self.c)))
 def test_score_mismatch_suspension_quarter_lines_and_duplicate_events(self):
  self.prepare();s=self.c.execute('SELECT * FROM v4_signals ORDER BY id LIMIT 1').fetchone();saved=json.loads(s['data'])
  for mutation in ('score','suspended','quarter','duplicate','missing_score'):
   payload=self.payload();event=payload['events'][0]
   if mutation=='score':event['score']['home']='1'
   if mutation=='missing_score':event.pop('score')
   if mutation=='suspended':
    for m in event['markets']:m['isActive']=False
   if mutation=='quarter':
    for m in event['markets']:m['selections'][0].update(rawName='Over 0.75',line=.75)
   if mutation=='duplicate':payload['events'].append(copy.deepcopy(event))
   result={'ok':True,'at':V.iso(self.now),**V.pulse_summary(payload,'test-only-private-key')}
   quote,why=V.pulse_signal_quote(result,s,saved,self.now);self.assertIsNone(quote,mutation);self.assertTrue(why)
 def test_existing_goal_requires_next_half_line(self):
  self.prepare();s=dict(self.c.execute('SELECT * FROM v4_signals WHERE market="goal_ft" LIMIT 1').fetchone());saved=json.loads(s['data']);saved['score']['h']=1;s['line']=1.5
  payload=self.payload({'home':'1','away':'0'});m=payload['events'][0]['markets'][0];m['selections'][0].update(rawName='Over 1.5',line=1.5)
  result={'ok':True,'at':V.iso(self.now),**V.pulse_summary(payload,'test-only-private-key')}
  quote,_=V.pulse_signal_quote(result,s,saved,self.now);self.assertEqual(quote['line'],1.5)
  s['line']=.5;self.assertIsNone(V.pulse_signal_quote(result,s,saved,self.now)[0])
 def test_api_backoff_and_off_switch_do_not_call_network(self):
  self.prepare()
  with self.c:V.runtime(self.c,'pulsescore_auto_last',{'ok':False,'at':V.iso(self.now-dt.timedelta(minutes=2)),'error':'Quota'})
  with patch.object(V.urllib.request,'build_opener') as network:V.pulse_enrich(self.c,self.now)
  network.assert_not_called()
  V.dispatch(self.c,{'action':'pulsescore_auto','enabled':False},self.now)
  with patch.object(V.urllib.request,'build_opener') as network:
   with self.assertRaisesRegex(ValueError,'désactivée'):V.pulse_test(self.c,self.now,automatic=True)
   network.assert_not_called()
 def test_lookup_hook_runs_and_failure_cannot_prevent_live(self):
  self.base.prepared(both_goals=True)
  with patch.object(V,'pulse_enrich',side_effect=RuntimeError('outage')),patch.object(V,'notify',return_value=('sent',None,1)) as send:
   V.run(self.base.db,send=True,now=self.now)
  send.assert_not_called()
  self.assertTrue(all(s["delivery"]=="filtered" for s in V.history(self.c)))
if __name__=='__main__':unittest.main()
