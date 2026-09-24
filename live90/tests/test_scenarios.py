import copy, datetime as dt, io, json, pathlib, re, socket, sqlite3, sys, tempfile, unittest, urllib.error
from unittest.mock import patch
ROOT=pathlib.Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'server'))
import scenarios as S, scenario_engine as E, engine
UTC=dt.timezone.utc

def fixture(now):
 b={'n_h':10,'n_a':10,'gf_h':1.6,'gf_a':1.2,'ga_h':1.2,'ga_a':1.5,'shots_h':10,'shots_a':8,'sot_h':4,'sot_a':3}
 s={'id':'reaction-h','title':'Réaction de A','hypothesis':'Hypothèse sans fréquence historique mesurée','evidence_status':'hypothesis','rationale':'Comparer la réaction observée aux moyennes de tirs avant-match','team':'h','trigger':{'kind':'conceded_early','before_minute':15},'window':{'from':15,'to':40,'period':'HT'},'score':{'min_total':1,'max_total':1,'relation':'trailing_one'},'conditions':[{'metric':'sot5','min':2},{'metric':'shots5','min':3},{'metric':'activity_ratio','min':1.2}],'market':{'type':'team_goals','period':'HT','side':'over','line':'next_half','min_odds':1.8,'max_odds':2.5}}
 m={'match_id':'123','home':'Alpha','away':'Beta','kickoff':(now+dt.timedelta(hours=2)).isoformat(),'prematch':b,'context':{'summary':'Données fictives de test','unknowns':['Compositions non recherchées'], 'sources':[]},'scenarios':[s]}
 return {'schema':'seuil90.scenarios.v1','generated_at':now.isoformat(),'matches':[m]}

