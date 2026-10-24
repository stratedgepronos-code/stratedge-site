#!/usr/bin/env python3
"""Consistent WAL snapshot -> compressed SQL, without an uncompressed DB copy.

Read-only source, bounded memory, two GiB disk reserve throughout. Archives are
private and SHA256 checked after decompression. Restore ONLY to a new file.
"""
import argparse
import datetime as dt
import gzip
import hashlib
import json
import math
import os
from pathlib import Path
import shutil
import sqlite3
import time

RESERVE = 2 * 1024**3
CHUNK = 1024**2
FORMAT = 'stratedge.sqlite-sql-gzip.v1'


def identifier(name):
    return '"' + name.replace('"', '""') + '"'


def literal(value):
    if value is None:
        return 'NULL'
    if isinstance(value, int):
        return str(value)
    if isinstance(value, float):
        if math.isnan(value):
            raise ValueError('Unexpected NaN value')
        return ('-' if value < 0 else '') + '9.0e999' if math.isinf(value) else repr(value)
    if isinstance(value, bytes):
        return "X'" + value.hex() + "'"
    if isinstance(value, str):
        # SQLite quote() truncates embedded NULs: preserve these as UTF-8 hex.
        if '\0' in value:
            return "CAST(X'" + value.encode('utf-8').hex() + "' AS TEXT)"
        return "'" + value.replace("'", "''") + "'"
    raise TypeError('Unsupported SQLite value type')


def fsync_dir(path):
    fd = os.open(path, os.O_RDONLY | os.O_DIRECTORY)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)


class Guard:
    def __init__(self, directories, reserve=RESERVE, timeout=900):
        self.directories = set(Path(p) for p in directories)
        self.reserve = reserve
        self.deadline = time.monotonic() + timeout
        self.last = 0

    def check(self, force=False):
        now = time.monotonic()
        if now > self.deadline:
            raise TimeoutError('Backup time limit reached; current engine preserved')
        if force or now - self.last >= 1:
            for p in self.directories:
                if shutil.disk_usage(p).free < self.reserve + CHUNK:
                    raise OSError('Backup disk reserve reached; current engine preserved')
            self.last = now


def manifest_path(archive):
    return archive.with_name(archive.name + '.json')


def verified_hash(archive, guard=None):
    digest = hashlib.sha256()
    size = 0
    with gzip.open(archive, 'rb') as stream:
        for block in iter(lambda: stream.read(CHUNK), b''):
            if guard:
                guard.check()
            digest.update(block)
            size += len(block)
    return digest.hexdigest(), size


def verify(archive, guard=None):
    archive = Path(archive)
    record = json.loads(manifest_path(archive).read_text())
    if record.get('format') != FORMAT or record.get('archive') != archive.name:
        raise ValueError('Unrecognized backup manifest')
    digest, size = verified_hash(archive, guard)
    if digest != record.get('sql_sha256') or size != record.get('sql_bytes'):
        raise ValueError('Backup integrity verification failed')
    return record


