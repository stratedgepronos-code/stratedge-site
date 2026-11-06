import importlib.util,os,pathlib,tempfile,time,unittest

spec=importlib.util.spec_from_file_location('archive_cleanup',pathlib.Path(__file__).resolve().parents[2]/'ops/storage-cleanup-archives-20261107.py')
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)

class ArchiveCleanup(unittest.TestCase):
 def test_external_dependencies_and_open_files_block_archive_removal(self):
  with tempfile.TemporaryDirectory() as tmp:
   root=pathlib.Path(tmp);archive=root/'archive';archive.mkdir();outside=root/'outside';outside.mkdir();proc=root/'proc';proc.mkdir()
   self.assertIsNone(M.in_use(archive,[outside],proc))
   ref=outside/'service';ref.write_text('WorkingDirectory='+str(archive))
   self.assertEqual(M.in_use(archive,[outside],proc),'external_reference');ref.unlink()
   ref.symlink_to(archive,target_is_directory=True)
   self.assertEqual(M.in_use(archive,[outside],proc),'external_symlink');ref.unlink()
   fd=proc/'123'/'fd';fd.mkdir(parents=True);(fd/'4').symlink_to(archive/'data.db')
   self.assertEqual(M.in_use(archive,[outside],proc),'running_process')
 def test_cache_preserves_recent_open_linked_and_symlink_files(self):
  with tempfile.TemporaryDirectory() as tmp:
   root=pathlib.Path(tmp);cache=root/'cache';cache.mkdir();now=time.time()
   for name in ('old','recent','opened','linked'):
    p=cache/name;p.write_bytes(b'123');os.utime(p,(0,0))
   os.utime(cache/'recent',(now,now));os.link(cache/'linked',cache/'hardlink')
   (cache/'symlink').symlink_to(cache/'old');(cache/'external').symlink_to(root,target_is_directory=True)
   self.assertEqual(M.clean_pip(cache,{str(cache/'opened')},now),(1,3))
   self.assertFalse((cache/'old').exists())
   for name in ('recent','opened','linked','hardlink'):self.assertTrue((cache/name).exists())
   self.assertTrue((cache/'symlink').is_symlink());self.assertTrue((cache/'external').is_symlink())