class ScenarioTests(unittest.TestCase):
 def setUp(self):
  self.now=dt.datetime.now(UTC);self.tmp=tempfile.TemporaryDirectory();self.path=str(pathlib.Path(self.tmp.name)/'db.sqlite');self.c=sqlite3.connect(self.path);self.c.row_factory=sqlite3.Row
  src=(ROOT/'public/api/live90/core.php').read_text();self.c.executescript('\n'.join(re.findall(r'\$db->exec\("(.*?)"\);',src,re.S)))
  self.bundle=fixture(self.now)
 def tearDown(self):self.c.close();self.tmp.cleanup()
 def test_valid_import_and_duplicate(self):
  self.assertEqual(S.import_bundle(self.c,self.bundle,self.now)['imported'],1)
  self.assertTrue(S.import_bundle(self.c,self.bundle,self.now)['duplicate'])
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM prematch').fetchone()[0],1)
 def test_atomic_reject(self):
  bad=copy.deepcopy(self.bundle['matches'][0]);bad['match_id']='456';bad['prematch']['n_h']=0;self.bundle['matches'].append(bad)
  with self.assertRaises(ValueError):S.import_bundle(self.c,self.bundle,self.now)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM playbooks').fetchone()[0],0)
 def test_after_kickoff_rejected(self):
  self.bundle['matches'][0]['kickoff']=self.now.isoformat()
  with self.assertRaises(ValueError):S.validate(self.bundle,self.now)
 def test_future_dossier_rejected(self):
  self.bundle['generated_at']=(self.now+dt.timedelta(minutes=1)).isoformat()
  with self.assertRaises(ValueError):S.validate(self.bundle,self.now)
 def test_unknown_fields_and_code_rejected(self):
  self.bundle['matches'][0]['scenarios'][0]['conditions'][0]['code']='print(1)'
  with self.assertRaises(ValueError):S.validate(self.bundle,self.now)
 def test_next_goal_not_substituted(self):
  self.bundle['matches'][0]['scenarios'][0]['market']['type']='next_goal'
  with self.assertRaises(ValueError):S.validate(self.bundle,self.now)
 def test_missing_prematch_comparison_rejected(self):
  self.bundle['matches'][0]['scenarios'][0]['conditions'].pop()
  with self.assertRaises(ValueError):S.validate(self.bundle,self.now)
 def test_boolean_threshold_rejected(self):
  self.bundle['matches'][0]['scenarios'][0]['conditions'][0]['min']=True
  with self.assertRaises(ValueError):S.validate(self.bundle,self.now)
 def test_nan_rejected(self):
  self.bundle['matches'][0]['prematch']['shots_h']=float('nan')
  with self.assertRaises(ValueError):S.validate(self.bundle,self.now)
 def prepare_live(self):
  then=self.now-dt.timedelta(hours=3);self.bundle=fixture(then);self.bundle['matches'][0]['kickoff']=(self.now-dt.timedelta(minutes=20)).isoformat();S.import_bundle(self.c,self.bundle,then)
  self.b=json.loads(self.c.execute('SELECT data FROM prematch').fetchone()[0]);self.s=self.bundle['matches'][0]['scenarios'][0]
  self.r={'packball_id':'123','home':'Alpha','away':'Beta','kickoff_ts':self.b['kickoff'],'state':'LIVE','minute':20,'minute_extra':0,'score':{'h':0,'a':1},'stats':{'shots':{'h':6,'a':2},'sot':{'h':3,'a':1},'red_cards':{'h':0,'a':0},'second_yellow':{'h':0,'a':0}},'ind5':{'shots5':{'h':4,'a':1},'sot5':{'h':2,'a':0}},'quotes':[{'market':'team_goals','period':'HT','side':'over','team':'h','line':.5,'odds':1.9,'verified':True,'bookmaker':'bet365','observed_at':self.now.isoformat()}]}
  self.add(dict(self.r,minute=9,score={'h':0,'a':0}),self.now-dt.timedelta(minutes=11),'goal-before')
  self.add(dict(self.r,minute=10),self.now-dt.timedelta(minutes=10,seconds=15),'goal-after')
 def add(self,r,at,key):
  r=copy.deepcopy(r)
  for q in r['quotes']:q['observed_at']=at.isoformat()
  self.c.execute('INSERT INTO samples(cycle_id,match_id,received_at,data) VALUES(?,?,?,?)',(key,'123',at.isoformat(),json.dumps(r)));self.c.commit()
 def evaluate(self):
  h=self.c.execute('SELECT * FROM samples ORDER BY id').fetchall();return E.safe_evaluate(self.r,self.b,self.s,self.now,self.now.isoformat(),h)
 def test_early_reaction_candidate(self):self.prepare_live();self.assertEqual(self.evaluate()[0],'candidate')
 def test_missing_goal_transition(self):
  self.prepare_live();self.c.execute("DELETE FROM samples WHERE cycle_id='goal-before'");self.assertEqual(self.evaluate()[0],'watch')
 def test_goal_gap_too_large(self):
  self.prepare_live();self.c.execute("UPDATE samples SET received_at=? WHERE cycle_id='goal-before'",((self.now-dt.timedelta(minutes=15)).isoformat(),));self.assertEqual(self.evaluate()[0],'watch')
 def test_shots_window_must_follow_goal(self):
  self.prepare_live();self.r['minute']=14;self.s['window']['from']=10;self.assertEqual(self.evaluate()[0],'watch')
 def test_score_correction_cancels_trigger(self):
  self.prepare_live();self.add(dict(self.r,minute=11,score={'h':0,'a':0}),self.now-dt.timedelta(minutes=9),'var');self.assertEqual(self.evaluate()[0],'watch')
 def test_score_change_cancels_scenario(self):
  self.prepare_live();self.r['score']['h']=1;self.assertEqual(self.evaluate()[0],'watch')
 def test_red_card_cancels(self):
  self.prepare_live();self.r['stats']['red_cards']['a']=1;self.assertEqual(self.evaluate()[0],'excluded')
 def test_missing_stats(self):
  self.prepare_live();self.r['ind5']['sot5']=None;self.assertEqual(self.evaluate()[0],'missing')
 def test_incoherent_window(self):
  self.prepare_live();self.r['ind5']['sot5']['h']=5;self.assertEqual(self.evaluate()[0],'missing')
 def test_wrong_market_period_team_and_stale_price(self):
  self.prepare_live();q=copy.deepcopy(self.r['quotes'][0])
  for key,val in [('market','total_goals'),('period','FT'),('team','a'),('verified',False),('line',1.5),('odds',1.3),('observed_at',(self.now-dt.timedelta(minutes=3)).isoformat())]:
   with self.subTest(key=key):self.r['quotes']=[dict(q,**{key:val})];self.assertEqual(self.evaluate()[0],'price')
 def test_added_time_excluded(self):
  self.prepare_live();self.r['minute_extra']=2;self.assertEqual(self.evaluate()[0],'waiting')
 def test_stale_sample(self):
  self.prepare_live();self.assertEqual(E.safe_evaluate(self.r,self.b,self.s,self.now,(self.now-dt.timedelta(minutes=3)).isoformat(),[])[0],'stale')
 def test_two_samples_dedup_and_no_api(self):
  self.prepare_live();self.add(dict(self.r,minute=19),self.now-dt.timedelta(seconds=45),'previous')
  self.add(self.r,self.now,'current')
  with patch('urllib.request.urlopen',side_effect=AssertionError('No API calls allowed')):
   engine.run(self.path);engine.run(self.path)
  rows=self.c.execute('SELECT * FROM signals').fetchall();self.assertEqual(len(rows),1);ctx=json.loads(rows[0]['context']);self.assertEqual(ctx['period'],'HT');self.assertEqual(ctx['team'],'h');self.assertEqual(ctx['comparison']['trigger']['minute'],10)
 def test_no_scenarios_no_legacy_fallback(self):
  self.prepare_live();self.c.execute('DELETE FROM playbooks');self.c.commit();self.add(self.r,self.now,'current');engine.run(self.path)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM signals').fetchone()[0],0)
 def test_reimport_profile_invalidates_playbook(self):
  self.prepare_live();self.c.execute('INSERT INTO prematch(match_id,recorded_at,kickoff,data) SELECT match_id,recorded_at,kickoff,data FROM prematch');self.c.commit();self.add(self.r,self.now,'current');engine.run(self.path)
  self.assertIn('remplacé',self.c.execute('SELECT reasons FROM decisions').fetchone()[0])
 def test_telegram_once_and_no_openai(self):
  self.prepare_live();self.add(dict(self.r,minute=19),self.now-dt.timedelta(seconds=45),'previous');self.add(self.r,self.now,'current')
  with patch.dict('os.environ',{'TELEGRAM_BOT_TOKEN':'fake','TELEGRAM_CHAT_ID':'fake'}),patch('urllib.request.urlopen',return_value=io.StringIO('{"ok":true}')) as send:
   engine.run(self.path,True);engine.run(self.path,True);self.assertEqual(send.call_count,1);self.assertTrue(send.call_args.args[0].full_url.startswith('https://api.telegram.org/'))
  self.assertEqual(self.c.execute('SELECT delivery FROM signals').fetchone()[0],'sent')
 def test_pressure_total_ft(self):
  self.prepare_live();self.s.update(team='total',trigger={'kind':'pressure','before_minute':None},window={'from':55,'to':75,'period':'FT'},score={'min_total':0,'max_total':2,'relation':'any'},conditions=[{'metric':'sot5','min':1},{'metric':'activity_ratio','min':.1}]);self.s['market'].update(type='total_goals',period='FT');self.r['minute']=60;self.r['quotes'][0].update(market='total_goals',period='FT',team=None,line=1.5);self.assertEqual(self.evaluate()[0],'candidate')
 def test_second_half_window_not_complete(self):
  self.prepare_live();self.s.update(trigger={'kind':'pressure','before_minute':None},window={'from':46,'to':75,'period':'FT'});self.s['market']['period']='FT';self.r['minute']=47;self.assertEqual(self.evaluate()[0],'watch')
 # ── Telegram : refus certain → failed, résultat ambigu → uncertain ──────
 # Chaque cas lance deux cycles moteur : le second ne doit JAMAIS renvoyer.
 def _deliver(self,**mock):
  self.prepare_live();self.add(dict(self.r,minute=19),self.now-dt.timedelta(seconds=45),'previous');self.add(self.r,self.now,'current')
  with patch.dict('os.environ',{'TELEGRAM_BOT_TOKEN':'fake','TELEGRAM_CHAT_ID':'fake'}),patch('urllib.request.urlopen',**mock) as send:
   engine.run(self.path,True);engine.run(self.path,True)
  return self.c.execute('SELECT delivery FROM signals').fetchone()[0],send.call_count
 def _http(self,code):return urllib.error.HTTPError('https://api.telegram.org/',code,'refus',{},io.BytesIO(b'{"ok":false}'))
 def test_telegram_http_400_is_failed(self):self.assertEqual(self._deliver(side_effect=self._http(400)),('failed',1))
 def test_telegram_http_401_is_failed(self):self.assertEqual(self._deliver(side_effect=self._http(401)),('failed',1))
 def test_telegram_ok_false_is_failed(self):self.assertEqual(self._deliver(return_value=io.StringIO('{"ok":false,"description":"Bad Request: chat not found"}')),('failed',1))
 def test_telegram_timeout_is_uncertain_not_resent(self):self.assertEqual(self._deliver(side_effect=socket.timeout('timed out')),('uncertain',1))
 def test_telegram_network_error_is_uncertain_not_resent(self):self.assertEqual(self._deliver(side_effect=urllib.error.URLError('connexion rompue')),('uncertain',1))
 def test_telegram_unreadable_response_is_uncertain(self):self.assertEqual(self._deliver(return_value=io.StringIO('<html>proxy</html>')),('uncertain',1))
 def test_telegram_missing_config_is_failed_without_call(self):
  self.prepare_live();self.add(dict(self.r,minute=19),self.now-dt.timedelta(seconds=45),'previous');self.add(self.r,self.now,'current')
  with patch.dict('os.environ',{'TELEGRAM_BOT_TOKEN':'','TELEGRAM_CHAT_ID':''}),patch('urllib.request.urlopen') as send:
   engine.run(self.path,True)
  self.assertEqual((self.c.execute('SELECT delivery FROM signals').fetchone()[0],send.call_count),('failed',0))
if __name__=='__main__':unittest.main()
