"""Observed clock poisoning and competing collectors: no real external calls."""
import copy
import datetime as dt
import json
import pathlib
import shutil
import subprocess
import tempfile
import unittest
import test_decision_v5 as F
import live_v4 as V


class SportingClock(unittest.TestCase):
    setUp=F.DeskTests.setUp
    tearDown=F.DeskTests.tearDown
    add=F.DeskTests.add

    def sample(self, minute, seconds, state='LIVE', extra=0):
        return dict(self.r, minute=minute, state=state, minute_extra=extra,
                    collected_at=V.iso(self.now+dt.timedelta(seconds=seconds)))

    def test_monaco_forward_jump_does_not_poison_remaining_half(self):
        high,_=V.stream_transition(None,self.sample(44,0))
        retained,incident=V.stream_transition(high,self.sample(90,30))
        self.assertEqual(incident['reason'],'clock_forward_jump')
        self.assertEqual(retained,high)
        high,incident=V.stream_transition(retained,self.sample(46,17*60))
        self.assertIsNone(incident)
        self.assertEqual(high['minute'],46)

    def test_normal_progression_stoppage_and_score_correction(self):
        high=None
        for minute,seconds,state,extra in [(44,0,'LIVE',0),(45,60,'LIVE',3),(45,240,'HT',0),(46,1200,'LIVE',0),(60,2040,'LIVE',0)]:
            high,incident=V.stream_transition(high,self.sample(minute,seconds,state,extra))
            self.assertFalse(incident and incident['blocked'])
        r=self.sample(61,2100);r['score']={'h':1,'a':0}
        high,_=V.stream_transition(high,r)
        high,incident=V.stream_transition(high,self.sample(62,2160))
        self.assertFalse(incident['blocked'])
        self.assertEqual(incident['reason'],'score_correction_unattributed')
        high,_=V.stream_transition(high,self.sample(90,4000,'FT'))
        _,incident=V.stream_transition(high,self.sample(90,4030))
        self.assertEqual(incident['reason'],'phase_regression')

    def test_real_collection_gap_allows_matching_clock_advance(self):
        high,_=V.stream_transition(None,self.sample(40,0))
        high,incident=V.stream_transition(high,self.sample(65,40*60))
        self.assertIsNone(incident)
        self.assertEqual(high['minute'],65)

    def test_migration_replays_poisoned_watermark_preserving_original_incidents(self):
        for minute,seconds in [(44,0),(90,30),(46,1020)]:
            self.add(self.sample(minute,seconds),self.now+dt.timedelta(seconds=seconds))
        ids=[r['id'] for r in self.c.execute('SELECT id FROM samples ORDER BY id')]
        poisoned={'identity':[V.norm(self.r['home']),V.norm(self.r['away']),self.r['kickoff_ts']],
                  'phase':3,'minute':90,'state':'LIVE','collected_at':V.iso(self.now+dt.timedelta(seconds=30))}
        self.c.execute('INSERT INTO v5_stream_watermarks VALUES(?,?,?)',('123',ids[-1],V.enc(poisoned)))
        self.c.execute('INSERT INTO v5_stream_incidents VALUES(?,?,?,?)',(ids[-1],'123',1,V.enc({'blocked':True,'reason':'clock_regression'})))
        self.c.commit()
        first=V.prepare_stream(self.c,'123',limit=1)
        self.assertEqual(first,ids[0])
        r=V.guarded_sample(self.c,V.latest(self.c)[0],first)
        self.assertTrue(r['continuity']['blocked'])
        self.c.close();self.c=V.connect(self.db)
        r=V.guarded_sample(self.c,V.latest(self.c)[0])
        self.assertFalse(r['continuity']['blocked'])
        self.assertEqual(r['continuity']['alert_clock_floor'],46)
        old=self.c.execute('SELECT data FROM v5_stream_incident_revisions').fetchone()
        self.assertEqual(V.dec(old[0])['reason'],'clock_regression')
        self.assertEqual(self.c.execute('SELECT COUNT(*) FROM samples').fetchone()[0],3)

    def test_unparseable_batch_does_not_restart_replay_forever(self):
        for i in range(3):self.add(dict(self.r,state='OTHER',minute=None),self.now+dt.timedelta(seconds=i))
        first=V.prepare_stream(self.c,'123',limit=1)
        second=V.prepare_stream(self.c,'123',limit=1)
        self.assertGreater(second,first)

    def test_window_diagnostics_distinguish_warmup_reset_missing_and_zero(self):
        def diagnostic(r,hist=()):
            measures={k:V.recent_measure(r,hist,'shots' if k.startswith('shots') else 'sot','h',10 if k.endswith('10') else 5) for k in ('shots10','sot10','shots5')}
            return V.window_diagnostic(measures,tuple(measures))
        self.assertEqual(diagnostic(self.sample(48,0))['code'],'warming_up')
        current=self.sample(30,0)
        old=self.sample(29,-60);old['score']={'h':1,'a':0}
        hist=[{'id':1,'data':V.enc(old),'received_at':old['collected_at']}]
        self.assertEqual(diagnostic(current,hist)['code'],'post_goal_reset')
        current['ind5']={};current['ind10']={}
        self.assertEqual(diagnostic(current)['code'],'missing_window')
        current['ind10']={'shots10':{'h':0,'a':0}}
        self.assertEqual(V.recent_measure(current,[],'shots','h',10)['value'],0)

    def test_aligned_shots_are_a_separate_shadow_lane(self):
        current=self.sample(30,0)
        current['stats']=copy.deepcopy(current['stats']);current['stats']['shots_inside']={'h':5,'a':1}
        hist=[]
        for minute,seconds,shots,sot,inside in [(20,-600,5,2,2),(25,-300,8,4,4)]:
            old=copy.deepcopy(current);old.update(minute=minute,collected_at=V.iso(self.now+dt.timedelta(seconds=seconds)))
            old['stats']['shots']['h']=shots;old['stats']['sot']['h']=sot;old['stats']['shots_inside']['h']=inside
            hist.append({'id':minute,'received_at':old['collected_at'],'data':V.enc(old)})
        audit=V.shot_quality_audit(current,hist,'h')
        self.assertEqual(audit['windows']['10']['shots']['value'],6) # Native supplier window unchanged.
        self.assertFalse(audit['windows']['10']['shots_inside']['usable'])
        aligned=audit['aligned_cumulative']
        self.assertEqual(aligned['usage'],'shadow_only')
        self.assertEqual(aligned['windows']['10']['shots']['value'],5)
        self.assertEqual(aligned['windows']['10']['shots_inside']['value'],3)
        self.assertTrue(aligned['windows']['10']['shots_inside']['usable'])
        self.assertEqual(aligned['windows']['10']['shots_inside']['anchor_id'],20)
        switched=dict(current,client_id='another-producer')
        self.assertFalse(V.shot_quality_audit(switched,hist,'h')['aligned_cumulative']['windows']['10']['shots_inside']['usable'])
        # Even this separate lane must reject a corrected/incompatible counter.
        current['stats']['shots_inside']['h']=20
        self.assertFalse(V.shot_quality_audit(current,hist,'h')['aligned_cumulative']['windows']['10']['shots_inside']['usable'])

    def test_daily_review_uses_paris_alert_day_and_latest_result_revision(self):
        # Midnight Paris is 23:00 UTC in November. Result changes can occur later.
        dates=['2026-11-04T22:59:59+00:00','2026-11-04T23:00:00+00:00','2026-11-05T22:59:59+00:00','2026-11-05T23:00:00+00:00']
        for i,at in enumerate(dates,1):
            self.c.execute("INSERT INTO v5_radar_notifications(id,match_id,created_at,delivery,data) VALUES(?,?,?,'sent','{}')",(i,str(i),at))
            review={'radar_id':i,'match_id':str(i),'revision':2,'created_at':at,'outcome':'unknown'}
            self.c.execute('INSERT INTO v5_learning_reviews VALUES(?,?,?,?,?,?)',(i,'unknown',0,2,V.iso(self.now),V.enc(review)))
        self.c.execute("INSERT INTO v5_radar_result_events(radar_id,created_at,outcome,previous) VALUES(2,'2026-11-06T01:00:00+00:00','unknown','observed')")
        self.c.commit()
        report=V.export_daily_review(self.c,'2026-11-05',self.now)
        self.assertEqual([r['radar_id'] for r in report['learning']['reviews']],[2,3])
        self.assertEqual(report['learning']['memory']['settled_count'],0)
        self.assertEqual(len(report['learning']['result_revisions']),1)
        self.assertTrue(report['coverage']['legacy_signals_separate'])
        self.assertEqual(report,V.export_daily_review(self.c,'2026-11-05',self.now))
        with self.assertRaises(ValueError):V.export_daily_review(self.c,'invalid',self.now)


