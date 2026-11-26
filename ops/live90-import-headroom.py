"""Recover write headroom before git/PHP/nginx need the disk. Never touch user data."""
import argparse
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import time

ROOT = Path('/var/www/stratedgepronos.fr')
MIB = 1024**2
TARGET = 2048*MIB


def available(path):
    fs = os.statvfs(path)
    return fs.f_bavail * fs.f_frsize


def pressure_cleanup(recover=False):
    before=available(ROOT)
    print('IMPORT_SPACE_BEFORE',before,flush=True)
    if before >= 256*MIB or not recover:
        return before
    if os.geteuid()!=0: raise PermissionError('root required for package-cache maintenance')
    # The already-reviewed application only considers old, single-link, ordinary
    # download files with names made of hashes. Never call its Edge Finder cleanup.
    module=ROOT/'ops/storage-cleanup-20261107.py'
    spec=importlib.util.spec_from_file_location('old_cache_rules',module)
    rules=importlib.util.module_from_spec(spec);spec.loader.exec_module(rules)
    changes=subprocess.run(['snap','changes'],capture_output=True,text=True,timeout=20) if shutil.which('snap') else None
    idle=bool(changes and changes.returncode==0 and not any(
        len(line.split())>1 and line.split()[1] not in ('Done','Error','Undone','Abort')
        for line in changes.stdout.splitlines()[1:] if line.strip()))
    opened=set()
    for fd in Path('/proc').glob('[0-9]*/fd/*'):
        try: opened.add(os.readlink(fd))
        except OSError: pass
    cache=Path('/var/lib/snapd/cache')
    rows=rules.cache_plan(cache,time.time(),opened) if idle else []
    # Only claim actual disk space gained, not the sum of the nominal file sizes.
    for row in sorted(rows,key=lambda row:-row[1].st_size):
        if available(ROOT)>=TARGET: break
        rules.apply_cache([row])
    print('IMPORT_SNAP_CACHE',json.dumps({'idle':idle,'eligible':len(rows),'available':available(ROOT)}),flush=True)
    if available(ROOT)<TARGET:
        subprocess.run(['apt-get','-o','DPkg::Lock::Timeout=1','clean'],timeout=30,check=False)
    if available(ROOT)<TARGET:
        # pip owns its cache; do not clear installed packages or other user files.
        subprocess.run(['sudo','-n','-H','-u','alex','/usr/bin/python3','-m','pip','cache','purge'],timeout=90,check=False)
    final=available(ROOT)
    print('IMPORT_SPACE_AFTER',final,'gained',final-before,flush=True)
    return final


if __name__=='__main__':
    ap=argparse.ArgumentParser();ap.add_argument('--recover', action='store_true')
    args=ap.parse_args()
    pressure_cleanup(args.recover)
