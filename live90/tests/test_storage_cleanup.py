import importlib.util,os,pathlib,tempfile,time,unittest
spec=importlib.util.spec_from_file_location('cleanup_storage',pathlib.Path(__file__).resolve().parents[2]/'ops/storage-cleanup-20261107.py')
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)
class StorageCleanup(unittest.TestCase):
 def test_only_old_single_link_inactive_cache_objects(self):
  with tempfile.TemporaryDirectory() as tmp:
   root=pathlib.Path(tmp);now=time.time()
   for c in ('a','b','c','d','e'):
    p=root/(c*64);p.write_bytes(b'cache');os.utime(p,(now-10*86400,now-10*86400))
   os.utime(root/('b'*64),(now,now));os.link(root/('c'*64),root/'hardlink')
   (root/('f'*64)).symlink_to(root/('a'*64));(root/'settings.json').write_text('{}')
   rows=M.cache_plan(root,now,opened=[str(root/('d'*64))])
   self.assertEqual({p.name for p,_ in rows},{'a'*64,'e'*64})
   self.assertEqual(M.apply_cache(rows),10)
   for name in ('b'*64,'c'*64,'d'*64,'settings.json','hardlink'):self.assertTrue((root/name).exists())
 def test_changed_file_blocks_whole_deletion(self):
  with tempfile.TemporaryDirectory() as tmp:
   root=pathlib.Path(tmp);p=root/('a'*64);p.write_bytes(b'cache');os.utime(p,(0,0));rows=M.cache_plan(root,time.time())
   p.write_bytes(b'changed')
   with self.assertRaises(ValueError):M.apply_cache(rows)
   self.assertTrue(p.exists())
