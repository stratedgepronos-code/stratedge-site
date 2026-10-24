import gzip
import importlib.util
import json
import sqlite3
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('stream_backup', Path(__file__).resolve().parents[2]/'ops/live90-stream-backup.py')
B = importlib.util.module_from_spec(spec)
spec.loader.exec_module(B)


class StreamBackupTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        self.db = self.root/'active.sqlite'
        self.archive = self.root/'snapshot.sql.gz'
        self.writer = sqlite3.connect(self.db)
        self.addCleanup(self.writer.close)
        self.writer.execute('PRAGMA journal_mode=WAL')
        self.writer.executescript('''
            PRAGMA user_version=7;
            PRAGMA application_id=1234;
            CREATE TABLE records(id INTEGER PRIMARY KEY AUTOINCREMENT, text TEXT, blob BLOB, n REAL);
            CREATE TABLE history(k TEXT PRIMARY KEY, v TEXT) WITHOUT ROWID;
            CREATE TABLE log(value TEXT);
            CREATE VIEW visible AS SELECT id,text FROM records;
            CREATE INDEX text_idx ON records(text);
            CREATE TRIGGER audit AFTER INSERT ON records BEGIN INSERT INTO log VALUES(new.text); END;
        ''')
        self.writer.executemany('INSERT INTO records(text,blob,n) VALUES(?,?,?)', [
            ("Un but d'Équipe\n🩷; -- fin", b'\x00\xff\x01', 1.5),
            ('avant\0après', b'', float('inf')),
            (None, None, -1e-18),
        ])
        self.writer.execute("INSERT INTO history VALUES('signal-1','observed')")
        self.writer.execute("INSERT INTO log(rowid,value) VALUES(42,'rowid à conserver')")
        self.writer.commit()

    def make(self, **kw):
        return B.backup(self.db, self.archive, reserve=0, **kw)

    def test_round_trip_preserves_all_data_schema_types_ids_and_versions(self):
        record = self.make()
        self.assertFalse((self.root/'snapshot.sqlite').exists())
        self.assertEqual(self.archive.stat().st_mode & 0o777, 0o600)
        self.assertEqual(B.manifest_path(self.archive).stat().st_mode & 0o777, 0o600)
        self.assertEqual(B.verify(self.archive), record)
        target = self.root/'restored.sqlite'
        B.restore(self.archive, target, reserve=0)
        with sqlite3.connect(target) as restored:
            for table in ('records', 'history', 'log'):
                self.assertEqual(self.writer.execute('SELECT * FROM '+table).fetchall(), restored.execute('SELECT * FROM '+table).fetchall())
            self.assertEqual(self.writer.execute('SELECT rowid,* FROM log').fetchall(), restored.execute('SELECT rowid,* FROM log').fetchall())
            schema = "SELECT type,name,sql FROM sqlite_schema WHERE sql IS NOT NULL ORDER BY name"
            self.assertEqual(self.writer.execute(schema).fetchall(), restored.execute(schema).fetchall())
            self.assertEqual(restored.execute('PRAGMA user_version').fetchone()[0], 7)
            self.assertEqual(restored.execute('PRAGMA application_id').fetchone()[0], 1234)
            restored.execute("INSERT INTO records(text) VALUES('next')")
            self.assertEqual(restored.execute('SELECT max(id) FROM records').fetchone()[0], 4)
            self.assertEqual(restored.execute('SELECT value FROM log ORDER BY rowid DESC LIMIT 1').fetchone()[0], 'next')

    def test_wal_writes_continue_and_snapshot_stays_consistent_across_tables(self):
        wrote = []
        def concurrent(table, count):
            if wrote:
                return
            self.writer.execute("INSERT INTO records(text) VALUES('arrived during backup')")
            self.writer.execute("UPDATE history SET v='later'")
            self.writer.commit()
            wrote.append(True)
        self.make(progress=concurrent)
        self.assertTrue(wrote)
        target = self.root/'restored.sqlite'
        B.restore(self.archive, target, reserve=0)
        with sqlite3.connect(target) as restored:
            self.assertEqual(restored.execute('SELECT count(*) FROM records').fetchone()[0], 3)
            self.assertEqual(restored.execute('SELECT v FROM history').fetchone()[0], 'observed')
        self.assertEqual(self.writer.execute('SELECT count(*) FROM records').fetchone()[0], 4)

    def test_corrupted_archive_refused_before_restore(self):
        self.make()
        self.archive.write_bytes(gzip.compress(b'BEGIN; COMMIT;'))
        target = self.root/'restored.sqlite'
        with self.assertRaises(ValueError): B.restore(self.archive, target, reserve=0)
        self.assertFalse(target.exists())

    def test_existing_backup_and_existing_restore_target_never_overwritten(self):
        self.make()
        original = self.archive.read_bytes()
        with self.assertRaises(FileExistsError): self.make()
        self.assertEqual(self.archive.read_bytes(), original)
        with self.assertRaises(FileExistsError): B.restore(self.archive, self.db, reserve=0)
        self.assertEqual(self.writer.execute('SELECT count(*) FROM records').fetchone()[0], 3)

    def test_disk_exhaustion_or_interruption_keeps_live_and_cleans_only_partial(self):
        def abort(*_): raise OSError('disk reserve reached')
        with self.assertRaises(OSError): self.make(progress=abort)
        self.assertFalse(self.archive.exists())
        self.assertFalse(self.archive.with_name(self.archive.name+'.partial').exists())
        self.assertEqual(self.writer.execute('SELECT count(*) FROM records').fetchone()[0], 3)
        self.writer.execute("INSERT INTO history VALUES('after','still running')")
        self.writer.commit()
        with patch.object(B.shutil, 'disk_usage') as usage:
            usage.return_value.free = 1
            with self.assertRaises(OSError): self.make()
        self.assertFalse(self.archive.exists())

    def test_new_destination_only_and_unsupported_journal_fail_closed(self):
        self.writer.execute('PRAGMA wal_checkpoint(TRUNCATE)')
        self.writer.execute('PRAGMA journal_mode=DELETE')
        with self.assertRaisesRegex(ValueError, 'WAL'): self.make()
        self.assertFalse(self.archive.exists())

    def test_generated_columns_and_quoted_names_restore(self):
        self.writer.execute('CREATE TABLE "odd\""name"(a INTEGER,b INTEGER GENERATED ALWAYS AS (a+1) STORED)')
        self.writer.execute('INSERT INTO "odd\""name"(a) VALUES(4)')
        self.writer.commit()
        self.make()
        target = self.root/'restored.sqlite'
        B.restore(self.archive, target, reserve=0)
        with sqlite3.connect(target) as restored:
            self.assertEqual(restored.execute('SELECT * FROM "odd\""name"').fetchall(), [(4,5)])

    def test_timeout_has_no_incomplete_archive(self):
        with self.assertRaises(TimeoutError): self.make(timeout=-1)
        self.assertFalse(self.archive.exists())


if __name__ == '__main__': unittest.main()
