"""Evidence contracts shared by context worker and signal engine."""
import datetime as dt, json, urllib.parse
UTC=dt.timezone.utc
TOPICS=('identity','lineup_h','lineup_a','absences_h','absences_a','schedule_h','schedule_a','weather')

def parse(t):
    try:
        d=dt.datetime.fromisoformat(t.replace('Z','+00:00'))
        return d if d.tzinfo else None
    except (ValueError,TypeError,AttributeError): return None

def url_ok(url):
    if not isinstance(url,str):return False
    try:
        u=urllib.parse.urlparse(url)
        return u.scheme=='https' and bool(u.hostname) and not u.username and not u.password and '.' in u.hostname and len(url)<2000
    except ValueError:return False

def source_list(response):
    # Only URLs actually cited by the research output, not model-generated URLs in free text.
    found={}
    for item in response.get('output',[]):
        for part in item.get('content',[]):
            for a in part.get('annotations',[]):
                if a.get('type')=='url_citation' and url_ok(a.get('url')):found[a['url']]={'url':a['url'],'title':a.get('title') or a['url']}
    return list(found.values())

def validate_report(report,sources,asof):
    if not isinstance(report,dict):raise ValueError('Objet de contexte attendu')
    allowed={s['url'] for s in sources};topics=report.get('topics',[])
    if len(topics)!=len(TOPICS) or {x.get('topic') for x in topics}!=set(TOPICS):raise ValueError('Rubriques contextuelles invalides')
    for t in topics:
        if t.get('status') not in ('official','reported','probable','unknown','conflict'):raise ValueError('Statut invalide')
        if not isinstance(t.get('text'),str) or not 1<=len(t['text'])<=2000:raise ValueError('Texte de rubrique invalide')
        urls=t.get('source_urls',[])
        if not isinstance(urls,list) or any(u not in allowed for u in urls):raise ValueError('Source non citée par la recherche')
        if t['status']!='unknown' and not urls:raise ValueError('Fait sans source')
        published=parse(t.get('published_at')) if t.get('published_at') else None
        if t.get('published_at') and (not published or published>asof):raise ValueError('Publication future ou invalide')
        if published and asof-published>dt.timedelta(days=7):t['status']='unknown';t['text']='Source trop ancienne : '+t['text']
    for k in ('contraindications','live_checks','unknowns'):
        if not isinstance(report.get(k),list) or len(report[k])>10 or any(not isinstance(x,str) or len(x)>1200 for x in report[k]):raise ValueError('Liste contextuelle invalide')
    if not isinstance(report.get('thesis'),str) or len(report['thesis'])>1500:raise ValueError('Scénario invalide')
    return report

def gate(record,prematch,now):
    if not record:return False,'Contexte avant-match absent'
    if record['prematch_id']!=prematch['id']:return False,'Contexte à recalculer après modification du profil'
    asof=parse(record['created_at']); ko=parse(prematch['kickoff'])
    if not asof or not ko or asof>=ko or asof>now:return False,'Contexte produit après le coup d’envoi'
    if now-asof>dt.timedelta(hours=4) or ko-asof>dt.timedelta(minutes=75):return False,'Actualisation des compositions avant-match manquante'
    if record['status']!='ready':return False,'Contexte incomplet ou contradictoire'
    return True,'Contexte sourcé disponible (lecture IA, à vérifier)'

def completeness(report):
    topics={t['topic']:t for t in report['topics']}
    if report['contraindications'] or any(t['status']=='conflict' for t in topics.values()):return 'blocked'
    if any(t['status'] in ('unknown','probable') for t in topics.values()):return 'partial'
    if any(topics[k]['status']!='official' for k in ('identity','lineup_h','lineup_a')):return 'partial'
    return 'ready'

def live_key(row):
    # One analysis per score and 5-minute slice, capped separately per match.
    s=row['score'];return f"{s['h']}-{s['a']}:{int(row['minute'])//5}"


def quota_pause(c, now):
    """Persisted circuit breaker: only an explicit insufficient_quota response."""
    row=c.execute("SELECT created_at FROM ai_calls WHERE state='insufficient_quota' ORDER BY id DESC LIMIT 1").fetchone()
    at=parse(row['created_at']) if row else None
    return bool(at and 0 <= (now-at).total_seconds() < 900)
