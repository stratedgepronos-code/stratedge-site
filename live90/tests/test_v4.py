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
 def test_research_preserves_normalized_packball_and_import_timing(self):
  self.m.update(state='NS',issues=['Cadrés HT non attribuables'],packball={'layout':'packball.prematch31_37.v1','teams':{'h':{'sot_for_ht':None,'goal_intervals':{'0-15':{'scored':.2,'conceded':.4}}}},'global':{'sot_ht_unallocated':4.3},'odds':[{'market':'total_goals','period':'2H','team':None,'side':'over','line':.5,'odds':1.8}]})
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.before)
  exported=V.export_analysis(self.c,self.now)['matches'][0]
  self.assertEqual(exported['packball'],self.m['packball']);self.assertEqual(exported['data_issues'],self.m['issues'])
  self.assertEqual(exported['state'],'NS');self.assertTrue(exported['prematch_usable']);self.assertEqual(exported['prematch_imported_at'],V.iso(self.before))
 def test_research_reports_late_import(self):
  V.import_bundle(self.c,'packball',self.bundle('packball'),self.now)
  exported=V.export_analysis(self.c,self.now)['matches'][0]
  self.assertFalse(exported['prematch_usable']);self.assertEqual(exported['prematch_imported_at'],V.iso(self.now))
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
 def test_existing_yellows_continue_at_next_line(self):
  for count in (0,1,2,4):
   self.r['stats']['yellow_cards']['h']=count
   self.r['quotes']=[{'market':'team_cards','unit':'cards','team':'h','period':'FT','side':'over','line':count+.5,'odds':1.8,'bookmaker':'bet365','verified':True,'observed_at':V.iso(self.now)}]
   d=self.evaluate('card_ft');self.assertEqual(d['status'],'candidate');self.assertEqual(d['line'],count+.5);self.assertIn(str(count+.5).replace('.',','),V.label(self.r,d))
  self.r['quotes'][0]['line']=.5;self.assertEqual(self.evaluate('card_ft')['status'],'price')
 def test_card_change_rebuilds_recent_foul_window(self):
  old=copy.deepcopy(self.r);self.add(old,self.now-dt.timedelta(minutes=3));self.r['stats']['yellow_cards']['h']=1
  hist=self.c.execute('SELECT * FROM samples').fetchall();d=self.evaluate('card_ft',hist=hist)
  self.assertEqual(d['status'],'watch');self.assertEqual(d['line'],1.5)
 def test_card_new_line_needs_two_new_samples_and_deduplicates(self):
  self.r['quotes']=[{'market':'team_cards','unit':'cards','team':'h','period':'FT','side':'over','line':.5,'odds':1.8,'bookmaker':'bet365','verified':True,'observed_at':V.iso(self.now)}]
  self.prepared();V.run(self.db,False,self.now)
  self.assertEqual(self.c.execute("SELECT COUNT(*) FROM v4_signals WHERE market='card_ft'").fetchone()[0],1)
  later=self.now+dt.timedelta(minutes=12);self.r['minute']=42;self.r['stats']['yellow_cards']['h']=1;self.r['quotes'][0]['line']=1.5
  self.add(self.r,later);V.run(self.db,False,later)
  self.assertEqual(self.c.execute("SELECT COUNT(*) FROM v4_signals WHERE market='card_ft'").fetchone()[0],1)
  self.add(self.r,later+dt.timedelta(seconds=30));V.run(self.db,False,later+dt.timedelta(seconds=30));V.run(self.db,False,later+dt.timedelta(seconds=30))
  self.assertEqual([r[0] for r in self.c.execute("SELECT line FROM v4_signals WHERE market='card_ft' ORDER BY line")],[.5,1.5])
 def referee_context(self):
  self.ctx['sources']=[{'id':'ref','url':'https://example.com/referee','title':'Source de test','checked_at':V.iso(self.before)}]
  self.ctx['referee']={'name':'Arbitre Test','appointment':'confirmed','appointment_source_ids':['ref'],'note':'Données fictives de test','stats':{'sample_label':'Compétition test, saison test','matches':25,'yellow_per_match':4.2,'red_per_match':.1,'fouls_per_match':None,'source_ids':['ref']}}
 def test_referee_context_roundtrip_and_card_notes(self):
  self.referee_context();V.import_bundle(self.c,'analyst',self.bundle('analyst'),self.before);f=V.bind(self.c,self.r);_,context=V.inputs(self.c,f,self.now)
  self.assertEqual(context['referee']['stats']['matches'],25);self.ctx=context
  d=self.evaluate('card_ft');self.assertEqual(d['metrics']['referee_yellow_per_match'],4.2);self.assertIn('Arbitre Test',d['context_notes'][0]);self.assertIsNone(d['metrics']['referee_fouls_per_match'])
 def test_referee_sources_and_numeric_values_validated(self):
  self.referee_context()
  for field,value in [('matches',-1),('yellow_per_match','4.2'),('source_ids',['inconnue'])]:
   bad=copy.deepcopy(self.ctx);bad['referee']['stats'][field]=value
   with self.assertRaises(ValueError):V.validate_context(bad,self.before)
  self.ctx['referee']['appointment_source_ids']=[]
  with self.assertRaises(ValueError):V.validate_context(self.ctx,self.before)

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
 def result_signal(self):
  self.prepared();V.run(self.db,False,self.now)
  return self.c.execute('SELECT id FROM v4_signals').fetchone()[0]
 def test_result_notification_sent_once_after_confirmation(self):
  sid=self.result_signal();x={'id':sid,'outcome':'won','source':'Résultat officiel contrôlé'}
  self.assertEqual(V.settle(self.c,x,self.now)['result_delivery'],'queued');self.assertTrue(V.settle(self.c,x,self.now)['unchanged'])
  with patch.object(V,'notify',return_value=('sent',None,77)) as send:
   V.run(self.db,True,self.now);V.run(self.db,True,self.now);self.assertEqual(send.call_count,1)
   msg=send.call_args.args[0];self.assertIn('GAGNANT',msg);self.assertIn('LIVE #'+str(sid),msg);self.assertIn('Bilan simulé pour 1 unité : +0.90 u',msg)
  event=V.history(self.c)[0]['result_notification'];self.assertEqual(event['delivery'],'sent');self.assertEqual(event['telegram_id'],77)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_results').fetchone()[0],1)
 def test_result_corrections_and_withdrawal_announced(self):
  sid=self.result_signal()
  with patch.object(V,'notify',return_value=('sent',None,78)) as send:
   for outcome in ('won','lost','void','pending'):
    V.settle(self.c,{'id':sid,'outcome':outcome,'source':'Correction officielle contrôlée'},self.now);V.deliver_results(self.c,self.now)
   self.assertEqual(send.call_count,4);texts=[x.args[0] for x in send.call_args_list]
   self.assertIn('CORRECTION',texts[1]);self.assertIn('PERDANT',texts[1]);self.assertIn('-1.00 u',texts[1]);self.assertIn('ANNULÉ',texts[2]);self.assertIn('validation retirée',texts[3])
 def test_queued_result_superseded_before_sending(self):
  sid=self.result_signal()
  for outcome in ('won','lost'):V.settle(self.c,{'id':sid,'outcome':outcome,'source':'Contrôle'},self.now)
  with patch.object(V,'notify',return_value=('sent',None,79)) as send:
   V.deliver_results(self.c,self.now);self.assertEqual(send.call_count,1);self.assertIn('PERDANT',send.call_args.args[0])
  self.assertEqual([r[0] for r in self.c.execute('SELECT delivery FROM v4_result_notifications ORDER BY result_id')],['superseded','sent'])
 def test_result_notifications_respect_disabled_setting(self):
  sid=self.result_signal();self.c.execute("INSERT OR REPLACE INTO v4_settings VALUES('telegram_enabled','false')");self.c.commit()
  result=V.settle(self.c,{'id':sid,'outcome':'void','source':'Annulation officielle'},self.now);self.assertEqual(result['result_delivery'],'disabled')
  with patch.object(V,'notify',side_effect=AssertionError('Disabled')):V.deliver_results(self.c,self.now)
 def test_result_network_uncertainty_is_not_retried(self):
  sid=self.result_signal();V.settle(self.c,{'id':sid,'outcome':'lost','source':'Score final'},self.now)
  with patch.object(V,'notify',return_value=('uncertain','Timeout',None)) as send:
   V.deliver_results(self.c,self.now);V.deliver_results(self.c,self.now);self.assertEqual(send.call_count,1)
  self.assertEqual(V.history(self.c)[0]['result_notification']['delivery'],'uncertain')
 def test_result_crash_recovery_and_no_historical_backfill(self):
  sid=self.result_signal();self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_result_notifications').fetchone()[0],0)
  V.settle(self.c,{'id':sid,'outcome':'won','source':'Score vérifié'},self.now)
  self.c.execute("UPDATE v4_result_notifications SET delivery='sending',attempted_at=?",(V.iso(self.now-dt.timedelta(minutes=4)),));self.c.commit()
  with patch.object(V,'notify',side_effect=AssertionError('No ambiguous retry')):V.deliver_results(self.c,self.now)
  self.assertEqual(V.history(self.c)[0]['result_notification']['delivery'],'uncertain')
 def auto_signal(self,market='goal_ht',line=.5):
  q=self.r['quotes'][0];q.update(market='team_cards' if market=='card_ft' else 'team_goals',period='HT' if market=='goal_ht' else 'FT',line=line)
  if market=='card_ft':q['unit']='cards';self.r['stats']['yellow_cards']['h']=int(line)
  self.prepared();V.run(self.db,False,self.now)
  return self.c.execute('SELECT id FROM v4_signals WHERE market=?',(market,)).fetchone()[0]
 def outcome(self,sid):return self.c.execute('SELECT outcome FROM v4_signals WHERE id=?',(sid,)).fetchone()[0]
 def test_auto_goal_win_immediately_and_telegram_once(self):
  sid=self.auto_signal();later=self.now+dt.timedelta(seconds=30);self.r['score']['h']=1;self.r['minute']=31;self.add(self.r,later)
  with patch.object(V,'notify',return_value=('sent',None,88)) as send:
   V.run(self.db,True,later);V.run(self.db,True,later);self.assertEqual(send.call_count,1);self.assertIn('RÉSULTAT PACKBALL',send.call_args.args[0]);self.assertIn('GAGNANT',send.call_args.args[0])
  self.assertEqual(self.outcome(sid),'won');self.assertEqual(V.history(self.c)[0]['data']['resolution']['mode'],'automatic')
 def test_auto_var_correction_reopens_and_later_ht_loss(self):
  sid=self.auto_signal();later=self.now+dt.timedelta(seconds=30);self.r['score']['h']=1;self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'won')
  self.r['score']['h']=0;later+=dt.timedelta(seconds=30);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'pending')
  self.r.update(state='HT',minute=45);later+=dt.timedelta(minutes=15);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'lost')
  self.r.update(state='LIVE',minute=65);self.r['score']['h']=2;later+=dt.timedelta(minutes=20);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'lost')
 def test_auto_ft_loss_requires_end_not_minute_90(self):
  sid=self.auto_signal('goal_ft');later=self.now+dt.timedelta(minutes=60);self.r.update(minute=90,minute_extra=3);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'pending')
  self.r.update(state='FT',minute=None);later+=dt.timedelta(seconds=30);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'lost')
 def test_auto_ht_no_second_half_score_substitution(self):
  sid=self.auto_signal();later=self.now+dt.timedelta(minutes=35);self.r.update(minute=65);self.r['score']['h']=2;self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'pending')
 def test_auto_cards_second_yellow_total_target_and_correction(self):
  sid=self.auto_signal('card_ft',1.5);later=self.now+dt.timedelta(seconds=30);self.r['stats']['yellow_cards']['h']=2;self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'won')
  self.r['stats']['yellow_cards']['h']=1;later+=dt.timedelta(seconds=30);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'pending')
  self.r.update(state='FT',minute=None);later+=dt.timedelta(minutes=60);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'lost')
 def test_auto_cards_red_ambiguous_not_bookmaker_void(self):
  sid=self.auto_signal('card_ft');later=self.now+dt.timedelta(seconds=30);self.r['stats']['yellow_cards']['h']=1;self.r['stats']['red_cards']['a']=1;self.r.update(state='FT',minute=None);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'pending')
 def test_auto_missing_stale_or_wrong_fixture_never_settles(self):
  sid=self.auto_signal();later=self.now+dt.timedelta(seconds=30);self.r['score']=None;self.r.update(state='HT',minute=45);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'pending')
  self.r['score']={'h':1,'a':0};self.add(self.r,later);V.auto_results(self.c,later+dt.timedelta(minutes=3));self.assertEqual(self.outcome(sid),'pending')
  self.r['home']='Mauvaise équipe';self.add(self.r,later+dt.timedelta(minutes=3));V.auto_results(self.c,later+dt.timedelta(minutes=3));self.assertEqual(self.outcome(sid),'pending')
 def test_auto_manual_override_and_same_verdict_manual_lock(self):
  sid=self.auto_signal();later=self.now+dt.timedelta(seconds=30);self.r['score']['h']=1;self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'won')
  V.settle(self.c,{'id':sid,'outcome':'won','source':'Validation bookmaker'},later)
  self.r['score']['h']=0;later+=dt.timedelta(seconds=30);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'won')
 def test_auto_interruption_does_not_assume_refund(self):
  sid=self.auto_signal('goal_ft');later=self.now+dt.timedelta(seconds=30);self.r.update(state='OTHER',minute=None);self.add(self.r,later);V.auto_results(self.c,later);self.assertEqual(self.outcome(sid),'pending')
 def test_active_packball_list_replaces_board_and_export_only(self):
  sid=self.result_signal();old=self.bundle('packball')
  other=copy.deepcopy(self.m);other['home']='Programme suivant';other['kickoff']=V.iso(self.now+dt.timedelta(hours=4))
  V.import_bundle(self.c,'packball',self.bundle('packball',[other]),self.now)
  self.assertEqual([m['home'] for m in V.board(self.c,self.now)['matches']],['Programme suivant'])
  self.assertEqual([m['home'] for m in V.export_analysis(self.c,self.now)['matches']],['Programme suivant'])
  self.assertEqual(V.history(self.c)[0]['id'],sid)
  V.import_bundle(self.c,'analyst',self.bundle('analyst'),self.now)
  self.assertEqual([m['home'] for m in V.board(self.c,self.now)['matches']],['Programme suivant'])
  self.assertTrue(V.import_bundle(self.c,'packball',old,self.now)['duplicate'])
  self.assertEqual([m['home'] for m in V.board(self.c,self.now)['matches']],[self.m['home']])
 def test_engine_does_not_alert_outside_active_list(self):
  self.prepared();other=copy.deepcopy(self.m);other['home']='Autre programme';other['kickoff']=V.iso(self.now+dt.timedelta(hours=5));V.import_bundle(self.c,'packball',self.bundle('packball',[other]),self.now)
  V.run(self.db,False,self.now);self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_signals').fetchone()[0],0)
 def test_board_without_csv_uses_latest_cycle_only(self):
  self.add(self.r,self.now)
  other=copy.deepcopy(self.r);other['packball_id']='456';other['home']='Autre';self.c.execute('INSERT INTO samples(cycle_id,match_id,received_at,data) VALUES(?,?,?,?)',('new','456',V.iso(self.now),V.enc(other)))
  self.c.execute('INSERT INTO cycles VALUES(?,?,?,?)',('new',V.iso(self.now),V.iso(self.now),V.enc({'rows':[other],'page_rows':1})));self.c.commit()
  self.assertEqual([r['packball_id'] for r in V.board(self.c,self.now)['matches']],['456'])
 def test_replay_audits_matches_without_signals_and_does_not_send(self):
  self.prepared();V.bind(self.c,self.r);self.c.commit();r=copy.deepcopy(self.r);r['stats']=[];r['quotes']=[];self.add(r,self.now+dt.timedelta(seconds=30))
  with patch.object(V,'notify',side_effect=AssertionError('Replay must never send')):
   audit=V.audit_matches(self.c,self.now+dt.timedelta(seconds=30))[0]
  self.assertEqual(audit['live_samples'],3);self.assertEqual(audit['empty_live_samples'],1)
  self.assertEqual(audit['markets']['goal_ht:h']['candidate_samples'],2)
  self.assertEqual(audit['markets']['goal_ht:h']['states']['missing'],1)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_signals').fetchone()[0],0)
 def test_replay_accounts_for_server_seconds_and_browser_milliseconds(self):
  self.prepared();V.bind(self.c,self.r)
  row=self.c.execute('SELECT * FROM samples ORDER BY id DESC LIMIT 1').fetchone();r=V.dec(row['data'],{})
  r['collected_at']=V.iso(self.now+dt.timedelta(milliseconds=550));r['quotes'][0]['observed_at']=r['collected_at']
  self.c.execute('UPDATE samples SET data=? WHERE id=?',(V.enc(r),row['id']));self.c.commit()
  audit=V.audit_matches(self.c,self.now+dt.timedelta(seconds=30))[0]
  self.assertEqual(audit['markets']['goal_ht:h']['candidate_samples'],2)
 def test_replay_does_not_use_profile_before_import(self):
  self.prepared();V.bind(self.c,self.r);self.c.execute('UPDATE v4_profiles SET imported_at=?',(V.iso(self.now+dt.timedelta(seconds=5)),));self.c.commit()
  audit=V.audit_matches(self.c,self.now+dt.timedelta(seconds=30))[0]
  self.assertEqual(audit['markets']['goal_ht:h']['candidate_samples'],0)
  self.assertIn('Profil Packball avant-match nécessaire pour comparer le rythme de tirs',audit['markets']['goal_ht:h']['reasons'])
 def test_health_missing_fields_even_during_halftime_and_no_columns(self):
  self.prepared();self.r.update(state='HT',minute=45,stats={},score=None);self.add(self.r,self.now)
  self.c.execute('INSERT INTO cycles VALUES(?,?,?,?)',('test',V.iso(self.now),V.iso(self.now),V.enc({'rows':[self.r],'stat_headers':[]})));self.c.commit()
  report=V.feed_health(self.c,self.now);codes={x['code'] for x in report['issues']}
  self.assertTrue({'stats','score','layout'}.issubset(codes));self.assertNotIn('quotes',codes)
 def test_health_accepts_empty_maps_reencoded_as_arrays_by_php(self):
  self.prepared();self.r.update(stats=[],ind5=[],ind10=[],quotes=[]);self.add(self.r,self.now)
  report=V.feed_health(self.c,self.now)
  self.assertTrue({'stats','quotes'}.issubset({x['code'] for x in report['issues']}))
  self.assertIsNone(V.stat(self.r,'red_cards','h'))
  self.c.commit();V.run(self.db,False,self.now)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_signals').fetchone()[0],0)
 def test_health_zero_is_data_and_bad_score_does_not_crash(self):
  self.prepared()
  for pair in self.r['stats'].values():pair.update(h=0,a=0)
  self.r['score']='unreadable';self.add(self.r,self.now)
  codes={x['code'] for x in V.feed_health(self.c,self.now)['issues']}
  self.assertNotIn('stats',codes);self.assertIn('score',codes);self.assertIn('quotes',codes)
  self.assertIn('stale',{x['code'] for x in V.feed_health(self.c,self.now+dt.timedelta(minutes=3))['issues']})
 def test_health_incidents_deduplicate_resolve_and_reopen(self):
  report={'issues':[{'key':'stats:123','code':'stats','detail':'Fautes manquantes'}]}
  for _ in range(3):V.record_health(self.c,report,self.now)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_health_events').fetchone()[0],1)
  V.record_health(self.c,{'issues':[]},self.now+dt.timedelta(seconds=30))
  self.assertIsNotNone(self.c.execute('SELECT resolved_at FROM v4_health_events').fetchone()[0])
  V.record_health(self.c,report,self.now+dt.timedelta(seconds=60))
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_health_events WHERE resolved_at IS NULL').fetchone()[0],1)
  self.assertEqual(self.c.execute('SELECT COUNT(*) FROM v4_health_events').fetchone()[0],2)
 def test_lost_review_preserves_facts_gaps_and_correction_versions(self):
  sid=self.auto_signal();later=self.now+dt.timedelta(minutes=15);self.r.update(state='HT',minute=45);self.r['stats']['shots']['h']=14;self.add(self.r,later);V.auto_results(self.c,later)
  signal=V.history(self.c)[0];review=signal['data']['review'];self.assertEqual(review['outcome'],'lost')
  self.assertTrue(any('10 → 14' in x for x in review['facts']));self.assertTrue(any('Trou de collecte' in x for x in review['limits']))
  self.assertIn('ne prouvent pas la cause',review['conclusion']);self.assertIn('Bilan automatique',self.c.execute('SELECT message FROM v4_result_notifications ORDER BY result_id DESC').fetchone()[0])
  self.r['score']['h']=1;later+=dt.timedelta(seconds=30);self.add(self.r,later);V.auto_results(self.c,later)
  self.assertEqual(V.history(self.c)[0]['data']['review']['outcome'],'won')
  events=[V.dec(x[0],{}) for x in self.c.execute('SELECT data FROM v4_results ORDER BY id')]
  self.assertEqual([x['review']['outcome'] for x in events],['lost','won'])
 def test_review_export_pending_retains_signal_context_without_network(self):
  self.result_signal()
  with patch('urllib.request.urlopen',side_effect=AssertionError('No API')):result=V.dispatch(self.c,{'action':'export_review'},self.now)
  self.assertEqual(result['schema'],'stratedge.audit.v4');self.assertEqual(result['coverage']['total_signals'],1)
  review=result['signals'][0]['data']['review'];self.assertEqual(review['signal_metrics']['shots10'],6)
  self.assertNotIn('Profil avant-match absent ou non exploitable au déclenchement.',review['limits'])
  self.assertEqual(result['all_history_counts'][0]['outcome'],'pending')
 def test_history_keeps_legacy_and_all_new_rows(self):
  self.c.execute('INSERT INTO signals VALUES(1,?,?)',(V.enc({'home':'Ancien'}),V.iso(self.before)));self.c.commit();b=V.board(self.c,self.now);self.assertEqual(b['legacy'][0]['context']['home'],'Ancien')
 def test_clock_or_missing_profile_never_invents(self):
  self.profile['prematch']['shots_h']=None;self.assertEqual(self.evaluate()['status'],'missing');self.r['collected_at']=V.iso(self.now+dt.timedelta(minutes=2));self.assertEqual(self.evaluate()['status'],'stale')
if __name__=='__main__':unittest.main()
