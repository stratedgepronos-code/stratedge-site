import importlib.util,pathlib,tempfile,unittest
from types import SimpleNamespace
from unittest.mock import Mock
SPEC=importlib.util.spec_from_file_location('export_hint',pathlib.Path(__file__).resolve().parents[2]/'ops/deploy-live90-export-hint.py')
H=importlib.util.module_from_spec(SPEC);SPEC.loader.exec_module(H)

class ExportHint(unittest.TestCase):
 def test_exact_change_only(self):
  old=b'q="'+H.OLD+b'"\n';new=old.replace(H.OLD,H.NEW)
  self.assertTrue(H.exact_hint_only(old,new))
  self.assertFalse(H.exact_hint_only(old,new+b'x=1\n'))
  self.assertFalse(H.exact_hint_only(old+old,new+new))
 def test_code_backup_and_rollback_without_db_access(self):
  with tempfile.TemporaryDirectory() as tmp:
   root=pathlib.Path(tmp);repo=root/'repo';target=root/'runtime';backups=root/'backups'
   (repo/'live90/server').mkdir(parents=True);target.mkdir();backups.mkdir()
   old=b'q="'+H.OLD+b'"\n';new=old.replace(H.OLD,H.NEW)
   for p in (repo/'live90/server/engine.py',target/'engine.py'):p.write_bytes(b'# wrapper\n')
   (repo/'live90/server/live_v4.py').write_bytes(new);(target/'live_v4.py').write_bytes(old)
   run=Mock(return_value=SimpleNamespace(returncode=0))
   self.assertTrue(H.deploy(repo,target,backups,run).startswith('installed_read_only_hint'))
   self.assertEqual((target/'live_v4.py').read_bytes(),new)
   self.assertEqual(next(backups.glob('*/live_v4.py')).read_bytes(),old)
   self.assertEqual(H.deploy(repo,target,backups,run),'already_current')
   (target/'live_v4.py').write_bytes(old)
   run=Mock(side_effect=[SimpleNamespace(returncode=0),RuntimeError('restart failure'),SimpleNamespace(returncode=0)])
   with self.assertRaises(RuntimeError):H.deploy(repo,target,backups,run)
   self.assertEqual((target/'live_v4.py').read_bytes(),old)

if __name__=='__main__':unittest.main()
