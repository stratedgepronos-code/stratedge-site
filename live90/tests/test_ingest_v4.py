"""Local HTTP ingestion: invalid views cannot replace a valid live sample."""
import http.client,json,os,pathlib,shutil,socket,sqlite3,subprocess,tempfile,time,unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
class IngestV4Tests(unittest.TestCase):
 def test_empty_live_layout_is_rejected_without_overwriting_samples(self):
  php=shutil.which('php')
  if not php:self.skipTest('PHP absent du poste ; recette aussi exécutée sur le VPS')
  if 'pdo_sqlite' not in subprocess.check_output([php,'-m'],text=True):self.skipTest('pdo_sqlite absent')
  with tempfile.TemporaryDirectory() as tmp:
   root=pathlib.Path(tmp);db=root/'test.sqlite';env=root/'test.env'
   env.write_text('SE_LIVE_TOKEN="synthetic-test-token"\nSE90_DB="'+str(db)+'"\n')
   for name in ('core.php','ingest.php'):shutil.copy(ROOT/'public/api/live90'/name,root/name)
   with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
   proc=subprocess.Popen([php,'-S',f'127.0.0.1:{port}','-t',str(root)],env=dict(os.environ,SE90_ENV=str(env)),stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
   try:
    for _ in range(50):
     try:
      with socket.create_connection(('127.0.0.1',port),timeout=.1):break
     except OSError:time.sleep(.02)
    def post(payload):
     c=http.client.HTTPConnection('127.0.0.1',port,timeout=5)
     c.request('POST','/ingest.php',json.dumps(payload),{'Content-Type':'application/json','X-SE-Token':'synthetic-test-token'})
     r=c.getresponse();status=r.status;data=json.loads(r.read());c.close();return status,data
    x={'schema':2,'cycle_id':'synthetic-cycle-0001','collected_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'stat_headers':['Buts Temps plein'],'rows':[{'packball_id':'123','state':'LIVE','stats':{'red_cards':{'h':0,'a':0}}}]}
    self.assertEqual(post(x)[0],200)
    bad=dict(x,cycle_id='synthetic-cycle-0002',stat_headers=[],rows=[{'packball_id':'123','state':'LIVE','stats':[]}])
    status,result=post(bad);self.assertEqual(status,422);self.assertEqual(result['code'],'NO_LIVE_COLUMNS')
    with sqlite3.connect(db) as c:
     self.assertEqual(c.execute('SELECT COUNT(*) FROM samples').fetchone()[0],1)
     self.assertEqual(c.execute('SELECT COUNT(*) FROM cycles').fetchone()[0],1)
     self.assertEqual(c.execute('SELECT COUNT(*) FROM collection_rejections').fetchone()[0],1)
    self.assertTrue(post(x)[1]['duplicate'])
   finally:proc.terminate();proc.wait(timeout=5)
