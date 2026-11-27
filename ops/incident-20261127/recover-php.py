"""Bounded recovery of the PHP 8.3 pool blocked during the ENOSPC incident."""
import os, subprocess, time, re, datetime
def cmd(args,t=8):
    try:
        p=subprocess.run(args,capture_output=True,text=True,timeout=t)
        return p.returncode,p.stdout.strip()
    except (OSError,subprocess.TimeoutExpired): return 125,''
def probe(path,limit=4):
    rc,out=cmd(['curl','--noproxy','*','--resolve','stratedgepronos.fr:443:127.0.0.1','--max-time',str(limit),'-sS','-o','/dev/null','-w','%{http_code} %{time_total}','https://stratedgepronos.fr'+path+'?se_check='+str(time.time_ns())],limit+2)
    print('HTTP',path,rc,out,flush=True)
    return out.split()[0] if out and rc==0 else '000'
def backlog():
    _,out=cmd(['ss','-H','-lxn']);return max([int(v[2]) for line in out.splitlines() if 'fpm' in line and len(v:=line.split())>=4 and v[2].isdigit()]+[0])
def state():
    _,status=cmd(['systemctl','show','php8.3-fpm.service','--property=StatusText','--value']);print('POOL',status,'backlog',backlog(),flush=True);return status
def healthy(): return probe('/api/live90/ingest.php')=='405'
def webtests():
    probe('/');probe('/panel-x9k3m/slate/live90/');probe('/api/live90/ingest.php')
def main():
    if datetime.datetime.now(datetime.timezone.utc).date().isoformat()!='2026-11-27': raise SystemExit('expired')
    if healthy(): print('ALREADY_RESPONDING');webtests();return
    fs=os.statvfs('/');initial=state()
    if not (fs.f_bavail*fs.f_frsize>=2*1024**3 and cmd(['mysqladmin','--connect-timeout=3','--protocol=socket','ping'])[0]==0 and 'idle: 0' in initial and backlog()>0):
        print('NO_RESTART: guard conditions not met');webtests();return
    print('PHP_GRACEFUL_RELOAD',cmd(['systemctl','reload','php8.3-fpm.service'],12)[0],flush=True)
    for interval in (3,5,7):
        time.sleep(interval)
        if healthy():print('RECOVERED_AFTER_RELOAD');webtests();return
    # Reload may leave pre-existing, unresponsive workers waiting. Restart just this pool if still blocked.
    current=state()
    if not backlog() or cmd(['systemctl','is-active','php8.3-fpm.service'])[0]!=0:
        print('NO_RESTART: backlog cleared or unit no longer active');webtests();return
    print('PHP_RESTART_IF_STILL_BLOCKED',flush=True)
    print('PHP_RESTART_RC',cmd(['systemctl','restart','php8.3-fpm.service'],110)[0],flush=True)
    for interval in (1,4,7):
        time.sleep(interval)
        if healthy():break
    state();webtests()
main()
