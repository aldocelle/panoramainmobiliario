"""Real HTTP test of JSON parser, cookies, CSRF, headers and routing.
Run: PANORAMA_PHP_COMMAND=php python3 backend/tests/http-smoke.py
Uses a temporary SQLite file; never connects to the configured production DB.
"""
import http.cookiejar,json,os,pathlib,socket,subprocess,tempfile,time,urllib.error,urllib.request,uuid
backend=pathlib.Path(__file__).resolve().parents[1]
name='http-test-'+uuid.uuid4().hex+'.sqlite'
with socket.socket() as sock:
    sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
env=dict(os.environ,APP_ENV='development',DB_DRIVER='sqlite',DB_PATH='var/'+name,ALLOW_PUBLIC_REGISTRATION='true',COOKIE_SECURE='false',APP_KEY='http-smoke-test-key-only',ALLOWED_ORIGINS='http://localhost:5173',MAIL_TRANSPORT='disabled')
php=os.environ.get('PANORAMA_PHP_COMMAND','php')
subprocess.run([php,'bin/migrate.php'],cwd=backend,env=env,check=True,capture_output=True)
with tempfile.TemporaryFile() as log:
    server=subprocess.Popen([php,'-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=backend,env=env,stdout=log,stderr=log)
    try:
        jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
        def request(method,path,body=None,headers=None):
            h={'Origin':'http://localhost:5173',**(headers or {})}
            data=None if body is None else (body if isinstance(body,bytes) else json.dumps(body).encode())
            if data is not None:h.setdefault('Content-Type','application/json')
            req=urllib.request.Request(f'http://127.0.0.1:{port}/api/v1'+path,data=data,headers=h,method=method)
            try:r=opener.open(req,timeout=5)
            except urllib.error.HTTPError as e:r=e
            return r.status,r.headers,r.read()
        for _ in range(100):
            try:
                if request('GET','/health')[0]==200:break
            except OSError:time.sleep(.02)
        else:raise RuntimeError('HTTP server did not start')
        assert request('POST','/auth/login',b'{bad')[0]==400
        assert request('POST','/auth/login',b'[]')[0]==400
        assert request('POST','/auth/login',b'{}',{'Content-Type':'text/plain'})[0]==415
        assert request('OPTIONS','/properties',headers={'Origin':'https://evil.example'})[0]==403
        status,headers,body=request('POST','/auth/register',{'name':'HTTP Test','email':'http@example.com','password':'HTTP test password 123!','organizationName':'HTTP Test'})
        assert status==201,(status,body)
        assert 'HttpOnly' in headers['Set-Cookie'] and 'SameSite=Lax' in headers['Set-Cookie']
        assert headers['X-Content-Type-Options']=='nosniff'
        assert request('GET','/auth/session')[0]==200
        assert request('POST','/properties',{})[0]==403
        csrf=json.loads(body)['csrfToken']
        assert request('POST','/properties',{}, {'X-CSRF-Token':csrf})[0]==422
        assert request('POST','/auth/logout',{}, {'X-CSRF-Token':csrf})[0]==204
        assert request('GET','/auth/session')[0]==401
        print('PASS HTTP real: JSON, sesión, cookie, CSRF, origen y cabeceras.')
    finally:
        server.terminate();server.wait(timeout=5)
        for path in (backend/'var').glob(name+'*'):path.unlink()
