"""Exact approved archive deletion, recent backup verification, fail closed."""
import datetime as dt,gzip,hashlib,importlib.util,json,os,sys,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('cleanup',Path(__file__).resolve().parents[2]/'ops/live90-cleanup-approved-20261101.py')
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)

class ApprovedCleanup(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup);self.root=Path(self.temp.name);self.now=dt.datetime.now(dt.timezone.utc)
 def candidate(self,suffix='approved',data=b'old'):
  p=self.root/('stratedge-live90-v4.'+suffix)/'live90.sqlite.gz';p.parent.mkdir();p.write_bytes(data);return p
 def recent(self,name,days=1,corrupt=False):
  p=self.root/('stratedge-live90-v4.'+name)/'live90.sql.gz';p.parent.mkdir();raw=b'BEGIN;\nCOMMIT;\n'
  with gzip.open(p,'wb') as f:f.write(raw)
  record={'format':'stratedge.sqlite-sql-gzip.v1','archive':p.name,'completed_at':(self.now-dt.timedelta(days=days)).isoformat(),'archive_bytes':p.stat().st_size,'sql_bytes':len(raw),'sql_sha256':'wrong' if corrupt else hashlib.sha256(raw).hexdigest(),'tables':{'samples':1,'cycles':1}}
  p.with_name(p.name+'.json').write_text(json.dumps(record));return p
 def test_exact_inventory_preserves_all_other_files(self):
  old=self.candidate();other=self.candidate('other');active=self.root/'live90.sqlite';active.write_bytes(b'active')
  rows=M.plan(self.root,{'approved':3});removed=M.apply(rows)
  self.assertFalse(old.exists());self.assertTrue(other.exists());self.assertEqual(active.read_bytes(),b'active');self.assertEqual(len(removed),1)
 def test_wrong_size_rejects_all_before_deleting(self):
  a=self.candidate();b=self.candidate('changed',b'more')
  with self.assertRaises(ValueError):M.plan(self.root,{'approved':3,'changed':3})
  self.assertTrue(a.exists());self.assertTrue(b.exists())
 def test_link_rejected(self):
  p=self.candidate();p.unlink();target=self.root/'target';target.write_bytes(b'old');p.symlink_to(target)
  with self.assertRaises(ValueError):M.plan(self.root,{'approved':3})
  self.assertTrue(target.exists())
 def test_concurrent_change_rejects_all_before_deleting(self):
  a=self.candidate();b=self.candidate('second');rows=M.plan(self.root,{'approved':3,'second':3});b.write_bytes(b'changed')
  with self.assertRaises(ValueError):M.apply(rows)
  self.assertTrue(a.exists());self.assertTrue(b.exists())
 def test_two_recent_full_sha256_checks_and_corruption_block(self):
  a=self.recent('new1');b=self.recent('new2',days=2)
  self.assertEqual(set(M.recent_verified(self.root,self.now)),{str(a),str(b)})
  record=json.loads(a.with_name(a.name+'.json').read_text());record['sql_sha256']='bad';a.with_name(a.name+'.json').write_text(json.dumps(record))
  with self.assertRaises(ValueError):M.recent_verified(self.root,self.now)
 def test_old_or_missing_backups_cannot_authorize_cleanup(self):
  self.recent('new');self.recent('old',days=8)
  with self.assertRaises(ValueError):M.recent_verified(self.root,self.now)
 def test_main_is_opt_in_and_success_is_idempotent(self):
  old=self.candidate();self.recent('new1');self.recent('new2',days=2)
  with patch.object(M,'ROOT',self.root),patch.object(M,'APPROVED',{'approved':3}),patch.object(os,'geteuid',return_value=0):
   with patch.object(sys,'argv',['cleanup']):M.main()
   self.assertTrue(old.exists())
   with patch.object(sys,'argv',['cleanup','--approval',M.APPROVAL]):M.main();M.main()
   self.assertFalse(old.exists());receipt=json.loads((self.root/'.stratedge-cleanup-20261101.json').read_text());self.assertEqual(receipt['freed_bytes'],3);self.assertEqual(len(receipt['verified_preserved']),2)

if __name__=='__main__':unittest.main()