def backup(database, archive, reserve=RESERVE, timeout=900, progress=None):
    database, archive = Path(database).resolve(), Path(archive).absolute()
    if not database.is_file() or not archive.parent.is_dir() or archive.parent.is_symlink():
        raise ValueError('Source database and real destination directory required')
    manifest = manifest_path(archive)
    stage = archive.with_name(archive.name + '.partial')
    if any(p.exists() or p.is_symlink() for p in (archive, manifest, stage)):
        raise FileExistsError('Backup destination already exists; never overwritten')
    guard = Guard((database.parent, archive.parent), reserve, timeout)
    guard.check(True)
    started = dt.datetime.now(dt.timezone.utc).isoformat()
    digest = hashlib.sha256()
    sql_bytes = 0
    tables = {}
    published = False
    source = sqlite3.connect(database.as_uri() + '?mode=ro', uri=True, timeout=10)
    try:
        source.execute('PRAGMA query_only=ON')
        if source.execute('PRAGMA journal_mode').fetchone()[0].lower() != 'wal':
            raise ValueError('WAL mode required; refusing to block live writers')
        source.execute('BEGIN')
        objects = source.execute("SELECT type,name,sql FROM sqlite_schema WHERE sql IS NOT NULL ORDER BY name").fetchall()
        if any(sql.lstrip().upper().startswith('CREATE VIRTUAL TABLE') for _, _, sql in objects):
            raise ValueError('Virtual tables require a dedicated backup adapter')
        versions = {k: source.execute('PRAGMA ' + k).fetchone()[0] for k in ('user_version', 'application_id')}
        page_bytes = source.execute('PRAGMA page_count').fetchone()[0] * source.execute('PRAGMA page_size').fetchone()[0]
        # The first schema read fixes the snapshot. WAL writers keep collecting.
        source.set_progress_handler(lambda: (guard.check() or 0), 10000)
        fd = os.open(stage, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, 'wb') as raw, gzip.GzipFile(fileobj=raw, mode='wb', compresslevel=1, mtime=0) as out:
            def emit(statement):
                nonlocal sql_bytes
                guard.check()
                data = (statement + '\n').encode('utf-8')
                digest.update(data)
                sql_bytes += len(data)
                out.write(data)
            emit('PRAGMA foreign_keys=OFF;')
            emit('BEGIN TRANSACTION;')
            for key, value in versions.items():
                emit('PRAGMA ' + key + '=' + str(value) + ';')
            last_report = time.monotonic()
            for kind, name, schema in objects:
                if kind != 'table' or name.startswith('sqlite_'):
                    continue
                emit(schema + ';')
                info = source.execute('PRAGMA table_xinfo(' + identifier(name) + ')').fetchall()
                columns = [c[1] for c in info if c[6] == 0]  # generated columns recompute
                # Preserve accessible implicit rowids as well as explicit primary keys.
                lower = {c[1].lower() for c in info}
                alias = next((a for a in ('rowid', '_rowid_', 'oid') if a not in lower), None)
                if alias:
                    try:
                        source.execute('SELECT ' + alias + ' FROM ' + identifier(name) + ' LIMIT 0')
                        columns.insert(0, alias)
                    except sqlite3.OperationalError as error:
                        if 'no such column' not in str(error).lower():
                            raise
                cols = ','.join(identifier(c) for c in columns)
                prefix = 'INSERT INTO ' + identifier(name) + '(' + cols + ') VALUES('
                count = 0
                for row in source.execute('SELECT ' + cols + ' FROM ' + identifier(name)):
                    emit(prefix + ','.join(literal(v) for v in row) + ');')
                    count += 1
                    if progress:
                        progress(name, count)
                    if time.monotonic() - last_report >= 30:
                        print('LIVE90_STREAM_PROGRESS rows=%d sql_bytes=%d' % (sum(tables.values()) + count, sql_bytes), flush=True)
                        last_report = time.monotonic()
                tables[name] = count
            if any(name == 'sqlite_sequence' for _, name, _ in objects):
                emit('DELETE FROM sqlite_sequence;')
                for name, seq in source.execute('SELECT name,seq FROM sqlite_sequence'):
                    emit('INSERT INTO sqlite_sequence VALUES(' + literal(name) + ',' + literal(seq) + ');')
            for kind, name, schema in objects:
                if kind in ('index', 'trigger', 'view') and not name.startswith('sqlite_'):
                    emit(schema + ';')
            emit('COMMIT;')
        source.rollback()  # release snapshot before the verification pass
        source.close()
        source = None
        guard.check(True)
        checksum, restored_bytes = verified_hash(stage, guard)
        if checksum != digest.hexdigest() or restored_bytes != sql_bytes:
            raise ValueError('Compressed backup round-trip mismatch')
        with stage.open('rb') as stream:
            os.fsync(stream.fileno())
        # O_EXCL link publication prevents overwriting a competing backup.
        os.link(stage, archive)
        published = True
        stage.unlink()
        record = {'format': FORMAT, 'archive': archive.name, 'started_at': started,
                  'completed_at': dt.datetime.now(dt.timezone.utc).isoformat(),
                  'source_page_bytes': page_bytes, 'sql_bytes': sql_bytes,
                  'archive_bytes': archive.stat().st_size, 'sql_sha256': checksum,
                  'tables': tables, **versions}
        fd = os.open(manifest, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, 'w') as stream:
            json.dump(record, stream, ensure_ascii=False)
            stream.flush()
            os.fsync(stream.fileno())
        fsync_dir(archive.parent)
        print('LIVE90_STREAM_BACKUP_OK archive=%s compressed_bytes=%d source_bytes=%d rows=%d' %
              (archive, record['archive_bytes'], page_bytes, sum(tables.values())), flush=True)
        return record
    except BaseException:
        stage.unlink(missing_ok=True)
        # A published archive is retained on a later error; never mistake it for verified without its manifest.
        if published:
            print('LIVE90_STREAM_BACKUP_INCOMPLETE retained=' + str(archive), flush=True)
        raise
    finally:
        if source is not None:
            source.close()


def restore(archive, destination, reserve=RESERVE, timeout=1800):
    """Offline restore into a NEW file; never replace an active SQLite database."""
    archive, destination = Path(archive), Path(destination)
    if destination.exists() or destination.is_symlink():
        raise FileExistsError('Restore destination exists; use a new path')
    guard = Guard((destination.parent,), reserve, timeout)
    guard.check(True)
    record = verify(archive, guard)
    fd = os.open(destination, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    os.close(fd)
    connection = sqlite3.connect(destination)
    try:
        connection.set_progress_handler(lambda: (guard.check() or 0), 10000)
        pending = ''
        with gzip.open(archive, 'rt', encoding='utf-8') as stream:
            for line in stream:
                guard.check()
                pending += line
                if sqlite3.complete_statement(pending):
                    connection.execute(pending)
                    pending = ''
        if pending.strip() or connection.in_transaction:
            raise ValueError('Incomplete SQL backup')
        if connection.execute('PRAGMA integrity_check').fetchall() != [('ok',)]:
            raise ValueError('Restored database integrity check failed')
        for table, count in record['tables'].items():
            if connection.execute('SELECT COUNT(*) FROM ' + identifier(table)).fetchone()[0] != count:
                raise ValueError('Restored row count mismatch')
        connection.close()
        with destination.open('rb') as stream:
            os.fsync(stream.fileno())
        fsync_dir(destination.parent)
        print('LIVE90_RESTORE_OK new_database=' + str(destination), flush=True)
    except BaseException:
        connection.close()
        # Remove only this failed restore, never an existing database or archive.
        destination.unlink(missing_ok=True)
        Path(str(destination) + '-journal').unlink(missing_ok=True)
        raise


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=('backup', 'verify', 'restore'))
    parser.add_argument('source', type=Path)
    parser.add_argument('destination', type=Path, nargs='?')
    args = parser.parse_args()
    if args.command in ('backup', 'restore') and args.destination is None:
        parser.error('destination required')
    if args.command == 'backup':
        backup(args.source, args.destination)
    elif args.command == 'verify':
        record = verify(args.source)
        print('LIVE90_ARCHIVE_OK compressed_bytes=' + str(record['archive_bytes']))
    else:
        restore(args.source, args.destination)
