"""Offline tests: the free provider quota is never used by the test suite."""
import datetime as dt,io,json,pathlib,sys,tempfile,unittest,urllib.error
from unittest.mock import patch,MagicMock
sys.path.insert(0,str(pathlib.Path(__file__).resolve().parents[1]/'server'))
import live_v4 as V
class PulseTests(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.c=V.connect(str(pathlib.Path(self.tmp.name)/'db'))
  self.now=dt.datetime(2026,9,29,20,tzinfo=V.UTC);self.secret='test-only-private-key'
  self.c.executescript('CREATE TABLE samples(id INTEGER PRIMARY KEY,cycle_id TEXT,match_id TEXT,received_at TEXT,data TEXT); CREATE TABLE cycles(id TEXT,collected_at TEXT,received_at TEXT,payload TEXT);')
 def tearDown(self):self.c.close();self.tmp.cleanup()
 def save(self):return V.dispatch(self.c,{'action':'pulsescore_key','key':self.secret},self.now)
 def response(self,payload):
  opener=MagicMock();opener.open.return_value.__enter__.return_value.read.return_value=V.enc(payload).encode();return opener
 def test_save_board_exports_never_call_provider_or_expose_key(self):
  with patch.object(V.urllib.request,'build_opener') as network:
   result=self.save()
   for x in (result,V.board(self.c,self.now),V.export_analysis(self.c,self.now),V.dispatch(self.c,{'action':'export_history'},self.now)):
    self.assertNotIn(self.secret,V.enc(x))
   self.assertTrue(result['pulsescore']['configured']);self.assertEqual(result['pulsescore']['attempts_31d'],0);network.assert_not_called()
 def test_one_call_no_pagination_and_redacted_preview(self):
  self.save();opener=self.response({'total':31,'hasNextPage':True,'events':[{'home':self.secret,'away':'B','markets':[{'canonicalMarket':'TEAM_TOTAL','rawName':'Team Goals','period':'FIRST_HALF','selections':[{'rawName':'Over','odds':1.9,'line':.5}]}]}]})
  with patch.object(V.urllib.request,'build_opener',return_value=opener):r=V.pulse_test(self.c,self.now)
  self.assertTrue(r['ok']);self.assertNotIn(self.secret,V.enc(r));self.assertEqual(r['pulsescore']['attempts_31d'],1)
  self.assertTrue(r['pulsescore']['last_test']['has_next_page']);opener.open.assert_called_once()
  req=opener.open.call_args.args[0];self.assertEqual(req.full_url,V.PULSE_URL);self.assertEqual(req.get_header('X-secret'),self.secret)
  with patch.object(V.urllib.request,'build_opener') as network:
   with self.assertRaisesRegex(ValueError,'minute'):V.pulse_test(self.c,self.now+dt.timedelta(seconds=10))
   network.assert_not_called()
 def test_errors_count_once_and_do_not_leak_provider(self):
  self.save()
  for i,error in enumerate([urllib.error.HTTPError(V.PULSE_URL,401,self.secret,{},io.BytesIO(self.secret.encode())),TimeoutError(self.secret),RuntimeError(self.secret)]):
   opener=MagicMock();opener.open.side_effect=error
   with patch.object(V.urllib.request,'build_opener',return_value=opener):r=V.pulse_test(self.c,self.now+dt.timedelta(minutes=i))
   self.assertFalse(r['ok']);self.assertNotIn(self.secret,V.enc(r));self.assertEqual(r['pulsescore']['attempts_31d'],i+1);opener.open.assert_called_once()
 def test_invalid_key_and_missing_configuration(self):
  for key in ['abc','unsafe\r\nHeader:value',{},'clé-non-ascii']:
   with self.assertRaises(ValueError):V.dispatch(self.c,{'action':'pulsescore_key','key':key},self.now)
  with patch.object(V.urllib.request,'build_opener') as network:
   with self.assertRaisesRegex(ValueError,'Enregistre'):V.pulse_test(self.c,self.now)
   network.assert_not_called()
 def test_local_budget_survives_key_changes_and_removal(self):
  self.save()
  with self.c:self.c.executemany('INSERT INTO v4_pulsescore_requests(attempted_at) VALUES(?)',[(V.iso(self.now-dt.timedelta(days=1)),)]*500)
  V.dispatch(self.c,{'action':'pulsescore_remove'},self.now);self.save()
  with patch.object(V.urllib.request,'build_opener') as network:
   with self.assertRaisesRegex(ValueError,'500'):V.pulse_test(self.c,self.now)
   network.assert_not_called()
  self.assertEqual(V.pulse_status(self.c,self.now+dt.timedelta(days=31))['attempts_31d'],0)
 def test_response_limits_invalid_json_and_unknown_schema(self):
  self.save()
  for i,raw in enumerate([b'x'*(4*1024*1024+1),b'<html>private</html>',b'{"data":[]}']):
   opener=MagicMock();opener.open.return_value.__enter__.return_value.read.return_value=raw
   with patch.object(V.urllib.request,'build_opener',return_value=opener):r=V.pulse_test(self.c,self.now+dt.timedelta(minutes=i))
   self.assertFalse(r['ok']);self.assertNotIn('private',V.enc(r))
 def test_redirects_never_forward_secret(self):
  req=V.urllib.request.Request(V.PULSE_URL,headers={'X-Secret':self.secret})
  self.assertIsNone(V.PulseNoRedirect().redirect_request(req,None,302,'',{},'https://elsewhere.invalid'))
 def test_empty_live_list_is_success_not_coverage_proof(self):
  self.save()
  with patch.object(V.urllib.request,'build_opener',return_value=self.response({'total':0,'events':[]})):r=V.pulse_test(self.c,self.now)
  self.assertTrue(r['ok']);self.assertEqual(r['pulsescore']['last_test']['returned'],0)
if __name__=='__main__':unittest.main()