class SourceLease(unittest.TestCase):
    def test_php_source_selection_upgrade_fallback_and_no_merging(self):
        php=shutil.which('php')
        if not php:self.skipTest('PHP absent')
        if 'pdo_sqlite' not in subprocess.check_output([php,'-m'],text=True):self.skipTest('pdo_sqlite absent')
        core=pathlib.Path(__file__).resolve().parents[1]/'public/api/live90/core.php'
        script='''<?php
require $argv[1];
$db=db90();$now=gmdate('Y-m-d\\TH:i:s\\Z');
$row=['packball_id'=>'1','state'=>'LIVE','minute'=>30,'score'=>['h'=>0,'a'=>0],
 'stats'=>['fouls'=>['h'=>9,'a'=>2],'red_cards'=>['h'=>0,'a'=>0],'tackles'=>['h'=>2,'a'=>4]]];
$x=['client_id'=>'old','collector_version'=>'5.0.0','rows'=>[$row]];
$answers=[];$db->beginTransaction();
$answers[]=source90($db,$x,$now);
$x['client_id']='new';$x['collector_version']='5.2.0';$x['installation_id']='installation';
$answers[]=source90($db,$x,$now);
$x['client_id']='old';$x['collector_version']='5.0.0';
$answers[]=source90($db,$x,$now);
$x['client_id']='new-tab';$x['collector_version']='5.2.0';
$answers[]=source90($db,$x,$now);
$x['client_id']='bad';$x['collector_version']='5.3.0';$x['rows'][0]['quality_errors']=['bad columns'];
$answers[]=source90($db,$x,$now);
$s=$db->prepare('UPDATE collection_primary SET last_good_at=?');$s->execute([gmdate('Y-m-d\\TH:i:s\\Z',time()-101)]);
$x['client_id']='fallback';$x['collector_version']='5.1.0';$x['rows']=[$row];
$answers[]=source90($db,$x,$now);
$primary=$db->query('SELECT * FROM collection_primary')->fetch();
$sources=$db->query('SELECT * FROM collection_sources')->fetchAll();$db->commit();
echo json_encode(['answers'=>$answers,'primary'=>$primary,'sources'=>$sources]);
'''
        with tempfile.TemporaryDirectory() as tmp:
            root=pathlib.Path(tmp);(root/'test.php').write_text(script)
            (root/'env').write_text('SE90_DB="'+str(root/'test.sqlite')+'"\n')
            import os
            result=json.loads(subprocess.check_output([php,str(root/'test.php'),str(core)],env=dict(os.environ,SE90_ENV=str(root/'env')),text=True))
        self.assertEqual([x['accepted'] for x in result['answers']],[True,True,False,False,False,True])
        self.assertEqual(result['answers'][2]['code'],'SOURCE_CONFLICT')
        self.assertEqual(result['primary']['client_id'],'fallback')
        self.assertEqual(len(result['sources']),5)
        self.assertIn('tackles',json.loads(result['sources'][0]['capabilities']))


if __name__=='__main__':unittest.main()
