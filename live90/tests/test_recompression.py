import gzip,hashlib,importlib.util,json,pathlib,tempfile,unittest
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('recompress',pathlib.Path(__file__).resolve().parents[2]/'ops/recompress-live90.py')
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)
class Recompression(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.p=pathlib.Path(self.tmp.name)/'live90.sql.gz'
  self.payload=(b'INSERT INTO snapshots VALUES (1,2,3,"football");\n'*50000)
  self.p.write_bytes(gzip.compress(self.payload,compresslevel=1));self.old=self.p.read_bytes()
  self.meta=self.p.with_name(self.p.name+'.json');self.meta.write_text(json.dumps({'archive_bytes':self.p.stat().st_size,'sql_sha256':hashlib.sha256(self.payload).hexdigest(),'sql_bytes':len(self.payload)}))
 def test_lossless_manifest_and_idempotence(self):
  freed=M.recompress(self.p,reserve=0);self.assertGreater(freed,0)
  self.assertEqual(gzip.decompress(self.p.read_bytes()),self.payload)
  self.assertEqual(json.loads(self.meta.read_text())['archive_bytes'],self.p.stat().st_size)
  self.assertEqual(M.recompress(self.p,reserve=0),0)
 def test_bad_original_manifest_keeps_original(self):
  self.meta.write_text(json.dumps({'archive_bytes':self.p.stat().st_size,'sql_sha256':'bad','sql_bytes':len(self.payload)}))
  with self.assertRaises(ValueError):M.recompress(self.p,reserve=0)
  self.assertEqual(self.p.read_bytes(),self.old);self.assertFalse(self.p.with_name(self.p.name+'.recompress.partial').exists())
 def test_disk_guard_and_timeout_preserve_original(self):
  for kwargs in ({'reserve':10**20},{'reserve':0,'max_seconds':-1}):
   with self.assertRaises((OSError,TimeoutError)):M.recompress(self.p,**kwargs)
   self.assertEqual(self.p.read_bytes(),self.old)
 def test_existing_stage_never_removed_and_symlink_refused(self):
  stage=self.p.with_name(self.p.name+'.recompress.partial');stage.write_bytes(b'existing')
  with self.assertRaises(FileExistsError):M.recompress(self.p,reserve=0)
  self.assertEqual(stage.read_bytes(),b'existing');self.assertEqual(self.p.read_bytes(),self.old)
  link=self.p.with_name('linked.gz');link.symlink_to(self.p)
  with self.assertRaises(ValueError):M.recompress(link,reserve=0)
 def test_roundtrip_failure_keeps_original(self):
  with patch.object(M,'digest_gzip',return_value=('wrong',1)):
   with self.assertRaises(ValueError):M.recompress(self.p,reserve=0)
  self.assertEqual(self.p.read_bytes(),self.old)
if __name__=='__main__':unittest.main()
