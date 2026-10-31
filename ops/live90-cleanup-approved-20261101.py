#!/usr/bin/env python3
"""One-off owner-approved removal of the 30 listed Oct 16/18 Live90 archives.

Never touches the active DB, directories, code, manifests or other archives.
Requires two newer SQL backups with valid full-stream SHA256 before any deletion.
"""
import argparse, datetime as dt, importlib.util, json, os, stat
from pathlib import Path

ROOT=Path('/var/backups')
APPROVAL='owner-approved-2026-11-01-oct16-oct18'
# Exact inventory supplied by the owner, not a date glob or retention policy.
APPROVED={
 'aIB8fu':16580334,'uWa5bj':19622360,'k5ymZA':20004403,'T6SWYA':20073625,
 'EhUOeE':20218299,'tOFqP1':20243912,'eu39U9':20392093,'JHI0z6':20410090,
 'wTaiTc':25321033,'YVecSM':25345329,'Nd9USk':25353058,'K1dPlM':39245517,
 'B29mWj':59377313,'zl7nNS':59669808,'fP6nOm':59906088,'zIoJ97':60351581,
 'vnCMlM':181312786,'0TfuIj':64449670,'L9U2GV':82042957,'Y0d0LA':89258709,
 'F7zbQ7':90708496,'Mh9kI8':197529840,'tK5DRU':318987776,'ej2Ocb':319197017,
 'VwYNuo':321912170,'gOX7uq':336494565,'LMLkFG':384537771,'fMHksL':384675481,
 'lND2YD':386785842,'ujeKsa':387756185,
}

def regular(path):
    if path.is_symlink() or path.parent.is_symlink():raise ValueError('Symlink refused: '+str(path))
    info=path.stat()
    if not stat.S_ISREG(info.st_mode) or info.st_nlink!=1:raise ValueError('Regular single-link file required: '+str(path))
    return info

def plan(root,approved):
    rows=[]
    for suffix,size in approved.items():
        path=root/('stratedge-live90-v4.'+suffix)/'live90.sqlite.gz'
        if not path.exists() and not path.is_symlink():continue
        info=regular(path)
        if info.st_size!=size:raise ValueError('Inventory size changed; nothing deleted: '+str(path))
        rows.append((path,info))
    return rows

def recent_verified(root,now):
    spec=importlib.util.spec_from_file_location('stream_backup',Path(__file__).with_name('live90-stream-backup.py'))
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    choices=[]
    for path in root.glob('stratedge-live90-v4.*/live90.sql.gz'):
        manifest=module.manifest_path(path)
        try:
            regular(path);regular(manifest)
            record=json.loads(manifest.read_text());at=dt.datetime.fromisoformat(record['completed_at'])
            if at.tzinfo is None or not now-dt.timedelta(days=7)<=at<=now:continue
            if record.get('archive_bytes')!=path.stat().st_size or not all(record.get('tables',{}).get(t,0)>0 for t in ('samples','cycles')):continue
            choices.append((at,path))
        except (OSError,ValueError,KeyError,TypeError):continue
    if len(choices)<2:raise ValueError('Two recent complete backup manifests required; nothing deleted')
    verified=[]
    for _,path in sorted(choices,reverse=True)[:2]:
        print('LIVE90_CLEANUP_VERIFY '+str(path),flush=True)
        module.verify(path) # Full decompression, CRC, SHA256 and SQL byte count.
        verified.append(str(path));print('LIVE90_CLEANUP_VERIFIED '+str(path),flush=True)
    return verified

def apply(rows):
    # Revalidate the entire immutable inventory before unlinking the first file.
    for path,before in rows:
        after=regular(path)
        if (after.st_ino,after.st_dev,after.st_size,after.st_mtime_ns)!=(before.st_ino,before.st_dev,before.st_size,before.st_mtime_ns):raise ValueError('Archive changed; nothing deleted')
    removed=[]
    for path,info in rows:
        path.unlink();removed.append({'path':str(path),'bytes':info.st_size})
        print('LIVE90_APPROVED_BACKUP_REMOVED '+str(path)+' bytes='+str(info.st_size),flush=True)
    return removed

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--approval');args=parser.parse_args()
    marker=ROOT/'.stratedge-cleanup-20261101.json'
    if marker.exists() or marker.is_symlink():
        regular(marker)
        if json.loads(marker.read_text()).get('approval')!=APPROVAL:raise ValueError('Unexpected cleanup receipt')
        print('LIVE90_APPROVED_CLEANUP_ALREADY_DONE',flush=True);return
    rows=plan(ROOT,APPROVED)
    print('LIVE90_APPROVED_CLEANUP_PLAN files='+str(len(rows))+' bytes='+str(sum(i.st_size for _,i in rows)),flush=True)
    if args.approval!=APPROVAL:
        print('DRY_RUN no files deleted',flush=True);return
    if os.geteuid()!=0:raise PermissionError('Root required')
    verified=recent_verified(ROOT,dt.datetime.now(dt.timezone.utc)) if rows else []
    removed=apply(rows)
    receipt={'approval':APPROVAL,'completed_at':dt.datetime.now(dt.timezone.utc).isoformat(),'verified_preserved':verified,'removed':removed,'freed_bytes':sum(r['bytes'] for r in removed)}
    fd=os.open(marker,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
    with os.fdopen(fd,'w') as f:json.dump(receipt,f);f.flush();os.fsync(f.fileno())
    print('LIVE90_APPROVED_CLEANUP_OK files='+str(len(removed))+' freed_bytes='+str(receipt['freed_bytes']),flush=True)

if __name__=='__main__':main()
