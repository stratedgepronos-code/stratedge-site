"""Settlement proof exports and audit metadata, without predictive changes."""
import copy,datetime as dt,importlib.util,json,os,pathlib,sys,unittest
from unittest.mock import patch
import test_decision_v5 as F
import test_radar_results as R
import test_approved_cleanup as C
import live_v4 as V

class EveningAudit(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 add=F.DeskTests.add
 prepare=F.DeskTests.prepare
 bundle=F.DeskTests.bundle
 reading=F.DeskTests.reading
 def test_programme_day_uses_kickoffs_not_export_clock(self):
  now=V.date('2026-11-05T22:30:00Z')
  result=V.programme_dates([{'kickoff':'2026-11-05T23:05:00Z'},{'kickoff':'2026-11-06T20:00:00Z'}],now)
  self.assertEqual(result['reference_day'],'2026-11-06');self.assertEqual(result['export_day'],'2026-11-05')
  for at,day in [('2026-03-28T23:30:00Z','2026-03-29'),('2026-10-25T22:30:00Z','2026-10-25'),('2026-10-25T23:00:00Z','2026-10-26')]:
   self.assertEqual(V.programme_dates([{'kickoff':at}],now)['reference_day'],day)
  mixed=V.programme_dates([{'kickoff':'2026-11-05T22:00:00Z'},{'kickoff':'2026-11-06T20:00:00Z'}],now)
  self.assertIsNone(mixed['reference_day']);self.assertEqual(mixed['reference_day_basis'],'multiple_fixture_days')
 def test_proofs_include_raw_terminal_rows_and_missing_references(self):
  self.r.update(state='FT',minute=90);self.add(self.r,self.now)
  sid=V.latest(self.c)[0]['id']
  proofs=V.export_result_proofs(self.c,[{'result_evidence':{'sample_id':sid,'confirmed_samples':[sid,999999]}}],[])
  self.assertEqual(proofs['samples'][str(sid)]['data']['state'],'FT')
  self.assertEqual(proofs['unavailable_sample_ids'],[999999]);self.assertFalse(proofs['all_references_resolved'])
  self.assertIsNone(proofs['samples']['999999'])
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v5_window_audits').fetchone()[0],0)
 def test_pagination_fixed_ceiling_resume_and_complete_scopes(self):
  at='2026-11-05T10:00:00Z'
  for i in range(7):self.c.execute('INSERT INTO v5_window_audits(sample_id,match_id,team,market,engine,created_at,data) VALUES(?,?,?,?,?,?,?)',(i+1,'123','h','goals','test',at,'{}'))
  self.c.commit()
  for size in (1,3,7,100):
   first=V.export_window_audit(self.c,{'day':'2026-11-05','page_size':size},self.now);pages=[first];cursor=first['next_cursor']
   while cursor is not None:
    page=V.export_window_audit(self.c,{'day':'2026-11-05','page_size':size,'cursor':cursor,'ceiling':first['ceiling']},self.now)
    self.assertEqual(page,V.export_window_audit(self.c,{'day':'2026-11-05','page_size':size,'cursor':cursor,'ceiling':first['ceiling']},self.now))
    pages.append(page);cursor=page['next_cursor']
   ids=[r['id'] for p in pages for r in p['readings']]
   self.assertEqual(len(ids),7);self.assertEqual(len(set(ids)),7);self.assertTrue(pages[-1]['pagination_done'])
   self.assertEqual(pages[-1]['complete'],len(pages)==1)
  self.c.execute('INSERT INTO v5_window_audits(sample_id,match_id,team,market,engine,created_at,data) VALUES(?,?,?,?,?,?,?)',(100,'123','h','goals','test',at,'{}'));self.c.commit()
  self.assertEqual(V.export_window_audit(self.c,{'day':'2026-11-05','ceiling':7},self.now)['total'],7)
 def test_breakdown_counts_notifications_current_results_and_first_per_match(self):
  for i,(outcome,path,market,period,delivery) in enumerate([('observed','card_rapid_5m','cards','FT','sent'),('pending','card_rapid_5m','cards','FT','sent'),('not_observed','usual','cards','FT','sent'),('unknown','half_10m','goals','HT','sent'),('observed','half_10m','goals','HT','failed')],1):
   snap={'engine':'v-test','prompt_revision':'2026-10-24-goal-periods1','live':{'collector_version':'5.0.0'},'reading':{'trigger_path':path}}
   saved={'result_target':{'period':period},'learning_snapshot':snap}
   self.c.execute('INSERT INTO v5_radar_notifications(id,match_id,market,created_at,delivery,data) VALUES(?,?,?,?,?,?)',(i,'123',market,f'2026-11-05T10:00:0{i}Z',delivery,V.enc(saved)))
   self.c.execute('INSERT INTO v5_radar_results VALUES(?,?,?,?,?)',(i,outcome,V.iso(self.now),None,'{}'))
  self.c.commit();b=V.radar_breakdown(self.c,('2026-11-04T23:00:00+00:00','2026-11-05T23:00:00+00:00'))
  self.assertEqual(b['alert_count'],4);self.assertEqual(b['match_count'],1);self.assertEqual(b['collector_versions_at_signal'],{'5.0.0':4})
  self.assertEqual(sum(sum(g['first_alert_control'].values()) for g in b['groups']),2)
  self.assertEqual(sum(g['outcomes']['observed'] for g in b['groups']),1)
 def test_input_hashes_do_not_claim_r6_or_mutate_context(self):
  ctx={'schema':'stratedge.context.v4','watch':True};before=copy.deepcopy(ctx)
  snapshot=V.learning_snapshot(self.r,{}, {},ctx,self.now)
  self.assertEqual(ctx,before);self.assertIsNone(snapshot['prompt_revision'])
  self.assertEqual(snapshot['prompt_revision_source'],'not_attested')
  self.assertEqual(len(snapshot['input_provenance']['context_sha256']),64)
  other=V.learning_snapshot(self.r,{}, {},dict(ctx,watch=False),self.now)
  self.assertNotEqual(snapshot['input_provenance']['context_sha256'],other['input_provenance']['context_sha256'])
 def test_exact_cutoff_and_postponed_do_not_trigger(self):
  self.prepare();r=V.sample_data(V.latest(self.c)[0]);r['minute']=79;r['collected_at']=V.iso(self.now)
  self.assertFalse(V.alert_cutoff_reached(r,self.now+dt.timedelta(seconds=59)))
  self.assertTrue(V.alert_cutoff_reached(r,self.now+dt.timedelta(seconds=60)))
  for state in ('POSTPONED','OTHER','HT','FT'):
   self.r['state']=state;self.add(self.r,self.now)
   for market in ('goals','cards'):self.assertNotEqual(self.reading(market)['status'],'active')

class PafosReplay(unittest.TestCase):
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
 events=R.RadarResults.events
 def test_yellow_retraction_live90_red_then_two_ft_and_late_correction(self):
  self.emit('cards');self.twice(value=1,market='cards');V.refresh_learning(self.c,self.now)
  self.assertEqual(self.result()['outcome'],'observed')
  self.twice(value=0,market='cards');V.refresh_learning(self.c,self.now)
  self.assertEqual(self.result()['outcome'],'pending')
  self.assertEqual(V.learning_memory(self.c)['groups'][0]['observed'],0)
  self.r['minute']=90;self.r['stats']['red_cards']['h']=1
  self.twice(value=0,market='cards',seconds=1800)
  self.assertEqual(self.result()['outcome'],'pending')
  self.twice(value=0,market='cards')
  self.assertEqual(self.result()['outcome'],'pending')
  self.advance(value=0,state='FT',market='cards');self.assertEqual(self.result()['outcome'],'pending')
  self.advance(value=0,state='FT',market='cards');self.assertEqual(self.result()['outcome'],'not_observed')
  # A valid late feed correction is followed even after the initial 3-day window.
  self.now+=dt.timedelta(days=4)
  self.twice(value=1,state='FT',market='cards');self.assertEqual(self.result()['outcome'],'observed')
  V.refresh_learning(self.c,self.now)
  report=V.export_learning(self.c,self.now)
  self.assertTrue(report['result_proofs']['all_references_resolved'])
  self.assertEqual(len(report['reviews']),1)
  ids={i for e in report['result_revisions'] for i in e['data'].get('confirmed_samples',[])}
  self.assertTrue(ids.issubset({int(k) for k in report['result_proofs']['samples']}))
  self.assertEqual(len(self.events()),4)

spec=importlib.util.spec_from_file_location('cleanup_evening',pathlib.Path(__file__).resolve().parents[2]/'ops/live90-cleanup-approved-20261105.py')
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)
class ApprovedEight(unittest.TestCase):
 setUp=C.ApprovedCleanup.setUp
 candidate=C.ApprovedCleanup.candidate
 recent=C.ApprovedCleanup.recent
 def test_exact_authorized_inventory(self):
  self.assertEqual(len(M.APPROVED),8);self.assertEqual(sum(M.APPROVED.values()),4544638408)
  self.assertFalse(set(M.APPROVED)&set(M.PRESERVED))
 def test_optin_full_verification_preserves_other_archives_idempotent(self):
  old=self.candidate('old');untouched=self.candidate('untouched');a=self.recent('new1');b=self.recent('new2')
  with patch.object(M,'ROOT',self.root),patch.object(M,'APPROVED',{'old':3}),patch.object(M,'PRESERVED',{'new1':a.stat().st_size,'new2':b.stat().st_size}),patch.object(os,'geteuid',return_value=0):
   with patch.object(sys,'argv',['cleanup']):M.main()
   self.assertTrue(old.exists())
   with patch.object(sys,'argv',['cleanup','--approval',M.APPROVAL]):M.main();M.main()
   self.assertFalse(old.exists());self.assertTrue(untouched.exists());self.assertTrue(a.exists());self.assertTrue(b.exists())
 def test_corrupt_preserved_backup_blocks_every_deletion(self):
  old=self.candidate('old');a=self.recent('new1');b=self.recent('new2',corrupt=True)
  with patch.object(M,'ROOT',self.root),patch.object(M,'APPROVED',{'old':3}),patch.object(M,'PRESERVED',{'new1':a.stat().st_size,'new2':b.stat().st_size}),patch.object(os,'geteuid',return_value=0),patch.object(sys,'argv',['cleanup','--approval',M.APPROVAL]):
   with self.assertRaises(ValueError):M.main()
  self.assertTrue(old.exists())

if __name__=='__main__':unittest.main()
