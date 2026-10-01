import datetime as dt
import unittest
from unittest.mock import patch
import test_v4
import live_v4 as V

class MinimumTelegramPrice(unittest.TestCase):
 def test_threshold_freshness_and_result_notifications(self):
  for market in ('goal_ht','goal_ft','card_ft'):
   for price,age,expected in [(1.12,0,False),(1.49,0,False),(1.4999,0,False),(1.50,0,True),(2.1,0,True),(None,0,False),(1.8,31,False),(1.8,-1,False)]:
    with self.subTest(market=market,price=price,age=age):
     base=test_v4.LiveV4Tests();base.setUp()
     try:
      if market=='goal_ft':base.r['minute']=60
      base.prepared(cards=market=='card_ft')
      with patch.object(V,'deliver'),patch.object(V,'pulse_enrich'):V.run(base.db,True,base.now)
      row=base.c.execute('SELECT * FROM v4_signals WHERE market=?',(market,)).fetchone();self.assertIsNotNone(row)
      saved=V.dec(row['data'])
      if price is not None:saved['stake_quote']={'odds':price,'received_at':V.iso(base.now-dt.timedelta(seconds=age))}
      base.c.execute('UPDATE v4_signals SET data=? WHERE id=?',(V.enc(saved),row['id']));base.c.commit()
      with patch.object(V,'notify',return_value=('sent',None,9)) as send:
       V.deliver(base.c,base.now);V.deliver(base.c,base.now)
       self.assertEqual(send.call_count,int(expected))
      signal=V.history(base.c)[0];self.assertEqual(signal['delivery'],'sent' if expected else 'filtered')
      if not expected:self.assertIn('1,50',signal['delivery_error'])
      with patch.object(V,'notify',return_value=('sent',None,10)) as send:
       V.settle(base.c,{'id':row['id'],'outcome':'won','source':'Fixture test'},base.now)
       V.deliver_results(base.c,base.now)
       self.assertEqual(send.call_count,int(expected))
      self.assertEqual(V.history(base.c)[0]['outcome'],'won')
     finally:base.tearDown()

if __name__=='__main__':unittest.main()
