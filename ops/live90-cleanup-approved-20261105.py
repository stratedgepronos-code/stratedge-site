#!/usr/bin/env python3
"""One-off removal of eight archives explicitly approved on 5 November 2026.

Preserves the active database and all sixteen newer archives. No retention rule.
"""
import argparse, datetime as dt, importlib.util, json, os
from pathlib import Path

ROOT=Path('/var/backups')
APPROVAL='owner-approved-2026-11-05-eight-oct22-oct23'
APPROVED={'RtI9lq':523008418,'kaRBxm':530601673,'lo4kNC':568011994,'F7cTIM':569075160,
          'g9aWZW':575017397,'TRyXl8':576806801,'zs5Nn1':592370791,'QWpb3M':609746174}
PRESERVED={'2JIfpg':792076393,'d87H4K':1021771570}

def module(name,filename):
    spec=importlib.util.spec_from_file_location(name,Path(__file__).with_name(filename))
    m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m

BASE=module('cleanup_nov01','live90-cleanup-approved-20261101.py')
BACKUP=module('backup_nov05','live90-stream-backup.py')

def verify_preserved(root):
    verified=[]
    for suffix,size in PRESERVED.items():
        path=root/('stratedge-live90-v4.'+suffix)/'live90.sql.gz'
        if BASE.regular(path).st_size!=size:raise ValueError('Preserved backup changed: '+str(path))
        manifest=BACKUP.manifest_path(path);BASE.regular(manifest)
        record=json.loads(manifest.read_text())
        if not all(record.get('tables',{}).get(t,0)>0 for t in ('samples','cycles')):raise ValueError('Incomplete backup tables')
        print('LIVE90_NOV05_VERIFY '+str(path),flush=True)
        BACKUP.verify(path)
        verified.append(str(path))
    return verified

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--approval');args=parser.parse_args()
    marker=ROOT/'.stratedge-cleanup-20261105.json'
    if marker.exists() or marker.is_symlink():
        BASE.regular(marker)
        if json.loads(marker.read_text()).get('approval')!=APPROVAL:raise ValueError('Unexpected cleanup receipt')
        print('LIVE90_NOV05_CLEANUP_ALREADY_DONE',flush=True);return
    rows=BASE.plan(ROOT,APPROVED)
    print('LIVE90_NOV05_PLAN files='+str(len(rows))+' bytes='+str(sum(i.st_size for _,i in rows)),flush=True)
    if args.approval!=APPROVAL:print('DRY_RUN no files deleted',flush=True);return
    if os.geteuid()!=0:raise PermissionError('Root required')
    verified=verify_preserved(ROOT) if rows else []
    removed=BASE.apply(rows)
    receipt={'approval':APPROVAL,'completed_at':dt.datetime.now(dt.timezone.utc).isoformat(),
             'verified_preserved':verified,'removed':removed,'freed_bytes':sum(r['bytes'] for r in removed)}
    fd=os.open(marker,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
    with os.fdopen(fd,'w') as f:json.dump(receipt,f);f.flush();os.fsync(f.fileno())
    print('LIVE90_NOV05_CLEANUP_OK files='+str(len(removed))+' freed_bytes='+str(receipt['freed_bytes']),flush=True)

if __name__=='__main__':main()
