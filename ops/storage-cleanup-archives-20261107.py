#!/usr/bin/env python3
"""Remove only inspected, retired August Edge Finder copies and old pip downloads."""
import datetime as dt,json,os,pathlib,shutil,stat,time
ROOT=pathlib.Path('/home/alex/_archive_20260830')
TARGETS=[ROOT/x for x in ('stratedge-edge-finder','tennis-edge-finder','web/edge-finder','web/edge-finder-tennis')]
MARKER=pathlib.Path('/var/backups/stratedge-storage-archives-20261107.json')

def in_use(target, bases=None, proc_root=pathlib.Path("/proc")):
    if bases is None:bases=("/home/alex","/home/alex/.config/systemd/user","/opt","/etc/systemd/system","/etc/cron.d","/var/spool/cron/crontabs","/var/www/stratedgepronos.fr/public_html")
    needle=str(target).encode()
    for proc in proc_root.glob('[0-9]*'):
        for name in ('cmdline','maps'):
            try:
                if needle in (proc/name).read_bytes():return 'running_process'
            except OSError:pass
        for link in [proc/'cwd',proc/'exe',*list((proc/'fd').glob('*'))]:
            try:
                resolved=str(link.resolve())
                if resolved==str(target) or resolved.startswith(str(target)+'/'):return 'running_process'
            except OSError:pass
    for base in bases:
        for directory,dirs,files in os.walk(base,followlinks=False):
            if directory==str(ROOT) or directory.startswith(str(ROOT)+'/'):dirs[:]=[];continue
            for name in dirs+files:
                p=pathlib.Path(directory)/name
                if p.is_symlink():
                    try:
                        resolved=str(p.resolve())
                        if resolved==str(target) or resolved.startswith(str(target)+'/'):return 'external_symlink'
                    except (OSError,RuntimeError):pass
            dirs[:]=[x for x in dirs if not x.startswith('.') and x not in ('venv','node_modules','data','uploads','logs','backups','assets')]
            for name in files:
                p=pathlib.Path(directory)/name
                try:
                    if not p.is_symlink() and p.stat().st_size<=2_000_000 and needle in p.read_bytes():return 'external_reference'
                except OSError:pass
    return None

def clean_pip(cache,opened,now):
    # Cached HTTP response bodies only; installed environments are elsewhere.
    count=total=0
    if not cache.is_dir() or cache.resolve()!=cache:return count,total
    for directory,dirs,files in os.walk(cache,followlinks=False):
        dirs[:]=[x for x in dirs if not (pathlib.Path(directory)/x).is_symlink()]
        for name in files:
            p=pathlib.Path(directory)/name;s=p.lstat()
            if stat.S_ISREG(s.st_mode) and s.st_nlink==1 and s.st_mtime<now-7*86400 and str(p) not in opened:
                p.unlink();total+=s.st_size;count+=1
    return count,total

def main():
    if os.geteuid()!=0:raise PermissionError('Root required')
    if MARKER.exists():print('STORAGE_ARCHIVES_ALREADY_DONE '+MARKER.read_text(),flush=True);return
    removed=[];preserved=[];total=0
    for path in TARGETS:
        if not path.exists():continue
        if path.is_symlink() or path.resolve()!=path:raise ValueError('Archive root symlink refused')
        reason=in_use(path)
        if reason:preserved.append({'path':str(path),'reason':reason});continue
        entries=list(path.rglob('*'))
        if any(os.path.ismount(p) for p in entries):raise ValueError('Mounted archive child refused')
        size=sum(p.lstat().st_size for p in entries if p.is_file() and not p.is_symlink())
        shutil.rmtree(path);total+=size;removed.append({'path':str(path),'bytes':size})
    opened=set()
    for fd in pathlib.Path('/proc').glob('[0-9]*/fd/*'):
        try:opened.add(os.readlink(fd))
        except OSError:pass
    pip_files,pip_bytes=clean_pip(pathlib.Path('/home/alex/.cache/pip/http-v2'),opened,time.time())
    report={'at':dt.datetime.now(dt.timezone.utc).isoformat(),'removed':removed,'preserved':preserved,'archive_bytes_removed':total,'pip_files':pip_files,'pip_bytes_removed':pip_bytes,'free_bytes':shutil.disk_usage('/var/backups').free}
    with MARKER.open('x') as f:json.dump(report,f);f.flush();os.fsync(f.fileno())
    os.chmod(MARKER,0o600);print('STORAGE_ARCHIVES_CLEANUP_OK '+json.dumps(report),flush=True)

if __name__=='__main__':main()
