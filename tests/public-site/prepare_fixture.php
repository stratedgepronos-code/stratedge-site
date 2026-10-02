<?php
if(PHP_SAPI!=='cli')exit;
$repo=dirname(__DIR__,2);$target=$argv[1]??'';if(!$target||is_dir($target))throw new RuntimeException('Use a new fixture directory');mkdir($target,0700,true);
function fixture_copy(string $from,string $to):void{if(is_dir($from)){if(!is_dir($to))mkdir($to,0700,true);foreach(scandir($from) as $f)if($f!=='.'&&$f!=='..')fixture_copy($from.'/'.$f,$to.'/'.$f);}else{if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);copy($from,$to);}}
foreach(['index.php','journal.php','article.php','methode.php','historique.php','offres.php','decouvrir.php','journal-sitemap.php','offre.php','offre-tennis.php','offre-fun.php','offres-multisports.php','souscrire.php','packs-daily.php','login.php','register.php','includes/packs-config.php','includes/footer-main.php','includes/footer-legal.php','includes/public-site','admin/journal.php','admin/sidebar.php','admin/design'] as $file)fixture_copy($repo.'/public_html/'.$file,$target.'/'.$file);
symlink($repo.'/public_html/assets',$target.'/assets');symlink($target.'/admin',$target.'/panel-x9k3m');
file_put_contents($target.'/includes/auth.php', <<<'PHP'
<?php
session_start();
function getDB(){static $db;if(!$db){$db=new PDO('sqlite:'.dirname(__DIR__).'/fixture.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}return $db;}
function isLoggedIn(){return isset($_SESSION['membre_id']);}function isAdmin(){return ($_SERVER['HTTP_X_TEST_ROLE']??'')==='admin'||isSuperAdmin();}function isSuperAdmin(){return ($_SERVER['HTTP_X_TEST_ROLE']??'')==='super';}
function requireAdmin(){if(!isAdmin()){http_response_code(403);exit('Fixture denied');}}function requireSuperAdmin(){if(!isSuperAdmin()){http_response_code(403);exit('Fixture denied');}}
function requireLogin(){if(!isLoggedIn()){header('Location: /login.php');exit;}}function getAdminRole(){return isSuperAdmin()?'superadmin':'admin_foot';}
function csrfToken(){return 'fixture-csrf';}function verifyCsrf($v){return $v==='fixture-csrf';}function clean($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function registerMembre($nom,$email,$pw,$opt=0,$dob=null){getDB()->exec("INSERT INTO fixture_accounts (nom) VALUES ('created')");return ['success'=>true,'id'=>1];}
function loginMembre($email,$pw){$_SESSION['membre_id']=1;return ['success'=>true];}
PHP);
file_put_contents($target.'/includes/mailer.php','<?php function emailBienvenue(...$args) {}');mkdir($target.'/includes/antibot');file_put_contents($target.'/includes/antibot/AntiBot.php', <<<'PHP'
<?php
class AntiBot {function __construct($db){}function validate($post,$ip){return ($post['antibot']??'')==='pass';}function getErrors(){return [];}function render(){return '';}static function formFields(){return '';}static function turnstileWidget(){return '';}}
PHP);
// Only the fixture replaces the widget rendering; production anti-bot stays intact.
$p=$target.'/register.php';$source=file_get_contents($p);$source=preg_replace('/\$abWidget\s*=.*?;/s',"\$abWidget = '';",$source);file_put_contents($p,$source);
$db=new PDO('sqlite:'.$target.'/fixture.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
require $repo.'/public_html/includes/public-site/Journal.php';$j=new \StratEdgePublic\Journal($db);$j->install();
foreach(require $repo.'/public_html/includes/public-site/seeds.php' as $seed)$j->save($seed,null,0,'published');
$draft=['title'=>'BROUILLON PRIVÉ DE RECETTE','slug'=>'private-draft','summary'=>'Cette publication ne doit pas sortir dans le journal public.','body'=>'Texte privé de recette qui ne doit jamais être visible sur une route publique.'];$j->save($draft);$draft['slug']='future-post';$draft['title']='FUTUR NON PUBLIÉ';$j->save($draft,null,0,'published',gmdate('Y-m-d\TH:i:s\Z',time()+86400));
$db->exec('CREATE TABLE fixture_accounts (nom TEXT)');$db->exec('CREATE TABLE bets (id INTEGER PRIMARY KEY,titre TEXT,cote TEXT,resultat TEXT,date_post TEXT,date_resultat TEXT,categorie TEXT,posted_by_role TEXT)');$q=$db->prepare('INSERT INTO bets VALUES (?,?,?,?,?,?,?,?)');
foreach(['gagne','perdu','annule'] as $i=>$r)$q->execute([$i+1,'Rencontre fictive de recette '.($i+1),$i===1?null:1.8,$r,date('Y-m-d H:i:s',time()-86400),date('Y-m-d H:i:s'),'multi','superadmin']);
echo $target;
