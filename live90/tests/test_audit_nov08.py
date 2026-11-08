"""Audit Nov 8: counting units and programme transitions, no predictive changes."""
import copy, datetime as dt, unittest
from unittest.mock import patch
import test_decision_v5 as F
import test_radar_results as R
import live_v4 as V

class AuditCounts(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 def test_report_register_three_counting_units_and_empty_legacy(self):
  # Synthetic rows reproduce only the published register; not raw proof verification.
  register=[('catolica','h','goals','FT',0),('thun','a','goals','FT',0),('aik','a','goals','FT',0),('manu','h','goals','HT',1),('freiburg','a','cards','FT',1),('freiburg','h','goals','HT',0),('viking','h','goals','FT',1),('inter','h','goals','HT',0),('brest','h','cards','FT',1),('midt','h','goals','HT',0),('inter','h','cards','FT',1),('brest','a','cards','FT',0),('havre','a','cards','FT',1),('celta','a','cards','FT',1),('midt','h','goals','FT',1),('inter','h','cards','FT',0),('union','h','goals','FT',1),('villarreal','h','goals','FT',1),('villarreal','a','cards','FT',0),('fiorentina','h','cards','FT',1),('utrecht','h','goals','FT',0),('estrela','a','goals','HT',0),('fiorentina','h','cards','FT',0)]
  at='2026-11-08T20:00:00+00:00'
  for i,(match,team,market,period,won) in enumerate(register,739):
   outcome='observed' if won else 'not_observed'
   saved={'result_target':{'period':period},'learning_snapshot':{'engine':'report-engine','reading':{'trigger_path':'synthetic'}}}
   self.c.execute('INSERT INTO v5_radar_notifications(id,event_key,match_id,team,market,counter,created_at,data,delivery) VALUES(?,?,?,?,?,?,?,?,?)',(i,str(i),match,team,market,0,at,V.enc(saved),'sent'))
   self.c.execute('INSERT INTO v5_radar_results(radar_id,outcome,updated_at,sample_id,data) VALUES(?,?,?,?,?)',(i,outcome,at,None,'{}'))
   self.c.execute('INSERT INTO v5_learning_reviews(radar_id,data) VALUES(?,?)',(i,V.enc({'radar_id':i,'match_id':match,'outcome':outcome})))
  self.c.commit()
  report=V.export_daily_review(self.c,'2026-11-08',V.date('2026-11-08T22:48:34Z'))
  self.assertEqual(report['signals'],[]);self.assertEqual(len(report['learning']['reviews']),23)
  breakdown=report['learning']['breakdown'];self.assertEqual(breakdown['match_count'],16)
  for name,total,yes,no in [('outcomes',23,11,12),('first_alert_control',20,11,9),('first_team_alert_control',21,11,10)]:
   group=breakdown['counting_controls'][name];self.assertEqual(group['total'],total)
   self.assertEqual(group['outcomes'],{'observed':yes,'not_observed':no,'unknown':0,'pending':0})
  self.assertFalse(report['coverage']['day_closed_at_export']);self.assertEqual(report['coverage']['radar_sent_alerts'],23)
  self.assertTrue(V.export_daily_review(self.c,'2026-11-08',V.date('2026-11-08T23:00:00Z'))['coverage']['day_closed_at_export'])
  # A correction affects current counts once, not the number of notifications.
  self.c.execute("UPDATE v5_radar_results SET outcome='pending' WHERE radar_id=739")
  counts=V.radar_breakdown(self.c,None)['counting_controls']['outcomes']
  self.assertEqual(counts['total'],23);self.assertEqual(counts['outcomes']['pending'],1)

class ProgrammeTransition(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 bundle=F.DeskTests.bundle
 add=F.DeskTests.add
 def test_activation_duplicate_reactivation_and_old_snapshot(self):
  original=self.bundle('packball');V.import_bundle(self.c,'packball',original,self.before)
  initial=copy.deepcopy(V.programme_state(self.c))
  V.import_bundle(self.c,'packball',original,self.before+dt.timedelta(seconds=1))
  self.assertEqual(V.programme_state(self.c),initial)
  next_match=copy.deepcopy(self.m);next_match.update(home='Tomorrow',kickoff=V.iso(self.ko+dt.timedelta(days=1)))
  next_bundle=self.bundle('packball',[next_match]);V.import_bundle(self.c,'packball',next_bundle,self.now)
  second=V.programme_state(self.c);self.assertEqual(second['previous_version'],initial['version'])
  self.assertEqual(second['removed_fixtures'],[V.key(self.m)])
  self.assertEqual(second['dates']['reference_day'],(self.ko+dt.timedelta(days=1)).astimezone(V.ZoneInfo('Europe/Paris')).date().isoformat())
  self.add(self.r,self.now);sample=V.latest(self.c)[0];V.record_sample_audit(self.c,sample,self.now,'outside_program')
  recorded=V.dec(self.c.execute('SELECT data FROM v5_sample_audits').fetchone()[0]);self.assertEqual(recorded['programme_version'],second['version'])
  # Reimport an older programme is a new activation, not a new prematch import.
  V.import_bundle(self.c,'packball',original,self.now)
  third=V.programme_state(self.c);self.assertNotEqual(third['version'],initial['version'])
  self.assertEqual(third['import_digest'],initial['import_digest'])
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_programme_events').fetchone()[0],3)
  self.assertEqual(V.dec(self.c.execute('SELECT data FROM v5_programme_events WHERE id=1').fetchone()[0]),initial)
  day=self.now.astimezone(V.ZoneInfo('Europe/Paris')).date().isoformat()
  report=V.export_daily_review(self.c,day,self.now)
  self.assertEqual(len(report['programme_transitions']),3)
 def test_missing_or_late_context_remains_unusable_and_null_prompt(self):
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.before)
  f=V.bind(self.c,self.r);p,ctx=V.inputs(self.c,f,self.now);self.assertIsNone(ctx)
  V.import_bundle(self.c,'analyst',self.bundle('analyst'),self.now)
  p,ctx=V.inputs(self.c,f,self.now);self.assertFalse(ctx['usable'])
  snap=V.learning_snapshot(self.r,{},p,ctx,self.now)
  self.assertIsNone(snap['prompt_revision']);self.assertEqual(snap['prompt_revision_source'],'not_attested')
  before=copy.deepcopy(snap);ctx['summary']='Subsequent context'
  # Persisted snapshots are serialized, never relabelled with the current prompt.
  frozen=V.dec(V.enc(before));self.assertIsNone(frozen['prompt_revision'])
 def test_invalid_import_has_no_programme_side_effect(self):
  bad=self.bundle('packball');bad['matches'][0]['prematch']['n_h']=-1
  with self.assertRaises(ValueError):V.import_bundle(self.c,'packball',bad,self.before)
  self.assertIsNone(V.programme_state(self.c));self.assertIsNone(V.active_fixtures(self.c))

class ResultAcrossProgramme(unittest.TestCase):
 setUp=R.RadarResults.setUp
 tearDown=R.RadarResults.tearDown
 bundle=R.RadarResults.bundle
 add=R.RadarResults.add
 prepare=R.RadarResults.prepare
 reading=R.RadarResults.reading
 emit=R.RadarResults.emit
 advance=R.RadarResults.advance
 twice=R.RadarResults.twice
 result=R.RadarResults.result
 def test_new_programme_excludes_new_signals_but_keeps_result_followup(self):
  self.emit('cards')
  next_match=copy.deepcopy(self.m);next_match.update(home='Next-day team',kickoff=V.iso(self.ko+dt.timedelta(days=1)))
  V.import_bundle(self.c,'packball',self.bundle('packball',[next_match]),self.now)
  self.assertNotIn(V.key(self.m),V.active_fixtures(self.c))
  count=self.c.execute('SELECT COUNT(*) FROM v5_radar_notifications').fetchone()[0]
  # Observe, retract, then close with terminal evidence after programme replacement.
  self.twice(value=1,market='cards');self.assertEqual(self.result()['outcome'],'observed')
  self.twice(value=0,market='cards');self.assertEqual(self.result()['outcome'],'pending')
  self.r['minute']=90;self.twice(value=0,state='FT',market='cards')
  self.assertEqual(self.result()['outcome'],'not_observed')
  with patch.object(V,'refresh_radar_prices'),patch.object(V,'deliver_radar_results'):
   V.run_dashboard(self.db,self.now)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_radar_notifications').fetchone()[0],count)

if __name__=='__main__':unittest.main()
