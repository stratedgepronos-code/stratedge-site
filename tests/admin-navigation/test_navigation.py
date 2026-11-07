"""Exercise the real PHP routing and sidebar with synthetic sessions, never a real login."""
import os
import pathlib
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
LIVE = '/panel-x9k3m/slate/live90/'


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


STUBS = '''<?php
namespace StratEdgePublic {
    class Offers {
        static function target($value) { return null; }
        static function safeRedirect($value) { return $value; }
    }
}
namespace {
$_SESSION=[];
$role=$_GET['as'] ?? 'guest';
$logged=$role!=='guest' && !isset($_GET['fresh']);
function isLoggedIn(){global $logged;return $logged;}
function isAdmin(){global $role;return isLoggedIn() && in_array($role,['super','admin','admin_tennis','admin_fun','admin_fun_sport'],true);}
function isSuperAdmin(){global $role;return isAdmin() && $role==='super';}
function getAdminRole(){global $role;return $role;}
function requireAdmin(){if(!isAdmin()){header('Location: /login.php');exit;}}
function verifyCsrf($token){return $token==='fixture';}
function loginMembre($email,$password){global $logged;if($password==='valid'){$logged=true;return ['success'=>true];}return ['success'=>false,'error'=>'invalid'];}
class FixtureQuery {function fetchColumn(){return 0;}}
class FixtureDB {function query($sql){return new FixtureQuery;}}
'''


