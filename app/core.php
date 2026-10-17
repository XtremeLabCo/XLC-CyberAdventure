<?php
declare(strict_types=1);

const PROJECT_ROOT = __DIR__ . '/..';

function conf(): array {
    static $config = null;
    if ($config === null) {
        $path = PROJECT_ROOT . '/config/local.php';
        $config = is_file($path) ? require $path : (require PROJECT_ROOT . '/config/local.example.php');
        // Allows a controlled test database choice only from the server/CLI environment.
        if (($testDb=getenv('SIBER_MACERA_DB_NAME'))!==false && preg_match('/^[a-zA-Z0-9_]{1,64}$/',$testDb)) $config['db_name']=$testDb;
    }
    return $config;
}
function cfg(string $key, mixed $default = null): mixed { return conf()[$key] ?? $default; }
function db(): PDO {
    static $db = null;
    if ($db instanceof PDO) return $db;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', cfg('db_host'), cfg('db_port','3306'), cfg('db_name'));
    $db = new PDO($dsn, (string) cfg('db_user'), (string) cfg('db_password'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $db;
}
function boot_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    session_name((string)cfg('session_name','cyber_adventure_session'));
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $path): never { header('Location: '.$path, true, 303); exit; }
function csrf(): string { if (!isset($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24)); return (string)$_SESSION['_csrf']; }
function check_csrf(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf(), $token)) throw new UserException('The request could not be verified. Refresh the page and try again.', 419);
}
class UserException extends RuntimeException {}
function jreply(array $data, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function input_json(): array {
    $raw = file_get_contents('php://input');
    $value = json_decode($raw ?: '{}',true);
    if (!is_array($value)) throw new UserException('Invalid request data.',400);
    return $value;
}
function flash(string $message, string $kind='info'): void { $_SESSION['flash']=['message'=>$message,'kind'=>$kind]; }
function read_flash(): ?array { $msg=$_SESSION['flash']??null; unset($_SESSION['flash']); return is_array($msg)?$msg:null; }
function login_player(int $id): void { session_regenerate_id(true); $_SESSION['player_id']=$id; unset($_SESSION['admin_id']); }
function login_admin(int $id): void { session_regenerate_id(true); $_SESSION['admin_id']=$id; unset($_SESSION['player_id']); }
function player(): ?array {
    if (empty($_SESSION['player_id'])) return null;
    $query=db()->prepare('SELECT p.*, ag.label AS age_label, ag.slug AS age_slug FROM players p JOIN age_groups ag ON ag.id=p.age_group_id WHERE p.id=? AND p.status=\'active\' LIMIT 1');
    $query->execute([(int)$_SESSION['player_id']]);$row=$query->fetch();if(!$row)unset($_SESSION['player_id']);return $row?:null;
}
function admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    $query=db()->prepare('SELECT id,username,role FROM admins WHERE id=? AND active=1 LIMIT 1');
    $query->execute([(int)$_SESSION['admin_id']]); return $query->fetch() ?: null;
}
function need_player(): array { $p=player(); if (!$p) go('/login'); return $p; }
function need_admin(): array { $a=admin(); if (!$a) go('/admin/login'); return $a; }
function setting(string $key,string $default=''): string {
    try { $q=db()->prepare('SELECT value FROM settings WHERE `key`=?'); $q->execute([$key]);$v=$q->fetchColumn();return $v===false?$default:(string)$v; }
    catch(Throwable $e){ return $default; }
}
function games(): array { return db()->query('SELECT * FROM games ORDER BY position ASC')->fetchAll(); }
function game_slug(string $slug): ?array {
    $q=db()->prepare('SELECT * FROM games WHERE slug=? AND enabled=1 LIMIT 1');$q->execute([$slug]);return $q->fetch()?:null;
}
function age_groups():array { return db()->query('SELECT * FROM age_groups WHERE enabled=1 ORDER BY position')->fetchAll(); }
function user_key(string $name): string {
    $map=['İ'=>'i','I'=>'i','ı'=>'i','Ğ'=>'g','ğ'=>'g','Ü'=>'u','ü'=>'u','Ş'=>'s','ş'=>'s','Ö'=>'o','ö'=>'o','Ç'=>'c','ç'=>'c'];
    return strtolower(strtr(trim($name),$map));
}
function encrypt_recovery_code(string $code): string {
    if(!preg_match('/^\d{4}$/',$code)) throw new InvalidArgumentException('The login code must be four digits.');
    if(!function_exists('openssl_encrypt')) throw new RuntimeException('Login code encryption support is not available.');
    $key=hash('sha256',(string)cfg('recovery_pepper'),true);$iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($code,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'siber-macera-recovery-v1');
    if($cipher===false) throw new RuntimeException('The login code could not be encrypted.');
    return 'v1.'.rtrim(strtr(base64_encode($iv.$tag.$cipher),'+/','-_'),'=');
}
function decrypt_recovery_code(?string $encrypted): ?string {
    if(!$encrypted||!str_starts_with($encrypted,'v1.')||!function_exists('openssl_decrypt')) return null;
    $data=substr($encrypted,3);$data=strtr($data,'-_','+/');$data.=str_repeat('=',(4-strlen($data)%4)%4);$raw=base64_decode($data,true);
    if($raw===false||strlen($raw)<29)return null;
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',(string)cfg('recovery_pepper'),true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'siber-macera-recovery-v1');
    return is_string($plain)&&preg_match('/^\d{4}$/',$plain)?$plain:null;
}
function recovery_values(string $username): array {
    $code=str_pad((string)random_int(0,9999),4,'0',STR_PAD_LEFT);$key=user_key($username);
    return [$code,hash_hmac('sha256',$key.':'.$code,(string)cfg('recovery_pepper')),encrypt_recovery_code($code)];
}
function valid_music_url(string $url): bool {
    if(preg_match('#^/assets/sounds/[a-zA-Z0-9._-]+\.(?:mp3|ogg|wav)$#',$url))return true;
    if(strlen($url)>1000||filter_var($url,FILTER_VALIDATE_URL)===false)return false;
    $parts=parse_url($url);return ($parts['scheme']??'')==='https'&&in_array(strtolower(pathinfo($parts['path']??'',PATHINFO_EXTENSION)),['mp3','ogg','wav'],true);
}
function nickname_ok(string $name): bool {
    if (!preg_match('/^[\p{L}\p{N}_-]{3,24}$/u',$name)) return false;
    $clean=user_key($name);$clean=preg_replace('/[0-9_\-]/','',$clean);
    foreach (['admin','moderator','xtremelab','fuck','shit','bitch','cunt','dick','porn','nazi','slut','whore'] as $banned) {
        if (str_contains($clean, $banned)) return false;
    }
    return true;
}
function avatar_svg(string $id): string {
    $avatars=['fox'=>['🦊','#FF8A4C'],'robot'=>['🤖','#7968EC'],'cat'=>['🐱','#F5B939'],'koala'=>['🐨','#69BBA6'],'panda'=>['🐼','#97A1BC'],'lion'=>['🦁','#EE9864'],'alien'=>['👾','#AB76DE'],'rocket'=>['🚀','#549FDF'],'owl'=>['🦉','#AE83C4'],'star'=>['⭐','#E0AC44'],'shield'=>['🛡️','#6C9BCF'],'dragon'=>['🐲','#52BD9E'],'frog'=>['🐸','#65C582'],'rabbit'=>['🐰','#FF91AB'],'penguin'=>['🐧','#75A7D9'],'unicorn'=>['🦄','#D186E5'],'bee'=>['🐝','#E6BA3C'],'dinosaur'=>['🦖','#5BB895'],'whale'=>['🐳','#66B8DA'],'dog'=>['🐶','#C19A76'],'planet'=>['🪐','#9673CF'],'mushroom'=>['🍄','#E47190'],'elephant'=>['🐘','#86AFBF'],'tiger'=>['🐯','#EBA34D']];
    [$emoji,$bg]=$avatars[$id]??$avatars['robot'];
    return '<span class="avatar" style="--avatar-bg:'.e($bg).'" aria-label="Avatar">'.$emoji.'</span>';
}
function avatar_choices(): array {return ['fox','robot','cat','koala','panda','lion','alien','rocket','owl','star','shield','dragon','frog','rabbit','penguin','unicorn','bee','dinosaur','whale','dog','planet','mushroom','elephant','tiger'];}
function theme_game(string $slug): array {
    return [
        'ai-or-real'=>['emoji'=>'✨','class'=>'theme-ai','number'=>'01','short'=>'Image Lab','desc'=>'Compare synthetic images with real sources.'],
        'phishing-detective'=>['emoji'=>'🕵️','class'=>'theme-phish','number'=>'02','short'=>'Detective Files','desc'=>'Track down suspicious emails.'],
        'threat-hunter'=>['emoji'=>'🔎','class'=>'theme-hunt','number'=>'03','short'=>'Hidden Risks','desc'=>'Find the cyber threats around you.'],
        'password-hero'=>['emoji'=>'🦸','class'=>'theme-password','number'=>'04','short'=>'Hero Academy','desc'=>'Lock down your accounts.'],
        'think-before-sharing'=>['emoji'=>'📱','class'=>'theme-privacy','number'=>'05','short'=>'Digital Footprints','desc'=>'See how far a single post can travel.'],
        'cyber-shield'=>['emoji'=>'🛡️','class'=>'theme-quiz','number'=>'06','short'=>'Knowledge Arena','desc'=>'Show what you know in the cyber arena.'],
    ][$slug]??['emoji'=>'🎮','class'=>'','number'=>'00','short'=>'Game','desc'=>''];
}
function render(string $view, array $data=[]): void {
    extract($data,EXTR_SKIP); $flash=read_flash();
    include PROJECT_ROOT.'/app/views/header.php';
    include PROJECT_ROOT.'/app/views/'.$view.'.php';
    include PROJECT_ROOT.'/app/views/footer.php';
}
function throttled(string $action, string $key, int $limit=8, int $seconds=600): bool {
    $finger=hash('sha256',$action.':'.$key);
    $pdo=db(); $q=$pdo->prepare('SELECT COUNT(*) FROM auth_events WHERE fingerprint=? AND happened_at > DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND)');
    $q->execute([$finger,$seconds]);return (int)$q->fetchColumn()>=$limit;
}
function auth_event(string $action,string $key): void {
    $q=db()->prepare('INSERT INTO auth_events (fingerprint,happened_at) VALUES (?,UTC_TIMESTAMP())');
    $q->execute([hash('sha256',$action.':'.$key)]);
}
function write_audit(int $adminId,string $action,string $target=''):void {
    $stmt=db()->prepare('INSERT INTO audit_logs (admin_id,action,target,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');$stmt->execute([$adminId,$action,$target]);
}
boot_session();
function mb_safe_upper(string $s):string {return mb_strtoupper($s,'UTF-8');}
