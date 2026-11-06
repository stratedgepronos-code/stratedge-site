#!/usr/bin/env python3
"""Owner-authorized obsolete cache/Edge Finder cleanup; live data is excluded."""
import argparse,datetime as dt,json,os,pathlib,re,shutil,stat,subprocess,time
CACHE=pathlib.Path('/var/lib/snapd/cache')
EDGE=pathlib.Path('/home/alex/_archive_20260522_edge-finder')
MARKER=pathlib.Path('/var/backups/stratedge-storage-cleanup-20261107.json')

def cache_plan(root,now,opened=()):
    if root.is_symlink():raise ValueError('Cache symlink refused')
    rows=[]
    if not root.is_dir():return rows
    for p in root.iterdir():
        info=p.lstat()
        if not re.fullmatch(r'[0-9a-f]{64,128}',p.name) or not stat.S_ISREG(info.st_mode) or info.st_nlink!=1:continue
        if info.st_mtime>now-7*86400 or str(p) in opened:continue
        rows.append((p,info))
    return rows

def apply_cache(rows):
    for p,old in rows:
        new=p.lstat()
        if (new.st_ino,new.st_dev,new.st_size,new.st_mtime_ns,new.st_nlink)!=(old.st_ino,old.st_dev,old.st_size,old.st_mtime_ns,old.st_nlink):raise ValueError('Cache changed before deletion')
    total=0
    for p,old in rows:p.unlink();total+=old.st_size
    return total

def referenced(target):
    needle=str(target).encode()
    for base in ('/etc/systemd/system','/etc/cron.d','/etc/cron.daily','/var/spool/cron/crontabs','/var/www/stratedgepronos.fr/public_html'):
        for directory,dirs,files in os.walk(base,followlinks=False):
            dirs[:]=[x for x in dirs if x not in ('.git','data','uploads','assets','private')]
            for name in files:
                p=pathlib.Path(directory)/name
                if p.is_symlink():continue
                try:
                    if p.stat().st_size<=2_000_000 and needle in p.read_bytes():return True
                except OSError:continue
    return False

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--apply',action='store_true');args=parser.parse_args()
    if MARKER.exists():print('STORAGE_CLEANUP_ALREADY_DONE '+MARKER.read_text(),flush=True);return
    opened=set()
    for proc in pathlib.Path('/proc').glob('[0-9]*/fd/*'):
        try:opened.add(os.readlink(proc))
        except OSError:pass
    changes=subprocess.run(['snap','changes'],capture_output=True,text=True,timeout=20)
    safe=changes.returncode==0 and not any(re.search(r'\b(Doing|Do|Undo|Undoing|Hold)\b',line) for line in changes.stdout.splitlines()[1:])
    rows=cache_plan(CACHE,time.time(),opened) if safe else []
    print('STORAGE_CLEANUP_PLAN '+json.dumps({'cache_files':len(rows),'cache_bytes':sum(s.st_size for _,s in rows),'snap_idle':safe}),flush=True)
    remove_edge=EDGE.is_dir() and not EDGE.is_symlink() and not referenced(EDGE)
    if not args.apply:return
    if os.geteuid()!=0:raise PermissionError('Root required')
    freed=apply_cache(rows)
    edge_bytes=0
    if remove_edge:
        edge_bytes=sum(p.lstat().st_size for p in EDGE.rglob('*') if p.is_file() and not p.is_symlink())
        shutil.rmtree(EDGE)
    result={'at':dt.datetime.now(dt.timezone.utc).isoformat(),'cache_files':len(rows),'cache_bytes_removed':freed,
            'obsolete_edge_archive_removed':remove_edge,'edge_bytes_removed':edge_bytes,'live_archives_deleted':0,
            'free_bytes':shutil.disk_usage('/var/backups').free}
    with MARKER.open('x') as f:json.dump(result,f);f.flush();os.fsync(f.fileno())
    os.chmod(MARKER,0o600)
    print('STORAGE_CLEANUP_OK '+json.dumps(result),flush=True)

if __name__=='__main__':main()
