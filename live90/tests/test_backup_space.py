import gzip
import importlib.util
import json
import os
import subprocess
from pathlib import Path
import tempfile
import unittest

spec=importlib.util.spec_from_file_location('backup_space',Path(__file__).resolve().parents[2]/'ops/live90-backup-space.py')
backup=importlib.util.module_from_spec(spec);spec.loader.exec_module(backup)

class BackupSpaceTests(unittest.TestCase):
    def test_workflow_preflight_only_skips_when_both_installed_engine_files_match(self):
        repo=Path(__file__).resolve().parents[2]
        workflow=(repo/'.github/workflows/deploy-vps.yml').read_text()
        block=workflow.split('# LIVE90_PREFLIGHT_BEGIN\n',1)[1].split('# LIVE90_PREFLIGHT_END',1)[0]
        installed=self.root/'installed';installed.mkdir()
        source=self.root/'live90/server';source.mkdir(parents=True)
        ops=self.root/'ops';ops.mkdir()
        marker=self.root/'preflight-called'
        (ops/'live90-backup-space.py').write_text('from pathlib import Path\nPath('+repr(str(marker))+').write_text("called")\n')
        for name in ('engine.py','live_v4.py'):
            (source/name).write_text(name);(installed/name).write_text(name)
        script='set -e\nsudo(){ shift; "$@"; }\n'+block.replace('/opt/stratedge/live90',str(installed))
        def run():
            marker.unlink(missing_ok=True)
            subprocess.run(['bash','-c',script],env=dict(os.environ,LIVE90_ROOT=str(self.root)),check=True,capture_output=True)
            return marker.exists()
        self.assertFalse(run())
        for name in ('engine.py','live_v4.py'):
            (installed/name).write_text('different');self.assertTrue(run())
            (installed/name).write_text(name)
        (installed/'engine.py').unlink();self.assertTrue(run())

    def test_stream_preflight_budgets_archive_beyond_reserve(self):
        size=10*1024**3
        self.assertEqual(backup.required_space(size),size+2*1024**3)
        self.assertGreater(backup.required_space(size,True),3*1024**3)
        self.assertLess(backup.required_space(size,True),backup.required_space(size))
        self.assertGreater(backup.required_space(1024,True),3*1024**3)
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup)
        self.root=Path(self.tmp.name);self.folder=self.root/'snapshot';self.folder.mkdir()
        self.db=self.root/'active.sqlite';self.db.write_bytes(b'active database unchanged')
        self.source=self.folder/'live90.sqlite';self.content=b'SQLite format 3\0'+b'x'*(1024*1024)
        self.source.write_bytes(self.content);self.now=backup.FAILED_END+86400*3
        self.old=self.now-7200;os.utime(self.source,(self.old,self.old));os.utime(self.folder,(self.old,self.old))
    def test_newer_than_incident_but_closed_old_backup_is_eligible(self):
        self.assertGreater(self.old,backup.FAILED_START)
        self.assertTrue(backup.eligible_backup(self.source,self.db,self.now))
    def test_active_recent_journal_and_invalid_copies_are_excluded(self):
        self.assertFalse(backup.eligible_backup(self.source,self.source,self.now))
        self.assertFalse(backup.eligible_backup(self.source,self.db,self.old+60))
        journal=Path(str(self.source)+'-wal');journal.write_bytes(b'pending')
        self.assertFalse(backup.eligible_backup(self.source,self.db,self.now));journal.unlink()
        self.source.write_bytes(b'bad header'+b'x'*1024*1024);os.utime(self.source,(self.old,self.old));os.utime(self.folder,(self.old,self.old))
        self.assertFalse(backup.eligible_backup(self.source,self.db,self.now))
    def test_compression_round_trip_and_active_preserved(self):
        self.assertTrue(backup.compress_verified(self.source,1024*1024))
        self.assertEqual(gzip.decompress(self.source.with_suffix('.sqlite.gz').read_bytes()),self.content)
        self.assertFalse(self.source.exists());self.assertEqual(self.db.read_bytes(),b'active database unchanged')
    def test_insufficient_allowance_preserves_original(self):
        with self.assertRaises(OSError):backup.compress_verified(self.source,1)
        self.assertEqual(self.source.read_bytes(),self.content)
        self.assertFalse(self.source.with_suffix('.sqlite.gz.partial').exists())

    def test_recent_copy_needs_matching_completion_marker(self):
        os.utime(self.source,(self.now,self.now));os.utime(self.folder,(self.now,self.now))
        self.assertFalse(backup.eligible_backup(self.source,self.db,self.now))
        info=self.source.stat();marker=self.folder/'snapshot-complete.json'
        marker.write_text(json.dumps({'size':info.st_size,'mtime_ns':info.st_mtime_ns}))
        self.assertTrue(backup.eligible_backup(self.source,self.db,self.now))
        self.assertFalse(backup.eligible_backup(self.source,self.source,self.now))
        journal=Path(str(self.source)+'-wal');journal.write_bytes(b'pending')
        self.assertFalse(backup.eligible_backup(self.source,self.db,self.now));journal.unlink()
        with self.source.open('ab') as f:f.write(b'changed')
        self.assertFalse(backup.eligible_backup(self.source,self.db,self.now))
