"""HTTP integration checks against an isolated SQLite database; stdlib only."""
from pathlib import Path
import http.cookiejar
import os
import re
import secrets
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = ROOT / 'runtime/php.exe'
CMD = [str(PHP), '-c', str(ROOT/'runtime/php.ini'), '-d', 'extension_dir='+str(ROOT/'runtime/ext')]
checks = 0

def check(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1
    print('PASS:', label)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class Client:
    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())
    def call(self, path, data=None, origin=None):
        headers = {}
        if data is not None:
            headers['Origin'] = origin or self.base
        request = urllib.request.Request(self.base+path, data=None if data is None else urllib.parse.urlencode(data).encode(), headers=headers)
        try:
            response = self.opener.open(request, timeout=15)
        except urllib.error.HTTPError as exc:
            response = exc
        return response.code, response.read().decode(), response.headers
    def csrf(self, path):
        status, body, _ = self.call(path)
        assert status == 200, (path, status)
        return re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]
    def cookie(self):
        return next(c for c in self.jar if c.name == 'santos_session')
    def login(self, email, password, remember=False):
        return self.call('/login.php', {'csrf':self.csrf('/login.php'), 'email':email, 'password':password, 'remember':'1' if remember else ''})

def run():
    for path in ROOT.rglob('*.php'):
        if 'runtime' in path.parts or '.tmp' in path.parts:
            continue
        result = subprocess.run(CMD+['-l',str(path)],capture_output=True,text=True)
        check(result.returncode == 0, 'PHP syntax: '+str(path.relative_to(ROOT)))
    temp_root=ROOT/'tests/.tmp'
    temp_root.mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(dir=temp_root) as tmp:
        env=dict(os.environ,APP_ENV='local',SANTOS_DATA_DIR=tmp)
        subprocess.run(CMD+['bin/init.php'],cwd=ROOT,env=env,check=True,capture_output=True)
        setup=subprocess.run(CMD+['bin/setup-code.php'],cwd=ROOT,env=env,check=True,capture_output=True,text=True).stdout
        setup_code=re.search(r'\b[a-f0-9]{32}\b',setup)[0]
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        base=f'http://127.0.0.1:{port}'
        with open(Path(tmp)/'server.log','w',encoding='utf-8') as log:
            server=subprocess.Popen(CMD+['-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log)
            db=None
            try:
                admin=Client(base)
                for _ in range(50):
                    try:
                        admin.call('/');break
                    except urllib.error.URLError:
                        time.sleep(.1)
                else:
                    raise RuntimeError('PHP server did not start')
                status, body, _=admin.call('/')
                check(status==200 and 'Entrar' in body and 'Efeitos <b>' not in body and 'Site oficial <span>' not in body,'PHP home preserves latest design and adds login')
                check(admin.call('/admin.php')[0]==303,'Anonymous admin access redirects to login')
                check(admin.call('/conta.php')[0]==303,'Anonymous account access redirects to login')
                check(admin.call('/var/santos.sqlite')[0]==404 and admin.call('/src/bootstrap.php')[0]==404,'Database and source are not served')
                check(admin.call('/.htaccess')[0]==404,'Dotfiles are not served')
                check(admin.call('/sair.php')[0]==405,'Logout rejects GET')
                secret='Santos!'+secrets.token_hex(10)
                csrf=admin.csrf('/configurar.php')
                payload=dict(csrf=csrf,code='wrong',name='Admin de Teste',email='admin@example.test',password=secret,confirmation=secret)
                status,body,_=admin.call('/configurar.php',payload)
                
                check(status==200 and 'Código inválido' in body,'First-admin setup rejects wrong installation code')
                payload['code']=setup_code
                status,_,headers=admin.call('/configurar.php',payload)
                check(status==303 and headers['Location'].startswith('/admin.php'),'First admin can be configured with one-time code')
                check(admin.call('/configurar.php')[0]==404,'First-admin setup is disabled after completion')
                check(admin.call('/admin.php')[0]==200,'Administrator can access dashboard')
                db=sqlite3.connect(Path(tmp)/'santos.sqlite')
                check(db.execute('SELECT COUNT(*) FROM users').fetchone()[0]==1,'Only one setup administrator exists')
                check(db.execute('SELECT password_hash FROM users').fetchone()[0]!=secret,'Passwords are hashed, never stored in plaintext')
                check(not (Path(tmp)/'setup.json').exists(),'Setup-code hash removed after successful setup')
                regular=Client(base);password='Torcedor!'+secrets.token_hex(10)
                status,body,_=regular.call('/cadastro.php',dict(csrf=regular.csrf('/cadastro.php'),name='Teste Torcedor',email='fan@example.test',password='short',confirmation='short'))
                check(status==200 and 'pelo menos 12' in body,'Registration rejects weak password')
                status,_,_=regular.call('/cadastro.php',dict(csrf=regular.csrf('/cadastro.php'),name='Teste Torcedor',email='fan@example.test',password=password,confirmation=password,role='admin'))
                check(status==303,'Registration logs in a new supporter')
                check(db.execute("SELECT role FROM users WHERE email='fan@example.test'").fetchone()[0]=='user','Forged admin role is ignored during registration')
                check(regular.call('/admin.php')[0]==403,'Supporter cannot access admin page')
                status,body,_=regular.call('/conta.php',dict(csrf='bad',action='profile',name='Changed'))
                check(status==403 and 'sessão expirou' in body,'CSRF token is required')
                status,body,_=regular.call('/conta.php',dict(csrf=regular.csrf('/conta.php'),action='profile',name='Changed'),origin='https://attacker.invalid')
                check(status==403 and 'Origem' in body,'Cross-origin form mutation rejected')
                check(db.execute("SELECT name FROM users WHERE email='fan@example.test'").fetchone()[0]=='Teste Torcedor','Rejected forms do not modify account')
                status,_,_=regular.call('/conta.php',dict(csrf=regular.csrf('/conta.php'),action='profile',name='<b>Torcedor</b>'))
                check(status==303 and '&lt;b&gt;Torcedor&lt;/b&gt;' in regular.call('/conta.php')[1],'Profile updates are HTML-escaped')
                csrf=regular.csrf('/conta.php');old_cookie=regular.cookie().value
                check(regular.call('/sair.php',dict(csrf=csrf))[0]==303,'Logout succeeds with CSRF')
                replay=Client(base)
                request=urllib.request.Request(base+'/conta.php',headers={'Cookie':'santos_session='+old_cookie})
                try: replay.opener.open(request);raise AssertionError('Logged-out cookie accepted')
                except urllib.error.HTTPError as exc: check(exc.code==303,'Logged-out token cannot be replayed')
                status,body,_=regular.login('fan@example.test','incorrect-password')
                check(status==200 and 'E-mail ou senha incorretos' in body,'Wrong password rejected')
                anon_cookie=regular.cookie().value
                status,_,headers=regular.login('fan@example.test',password,True)
                check(status==303,'Remember-me login succeeds')
                cookie=regular.cookie()
                check(cookie.value!=anon_cookie,'Login rotates anonymous session identifier')
                check(cookie.expires and cookie.expires>time.time()+29*86400,'Remember-me cookie lasts 30 days')
                check('HttpOnly' in headers.get('Set-Cookie','') and 'SameSite=Lax' in headers.get('Set-Cookie',''),'Authentication cookie is HttpOnly and SameSite')
                check(db.execute('SELECT COUNT(*) FROM sessions WHERE token_hash=?',(cookie.value,)).fetchone()[0]==0,'Raw session tokens are not stored in database')
                saved=Path(tmp)/'cookies.txt';diskjar=http.cookiejar.MozillaCookieJar(str(saved))
                for c in regular.jar: diskjar.set_cookie(c)
                diskjar.save()
                resumed=Client(base);loaded=http.cookiejar.MozillaCookieJar(str(saved));loaded.load()
                for c in loaded:resumed.jar.set_cookie(c)
                check(resumed.call('/conta.php')[0]==200,'Remember-me survives browser cookie-jar restart')
                other=Client(base);check(other.login('fan@example.test',password)[0]==303,'Second device can sign in')
                check(other.cookie().expires is None,'Login without remember-me uses session cookie')
                new_password='NovaSenha!'+secrets.token_hex(9)
                status,_,_=resumed.call('/conta.php',dict(csrf=resumed.csrf('/conta.php'),action='password',current_password=password,password=new_password,confirmation=new_password))
                check(status==303,'Password change succeeds with current password')
                check(other.call('/conta.php')[0]==303 and regular.call('/conta.php')[0]==303,'Password change revokes other sessions, including remembered login')
                check(resumed.call('/conta.php')[0]==200,'Password changer retains a new valid session')
                fan_id=db.execute("SELECT id FROM users WHERE email='fan@example.test'").fetchone()[0]
                status,_,_=admin.call('/admin.php',dict(csrf=admin.csrf('/admin.php'),user_id=fan_id,action='disable',admin_password=secret))
                check(status==303 and resumed.call('/conta.php')[0]==303,'Administrator blocking revokes active sessions')
                check(other.login('fan@example.test',new_password)[0]==200,'Blocked account cannot log in')
                for action in ['enable','promote']:
                    check(admin.call('/admin.php',dict(csrf=admin.csrf('/admin.php'),user_id=fan_id,action=action,admin_password=secret))[0]==303,'Admin action: '+action)
                check(other.login('fan@example.test',new_password)[0]==303 and other.call('/admin.php')[0]==200,'Promoted user can access admin dashboard after new login')
                check(admin.call('/admin.php',dict(csrf=admin.csrf('/admin.php'),user_id=fan_id,action='demote',admin_password=secret))[0]==303,'Administrator can revoke administrator role')
                check(other.call('/admin.php')[0]==303,'Demotion invalidates existing administrator session')
                status,body,_=admin.call('/admin.php',dict(csrf=admin.csrf('/admin.php'),user_id=1,action='demote',admin_password=secret))
                check(status==200 and 'própria conta' in body,'Admin cannot remove own admin access')
                check('fan@example.test' in admin.call('/admin.php?q=Torcedor')[1],'Admin search returns matching account')
                check(other.login('fan@example.test',new_password)[0]==303,'Supporter can sign in after demotion')
                check(other.call('/conta.php',dict(csrf=other.csrf('/conta.php'),action='sessions',current_password_sessions=new_password))[0]==303 and other.call('/conta.php')[0]==303,'Sign out all devices invalidates current session')
                guess=Client(base)
                results=[guess.login('missing@example.test','totally-wrong-pass')[0] for _ in range(9)]
                check(results[-1]==429,'Repeated login attempts are rate limited')
                check(db.execute('SELECT COUNT(*) FROM audit_log').fetchone()[0]>=10,'Account and administrator activity is audited')
            finally:
                if db is not None: db.close()
                server.terminate();server.wait(timeout=10)
        errors=(Path(tmp)/'server.log').read_text(encoding='utf-8')
        check('Fatal error' not in errors and 'Warning:' not in errors,'No PHP warnings or fatal errors during HTTP tests')
    print(f'\n{checks} checks passed. Test data isolated from the real local installation.')

if __name__=='__main__':run()
