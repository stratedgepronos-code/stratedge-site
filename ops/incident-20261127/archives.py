"""One-off plan/apply for the completed Live90 DB archives inventoried on Nov 26."""
import hashlib, json, os, re, stat, sys, time
from pathlib import Path

ROOT = Path('/var/backups')
ACTIVE = Path('/var/lib/stratedge/live90.sqlite')
SLOTS = 'ThwHkp DIKtvP cesjVP 7hxhL5 PcUkOi h5Omrd IXedwm FQ9BSV d87H4K 2JIfpg DyjBmG Ajz2rP UClztJ kGCfd5 3YtkyC 0GXQ2U Z2WrDe PrebTz WNdqDb PuQqS3 niQr7q'.split()
NAMES = ('live90.sql.gz','live90.sqlite.gz')

def disk(p):
    f = os.statvfs(p)
    return f.f_bavail*f.f_frsize

def meta(s):
    return (s.st_dev,s.st_ino,s.st_size,s.st_mtime_ns,s.st_ctime_ns)

def ensure(ok,msg):
    if not ok: raise ValueError(msg)

def run(root,db,approval):
    ensure(root.resolve()==root and db.resolve()==db,'unexpected path resolution')
    active = db.stat()
    ensure(stat.S_ISREG(active.st_mode) and active.st_size>0,'active DB not found')
    with db.open('rb') as f: ensure(f.read(16)==b'SQLite format 3\0','active file not SQLite')
    dirfds=[]; rows=[]; handles=[]
    try:
        rootfd=os.open(root,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
        dirfds.append(rootfd)
        for slot in sorted(SLOTS):
            name='stratedge-live90-v4.'+slot
            fd=os.open(name,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=rootfd); dirfds.append(fd)
            entries=set(os.listdir(fd))
            ensure(not any('partial' in n for n in entries),name+': operation in progress')
            for fn in sorted(entries.intersection(NAMES)):
                f=os.open(fn,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=fd);handles.append(f)
                s=os.fstat(f)
                ensure(stat.S_ISREG(s.st_mode) and s.st_nlink==1 and (s.st_dev,s.st_ino)!=(active.st_dev,active.st_ino),name+': unsafe target')
                ensure(s.st_size>0 and s.st_mtime < time.time()-86400 and os.read(f,2)==b'\x1f\x8b',name+': not an old gzip archive')
                rows.append((name,fn,meta(s),fd))
        total=sum(m[2] for _,_,m,_ in rows)
        ensure(total<=12_100_000_000,'inventory exceeded')
        serial=[(d,n,m) for d,n,m,fd in rows]
        digest=hashlib.sha256(json.dumps(serial,separators=(',',':')).encode()).hexdigest()
        before=disk(root)
        print('ARCHIVES_PLAN',json.dumps({'files':len(rows),'bytes':total,'available':before,'digest':digest}),flush=True)
        for d,n,m,fd in rows: print('ARCHIVE',d+'/'+n,m[2],flush=True)
        if approval=='plan': return
        ensure(bool(rows) and approval==digest,'inventory changed or approval mismatch')
        for d,n,m,fd in rows:
            ensure(meta(os.stat(n,dir_fd=fd,follow_symlinks=False))==m,'changed before deletion')
        # Directories are specific backup locations; do not remove any directory, manifest or other file.
        for d,n,m,fd in rows:
            ensure(meta(os.stat(n,dir_fd=fd,follow_symlinks=False))==m,'changed before unlink')
            os.unlink(n,dir_fd=fd)
            os.fsync(fd)
        for f in handles: os.close(f)
        handles.clear()
        ensure(all(n not in set(os.listdir(fd)) for d,n,m,fd in rows),'archive still present')
        now=db.stat()
        ensure((now.st_dev,now.st_ino)==(active.st_dev,active.st_ino),'active database identity changed')
        print('ARCHIVES_REMOVED',json.dumps({'files':len(rows),'bytes':total,'available_after':disk(root),'active_db_bytes':now.st_size}),flush=True)
    finally:
        for fd in handles+dirfds: os.close(fd)
if __name__=='__main__':
    ensure(len(sys.argv)==2 and re.fullmatch('plan|[a-f0-9]{64}',sys.argv[1]),'invalid mode')
    run(ROOT,ACTIVE,sys.argv[1])
