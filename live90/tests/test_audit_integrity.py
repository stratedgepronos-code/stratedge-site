"""Regression cases found during the 26 October operational audit."""
import copy, datetime as dt, unittest
from unittest.mock import patch
import test_decision_v5 as F
import live_v4 as V

class AuditIntegrityTests(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 add=F.DeskTests.add
 bundle=F.DeskTests.bundle
 reading=F.DeskTests.reading

 def test_health_reports_imports_even_when_fixture_is_outside_program(self):
  self.prepare();V.runtime(self.c,'active_packball_fixtures',[]);self.c.commit()
  with patch.object(V,'current_cycle_ids',return_value={str(self.r['packball_id'])}):
   h=V.feed_health(self.c,self.now)
  self.assertIn('outside_program',{x['code'] for x in h['issues']})
  self.assertEqual(h['readiness']['outside_program'],1)

 def test_health_reports_late_and_missing_profiles(self):
  self.prepare();self.c.execute('UPDATE v4_profiles SET imported_at=?',(V.iso(self.now),));self.c.commit()
  h=V.feed_health(self.c,self.now)
  self.assertIn('prematch_late',{x['code'] for x in h['issues']})
  self.c.execute('DELETE FROM v4_profiles');self.c.commit()
  h=V.feed_health(self.c,self.now)
  self.assertIn('prematch_missing',{x['code'] for x in h['issues']})

 def test_health_does_not_demand_prematch_for_finished_matches(self):
  self.prepare();self.r.update(state='FT',minute=None);self.add(self.r,self.now)
  self.c.execute('DELETE FROM v4_profiles');self.c.commit()
  h=V.feed_health(self.c,self.now)
  self.assertNotIn('prematch_missing',{x['code'] for x in h['issues']})

 def test_nested_goal_windows_cannot_confirm_impossible_activity(self):
  self.prepare();self.r['stats']['shots']['h']=20
  self.r['ind10']['shots10']['h']=3;self.r['ind5']['shots5']['h']=4
  self.add(self.r,self.now-dt.timedelta(seconds=1));self.add(self.r,self.now+dt.timedelta(seconds=30));self.now+=dt.timedelta(seconds=30)
  self.assertEqual(self.reading()['status'],'missing')

 def test_fractional_event_windows_are_unknown_not_momentum(self):
  self.prepare();self.r['ind10']['sot10']['h']=1.5
  self.add(self.r,self.now-dt.timedelta(seconds=1));self.add(self.r,self.now+dt.timedelta(seconds=30));self.now+=dt.timedelta(seconds=30)
  self.assertEqual(self.reading()['status'],'missing')

 def test_learning_export_covers_all_ids_and_result_revisions(self):
  for i in range(1,267):
   d={'radar_id':i,'revision':1,'outcome':'unknown'}
   self.c.execute('INSERT INTO v5_learning_reviews VALUES(?,?,?,?,?,?)',(i,'unknown',0,1,V.iso(self.now),V.enc(d)))
  self.c.commit()
  exported=V.export_learning(self.c,self.now)
  self.assertEqual(len(exported['reviews']),266)
  self.assertTrue(exported['coverage']['complete'])
  self.assertEqual({r['radar_id'] for r in exported['reviews']},set(range(1,267)))
  self.assertIn('result_revisions',exported)
  self.assertEqual(exported,V.export_learning(self.c,self.now))

 def test_confirmed_id_survives_schedule_change_without_rewriting_import(self):
  self.prepare();original=V.bind(self.c,self.r);self.c.commit()
  before=[tuple(r) for r in self.c.execute('SELECT * FROM v4_profiles')]
  moved=dict(self.r,kickoff_ts=V.iso(self.ko+dt.timedelta(minutes=30)))
  bound=V.bind(self.c,moved)
  self.assertIsNotNone(bound);self.assertEqual(bound['fixture'],original['fixture'])
  self.assertEqual(bound['binding_method'],'confirmed_id')
  self.assertEqual(before,[tuple(r) for r in self.c.execute('SELECT * FROM v4_profiles')])
  self.assertEqual(bound['kickoff'],original['kickoff'])
  self.assertIsNone(V.bind(self.c,dict(moved,home='Other team')))
  self.assertIsNone(V.bind(self.c,dict(moved,kickoff_ts=V.iso(self.ko+dt.timedelta(days=1)))))

 def test_earlier_actual_kickoff_never_makes_late_profile_usable(self):
  self.prepare();V.bind(self.c,self.r)
  self.c.execute('UPDATE v4_profiles SET imported_at=?',(V.iso(self.ko-dt.timedelta(minutes=10)),));self.c.commit()
  bound=V.bind(self.c,dict(self.r,kickoff_ts=V.iso(self.ko-dt.timedelta(minutes=30))))
  self.assertIsNotNone(bound)
  profile,_=V.inputs(self.c,bound,self.now)
  self.assertFalse(profile['usable'])

if __name__=='__main__':unittest.main()
