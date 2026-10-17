<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/core.php';
require dirname(__DIR__).'/app/game_service.php';
require dirname(__DIR__).'/app/question_bank.php';
require dirname(__DIR__).'/app/admin.php';
date_default_timezone_set('UTC');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com; connect-src 'self' https:; media-src 'self' https:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$path=rtrim($path,'/')?:'/';
$method=$_SERVER['REQUEST_METHOD']??'GET';
$isApi=str_starts_with($path,'/api/');
try {
    if ($path==='/') {render('home',['page_title'=>'Cyber Adventure — Play. Learn. Collect Points!']);exit;}
    if ($path==='/rules') {render('rules',['page_title'=>'How to Play']);exit;}
    if ($path==='/password-test') {render('meter',['page_title'=>'Password Strength Test']);exit;}
    if ($path==='/face-search') {render('face',['page_title'=>'About Face Search']);exit;}
    if ($path==='/register') {
        if ($method==='POST') {
            check_csrf();
            if (setting('registration_open','1')!=='1')throw new UserException('New sign-ups are currently closed.',403);
            $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
            if(throttled('register',$ip,8,3600))throw new UserException('Too many sign-up attempts. Please try again later.',429);
            $username=trim((string)($_POST['username']??''));
            if(!nickname_ok($username))throw new UserException('Usernames must be 3–24 characters, use only letters/numbers/_/- and be appropriate.',400);
            $first=trim(strip_tags((string)($_POST['first_name']??'')));
            $last=trim(strip_tags((string)($_POST['last_name']??'')));
            $email=trim((string)($_POST['email']??''));
            if($first===''||$last===''||!preg_match('/^.{2,80}$/us',$first)||!preg_match('/^.{2,80}$/us',$last))throw new UserException('First and last name are required and must be at least 2 characters.',400);
            if(strlen($email)>190||filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new UserException('Please enter a valid email address.',400);
            $age=(int)($_POST['age_group_id']??0);$allowed=array_column(age_groups(),'id');
            if(!in_array($age,array_map('intval',$allowed),true))throw new UserException('Please choose a valid age range.',400);
            $gender=(string)($_POST['gender']??'');
            if(!in_array($gender,['female','male','prefer_not_to_say'],true))throw new UserException('Invalid option.',400);
            $avatar=(string)($_POST['avatar_id']??'robot');
            if(!in_array($avatar,avatar_choices(),true))throw new UserException('Please choose an avatar.',400);
            if(strlen((string)cfg('recovery_pepper',''))<32)throw new UserException('The player key is not configured. Please contact your administrator.',503);
            // Login is verified with HMAC; the code is also AES-GCM encrypted so an admin can show it when needed.
            [$code,$key,$encryptedCode]=recovery_values($username);
            $q=db()->prepare('INSERT INTO players(username,username_key,first_name,last_name,email,age_group_id,gender,avatar_id,recovery_hash,recovery_code_enc) VALUES(?,?,?,?,?,?,?,?,?,?)');
            try {$q->execute([$username,user_key($username),$first,$last,strtolower($email),$age,$gender,$avatar,$key,$encryptedCode]);}
            catch(PDOException $e){if($e->getCode()==='23000')throw new UserException('This username or email address is already in use. Try a different one.',409);throw $e;}
            $playerId=(int)db()->lastInsertId();auth_event('register',$ip);login_player($playerId);$_SESSION['first_code']=$code;go('/welcome');
        }
        render('register',['page_title'=>'Sign Up','age_groups'=>age_groups()]);exit;
    }
    if ($path==='/welcome') {
        $p=need_player();$code=$_SESSION['first_code']??decrypt_recovery_code($p['recovery_code_enc']??null);unset($_SESSION['first_code']);
        render('welcome',['page_title'=>'The Adventure Begins!','p'=>$p,'code'=>$code]);exit;
    }
    if($path==='/api/username' && $method==='GET'){
        $username=trim((string)($_GET['username']??''));$valid=nickname_ok($username);$available=false;
        if($valid){$q=db()->prepare('SELECT 1 FROM players WHERE username_key=? LIMIT 1');$q->execute([user_key($username)]);$available=$q->fetchColumn()===false;}
        header('Cache-Control: no-store');jreply(['ok'=>true,'valid'=>$valid,'available'=>$available,'message'=>!$valid?'3–24 characters; use only letters, numbers, _ or -.':($available?'This username is available.':'This username is taken; try another one.')]);
    }
    if ($path==='/login') {
        if($method==='POST'){
            check_csrf();$username=trim((string)($_POST['username']??''));
            $code=strtoupper(preg_replace('/[^a-fA-F0-9]/','',(string)($_POST['recovery_code']??'')));
            $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
            $userKey=user_key($username);
            if(throttled('login',$ip,5,900)||throttled('login_user',$userKey,5,900))throw new UserException('Too many login attempts. For your security, try again in 15 minutes.',429);
            $q=db()->prepare("SELECT id,recovery_hash FROM players WHERE username_key=? AND status='active' LIMIT 1");$q->execute([$userKey]);$row=$q->fetch();
            $validCodeLength=in_array(strlen($code),[4,20],true); // 20: backward compatibility for v1 records
            $candidate=strlen($code)===4?$userKey.':'.$code:$code;
            if(!$row||!$validCodeLength||!hash_equals($row['recovery_hash'],hash_hmac('sha256',$candidate,(string)cfg('recovery_pepper')))){
                auth_event('login',$ip);auth_event('login_user',$userKey);throw new UserException('Incorrect username or recovery code.',401);
            }
            login_player((int)$row['id']);go('/dashboard');
        }
        render('login',['page_title'=>'Log In']);exit;
    }
    if($path==='/logout' && $method==='POST'){
        check_csrf();$_SESSION=[];session_regenerate_id(true);flash('You have logged out safely. See you soon!');go('/');
    }
    if($path==='/dashboard'){
        $p=need_player();$all=games();$scores=best_scores((int)$p['id']);
        render('dashboard',['page_title'=>'Player Dashboard','p'=>$p,'all'=>$all,'scores'=>$scores,'used'=>attempts_used((int)$p['id'])]);exit;
    }
    if($path==='/leaderboard'){
        $gameId=filter_input(INPUT_GET,'game',FILTER_VALIDATE_INT)?:null;
        $ageId=filter_input(INPUT_GET,'age',FILTER_VALIDATE_INT)?:null;
        $all=games();$groups=age_groups();
        if ($gameId && !in_array($gameId,array_map(fn($g)=>(int)$g['id'],$all),true))$gameId=null;
        if ($ageId && !in_array($ageId,array_map(fn($g)=>(int)$g['id'],$groups),true))$ageId=null;
        render('leaders',['page_title'=>'Leaderboard','rows'=>ranking($gameId,$ageId), 'gameId'=>$gameId,'ageId'=>$ageId,'all'=>$all,'groups'=>$groups]);exit;
    }
    if(preg_match('#^/game/([a-z0-9-]+)$#',$path,$m)){
        $p=need_player();$game=game_slug($m[1]);if(!$game)throw new UserException('This game is not available right now.',404);
        $left=max(0,(int)$game['max_scored_attempts']-(attempts_used((int)$p['id'])[(int)$game['id']]??0));
        render('game',['page_title'=>$game['name'],'p'=>$p,'game'=>$game,'theme'=>theme_game($game['slug']),'left'=>$left,'resume'=>has_active_attempt((int)$p['id'],(int)$game['id'])]);exit;
    }
    if($path==='/api/game/start' && $method==='POST'){
        check_csrf();$p=player();if(!$p)throw new UserException('Your session has expired. Please log in again.',401);
        $data=input_json();$slug=(string)($data['game']??'');jreply(['ok'=>true,'data'=>start_game((int)$p['id'],$slug)]);
    }
    if($path==='/api/game/answer' && $method==='POST'){
        check_csrf();$p=player();if(!$p)throw new UserException('Your session has expired. Please log in again.',401);
        $data=input_json();$id=(string)($data['attempt_id']??'');
        if(!preg_match('/^[a-f0-9]{32}$/',$id))throw new UserException('Invalid game session.',400);
        jreply(['ok'=>true,'data'=>answer_game((int)$p['id'],$id,(int)($data['question_id']??0),(string)($data['choice']??''))]);
    }
    if($path==='/setup'){
        handle_setup($method);exit;
    }
    if(str_starts_with($path,'/admin')){
        handle_admin($path,$method);exit;
    }
    http_response_code(404);render('error',['page_title'=>'Page Not Found','error'=>'The page you are looking for could not be found.']);
}catch(UserException $e){
    $status=$e->getCode();if($status<400||$status>599)$status=400;
    if($isApi){jreply(['ok'=>false,'message'=>$e->getMessage()],$status);}
    http_response_code($status);
    render('error',['page_title'=>'Something Went Wrong','error'=>$e->getMessage()]);
}catch(Throwable $e){
    error_log('[SiberMacera] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
    if($isApi)jreply(['ok'=>false,'message'=>'There was a problem connecting to the server. Please try again.'],500);
    http_response_code(503);
    render('error',['page_title'=>'Connection Problem','error'=>'System data cannot be reached. Check that setup has been completed.']);
}
