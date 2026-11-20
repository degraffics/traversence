import re, subprocess, sys, urllib.request
LOG='/var/lib/mysql/sql_errors.log'
URLS=sys.argv[1:]
def sql(q):
    r=subprocess.run(['mysql','-uroot','tvh','-e',q],capture_output=True,text=True); return r.stderr.strip()
def typ(c):
    c=c.lower()
    if c in('lat','lon','latitude','longitude','lng') or c.endswith('_lat') or c.endswith('_lon'): return 'DECIMAL(10,6) NULL'
    if c=='id' or c.endswith('_id') or c in('n','sort','count','views','is_suppressed','hidden','enabled','active','position','level','score','priority','year') or c.startswith('is_') or c.startswith('has_') or c.endswith('_count'): return 'INT NULL'
    if c.endswith('_at') or c in('at','day','created','updated','starts','ends','date'): return 'DATETIME NULL'
    if c in('meta_value','body','summary','content','description','notes','data','v','value','json','payload','text','html'): return 'MEDIUMTEXT NULL'
    return 'VARCHAR(191) NULL'
def tables(q):
    m={}
    for t,a in re.findall(r'(?:FROM|JOIN|UPDATE|INTO)\s+`?(?:tvh\.)?`?(\w+)`?(?:\s+(?:AS\s+)?(\w+))?',q,re.I):
        m.setdefault(t,t); 
        if a and a.upper() not in('WHERE','ON','LEFT','JOIN','INNER','SET','VALUES','GROUP','ORDER','LIMIT','USING','JOIN','CROSS','RIGHT','SELECT','UNION','AND','OR','FORCE','USE','STRAIGHT_JOIN','NATURAL'): m[a]=t
    return m
for rnd in range(40):
    open(LOG,'w').close()
    for u in URLS:
        try: urllib.request.urlopen(urllib.request.Request('http://127.0.0.1:8790'+u,headers={'Cookie':'traversence_session='+__import__('os').environ.get('SID','harness0000000000000000000000'),'User-Agent':'Mozilla/5.0 Chrome/130','Accept':'text/html'}),timeout=90).read()
        except Exception as e: pass
    import time; time.sleep(1.5); txt=open(LOG,errors='replace').read()
    did=0
    for t in set(re.findall(r"ERROR 1146: Table 'tvh\.(\w+)' doesn't exist",txt)):
        sql(f"CREATE TABLE IF NOT EXISTS `{t}` (id INT AUTO_INCREMENT PRIMARY KEY) DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci"); did+=1; print('table',t)
    for line in re.split(r'\n(?=\d{4}-\d\d-\d\d \d)',txt):
        m=re.search(r"ERROR 1054: Unknown column '([\w.]+)' in '([^']*)' : (.*)",line,re.S)
        if not m: continue
        col,q=m.group(1),m.group(3); tm=tables(q)
        if '.' in col: a,c=col.split('.',1); t=tm.get(a) or {'e':'entities','c':'categories','g':'categories','l':'landmarks','mc':'micro_clusters','j':'journeys','h':'hubs','gh':'geo_hubs','u':'users','lm':'landmarks'}.get(a,a)
        else:
            c=col; ts=[v for v in dict.fromkeys(tm.values())]
            if not ts: continue
            t=ts[0]
        e=sql(f"ALTER TABLE `{t}` ADD COLUMN `{c}` {typ(c)}")
        if not e: did+=1; print("col",t,c)
        else: print("ERR",t,c,e[:120])
    other=set(re.findall(r'ERROR (\d+)',txt))-{'1146','1054'}
    if not did:
        print('done; other errors:',other); print('\n'.join(l[:300] for l in txt.split('\n') if re.search(r'ERROR (?!1146|1054)',l))[:3000]); break
