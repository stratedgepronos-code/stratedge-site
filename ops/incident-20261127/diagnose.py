import collections, concurrent.futures, json, os, pathlib, subprocess, time, sys
from pathlib import Path

def cmd(args,t=8):
    try:
        p=subprocess.run(args,capture_output=True,text=True,timeout=t)
        return p.returncode,p.stdout.strip()
    except (OSError,subprocess.TimeoutExpired): return 125,''
def report():
    f=os.statvfs('/');print('SPACE',f.f_bavail*f.f_frsize,flush=True)
    for s in ('mysql','php8.3-fpm','nginx','seuil90-live'):
        _,v=cmd(['systemctl','show',s,'-p','ActiveState','-p','SubState','-p','StatusText'])
        print('SERVICE',s,v.replace('\n',';')[:430],flush=True)
    p=cmd(['ss','-H','-lxn']);print('FPM_BACKLOG',[(a[2],a[3]) for line in p[1].splitlines() if 'fpm' in line and len(a:=line.split())>3],flush=True)
    print('MYSQL_PING',cmd(['mysqladmin','--connect-timeout=3','--protocol=socket','ping'],6),flush=True)
    rc,v=cmd(['mysql','--connect-timeout=3','--protocol=socket','-Nse',"SELECT COALESCE(STATE,''),COUNT(*),MAX(TIME) FROM INFORMATION_SCHEMA.PROCESSLIST WHERE COMMAND <> 'Sleep' GROUP BY STATE ORDER BY 2 DESC LIMIT 12;"],6)
    # State and counts only; never print a query, user or processlist details.
    print('MYSQL_STATES',rc,v[:500],flush=True)
    for name in ('/var/log/nginx/error.log','/var/log/php8.3-fpm.log','/var/log/mysql/error.log'):
        try:
            with open(name,'rb') as f:
                f.seek(0,2);f.seek(max(0,f.tell()-65536)); data=f.read().lower()
            print('LOG_COUNTS',Path(name).name,[(k,data.count(k.encode())) for k in ('no space left','timed out','max_children','error 28','disk is full','too many connections','connection refused') if k.encode() in data],flush=True)
        except OSError: pass
    def get(path):
        r,v=cmd(['curl','--noproxy','*','--resolve','stratedgepronos.fr:443:127.0.0.1','--max-time','7','-sS','-o','/dev/null','-w','%{http_code} %{time_total}','https://stratedgepronos.fr'+path+'?se_local='+str(time.time_ns())],9)
        print('LOCAL_HTTP',path,r,v,flush=True)
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as ex:list(ex.map(get,('/','/api/live90/ingest.php','/panel-x9k3m/slate/live90/')))
report()
