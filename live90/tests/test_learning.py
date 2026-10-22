"""Review the actual V5 outbox, preserve hindsight boundaries and corrections."""
import copy,datetime as dt,unittest
from unittest.mock import patch
import test_radar_results as R
import live_v4 as V

class LearningTests(unittest.TestCase):
 bundle=R.RadarResults.bundle
 add=R.RadarResults.add
 prepare=R.RadarResults.prepare
 reading=R.RadarResults.reading
 setUp=R.RadarResults.setUp
 tearDown=R.RadarResults.tearDown
 emit=R.RadarResults.emit
 advance=R.RadarResults.advance
 twice=R.RadarResults.twice
 result=R.RadarResults.result
 events=R.RadarResults.events
 def review(self):
  return V.dec(self.c.execute('SELECT data FROM v5_learning_reviews WHERE radar_id=?',(self.event['id'],)).fetchone()[0])
 def test_snapshot_is_frozen_before_result_not_rebuilt_from_later_imports(self):
  self.emit();saved=V.dec(self.event['data']);snap=copy.deepcopy(saved['learning_snapshot'])
  self.assertEqual(snap['engine'],V.ENGINE_FINGERPRINT)
  self.c.execute("UPDATE v4_contexts SET data='{}'");self.c.execute("UPDATE v4_profiles SET data='{}'");self.c.commit()
  self.twice(value=1);self.assertEqual(V.refresh_learning(self.c,self.now),1)
  review=self.review();self.assertEqual(review['known_at_signal'],snap)
  self.assertEqual(review['outcome'],'observed');self.assertIsNotNone(review['confirmation_delay_minutes'])
  self.assertIn('cause non identifiée',review['conclusion'])
  metric=V.learning_memory(self.c)['groups'][0]['signal_metrics_by_outcome']['observed']['shots10']
  self.assertEqual(metric['n'],1);self.assertEqual(metric['mean'],snap['reading']['metrics']['shots10'])
 def test_corrections_replace_counts_and_preserve_every_revision(self):
  self.emit();self.twice(value=1);V.refresh_learning(self.c,self.now)
  first=V.learning_memory(self.c)['revision'];self.assertEqual(V.learning_memory(self.c)['settled_count'],1)
  self.twice(value=0);V.refresh_learning(self.c,self.now)
  self.assertEqual(V.learning_memory(self.c)['settled_count'],0)
  self.assertEqual(self.review()['outcome'],'pending')
  self.twice(value=0,state='FT');V.refresh_learning(self.c,self.now)
  memory=V.learning_memory(self.c);self.assertEqual(memory['settled_count'],1)
  self.assertEqual(sum(g['observed'] for g in memory['groups']),0)
  self.assertEqual(sum(g['not_observed'] for g in memory['groups']),1)
  self.twice(value=1,state='FT');V.refresh_learning(self.c,self.now)
  memory=V.learning_memory(self.c);self.assertGreater(memory['revision'],first)
  self.assertEqual(sum(g['observed'] for g in memory['groups']),1)
  self.assertEqual(sum(g['not_observed'] for g in memory['groups']),0)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_learning_review_versions').fetchone()[0],4)
  self.assertEqual(memory['review_count'],1)
 def test_idempotence_and_unknown_is_not_a_loss(self):
  self.emit();self.assertEqual(V.refresh_learning(self.c,self.now),0)
  self.now+=dt.timedelta(hours=7);V.settle_radar_results(self.c,self.now)
  self.assertEqual(V.refresh_learning(self.c,self.now),1)
  memory=V.learning_memory(self.c);self.assertEqual(memory['settled_count'],0)
  self.assertEqual(memory['groups'][0]['unknown'],1)
  for _ in range(4):self.assertEqual(V.refresh_learning(self.c,self.now),0)
  self.assertEqual(memory['revision'],V.learning_memory(self.c)['revision'])
 def test_legacy_is_explicit_not_reconstructed(self):
  self.emit();saved=V.dec(self.event['data']);saved.pop('learning_snapshot')
  self.c.execute('UPDATE v5_radar_notifications SET data=? WHERE id=?',(V.enc(saved),self.event['id']));self.c.commit()
  self.twice(value=1);V.refresh_learning(self.c,self.now)
  r=self.review();self.assertFalse(r['snapshot_complete']);self.assertEqual(r['engine'],'legacy_unknown')
  self.assertNotIn('context',r['known_at_signal']);self.assertIn('legacy_snapshot',[x['code'] for x in r['flags']])
 def test_real_outbox_export_and_dynamic_prompt_no_secrets_or_rule_changes(self):
  self.emit('cards');self.twice(value=1,market='cards');V.refresh_learning(self.c,self.now)
  self.c.execute("INSERT OR REPLACE INTO v4_settings VALUES('pulsescore_key','\"private-key-test\"')");self.c.commit()
  exported=V.dispatch(self.c,{'action':'export_learning'},self.now)
  self.assertEqual(exported['schema'],'stratedge.learning.v1');self.assertEqual(exported['coverage']['total_reviews'],1)
  self.assertNotIn('private-key-test',V.enc(exported));self.assertFalse(exported['memory']['changes_executable_rules'])
  analysis=V.export_analysis(self.c,self.now);self.assertIn('Cartons',analysis['learning_memory']['prompt_addendum'])
  self.assertNotIn('known_at_signal',V.learning_status(self.c)['recent'][0])
  self.send.reset_mock();V.dispatch(self.c,{'action':'export_learning'},self.now);self.send.assert_not_called()
 def test_incoherence_is_a_data_issue_not_invented_cause(self):
  self.emit();saved=V.dec(self.event['data']);saved['learning_snapshot']['reading']['metrics'].update(shots10=0,sot10=1)
  self.c.execute('UPDATE v5_radar_notifications SET data=? WHERE id=?',(V.enc(saved),self.event['id']));self.c.commit()
  self.twice(value=0,state='FT');V.refresh_learning(self.c,self.now)
  self.assertIn('inconsistent_shots',[x['code'] for x in self.review()['flags']])
  self.assertIn('colonnes',V.learning_memory(self.c)['prompt_addendum'])
 def test_learning_failure_does_not_prevent_live_or_result_notifications(self):
  self.emit();self.advance(value=1,send=False);self.advance(value=1,send=False);self.send.reset_mock()
  with patch.object(V,'refresh_learning',side_effect=RuntimeError('review failure')):
   V.run_dashboard(self.db,self.now)
  self.assertTrue(self.send.called)
  self.assertEqual(V.learning_status(self.c)['error']['type'],'RuntimeError')
  V.run_dashboard(self.db,self.now)
  self.assertIsNone(V.learning_status(self.c)['error']);self.assertEqual(V.learning_memory(self.c)['review_count'],1)
 def test_bounded_backfill_and_unsent_excluded(self):
  self.emit();self.twice(value=1)
  self.assertEqual(V.refresh_learning(self.c,self.now,limit=0),0)
  self.c.execute("UPDATE v5_radar_notifications SET delivery='failed'");self.c.commit()
  self.assertEqual(V.refresh_learning(self.c,self.now),0)
  self.c.execute("UPDATE v5_radar_notifications SET delivery='sent'");self.c.commit()
  self.assertEqual(V.refresh_learning(self.c,self.now,limit=1),1)

if __name__=='__main__':unittest.main()
