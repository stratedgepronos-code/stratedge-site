#!/usr/bin/env python3
"""Lossless gzip recompression of completed inactive backups. No archive pruning."""
import argparse,datetime as dt,gzip,hashlib,json,os,pathlib,shutil,stat,time
ROOT=pathlib.Path('/var/backups')
RESERVE=2*1024**3
CHUNK=1024**2

def identity(p):
    s=p.lstat()
    if not stat.S_ISREG(s.st_mode) or s.st_nlink!=1 or p.parent.is_symlink():raise ValueError('Regular private archive required')
    return (s.st_dev,s.st_ino,s.st_size,s.st_mtime_ns)

def digest_gzip(p,guard):
    h=hashlib.sha256();size=0
    with gzip.open(p,'rb') as f:
        for b in iter(lambda:f.read(CHUNK),b''):guard();h.update(b);size+=len(b)
    return h.hexdigest(),size

def recompress(p,reserve=RESERVE,max_seconds=1200):
    before=identity(p);meta=p.with_name(p.name+'.json');receipt=p.with_name(p.name+'.recompression.json')
    if receipt.exists():
        record=json.loads(receipt.read_text())
        if record.get('new_bytes')==p.stat().st_size:
            print('STORAGE_RECOMPRESS_ALREADY_DONE '+str(p),flush=True);return 0
        raise ValueError('Recompressed archive changed')
    manifest=json.loads(meta.read_text()) if meta.exists() else None
    if meta.exists():identity(meta)
    if manifest and manifest.get('archive_bytes')!=before[2]:raise ValueError('Manifest size mismatch')
    stage=p.with_name(p.name+'.recompress.partial');record_stage=receipt.with_name(receipt.name+'.partial')
    started=time.monotonic();last=started
    def guard():
        nonlocal last
        if time.monotonic()-started>max_seconds:raise TimeoutError('Recompression timeout; original preserved')
        if shutil.disk_usage(p.parent).free<reserve+CHUNK:raise OSError('Disk reserve reached; original preserved')
        if time.monotonic()-last>=30:
            print('STORAGE_RECOMPRESS_PROGRESS '+json.dumps({'path':str(p),'elapsed_seconds':round(time.monotonic()-started)}),flush=True);last=time.monotonic()
    guard();digest=hashlib.sha256();size=0;created=False
    print('STORAGE_RECOMPRESS_START '+json.dumps({'path':str(p),'old_bytes':before[2],'level':6}),flush=True)
    try:
        fd=os.open(stage,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        created=True
        with os.fdopen(fd,'wb') as raw,gzip.GzipFile(filename='',fileobj=raw,mode='wb',compresslevel=6,mtime=0) as out,gzip.open(p,'rb') as source:
            for block in iter(lambda:source.read(CHUNK),b''):
                guard();digest.update(block);size+=len(block);out.write(block)
        checksum,verified_size=digest_gzip(stage,guard)
        if checksum!=digest.hexdigest() or verified_size!=size:raise ValueError('Round-trip mismatch; original preserved')
        if manifest and (manifest.get('sql_sha256')!=checksum or manifest.get('sql_bytes')!=size):raise ValueError('Original manifest checksum mismatch')
        if identity(p)!=before:raise ValueError('Original archive changed during compression')
        new_size=stage.stat().st_size
        if new_size>=before[2]:print('STORAGE_RECOMPRESS_NO_GAIN '+str(p),flush=True);return 0
        with stage.open('rb') as f:os.fsync(f.fileno())
        record={'path':str(p),'old_bytes':before[2],'new_bytes':new_size,'uncompressed_bytes':size,'uncompressed_sha256':checksum,'level':6,'completed_at':dt.datetime.now(dt.timezone.utc).isoformat()}
        # SQL hash is unchanged. Both old and new compressed representations restore identically.
        os.replace(stage,p)
        if manifest:
            manifest.update(archive_bytes=new_size,compression_level=6)
            temp=meta.with_name(meta.name+'.recompress.partial')
            with temp.open('x') as f:json.dump(manifest,f);f.flush();os.fsync(f.fileno())
            os.chmod(temp,0o600);os.replace(temp,meta)
        with record_stage.open('x') as f:json.dump(record,f);f.flush();os.fsync(f.fileno())
        os.chmod(record_stage,0o600);os.replace(record_stage,receipt)
        dfd=os.open(p.parent,os.O_RDONLY)
        try:os.fsync(dfd)
        finally:os.close(dfd)
        print('STORAGE_RECOMPRESS_OK '+json.dumps(record),flush=True)
        return before[2]-new_size
    finally:
        # Only our new temporary output is removed. The original stays until verification.
        if created and stage.exists():stage.unlink()

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--apply',action='store_true');parser.add_argument('--target-free-gib',type=float,default=8);parser.add_argument('--max-archives',type=int,default=4);args=parser.parse_args()
    choices=[p for p in ROOT.glob('stratedge-live90-v4.*/live90.*.gz') if p.name in ('live90.sql.gz','live90.sqlite.gz') and not p.is_symlink() and p.stat().st_mtime<time.time()-3600]
    choices.sort(key=lambda p:p.stat().st_size,reverse=True)
    freed=0
    for p in [p for p in choices if not p.with_name(p.name+'.recompression.json').exists()][:args.max_archives]:
        if shutil.disk_usage(ROOT).free>=args.target_free_gib*1024**3:break
        if not args.apply:print('STORAGE_RECOMPRESS_PLAN '+str(p),flush=True);continue
        freed+=recompress(p)
    print('STORAGE_RECOMPRESS_TOTAL '+json.dumps({'freed_bytes':freed,'free_bytes':shutil.disk_usage(ROOT).free,'archives_deleted':0}),flush=True)

if __name__=='__main__':main()
