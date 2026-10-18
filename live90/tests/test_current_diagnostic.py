import os
import unittest
from unittest.mock import patch
import test_decision_v5 as F
import live_v4 as V

class DiagnosticTests(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 add=F.DeskTests.add
 bundle=F.DeskTests.bundle
 def test_health_never_replays_history_or_contacts_providers(self):
  self.prepare()
  with patch.object(V,'audit_matches',side_effect=AssertionError('Historical replay in health')),patch.object(V,'notify',side_effect=AssertionError('Telegram')),patch.object(V,'pulse_test',side_effect=AssertionError('API')),patch.dict(os.environ,{'TELEGRAM_BOT_TOKEN':'private-bot-token','TELEGRAM_CHAT_ID':'private-chat'}):
   result=V.current_diagnostic(self.c,self.now)
  self.assertFalse(result['diagnostic']['historical_replay'])
  self.assertTrue(result['telegram']['configured']);self.assertEqual(result['list']['matches'],1)
  text=V.enc(result);self.assertNotIn('private-bot-token',text);self.assertNotIn('private-chat',text)
  self.assertNotIn('sources',text);self.assertNotIn('summary',text)
  self.assertEqual(result['coherence']['policy'],'prematch_live_halves_v2')
 def test_missing_half_references_and_current_block_are_visible(self):
  for side in ('h','a'):self.ctx['teams'][side].pop('goal_halves')
  self.prepare();result=V.current_diagnostic(self.c,self.now);match=result['matches'][0]
  self.assertFalse(match['references']['h']['half_scope_present'])
  reading=next(d for d in match['readings'] if d['market']=='goals' and d['team']=='h')
  self.assertEqual(reading['status'],'context_wait');self.assertTrue(reading['missing'])
  self.assertIn(reading['reason'],result['live_block_reasons'])

if __name__=='__main__':unittest.main()
