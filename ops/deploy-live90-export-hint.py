#!/usr/bin/env python3
"""One exact read-only SELECT hint: archive code, no database/schema mutation.

Every other engine change still uses deploy-live90-v4.sh and a full DB backup.
"""
import os,pathlib,shutil,subprocess,sys,tempfile
OLD=b'SELECT * FROM v5_window_audits WHERE created_at>=? AND created_at<? AND id<=? AND id>? ORDER BY id LIMIT ?'
NEW=b'SELECT * FROM v5_window_audits INDEXED BY v5_window_audits_created WHERE created_at>=? AND created_at<? AND id<=? AND id>? ORDER BY id LIMIT ?'

def exact_hint_only(before,after):
    return before.count(OLD)==1 and before.replace(OLD,NEW)==after

def atomic_copy(source,target):
    fd,stage=tempfile.mkstemp(prefix='.export-hint-',dir=target.parent)
    try:
        with os.fdopen(fd,'wb') as f:
            f.write(source.read_bytes());f.flush();os.fsync(f.fileno())
        os.chmod(stage,0o644);os.replace(stage,target)
    finally:
        if os.path.exists(stage):os.unlink(stage)

def deploy(repo,target,backup_root,run=subprocess.run):
    source=repo/'live90/server/live_v4.py';live=target/'live_v4.py'
    if not live.exists() or not (target/'engine.py').exists():return 'not_applicable'
    if (repo/'live90/server/engine.py').read_bytes()!=(target/'engine.py').read_bytes():return 'full_backup_required'
    before,after=live.read_bytes(),source.read_bytes()
    if before==after:return 'already_current'
    if not exact_hint_only(before,after):return 'full_backup_required'
    compile(after,str(source),'exec')
    backup=pathlib.Path(tempfile.mkdtemp(prefix='stratedge-live90-export-hint.',dir=backup_root))
    shutil.copy2(live,backup/'live_v4.py');os.chmod(backup,0o700)
    (backup/'reason.txt').write_text('Exact SELECT INDEXED BY replacement only. No DB/schema write. Previous full backup retained.\n')
    active=run(['systemctl','is-active','--quiet','seuil90-live.service']).returncode==0
    try:
        atomic_copy(source,live)
        if active:
            run(['systemctl','restart','seuil90-live.service'],check=True)
            run(['systemctl','is-active','--quiet','seuil90-live.service'],check=True)
        if live.read_bytes()!=after:raise RuntimeError('Installed file mismatch')
    except Exception:
        atomic_copy(backup/'live_v4.py',live)
        if active:run(['systemctl','restart','seuil90-live.service'],check=False)
        raise
    return 'installed_read_only_hint code_backup='+str(backup)

if __name__=='__main__':
    if os.geteuid()!=0:raise SystemExit('Root required')
    print('LIVE90_EXPORT_HINT '+deploy(pathlib.Path(sys.argv[1]),pathlib.Path('/opt/stratedge/live90'),pathlib.Path('/var/backups')),flush=True)
