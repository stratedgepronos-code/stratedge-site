import copy,datetime as dt,io,json,pathlib,sqlite3,sys,tempfile,unittest,urllib.error
from unittest.mock import patch
ROOT=pathlib.Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'server'))
import live_v4 as V
class LiveV4Tests(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.db=str(pathlib.Path(self.tmp.name)/'live.sqlite');self.c=V.connect(self.db);self.now=dt.datetime(2026,9,28,18,30,tzinfo=dt.timezone.utc)
  self.c.executescript('CREATE TABLE samples(id INTEGER PRIMARY KEY,cycle_id TEXT,match_id TEXT,received_at TEXT,data TEXT); CREATE TABLE cycles(id TEXT,collected_at TEXT,received_at TEXT,payload TEXT); CREATE TABLE signals(id INTEGER PRIMARY KEY,context TEXT,created_at TEXT);')
  self.ko=self.now-dt.timedelta(minutes=30);self.before=self.ko-dt.timedelta(hours=2)
  self.m={'match_id':None,'home':'Équipe A','away':'Équipe B','kickoff':V.iso(self.ko),'league':'Test','prematch':{'n_h':10,'n_a':10,'gf_h':1.5,'gf_a':1.4,'ga_h':1,'ga_a':1.2,'shots_h':12,'shots_a':10,'sot_h':4,'sot_a':3}}
  self.ctx={k:self.m[k] for k in ('match_id','home','away','kickoff','league')};self.ctx.update(watch=True,summary='Contexte complet sans élimination',teams={'h':{'note':'Rien de confirmé','flags':[]},'a':{'note':'Rien de confirmé','flags':[]}},unknowns=['Compositions'],sources=[])
  self.r={'packball_id':'123','home':self.m['home'],'away':self.m['away'],'kickoff_ts':self.m['kickoff'],'collected_at':V.iso(self.now),'received_at':V.iso(self.now),'state':'LIVE','minute':30,'minute_extra':0,'score':{'h':0,'a':0},'stats':{'red_cards':{'h':0,'a':0},'second_yellow':{'h':None,'a':None},'yellow_cards':{'h':0,'a':1},'shots':{'h':10,'a':7},'sot':{'h':5,'a':3},'possession':{'h':40,'a':60},'fouls':{'h':9,'a':4}},'ind10':{'shots10':{'h':6,'a':3},'sot10':{'h':3,'a':1},'fouls10':{'h':4,'a':1}},'ind5':{'shots5':{'h':3,'a':1},'sot5':{'h':1,'a':0}},'quotes':[{'market':'team_goals','team':'h','period':'HT','side':'over','line':.5,'odds':1.9,'bookmaker':'bet365','verified':True,'observed_at':V.iso(self.now)}]}
  self.settings={'min_odds':1.65,'telegram_enabled':True};self.profile=dict(self.m,usable=True)
 def tearDown(self):self.c.close();self.tmp.cleanup()
 def bundle(self,kind,matches=None):return {'schema':'stratedge.context.v4' if kind=='analyst' else 'stratedge.packball.v4','timezone':'Europe/Paris',('generated_at' if kind=='analyst' else 'exported_at'):V.iso(self.before),'matches':matches or [self.ctx if kind=='analyst' else self.m]}
 def evaluate(self,market='goal_ht',team='h',hist=None):return V.safe_evaluate(self.r,self.profile,self.ctx,market,team,self.now,hist or [],self.settings)
 def add(self,r,when):
  x=copy.deepcopy(r);x['collected_at']=V.iso(when);x.pop('received_at',None)
  for q in x['quotes']:q['observed_at']=V.iso(when)
  self.c.execute('INSERT INTO samples(cycle_id,match_id,received_at,data) VALUES(?,?,?,?)',('test','123',V.iso(when),V.enc(x)));self.c.commit()
 def prepared(self):
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.before);V.import_bundle(self.c,'analyst',self.bundle('analyst'),self.before)
  self.add(dict(self.r,minute=29),self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
 def test_two_import_orders_and_all_matches_kept(self):
  # A severe context and an incomplete profile must remain visible.
  m2=copy.deepcopy(self.m);m2['home']='Autre équipe';m2['prematch']['shots_h']=None
  for kind,rows in [('analyst',[self.ctx]),('packball',[self.m,m2])]:self.assertEqual(V.import_bundle(self.c,kind,self.bundle(kind,rows),self.before)['imported'],len(rows))
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_fixtures').fetchone()[0],2)
  V.bind(self.c,self.r);self.assertEqual(self.c.execute('SELECT match_id FROM v4_fixtures WHERE home=?',(self.m['home'],)).fetchone()[0],'123')
 def test_false_watch_rejected(self):
  self.ctx['watch']=False
  with self.assertRaises(ValueError):V.import_bundle(self.c,'analyst',self.bundle('analyst'),self.before)
 def test_duplicate_import_idempotent(self):
  b=self.bundle('packball');V.import_bundle(self.c,'packball',b,self.before);self.assertTrue(V.import_bundle(self.c,'packball',b,self.before)['duplicate'])
 def test_late_import_cannot_replace_prematch(self):
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.before);self.m['prematch']['shots_h']=99
  x=V.import_bundle(self.c,'packball',self.bundle('packball'),self.now);self.assertEqual(x['preserved'],1)
  f=V.bind(self.c,self.r);p,_=V.inputs(self.c,f,self.now);self.assertEqual(p['prematch']['shots_h'],12)
 def test_late_first_import_visible_but_unusable(self):
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.now);f=V.bind(self.c,self.r);p,_=V.inputs(self.c,f,self.now);self.assertFalse(p['usable'])
 def test_ambiguous_identity_never_linked(self):
  x=copy.deepcopy(self.m);x['kickoff']=V.iso(self.ko+dt.timedelta(seconds=30));V.import_bundle(self.c,'packball',self.bundle('packball',[self.m,x]),self.before);self.assertIsNone(V.bind(self.c,self.r))
 def test_missing_source_rejected_atomically(self):
  self.ctx['teams']['h']['flags']=[{'kind':'fatigue','severity':'high','certainty':'confirmed','detail':'Très fatiguée','source_ids':[]}]
  with self.assertRaises(ValueError):V.import_bundle(self.c,'analyst',self.bundle('analyst'),self.before)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_fixtures').fetchone()[0],0)
 def test_goals_and_optional_second_yellow(self):self.assertEqual(self.evaluate()['status'],'candidate')
 def test_severe_context_still_allows_strong_live_signal(self):
  self.ctx['usable']=True;self.ctx['teams']['h']['flags']=[{'kind':'attack_absences','severity':'high','certainty':'confirmed','detail':'Absence importante'}];x=self.evaluate();self.assertEqual(x['status'],'candidate');self.assertEqual(x['threshold'],76)
 def test_missing_total_red_blocks_and_any_expulsion_suspends(self):
  self.r['stats']['red_cards']['h']=None;self.assertEqual(self.evaluate()['status'],'missing');self.r['stats']['second_yellow']['a']=1;self.assertEqual(self.evaluate()['status'],'suspended')
 def test_total_next_goal_wrong_period_no_substitution(self):
  original=copy.deepcopy(self.r['quotes'][0])
  for changes in ({'market':'total_goals'},{'market':'next_goal'},{'period':'FT'},{'team':'a'},{'line':1.5},{'verified':False},{'observed_at':V.iso(self.now-dt.timedelta(minutes=3))}):
   with self.subTest(changes=changes):self.r['quotes']=[dict(original,**changes)];self.assertEqual(self.evaluate()['status'],'price')
 def test_ft_team_has_already_scored_uses_next_half_line(self):
  self.r['minute']=65;self.r['score']['h']=1;self.r['quotes'][0].update(period='FT',line=1.5);self.assertEqual(self.evaluate('goal_ft')['status'],'candidate')
 def test_card_requires_exact_team_count_market(self):
  self.r['quotes']=[{'market':'team_cards','unit':'cards','team':'h','period':'FT','side':'over','line':.5,'odds':1.8,'bookmaker':'bet365','verified':True,'observed_at':V.iso(self.now)}]
  self.assertEqual(self.evaluate('card_ft')['status'],'candidate')
  self.r['quotes'][0]['unit']='booking_points';self.assertEqual(self.evaluate('card_ft')['status'],'price')
 def test_existing_yellow_prevents_first_card_signal(self):self.r['stats']['yellow_cards']['h']=1;self.assertEqual(self.evaluate('card_ft')['status'],'covered')
 def test_possession_alone_never_triggers_cards(self):self.r['stats']['fouls']['h']=2;self.r['ind10']['fouls10']['h']=1;self.assertEqual(self.evaluate('card_ft')['status'],'watch')
 def test_recent_fouls_derived_from_history(self):
  self.r['ind10'].pop('fouls10');old=copy.deepcopy(self.r);old['minute']=20;old['stats']['fouls']['h']=5;self.add(old,self.now-dt.timedelta(minutes=10));hist=self.c.execute('SELECT * FROM samples').fetchall();self.assertEqual(self.evaluate('card_ft',hist=hist)['metrics']['fouls10'],4)
 def test_goal_resets_recent_window(self):
  old=copy.deepcopy(self.r);self.add(old,self.now-dt.timedelta(minutes=4));self.r['score']['h']=1;hist=self.c.execute('SELECT * FROM samples').fetchall();self.assertEqual(self.evaluate(hist=hist)['status'],'missing')
 def test_second_half_window_and_staleness(self):
  self.r['minute']=49;self.assertEqual(self.evaluate('goal_ft')['status'],'missing');self.r['received_at']=V.iso(self.now-dt.timedelta(minutes=3));self.assertEqual(self.evaluate()['status'],'stale')
 def test_one_sample_does_not_alert(self):
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.before);self.add(self.r,self.now);V.run(self.db,False,self.now);self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_signals').fetchone()[0],0)
 def test_two_samples_persist_and_deduplicate_without_ai(self):
  self.prepared()
  with patch('urllib.request.urlopen',side_effect=AssertionError('No API')):V.run(self.db,False,self.now);V.run(self.db,False,self.now)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_signals').fetchone()[0],1)
  s=self.c.execute('SELECT * FROM v4_signals').fetchone();self.assertEqual(s['delivery'],'disabled');self.assertEqual(V.dec(s['data'])['profile']['prematch']['shots_h'],12)
 def test_telegram_sent_once(self):
  self.prepared()
  with patch.dict('os.environ',{'TELEGRAM_BOT_TOKEN':'fake','TELEGRAM_CHAT_ID':'fake'}),patch('urllib.request.urlopen',return_value=io.StringIO('{"ok":true,"result":{"message_id":8}}')) as send:
   V.run(self.db,True,self.now);V.run(self.db,True,self.now);self.assertEqual(send.call_count,1);self.assertIn('api.telegram.org',send.call_args.args[0].full_url)
  self.assertEqual(self.c.execute('SELECT delivery FROM v4_signals').fetchone()[0],'sent')
 def test_http_refusal_and_network_uncertainty(self):
  with patch.dict('os.environ',{'TELEGRAM_BOT_TOKEN':'fake','TELEGRAM_CHAT_ID':'fake'}):
   for code in (400,401,429):
    with patch('urllib.request.urlopen',side_effect=urllib.error.HTTPError('https://api.telegram.org',code,'error',{},io.BytesIO())):self.assertEqual(V.notify('test')[0],'failed')
   with patch('urllib.request.urlopen',side_effect=TimeoutError()):self.assertEqual(V.notify('test')[0],'uncertain')
 def test_changed_price_before_delivery_expires(self):
  self.prepared();V.run(self.db,False,self.now);self.c.execute("UPDATE v4_signals SET delivery='queued'");self.c.commit();self.r['quotes'][0]['odds']=1.85;self.add(self.r,self.now)
  with patch('urllib.request.urlopen',side_effect=AssertionError('Must not send')):V.deliver(self.c,self.now)
  self.assertEqual(self.c.execute('SELECT delivery FROM v4_signals').fetchone()[0],'expired')
 def test_results_and_correction_audited(self):
  self.prepared();V.run(self.db,False,self.now);sid=self.c.execute('SELECT id FROM v4_signals').fetchone()[0]
  for out in ('won','void'):V.settle(self.c,{'id':sid,'outcome':out,'source':'Source officielle contrôlée'},self.now)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_results').fetchone()[0],2);self.assertEqual(V.history(self.c)[0]['profit_units'],0)
 def test_history_keeps_legacy_and_all_new_rows(self):
  self.c.execute('INSERT INTO signals VALUES(1,?,?)',(V.enc({'home':'Ancien'}),V.iso(self.before)));self.c.commit();b=V.board(self.c,self.now);self.assertEqual(b['legacy'][0]['context']['home'],'Ancien')
 def test_clock_or_missing_profile_never_invents(self):
  self.profile['prematch']['shots_h']=None;self.assertEqual(self.evaluate()['status'],'missing');self.r['collected_at']=V.iso(self.now+dt.timedelta(minutes=2));self.assertEqual(self.evaluate()['status'],'stale')
if __name__=='__main__':unittest.main()
