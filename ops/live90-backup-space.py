#!/usr/bin/env python3
"""Recover this deployment's invalid copy, then losslessly compress older backups.

Never modifies the active database. Original backup bytes are removed only after
a complete gzip round-trip SHA256 verification. No retention deletion.
"""
import datetime as dt
import gzip
import hashlib
import os
import pathlib
import shutil
import sys

ROOT = pathlib.Path('/var/backups')
FAILED_START = dt.datetime(2026, 10, 16, 13, 44, 59, tzinfo=dt.timezone.utc).timestamp()
FAILED_END = dt.datetime(2026, 10, 16, 13, 45, 25, tzinfo=dt.timezone.utc).timestamp()

def eligible_backup(source, db, now):
    # The failed-copy incident date is not a retention cutoff: completed newer
    # backups also need compression. Leave recent/in-progress copies untouched.
    if not source.is_file() or source.is_symlink() or source.parent.is_symlink():
        return False
    info = source.stat()
    if source.samefile(db) or info.st_size < 1024 * 1024 or max(info.st_mtime, source.parent.stat().st_mtime) >= now - 3600:
        return False
    if any(pathlib.Path(str(source) + suffix).exists() for suffix in ('-wal', '-shm', '-journal')):
        return False
    with source.open('rb') as f:
        return f.read(16) == b'SQLite format 3\0'

def compress_verified(source, allowance):
    target = source.with_suffix('.sqlite.gz')
    stage = source.with_suffix('.sqlite.gz.partial')
    if target.exists() or stage.exists() or source.is_symlink():
        return False
    before = source.stat()
    digest = hashlib.sha256()
    try:
        fd = os.open(stage, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, 'wb') as raw, gzip.GzipFile(fileobj=raw, mode='wb', compresslevel=1) as out, source.open('rb') as inp:
            for block in iter(lambda: inp.read(1024 * 1024), b''):
                digest.update(block)
                out.write(block)
                if raw.tell() > allowance:
                    raise OSError('Compression trop volumineuse pour la marge disponible')
        check = hashlib.sha256()
        with gzip.open(stage, 'rb') as inp:
            for block in iter(lambda: inp.read(1024 * 1024), b''):
                check.update(block)
        after = source.stat()
        if digest.digest() != check.digest() or (before.st_size, before.st_mtime_ns) != (after.st_size, after.st_mtime_ns):
            raise OSError('Vérification de sauvegarde échouée ; original conservé')
        with stage.open('rb') as f:
            os.fsync(f.fileno())
        stage.rename(target)
        source.unlink()
        print('LIVE90_BACKUP_COMPRESSED', source.parent, 'original_bytes='+str(before.st_size), 'gzip_bytes='+str(target.stat().st_size), flush=True)
        return True
    except Exception:
        stage.unlink(missing_ok=True)
        raise

def main(db, reserve_only=False):
    backups = [p for p in ROOT.glob('stratedge-live90-v4.*/live90.sqlite') if p.is_file() and not p.is_symlink() and not p.parent.is_symlink()]
    print('LIVE90_SPACE free_bytes='+str(shutil.disk_usage(ROOT).free), 'db_bytes='+str(db.stat().st_size), flush=True)
    # Only the failed copy created by run 38910129770, verified to lack a DB header.
    for p in backups:
        if FAILED_START <= p.parent.stat().st_mtime <= FAILED_END and FAILED_START <= p.stat().st_mtime <= FAILED_END:
            with p.open('rb') as f:
                header = f.read(100)
            print('LIVE90_FAILED_COPY', p, 'bytes='+str(p.stat().st_size), 'sqlite_header='+str(header.startswith(b'SQLite format 3\0')), flush=True)
            if not header or header == b'\0' * len(header):
                size = p.stat().st_size
                p.unlink()
                print('LIVE90_INCOMPLETE_COPY_REMOVED bytes='+str(size), flush=True)
    required = (0 if reserve_only else db.stat().st_size) + 2 * 1024 * 1024 * 1024
    for p in sorted(backups, key=lambda p: p.parent.stat().st_mtime):
        free = shutil.disk_usage(ROOT).free
        if free >= required:
            break
        if not eligible_backup(p, db, dt.datetime.now(dt.timezone.utc).timestamp()):
            continue
        if free < 64 * 1024 * 1024:
            break
        compress_verified(p, min(free // 2, free - 32 * 1024 * 1024))
    free = shutil.disk_usage(ROOT).free
    print('LIVE90_SPACE_AFTER free_bytes='+str(free), 'required_bytes='+str(required), flush=True)
    if free < required:
        for p in backups:
            if p.exists(): print('LIVE90_BACKUP_RETAINED', p, 'bytes='+str(p.stat().st_size), flush=True)
        raise SystemExit('Espace encore insuffisant ; aucun historique actif supprimé')

if __name__ == '__main__':
    args=[a for a in sys.argv[1:] if a != '--reserve-only']
    main(pathlib.Path(args[0] if args else '/var/lib/stratedge/live90.sqlite'), '--reserve-only' in sys.argv)

