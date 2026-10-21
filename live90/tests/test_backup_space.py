import gzip
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest

spec=importlib.util.spec_from_file_location('backup_space',Path(__file__).resolve().parents[2]/'ops/live90-backup-space.py')
backup=importlib.util.module_from_spec(spec);spec.loader.exec_module(backup)

class BackupSpaceTests(unittest.TestCase):
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
