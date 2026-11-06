#!/usr/bin/env python3
"""Read-only storage inventory: sizes and referencing paths, never file contents."""
import json,os,pathlib,re,shutil,subprocess,sys
root=pathlib.Path(sys.argv[1]).resolve()
print('STORAGE_DISK '+json.dumps(shutil.disk_usage(root)._asdict()),flush=True)
for base in (root,pathlib.Path('/var/lib'),pathlib.Path('/var/log'),pathlib.Path('/var/cache'),pathlib.Path('/var/backups'),pathlib.Path('/opt'),pathlib.Path('/home')):
    if not base.exists():continue
    run=subprocess.run(['du','-x','-B1','--max-depth=2',str(base)],capture_output=True,text=True,timeout=90)
    rows=[]
    for line in run.stdout.splitlines():
        size,_,path=line.partition('\t')
        if size.isdigit():rows.append({'bytes':int(size),'path':path})
    print('STORAGE_DIRECTORIES '+json.dumps(sorted(rows,key=lambda x:x['bytes'],reverse=True)[:35]),flush=True)
targets=[]
for parent in (root/'public_html/admin',root/'public_html/panel-x9k3m'):
    if parent.is_dir():
        for path in parent.iterdir():
            if 'edge-finder' in path.name:
                targets.append(str(path));print('STORAGE_EDGE '+json.dumps({'path':str(path),'symlink':path.is_symlink(),'resolved':str(path.resolve())}),flush=True)
                if not path.is_symlink():
                    listing=sorted(({'path':str(p),'bytes':p.stat().st_size} for p in path.rglob('*') if p.is_file() and not p.is_symlink()),key=lambda x:x['bytes'],reverse=True)
                    print('STORAGE_EDGE_FILES '+json.dumps(listing[:45]),flush=True)
for base in (root,pathlib.Path('/etc/cron.d'),pathlib.Path('/etc/systemd/system'),pathlib.Path('/opt')):
    for directory,dirs,files in os.walk(base,followlinks=False):
        dirs[:]=[x for x in dirs if x not in ('.git','node_modules','vendor','private-config','private-backups','data','logs') and 'edge-finder' not in x]
        for name in files:
            p=pathlib.Path(directory)/name
            try:
                if p.is_symlink() or p.stat().st_size>2_000_000 or p.suffix.lower() not in ('.php','.js','.json','.py','.sh','.service','.timer','.conf',''):continue
                contents=p.read_bytes()
                if b'edge-finder' in contents:
                    refs=sorted(set(x.decode('ascii') for x in re.findall(rb'edge-finder(?:-tennis-v4-preview)?/[A-Za-z0-9_./-]+',contents)))
                    print('STORAGE_EDGE_REFERENCE '+json.dumps({'path':str(p),'references':refs}),flush=True)
            except (OSError,ValueError):pass
print('STORAGE_INVENTORY_COMPLETE',flush=True)