class AdminNavigation(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory()
        directory = pathlib.Path(cls.tmp.name)
        login = (ROOT/'public_html/login.php').read_text().split('?>', 1)[0]
        for line in ("require_once __DIR__ . '/includes/auth.php';", "require_once __DIR__ . '/includes/packs-config.php';", "require_once __DIR__ . '/includes/public-site/Offers.php';"):
            assert line in login
            login = login.replace(line, '')
        index = (ROOT/'public_html/admin/index.php').read_text().split("$pageActive = 'index';", 1)[0]
        index = index.replace("require_once __DIR__ . '/../includes/auth.php';", '')
        (directory/'login.php').write_text(STUBS+login.removeprefix('<?php')+"echo 'LOGIN_FORM';}")
        (directory/'index.php').write_text(STUBS+index.removeprefix('<?php')+"echo 'OVERVIEW';}")
        sidebar = str(ROOT/'public_html/admin/sidebar.php').replace("'", "\\'")
        (directory/'nav.php').write_text(STUBS+"$db=new FixtureDB;$pageActive=$_GET['page']??'live90';$_SERVER['REQUEST_URI']=$_GET['uri']??'/panel-x9k3m/slate/live90/';require '"+sidebar+"';}")
        auth = (ROOT/'public_html/includes/auth.php').read_text()
        auth = auth.replace("require_once __DIR__ . '/db.php';", "define('ADMIN_EMAIL', 'owner@example.test');")
        (directory/'auth.php').write_text(auth)
        fixture_session = """
$_SESSION=[];
$role=$_GET['as']??'guest';
if($role!=='guest'){
    $_SESSION['membre_id']=1;
    $_SESSION['is_admin']=in_array($role,['super','admin','admin_tennis','admin_fun','admin_fun_sport'],true);
    $_SESSION['membre_email']=$role==='super'?'owner@example.test':'fixture@example.test';
    $_SESSION['admin_role']=$role;
}
$_SESSION['live90_csrf']='fixture';
"""
        for name, boundary in [('index.php', 'if(session_status()'), ('historique.php', 'if(session_status()'), ('download.php', '$root='), ('api.php', ' db90();')]:
            source = (ROOT/'public_html/admin/slate/live90'/name).read_text().split(boundary, 1)[0]
            source = source.replace("require_once dirname(__DIR__,3).'/includes/auth.php';", "require __DIR__.'/auth.php';"+fixture_session)
            source = source.replace("require_once dirname(__DIR__,3).'/api/live90/core.php';", "function reply90($data,$status){http_response_code($status);echo json_encode($data);exit;} function body90(){return json_decode(file_get_contents('php://input'),true)??[];}")
            if name=='api.php':
                source += " echo 'ACCESS_ALLOWED';}catch(Throwable $e){http_response_code(500);echo 'TEST_ERROR';}"
            else:
                source += "echo 'ACCESS_ALLOWED';"
            (directory/('live-'+name)).write_text(source)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            cls.port = sock.getsockname()[1]
        php = os.environ.get('PHP_BIN') or shutil.which('php')
        if not php:
            raise RuntimeError('PHP is required for routing checks')
        cls.server = subprocess.Popen([php, '-d', 'session.save_path='+str(directory), '-S', '127.0.0.1:'+str(cls.port), '-t', str(directory)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        cls.client = urllib.request.build_opener(NoRedirect)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', cls.port), timeout=.1):
                    break
            except OSError:
                time.sleep(.05)
        else:
            cls.server.terminate()
            raise RuntimeError('PHP test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.tmp.cleanup()

    def request(self, path, data=None, headers=None):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            request = urllib.request.Request('http://127.0.0.1:'+str(self.port)+path, data=body, headers=headers or {})
            response = self.client.open(request, timeout=5)
        except urllib.error.HTTPError as response_error:
            response = response_error
        with response:
            return response.status, response.headers.get('Location'), response.read().decode()

    def test_live_routes_use_real_admin_auth_for_all_roles(self):
        for page in ('index.php','historique.php','api.php','download.php'):
            for role in ('super','admin','admin_tennis','admin_fun','admin_fun_sport'):
                with self.subTest(page=page, role=role):
                    self.assertEqual(self.request('/live-'+page+'?as='+role), (200, None, 'ACCESS_ALLOWED'))
            for role in ('guest','member'):
                self.assertEqual(self.request('/live-'+page+'?as='+role)[:2], (302, '/login.php'))

    def test_live_api_keeps_csrf_protection_for_every_admin(self):
        for role in ('super','admin','admin_tennis','admin_fun','admin_fun_sport'):
            path='/live-api.php?as='+role
            self.assertEqual(self.request(path, {})[0], 403)
            self.assertEqual(self.request(path, {}, {'X-CSRF-Token':'wrong'})[0], 403)
            self.assertEqual(self.request(path, {}, {'X-CSRF-Token':'fixture'}), (200, None, 'ACCESS_ALLOWED'))
        self.assertEqual(self.request('/live-api.php?as=member', {}, {'X-CSRF-Token':'fixture'})[:2], (302, '/login.php'))

    def test_packaged_live_routes_match_deployed_routes(self):
        for page in ('index.php','historique.php','api.php','download.php'):
            self.assertEqual((ROOT/'live90/public/admin/slate/live90'/page).read_bytes(), (ROOT/'public_html/admin/slate/live90'/page).read_bytes())

    def test_owner_login_fresh_and_existing_session(self):
        self.assertEqual(self.request('/login.php?as=super')[:2], (302, LIVE))
        credentials = {'email':'fixture@example.test','password':'valid','csrf_token':'fixture'}
        self.assertEqual(self.request('/login.php?as=super&fresh=1', credentials)[:2], (302, LIVE))

    def test_other_roles_keep_their_authorized_landing(self):
        for role in ('admin','admin_tennis','admin_fun','admin_fun_sport','member'):
            target = '/dashboard.php' if role=='member' else '/panel-x9k3m/'
            self.assertEqual(self.request('/login.php?as='+role)[:2], (302, target))
            credentials = {'email':'fixture@example.test','password':'valid','csrf_token':'fixture'}
            self.assertEqual(self.request('/login.php?as='+role+'&fresh=1', credentials)[:2], (302, target))

    def test_failed_login_does_not_redirect(self):
        for password, token in (('wrong','fixture'),('valid','bad')):
            self.assertEqual(self.request('/login.php?as=super&fresh=1', {'email':'fixture@example.test','password':password,'csrf_token':token})[:2], (200, None))

    def test_admin_entry_and_explicit_overview(self):
        self.assertEqual(self.request('/index.php?as=super')[:2], (302, LIVE))
        self.assertEqual(self.request('/index.php?as=super&vue=overview'), (200, None, 'OVERVIEW'))
        self.assertEqual(self.request('/index.php?as=admin'), (200, None, 'OVERVIEW'))
        self.assertEqual(self.request('/index.php')[:2], (302, '/login.php'))

    def test_sidebar_removes_retired_tools_and_prioritizes_live(self):
        status, _, html = self.request('/nav.php?as=super')
        self.assertEqual(status, 200)
        for obsolete in ('href="/panel-x9k3m/slate/"', 'ht-tracker.php', 'montante-foot.php', 'montante-tennis.php'):
            self.assertNotIn(obsolete, html)
        nav = html.split('<nav aria-label="Navigation principale">')[1].split('</nav>')[0]
        self.assertEqual(nav.split('href="')[1].split('"')[0], LIVE)
        self.assertIn('href="'+LIVE+'historique.php"', nav)
        self.assertIn('index.php?vue=overview', nav)
        self.assertIn('football-lab/index.php', nav)
        self.assertIn('valider-bets.php', nav)
        self.assertNotIn('Fatal error', html)
        history = self.request('/nav.php?as=super&uri='+urllib.parse.quote(LIVE+'historique.php'))[2]
        self.assertIn('href="'+LIVE+'historique.php" class="nav-item active"', history)
        self.assertNotIn('href="'+LIVE+'" class="nav-item active"', history)
        for role in ('admin','admin_tennis','admin_fun','admin_fun_sport'):
            ordinary = self.request('/nav.php?as='+role)[2]
            self.assertIn('href="'+LIVE+'"', ordinary)
            self.assertIn('href="'+LIVE+'historique.php"', ordinary)
            self.assertIn('<span>Live</span>', ordinary)
            self.assertNotIn('football-lab/index.php', ordinary)
            self.assertNotIn('messagerie-interne.php', ordinary)
        for role in ('guest','member'):
            self.assertNotIn('href="'+LIVE, self.request('/nav.php?as='+role)[2])


if __name__ == '__main__':
    unittest.main()

