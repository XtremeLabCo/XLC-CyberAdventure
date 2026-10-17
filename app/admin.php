<?php
declare(strict_types=1);

function handle_setup(string $method):void {
    if(cfg('setup_key')==='PUT_AT_LEAST_32_RANDOM_CHARACTERS_HERE'||strlen((string)cfg('setup_key'))<32) {
        throw new UserException('First set the setup key in config/local.php to at least 32 random characters.',503);
    }
    $count=(int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if($count>0){render('setup_closed',['page_title'=>'Setup Complete']);return;}
    if($method==='POST'){
        check_csrf();$key=(string)($_POST['setup_key']??'');
        if(!hash_equals((string)cfg('setup_key'),$key))throw new UserException('The setup key is incorrect.',403);
        $username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
        if(!preg_match('/^[a-zA-Z0-9_.-]{4,50}$/',$username)||strlen($password)<12)throw new UserException('The admin username must be 4–50 characters and the password at least 12 characters.',400);
        $q=db()->prepare("INSERT INTO admins (username,password_hash,role) VALUES(?,?,'super')");
        $q->execute([$username,password_hash($password,PASSWORD_DEFAULT)]);
        db()->prepare("UPDATE settings SET value='1' WHERE `key`='setup_complete'")->execute();
        flash('The first admin account has been created. For security, restrict access to the setup page.','success');go('/admin/login');
    }
    render('setup',['page_title'=>'Initial Admin Setup']);
}
function handle_admin(string $path,string $method):void {
    if($path==='/admin/login'){
        if($method==='POST'){
            check_csrf();$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
            $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
            if(throttled('admin_login',$ip,8,900))throw new UserException('Too many admin login attempts.',429);
            $q=db()->prepare('SELECT * FROM admins WHERE username=? AND active=1 LIMIT 1');$q->execute([$username]);$row=$q->fetch();
            if(!$row||!password_verify($password,$row['password_hash'])){
                auth_event('admin_login',$ip);throw new UserException('Incorrect username or password.',401);
            }
            login_admin((int)$row['id']);go('/admin');
        }
        render('admin_login',['page_title'=>'Admin Login']);return;
    }
    if($path==='/admin/logout' && $method==='POST'){
        check_csrf();unset($_SESSION['admin_id']);session_regenerate_id(true);go('/admin/login');
    }
    $a=need_admin();
    if($path==='/admin'){
        $stats=[
            'players'=>(int)db()->query('SELECT COUNT(*) FROM players')->fetchColumn(),
            'attempts'=>(int)db()->query('SELECT COUNT(*) FROM attempts')->fetchColumn(),
            'completed'=>(int)db()->query("SELECT COUNT(*) FROM attempts WHERE status='finished'")->fetchColumn(),
            'active'=>(int)db()->query("SELECT COUNT(*) FROM attempts WHERE status='active' AND ends_at>UTC_TIMESTAMP()")->fetchColumn(),
        ];
        $overview=db()->query("SELECT g.name,COUNT(a.id) AS total,ROUND(AVG(CASE WHEN a.status='finished' THEN a.score ELSE NULL END),1) AS mean_score FROM games g LEFT JOIN attempts a ON a.game_id=g.id GROUP BY g.id,g.name ORDER BY g.position")->fetchAll();
        render('admin_home',['page_title'=>'Admin — Overview','a'=>$a,'stats'=>$stats,'overview'=>$overview]);return;
    }
    if($path==='/admin/players'){
        if($method==='POST'){
            require_admin_role($a,['super','admin']);check_csrf();
            $playerId=(int)($_POST['player_id']??0);$action=(string)($_POST['action']??'status');
            if($action==='reset_code'){
                $q=db()->prepare('SELECT username FROM players WHERE id=? LIMIT 1');$q->execute([$playerId]);$username=$q->fetchColumn();
                if(!is_string($username))throw new UserException('Player not found.',404);
                [$code,$hash,$encrypted]=recovery_values($username);
                $q=db()->prepare('UPDATE players SET recovery_hash=?,recovery_code_enc=? WHERE id=?');$q->execute([$hash,$encrypted,$playerId]);
                write_audit((int)$a['id'],'player_code_reset',(string)$playerId);flash('Login code reset: '.$code.' — share it with the player securely.','success');go('/admin/players');
            }
            $status=(string)($_POST['status']??'');if(!in_array($status,['active','blocked'],true))throw new UserException('Invalid status.',400);
            $q=db()->prepare('UPDATE players SET status=? WHERE id=?');$q->execute([$status,$playerId]);
            write_audit((int)$a['id'],'player_status',$playerId.':'.$status);flash('Player status updated.','success');go('/admin/players');
        }
        $players=db()->query('SELECT p.id,p.username,p.first_name,p.last_name,p.email,p.gender,p.avatar_id,p.recovery_code_enc,p.status,p.created_at,p.last_seen_at,ag.label AS age_label FROM players p JOIN age_groups ag ON ag.id=p.age_group_id ORDER BY p.id DESC LIMIT 200')->fetchAll();
        foreach($players as &$playerRow)$playerRow['recovery_code']=decrypt_recovery_code($playerRow['recovery_code_enc']??null);unset($playerRow);
        render('admin_players',['page_title'=>'Player Management','a'=>$a,'players'=>$players]);return;
    }
    if($path==='/admin/games'){
        if($method==='POST'){
            require_admin_role($a,['super','admin']);check_csrf();
            $id=(int)($_POST['game_id']??0);$duration=max(60,min(900,(int)($_POST['seconds_limit']??180)));
            $perAttempt=max(2,min(12,(int)($_POST['questions_per_attempt']??5)));
            $maxAttempt=max(1,min(10,(int)($_POST['max_scored_attempts']??2)));
            $on=isset($_POST['enabled'])?1:0;
            $q=db()->prepare('UPDATE games SET seconds_limit=?,questions_per_attempt=?,max_scored_attempts=?,enabled=? WHERE id=?');
            $q->execute([$duration,$perAttempt,$maxAttempt,$on,$id]);
            write_audit((int)$a['id'],'game_settings',(string)$id);flash('Game settings saved.','success');go('/admin/games');
        }
        render('admin_games',['page_title'=>'Game Management','a'=>$a,'all'=>games()]);return;
    }
    if($path==='/admin/questions/sample.csv'){
        require_admin_role($a,['super','admin']);
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="question-bank-sample.csv"');header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";readfile(PROJECT_ROOT.'/app/templates/question_bank_sample.csv');exit;
    }
    if($path==='/admin/questions'){
        require_admin_role($a,['super','admin']);
        if($method==='POST'){
            check_csrf();$action=(string)($_POST['action']??'save');$gameId=(int)($_POST['game_id']??0);
            if($action==='csv'){
                $result=import_question_csv($_FILES['csv']??[]);
                if($result['errors']){$_SESSION['import_errors']=$result['errors'];flash('CSV not imported: '.count($result['errors']).' row(s) have errors. Fix them and upload the file again.','error');go('/admin/questions?game='.$gameId.'#bulk-upload');}
                write_audit((int)$a['id'],'csv_import',$result['inserted'].' added, '.$result['skipped'].' skipped');
                flash($result['inserted'].' question(s) added'.($result['skipped']?'; '.$result['skipped'].' question(s) skipped because the title already exists':'').'.','success');go('/admin/questions?game='.$gameId);
            }
            if($action==='media'){
                $files=uploaded_files('images');if(!$files)throw new UserException('Select images to upload.',400);
                $paths=array_map('store_uploaded_image',$files);
                write_audit((int)$a['id'],'image_upload',implode(',',$paths));flash(count($paths).' image(s) added to the library.','success');go('/admin/questions?game='.$gameId.'#image-library');
            }
            $id=(int)($_POST['id']??0);$input=$_POST;
            if(($input['kind']??'')==='ai'&&isset($input['image_form'])){
                $images=[];
                foreach([1,2] as $n){
                    $file=$_FILES['image_'.$n]??null;
                    $images[]=is_array($file)&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE?store_uploaded_image($file):trim((string)($input['image_path_'.$n]??''));
                }
                $base=json_decode((string)($input['public_json']??''),true);
                [$input['public_json'],$input['answer_json']]=build_image_task($images,(int)($input['correct_image']??-1),trim((string)($input['credit']??'')),is_array($base)?$base:[]);
            }
            $q=validate_question($input);
            if($id>0){
                $check=db()->prepare("SELECT COUNT(*) FROM attempts a JOIN questions x ON x.game_id=a.game_id WHERE x.id=? AND a.status='active' AND a.ends_at>UTC_TIMESTAMP()");$check->execute([$id]);
                if((int)$check->fetchColumn()>0)throw new UserException('Do not change content while this game has active attempts. Try again in a few minutes.',409);
                $stmt=db()->prepare('UPDATE questions SET title=?,prompt=?,public_json=?,answer_json=?,explanation=?,age_scope=?,difficulty=?,active=? WHERE id=?');
                $stmt->execute([$q['title'],$q['prompt'],$q['public_json'],$q['answer_json'],$q['explanation'],$q['age_scope'],$q['difficulty'],$q['active'],$id]);
            }else{
                $id=insert_question($q);
            }
            write_audit((int)$a['id'],'question_save',(string)$id);flash('Game content saved.','success');go('/admin/questions?game='.$q['game_id']);
        }
        $all=games();$filter=(int)($_GET['game']??1);$edit=(int)($_GET['edit']??0);
        $data=null;if($edit){$q=db()->prepare('SELECT * FROM questions WHERE id=?');$q->execute([$edit]);$data=$q->fetch()?:null;}
        $stmt=db()->prepare('SELECT q.id,q.title,q.kind,q.difficulty,q.age_scope,q.active,q.game_id,g.name AS game_name FROM questions q JOIN games g ON q.game_id=g.id WHERE q.game_id=? ORDER BY q.id DESC LIMIT 200');
        $stmt->execute([$filter]);$rows=$stmt->fetchAll();
        $importErrors=$_SESSION['import_errors']??[];unset($_SESSION['import_errors']);
        render('admin_questions',['page_title'=>'Question and Scenario Bank','a'=>$a,'rows'=>$rows,'all'=>$all,'filter'=>$filter,'edit'=>$data,'importErrors'=>$importErrors,'media'=>media_library()]);return;
    }
    if($path==='/admin/settings'){
        require_admin_role($a,['super']);
        if($method==='POST'){
            require_admin_role($a,['super']);check_csrf();
            $action=(string)($_POST['action']??'');
            if($action==='age'){
                $id=(int)($_POST['age_id']??0);$min=max(0,min(120,(int)($_POST['min_age']??0)));
                $maxInput=trim((string)($_POST['max_age']??''));$max=$maxInput===''?null:max(0,min(120,(int)$maxInput));
                if($max!==null&&$max<$min)throw new UserException('The maximum age cannot be lower than the minimum age.',400);
                $label=trim((string)($_POST['age_label']??''));
                if(!preg_match('/^[0-9–+\-]{2,15}$/u',$label))throw new UserException('The age label must be numeric (for example 7–9 or 18+).',400);
                $q=db()->prepare('UPDATE age_groups SET label=?,min_age=?,max_age=? WHERE id=?');$q->execute([$label,$min,$max,$id]);
                write_audit((int)$a['id'],'age_group_edit',(string)$id);
            }else if($action==='music'){
                $main=trim((string)($_POST['music_main_url']??''));$game=trim((string)($_POST['music_game_url']??''));$source=trim((string)($_POST['music_source_url']??''));
                if(!valid_music_url($main)||!valid_music_url($game))throw new UserException('The music URL must be an MP3/OGG/WAV file in /assets/sounds or a direct HTTPS audio link.',400);
                if($source!==''&&(filter_var($source,FILTER_VALIDATE_URL)===false||!str_starts_with($source,'https://')))throw new UserException('The source page must be a valid HTTPS URL.',400);
                $q=db()->prepare('INSERT INTO settings (`key`,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
                $q->execute(['music_main_url',$main]);$q->execute(['music_game_url',$game]);$q->execute(['music_source_url',$source]);
                write_audit((int)$a['id'],'music_settings');
            }else{
                $text=trim((string)($_POST['site_notice']??''));
                if(strlen($text)>450)throw new UserException('The announcement is too long.',400);
                $open=isset($_POST['registration_open'])?'1':'0';
                $q=db()->prepare('UPDATE settings SET value=? WHERE `key`=?');
                $q->execute([$text,'site_notice']);$q->execute([$open,'registration_open']);
                write_audit((int)$a['id'],'general_settings');
            }
            flash('Settings saved.','success');go('/admin/settings');
        }
        render('admin_settings',['page_title'=>'System Settings','a'=>$a,'groups'=>age_groups()]);return;
    }
    if($path==='/admin/reports'||$path==='/admin/reports.csv'){
        $report=db()->query("SELECT g.name,COUNT(DISTINCT a.player_id) AS unique_players,COUNT(a.id) AS started,SUM(CASE WHEN a.status='finished' THEN 1 ELSE 0 END) AS finished,ROUND(100*SUM(CASE WHEN a.status='finished' THEN 1 ELSE 0 END)/NULLIF(COUNT(a.id),0),1) AS completion_rate,ROUND(AVG(CASE WHEN a.status='finished' THEN a.score END),1) AS average,ROUND(100*SUM(CASE WHEN a.status='finished' THEN a.correct_count ELSE 0 END)/NULLIF(SUM(CASE WHEN a.status='finished' THEN a.total_steps ELSE 0 END),0),1) AS accuracy,ROUND(AVG(CASE WHEN a.status='finished' THEN TIMESTAMPDIFF(SECOND,a.started_at,a.finished_at) END)) AS avg_seconds FROM games g LEFT JOIN attempts a ON g.id=a.game_id GROUP BY g.id,g.name ORDER BY g.position")->fetchAll();
        $ageReport=db()->query("SELECT ag.label,COUNT(DISTINCT a.player_id) unique_players,COUNT(a.id) started,SUM(CASE WHEN a.status='finished' THEN 1 ELSE 0 END) finished,ROUND(AVG(CASE WHEN a.status='finished' THEN a.score END),1) average FROM age_groups ag LEFT JOIN attempts a ON a.age_group_id=ag.id WHERE ag.enabled=1 GROUP BY ag.id,ag.label ORDER BY ag.position")->fetchAll();
        $summary=db()->query("SELECT COUNT(*) started,SUM(status='finished') finished,COUNT(DISTINCT player_id) players,ROUND(AVG(CASE WHEN status='finished' THEN score END),1) average FROM attempts")->fetch()?:[];
        $daily=db()->query("SELECT DATE(started_at) day,COUNT(*) started,SUM(status='finished') finished FROM attempts WHERE started_at>=DATE_SUB(UTC_DATE(),INTERVAL 13 DAY) GROUP BY DATE(started_at) ORDER BY day")->fetchAll();
        if($path==='/admin/reports.csv'||($_GET['download']??'')==='csv'){
            require_admin_role($a,['super','admin']);
            header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="cyber-adventure-report-'.gmdate('Y-m-d').'.csv"');header('Cache-Control: no-store');
            $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
            $columns=['Player ID','Username','First Name','Last Name','Email','4-Digit Code','Age Group','Gender','Avatar','Player Status','Registered At','Last Seen','Attempt ID','Game','Mode','Attempt Status','Step','Correct Answers','Total Questions','Score','Started At','Deadline','Finished At','Duration (s)','Question ID','Question Title','Question Text','Difficulty','Given Answer','Correct Answer','Answered Correctly','Explanation'];fputcsv($out,$columns,',','"','');
            $questions=[];foreach(db()->query('SELECT id,title,prompt,public_json,answer_json,explanation,difficulty FROM questions')->fetchAll() as $qRow)$questions[(int)$qRow['id']]=$qRow;
            $rows=db()->query("SELECT p.*,ag.label age_label,a.id attempt_id,a.mode,a.status attempt_status,a.current_step,a.correct_count,a.total_steps,a.score,a.responses_json,a.started_at,a.ends_at,a.finished_at,g.name game_name FROM players p JOIN age_groups ag ON ag.id=p.age_group_id LEFT JOIN attempts a ON a.player_id=p.id LEFT JOIN games g ON g.id=a.game_id ORDER BY p.id,a.started_at,a.id")->fetchAll();
            $safeValue=static function(mixed $value):string{$text=(string)($value??'');return preg_match('/^[=+\-@]/',$text)?"'".$text:$text;};
            foreach($rows as $row){$responses=json_decode((string)($row['responses_json']??''),true);if(!is_array($responses)||!$responses)$responses=[null];foreach($responses as $response){$question=is_array($response)?($questions[(int)($response['question_id']??0)]??null):null;$public=$question?json_decode((string)$question['public_json'],true):[];$answer=$question?json_decode((string)$question['answer_json'],true):[];$optionTexts=[];foreach(($public['options']??[]) as $option)$optionTexts[(string)($option['id']??'')]=(string)($option['text']??'');$choice=is_array($response)?(string)($response['choice']??''):'';$right=(string)($answer['choice']??'');$duration=$row['finished_at']&&$row['started_at']?max(0,strtotime($row['finished_at'])-strtotime($row['started_at'])):'';$values=[$row['id'],$row['username'],$row['first_name'],$row['last_name'],$row['email'],decrypt_recovery_code($row['recovery_code_enc']??null)??'Needs reset',$row['age_label'],$row['gender'],$row['avatar_id'],$row['status'],$row['created_at'],$row['last_seen_at'],$row['attempt_id'],$row['game_name'],$row['mode'],$row['attempt_status'],$row['current_step'],$row['correct_count'],$row['total_steps'],$row['score'],$row['started_at'],$row['ends_at'],$row['finished_at'],$duration,$question['id']??'', $question['title']??'', $question['prompt']??'', $question['difficulty']??'', $optionTexts[$choice]??$choice,$optionTexts[$right]??$right,is_array($response)?(!empty($response['correct'])?'Yes':'No'):'',$question['explanation']??''];fputcsv($out,array_map($safeValue,$values),',','"','');}}
            fclose($out);exit;
        }
        render('admin_reports',['page_title'=>'Participation Reports','a'=>$a,'report'=>$report,'ageReport'=>$ageReport,'summary'=>$summary,'daily'=>$daily]);return;
    }
    throw new UserException('Admin page not found.',404);
}
function require_admin_role(array $admin,array $roles):void {
    if(!in_array($admin['role'],$roles,true))throw new UserException('You do not have permission for this action.',403);
}
