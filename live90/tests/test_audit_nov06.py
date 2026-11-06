"""Nominal foul windows: preserve decisions, export clocks and replay raw deltas."""
import copy,datetime as dt,json,pathlib,subprocess,sys,unittest
from unittest.mock import patch
import test_decision_v5 as F
import live_v4 as V

class WindowTiming(unittest.TestCase):
 def pair(self,seconds=360,delta=6):
  now=V.date('2026-11-06T21:28:49.088Z')
  current={'sample_id':200,'packball_id':'m1','client_id':'c','collector_version':'5.2.0','state':'LIVE','minute':70,'minute_extra':0,'score':{'h':0,'a':0},'stats':{'fouls':{'h':14,'a':2}},'collected_at':V.iso(now),'received_at':V.iso(now)}
  old=copy.deepcopy(current);old.update(sample_id=100,minute=70-delta,collected_at=V.iso(now-dt.timedelta(seconds=seconds)),received_at=V.iso(now-dt.timedelta(seconds=seconds)))
  old['stats']['fouls']['h']=11
  return current,old
 def measured(self,current,old,w=5):
  return V.recent_measure(current,[{'id':100,'received_at':old['received_at'],'data':V.enc(old)}],'fouls','h',w)
 def test_boundary_late_capture_and_existing_tolerance_no_prorating(self):
  for seconds,delta,expected in [(300,5,3),(360,6,3),(400,7,3),(400.001,7,None),(299.999,5,None)]:
   with self.subTest(seconds=seconds):
    current,old=self.pair(seconds,delta);q=self.measured(current,old)
    self.assertEqual(q['value'],expected)
    if expected is not None:
     self.assertAlmostEqual(q['timing']['elapsed_seconds'],seconds)
     self.assertEqual(q['counters'],{'stat':'fouls','team':'h','before':11,'after':14})
     self.assertEqual(q['timing']['sporting_minutes_delta'],delta)
 def test_two_reported_examples_have_durations_not_exact_nominal_windows(self):
  for at,start,seconds in [('2026-11-06T21:28:49.088Z','2026-11-06T21:22:57.145Z',351.943),('2026-11-06T21:28:49.088Z','2026-11-06T21:17:57.144Z',651.944),('2026-11-06T20:12:57.161Z','2026-11-06T20:06:57.160Z',360.001),('2026-11-06T20:12:57.161Z','2026-11-06T20:01:57.152Z',660.009)]:
   # Clock values from the report; counters/football clock deliberately synthetic.
   current,old=self.pair();current['collected_at']=at;old['collected_at']=start
   timing=V.window_timing(current,old,5 if seconds<600 else 10,'cumulative_delta')
   self.assertAlmostEqual(timing['elapsed_seconds'],seconds)
  self.assertEqual(V.covered_duration({'timing':{'elapsed_seconds':351.943}},5),'5 min 52 s entre captures')
 def test_pause_and_counter_correction_never_create_fabricated_counts(self):
  current,old=self.pair();old.update(state='HT');self.assertIsNone(self.measured(current,old)['value'])
  old.update(state='LIVE',minute=44);current['minute']=50;self.assertIsNone(self.measured(current,old)['value'])
  current,old=self.pair();old['stats']['fouls']['h']=15
  q=self.measured(current,old);self.assertIsNone(q['value']);self.assertIn('Compteur corrigé',q['reason'])
  self.assertEqual(q['timing']['elapsed_seconds'],360)
 def test_provider_windows_keep_unknown_event_duration_and_measured_zero(self):
  current,old=self.pair();current['ind5']={'fouls5':{'h':0}}
  q=self.measured(current,old)
  self.assertEqual(q['value'],0);self.assertIsNone(q['timing']['elapsed_seconds']);self.assertIsNone(q['anchor_id'])
  self.assertEqual(q['timing']['convention'],'provider_nominal_window')
 def test_offline_delta_checks_identity_period_source_and_corrections(self):
  current,old=self.pair();q=self.measured(current,old)
  check=V.replay_window(q,current,old,'fouls','h');self.assertEqual(check['status'],'verified_delta');self.assertEqual(check['recomputed_count'],3)
  for key,value,issue in [('packball_id','other','identity_unknown_or_mismatch'),('minute',44,'phase_unknown_or_mismatch'),('collector_version','old','source_changed')]:
   changed=dict(old,**{key:value});result=V.replay_window(q,current,changed,'fouls','h')
   self.assertIn(issue,result['issues']);self.assertEqual(result['status'],'not_verified')
  changed=copy.deepcopy(old);changed['stats']['fouls']['h']=15
  self.assertIn('counter_correction',V.replay_window(q,current,changed,'fouls','h')['issues'])
  self.assertEqual(V.replay_window(q,current,None,'fouls','h')['status'],'unavailable')
 def test_effective_reference_shadow_never_normalizes_event_counts(self):
  current,old=self.pair();q=self.measured(current,old)
  d={'metrics':{'comparison_window':5},'window_quality':{'fouls5':q}}
  ctx={'teams':{'h':{'card_references':{'home':{'fouls_for_per_match':50}}}}}
  before=copy.deepcopy(d);shadow=V.foul_duration_shadow(d,ctx,'h')
  self.assertTrue(shadow['nominal_exceeds']);self.assertFalse(shadow['elapsed_exceeds'])
  self.assertEqual(shadow['observed_count'],3);self.assertEqual(shadow['elapsed_expected'],50/15)
  self.assertEqual(shadow['usage'],'shadow_only');self.assertFalse(shadow['sends_notifications']);self.assertEqual(d,before)
 def test_intermediate_pause_or_correction_is_flagged_without_new_gate(self):
  current,old=self.pair();middle=copy.deepcopy(old);middle['collected_at']=V.iso(V.date(old['collected_at'])+dt.timedelta(seconds=120))
  ctx={'teams':{'h':{'card_references':{'home':{'fouls_for_per_match':12}}}}}
  for state,count,key in [('HT',12,'non_live_sample_ids'),('LIVE',10,'counter_decrease_sample_ids')]:
   middle['state']=state;middle['stats']['fouls']['h']=count
   hist=[{'id':100,'received_at':old['received_at'],'data':V.enc(old)},{'id':150,'received_at':middle['collected_at'],'data':V.enc(middle)}]
   q=V.recent_measure(current,hist,'fouls','h',5)
   self.assertEqual(q['value'],3);self.assertEqual(q['audit_observations'][key],[150])
   d={'metrics':{'comparison_window':5},'window_quality':{'fouls5':q}}
   self.assertEqual(V.foul_duration_shadow(d,ctx,'h')['status'],'unavailable')

