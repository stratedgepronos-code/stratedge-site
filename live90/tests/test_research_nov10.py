import ast,copy,datetime as dt,pathlib,unittest
from unittest.mock import patch
import test_decision_v5 as F
import live_v4 as V

class Research(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 bundle=F.DeskTests.bundle
 add=F.DeskTests.add
 def test_absent_is_not_zero_and_no_collector_version_assumption(self):
  r=copy.deepcopy(self.r)
  self.assertEqual(len(V.research_collection_gaps(r)),6)
  for group,key in [('stats','shots_off_target'),('stats','shots_outside'),('stats','tackles'),('stats','da'),('ind10','da10'),('ind5','da5')]:r.setdefault(group,{})[key]={'h':0,'a':0}
  self.assertEqual(V.research_collection_gaps(r),[])
  r['stats']['tackles']['a']=None
  self.assertEqual(V.research_collection_gaps(r),['Tacles'])
 def test_shadow_features_missing_thresholds_period_and_cutoff(self):
  r=copy.deepcopy(self.r);r['minute']=60;r['stats']['da']={'h':33,'a':20}
  p=copy.deepcopy(self.profile);p['prematch']['gf_h']=2.1
  d={'team':'h','market':'goals','status':'active'}
  out=V.prospective_research(r,d,p,self.ctx,self.now)
  self.assertFalse(out['sends_notifications'])
  self.assertEqual(out['candidates'],{'current_plus_da055':True,'current_plus_ftsum33':True})
  r['stats']['da']['h']=None
  self.assertIsNone(V.prospective_research(r,d,p,self.ctx,self.now)['candidates']['current_plus_da055'])
  for status in ('missing','excluded','stale','caution','waiting'):
   self.assertEqual(V.prospective_research(r,dict(d,status=status),p,self.ctx,self.now)['candidates'],{})
  for minute in (80,90):
   r['minute']=minute;self.assertEqual(V.prospective_research(r,d,p,self.ctx,self.now)['status'],'ineligible')
  r['minute']=60;d['continuity']={'blocked':True}
  self.assertEqual(V.prospective_research(r,d,p,self.ctx,self.now)['status'],'ineligible')
 def test_cards_additional_candidate_does_not_require_possession(self):
  r=copy.deepcopy(self.r);r['minute']=60;r['stats']['fouls']['h']=9
  r['stats']['possession']['h']=90
  ctx=copy.deepcopy(self.ctx);ctx['usable']=True;ctx['teams']['h']['card_references']['home']['for_per_match']=2
  d={'team':'h','market':'cards','status':'neutral'}
  out=V.prospective_research(r,d,self.profile,ctx,self.now)
  self.assertTrue(all(out['candidates'].values()));self.assertFalse(out['current_rule_confirmed'])
  self.assertEqual(d['status'],'neutral')
 def test_audit_archive_addition_leaves_decisions_and_outbound_unchanged(self):
  self.prepare();s=V.latest(self.c)[0];r=V.sample_data(s);p,ctx=V.inputs(self.c,V.bind(self.c,r),self.now)
  readings=V.desk_advice(self.c,s,p,ctx,self.now);before=copy.deepcopy(readings)
  with patch.object(V,'notify') as notify:
   for d in readings:V.record_window_audit(self.c,r,d,p,ctx,self.now)
   notify.assert_not_called()
  self.assertEqual(before,readings)
  rows=self.c.execute('SELECT data FROM v5_window_audits').fetchall()
  self.assertTrue(rows)
  for row in rows:self.assertEqual(V.dec(row[0])['prospective_research']['policy'],'research_20261110_v1')

class StrictAudit(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  source=(pathlib.Path(__file__).resolve().parents[2]/'ops/audit/mesure_live90.py').read_text()
  tree=ast.parse(source)
  names={'stamp','terminal_pair','truth','prematch_known'}
  subset=ast.Module(body=[n for n in tree.body if isinstance(n,ast.FunctionDef) and n.name in names],type_ignores=[])
  cls.env={'datetime':dt.datetime,'timezone':dt.timezone,'BASE':5,'KI':{'yellow_cards':11,'red_cards':12,'second_yellow':13}}
  exec(compile(subset,'audit_helpers','exec'),cls.env)
 def pair(self,score=0):
  p=[0]*40;p[1]=score
  return [(1,'2026-11-10T20:00:00Z','2026-11-10T20:00:01Z',tuple(p),False),
          (2,'2026-11-10T20:00:30Z','2026-11-10T20:00:31Z',tuple(p),False)]
 def test_live_46_and_90_plus_never_terminal(self):
  self.assertEqual(self.env['truth']({'ht':[],'ft':[],'live':{46:[2],47:[2]},'late':[3]}),(None,None,None,None))
 def test_confirmed_latest_pair_and_correction(self):
  pair=self.pair();self.assertIsNotNone(self.env['terminal_pair'](pair))
  pair[1]=self.pair(1)[1];self.assertIsNone(self.env['terminal_pair'](pair))
  self.assertIsNotNone(self.env['terminal_pair'](self.pair(1)))
  self.assertIsNone(self.env['terminal_pair']([pair[0],pair[0]]))
 def test_late_and_unknown_prematch_rejected(self):
  f=self.env['prematch_known'];ko='2026-11-10T20:00:00Z'
  self.assertTrue(f('2026-11-10T19:00:00Z',ko,'2026-11-10T18:59:00Z'))
  for at in (ko,'2026-11-10T20:01:00Z',None,'2026-11-10T19:00:00'):
   self.assertFalse(f(at,ko))
  self.assertFalse(f('2026-11-10T19:00:00Z',ko,'2026-11-10T19:01:00Z'))

if __name__=='__main__':unittest.main()
