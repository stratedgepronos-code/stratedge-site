import unittest
import test_scenarios as fixtures
from test_scenarios import S, dt, json

class PendingTests(unittest.TestCase):
 setUp=fixtures.ScenarioTests.setUp
 tearDown=fixtures.ScenarioTests.tearDown
 def sample(self,mid='789',away='Beta',offset=0):
  m=self.bundle['matches'][0]
  row={'home':m['home'],'away':away,'kickoff_ts':(S.parse(m['kickoff'])+dt.timedelta(days=offset)).isoformat()}
  self.c.execute('INSERT INTO samples(cycle_id,match_id,received_at,data) VALUES(?,?,?,?)',(mid,mid,self.now.isoformat(),json.dumps(row)));self.c.commit()
 def test_pending_late_binding_and_idempotence(self):
  self.bundle['matches'][0]['match_id']=None
  x=S.accept_pending(self.c,self.bundle,self.now);self.assertEqual(x['pending'],1)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM playbooks').fetchone()[0],0)
  self.sample();later=self.now+dt.timedelta(hours=2,minutes=1)
  self.assertEqual(S.resolve_pending(self.c,later),1);self.assertEqual(S.resolve_pending(self.c,later),0)
  b=json.loads(self.c.execute('SELECT data FROM prematch').fetchone()[0]);self.assertEqual(b['recorded_at'],self.now.isoformat());self.assertEqual(b['match_id'],'789')
 def test_ambiguous_no_activation(self):
  self.bundle['matches'][0]['match_id']=None;self.sample('789');self.sample('790')
  self.assertEqual(S.accept_pending(self.c,self.bundle,self.now)['pending'],1)
  self.assertEqual(self.c.execute('SELECT state FROM pending_playbooks').fetchone()[0],'ambiguous')
 def test_wrong_day_no_activation(self):
  self.bundle['matches'][0]['match_id']=None;self.sample(offset=1)
  self.assertEqual(S.accept_pending(self.c,self.bundle,self.now)['pending'],1)
 def test_invalid_pending_rejected(self):
  self.bundle['matches'][0]['match_id']=None;self.bundle['matches'][0]['prematch']['shots_h']=None
  with self.assertRaises(ValueError):S.accept_pending(self.c,self.bundle,self.now)