class ProofExport(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 add=F.DeskTests.add
 prepare=F.DeskTests.prepare
 reading=F.DeskTests.reading
 bundle=F.DeskTests.bundle
 def sample_with_anchor(self):
  old=copy.deepcopy(self.r);old['minute']=24;old['stats']['fouls']['h']=6
  self.add(old,self.now-dt.timedelta(seconds=360));anchor=V.sample_data(V.latest(self.c)[0])
  self.add(self.r,self.now);current=V.sample_data(V.latest(self.c)[0])
  q=V.recent_measure(current,self.c.execute('SELECT * FROM samples').fetchall(),'fouls','h',5)
  return current,anchor,q
 def test_export_raw_anchors_replay_and_explicit_unavailable(self):
  current,anchor,q=self.sample_with_anchor()
  missing=dict(q,anchor_id=999999)
  snapshot={'live':current,'reading':{'team':'h','window_quality':{'fouls5':q,'fouls10':missing}}}
  export=V.export_decision_proofs(self.c,[{'radar_id':678,'known_at_signal':snapshot}])
  self.assertEqual(export['unavailable_sample_ids'],[999999]);self.assertFalse(export['all_references_resolved'])
  self.assertEqual(export['samples'][str(anchor['sample_id'])]['data']['stats']['fouls']['h'],6)
  checks=export['window_replays'][0]['checks'];self.assertEqual(checks['fouls5']['status'],'verified_delta')
  self.assertEqual(checks['fouls5']['recomputed_count'],3);self.assertEqual(checks['fouls10']['status'],'unavailable')
 def test_full_confirmations_and_anchors_in_window_page(self):
  current,anchor,q=self.sample_with_anchor()
  d={'team':'h','market':'cards','status':'active','window_quality':{'fouls5':q},'facts':['fact'],
     'duration_shadow':{'usage':'shadow_only'},'metrics':{'comparison_window':5}}
  frozen=V.frozen_confirmation(current,d);d['facts'].append('changed')
  self.assertEqual(frozen['reading']['facts'],['fact'])
  d['confirmation']={'samples':[frozen,V.frozen_confirmation(current,d)]}
  V.record_window_audit(self.c,current,d,{}, {},self.now);self.c.commit()
  page=V.export_window_audit(self.c,{'day':self.now.astimezone(V.ZoneInfo('Europe/Paris')).date().isoformat()},self.now)
  self.assertIn(anchor['sample_id'],page['raw_samples']);self.assertIn(anchor['sample_id'],page['samples'])
  self.assertEqual(page['samples'][anchor['sample_id']]['stats']['fouls']['h'],6)
  archived=page['readings'][0]['data'];self.assertEqual(archived['confirmation']['samples'][0]['reading'],frozen['reading'])
  self.assertEqual(archived['window_quality']['fouls5']['timing']['elapsed_seconds'],360)
  self.assertEqual(page['unavailable_sample_ids'],[])
 def test_archives_without_window_metadata_not_reconstructed(self):
  current,anchor,q=self.sample_with_anchor()
  snapshot={'live':current,'reading':{'team':'h','window_quality':{'fouls5':q}},'initial_confirmation':{'samples':[{'sample':anchor,'reading':{}}]}}
  export=V.export_decision_proofs(self.c,[{'radar_id':670,'known_at_signal':snapshot}])
  self.assertTrue(export['all_references_resolved'])
  initial=export['window_replays'][1];self.assertFalse(initial['window_metadata_available']);self.assertEqual(initial['checks'],{})
 def test_live_candidate_unchanged_when_shadow_disagrees(self):
  self.prepare();p=dict(self.m,usable=True);ctx=dict(self.ctx,usable=True)
  ctx=copy.deepcopy(ctx);ctx['teams']['h']['card_references']['home']['fouls_for_per_match']=50
  current=copy.deepcopy(self.r);current['ind5'].pop('fouls5',None);current['ind10'].pop('fouls10',None)
  old=copy.deepcopy(current);old['minute']=24;old['collected_at']=V.iso(self.now-dt.timedelta(seconds=360));old['received_at']=old['collected_at'];old['stats']['fouls']['h']=6
  hist=[{'id':1,'received_at':old['received_at'],'data':V.enc(old)}]
  d=V.desk_reading(current,p,ctx,'h','cards',self.now,hist)
  self.assertEqual(d['status'],'candidate');self.assertTrue(d['duration_shadow']['nominal_exceeds']);self.assertFalse(d['duration_shadow']['elapsed_exceeds'])
  self.assertEqual(d['metrics']['fouls5'],3);self.assertIsNone(d['metrics']['fouls10'])
  self.assertEqual(d['window'],'6 min entre captures (cible nominale 5 min)')
  self.assertEqual(V.audit_presentation(d)['window'],d['window'])
  message=V.radar_message(current,d,{'quotes':[]})
  self.assertIn('3 fautes sur 6 min entre captures',message);self.assertIn('repère nominal',message)
 def test_readonly_operator_replay_never_writes_db(self):
  current,anchor,q=self.sample_with_anchor()
  review={'radar_id':670,'known_at_signal':{'live':current,'reading':{'team':'h','window_quality':{'fouls5':q}}}}
  self.c.execute('INSERT INTO v5_learning_reviews(radar_id,data) VALUES(?,?)',(670,V.enc(review)));self.c.commit()
  before=self.c.execute('SELECT total_changes()').fetchone()[0]
  script=pathlib.Path(__file__).resolve().parents[2]/'ops/replay-live90-windows.py'
  run=subprocess.run([sys.executable,str(script),'--db',str(self.db),'--radar-ids','670,678'],capture_output=True,text=True,check=True)
  payload=json.loads(run.stdout.split(' ',1)[1]);self.assertTrue(payload['read_only']);self.assertFalse(payload['changes_notifications'])
  self.assertEqual(payload['missing_review_ids'],[678]);self.assertEqual(payload['unavailable_sample_ids'],[])
  self.assertEqual(self.c.execute('SELECT total_changes()').fetchone()[0],before)

if __name__=='__main__':unittest.main()
