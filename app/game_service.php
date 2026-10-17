<?php
declare(strict_types=1);

const AGE_DIFFICULTY_WINDOW = 2;

function shuffle_secure(array $items): array {
    for ($i=count($items)-1;$i>0;$i--) {
        $j=random_int(0,$i);[$items[$i],$items[$j]]=[$items[$j],$items[$i]];
    }
    return $items;
}
function public_task(array $item): array {
    $q=db()->prepare('SELECT id,kind,title,prompt,public_json,difficulty FROM questions WHERE id=? AND active=1');
    $q->execute([(int)$item['id']]); $row=$q->fetch();
    if (!$row) throw new UserException('This task is no longer available.',409);
    $public=json_decode((string)$row['public_json'],true);
    if (!is_array($public)) throw new UserException('The game content is invalid.',500);
    $options=$public['options']??[]; $byId=[];
    foreach ($options as $option) $byId[(string)$option['id']]=$option;
    $order=[];foreach ($item['order'] as $id) if(isset($byId[$id]))$order[]=$byId[$id];
    $public['options']=$order;
    return ['id'=>(int)$row['id'],'kind'=>$row['kind'],'title'=>$row['title'],'prompt'=>$row['prompt'],'difficulty'=>(int)$row['difficulty'],'content'=>$public];
}
function get_attempt(string $id, int $playerId, bool $lock=false): ?array {
    $q=db()->prepare('SELECT a.*,g.name AS game_name,g.slug AS game_slug FROM attempts a JOIN games g ON g.id=a.game_id WHERE a.id=? AND a.player_id=?'.($lock?' FOR UPDATE':''));
    $q->execute([$id,$playerId]);return $q->fetch()?:null;
}
function remain_seconds(array $row): int { return max(0,strtotime((string)$row['ends_at'].' UTC') - time()); }
function start_game(int $playerId,string $slug): array {
    $pdo=db();$pdo->beginTransaction();
    try {
        $game=game_slug($slug);if(!$game)throw new UserException('Game not found.',404);
        $existing=$pdo->prepare("SELECT id,ends_at FROM attempts WHERE player_id=? AND game_id=? AND status='active' ORDER BY started_at DESC LIMIT 1 FOR UPDATE");
        $existing->execute([$playerId,$game['id']]);$old=$existing->fetch();
        if ($old && remain_seconds($old)>0) {
            $pdo->commit();return game_state((string)$old['id'],$playerId);
        }
        if ($old) {
            $pdo->prepare("UPDATE attempts SET status='expired',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([$old['id']]);
        }
        $p=$pdo->prepare('SELECT age_group_id FROM players WHERE id=? FOR UPDATE');$p->execute([$playerId]);$ageId=(int)$p->fetchColumn();
        $ageQ=$pdo->prepare('SELECT slug,difficulty FROM age_groups WHERE id=?');$ageQ->execute([$ageId]);$age=$ageQ->fetch();
        if (!$age) throw new UserException('Age group not found.',409);
        // Each player may enter each game at most max_scored_attempts times; expired or unfinished entries also count.
        $used=attempts_used($playerId)[(int)$game['id']]??0;
        if($used>=(int)$game['max_scored_attempts'])throw new UserException('You have used all '.(int)$game['max_scored_attempts'].' entries for this game.',403);
        $mode='scored';
        $limit=max(2,min(12,(int)$game['questions_per_attempt']));
        $difficulty=max(2,min(5,(int)$age['difficulty']));
        // Use at most two levels below the age group ceiling; adults never get difficulty 1-2 questions.
        $floor=max(1,$difficulty-AGE_DIFFICULTY_WINDOW);
        $rows=$pdo->prepare("SELECT id,public_json,difficulty FROM questions WHERE game_id=? AND active=1 AND difficulty BETWEEN ? AND ? AND (age_scope='all' OR FIND_IN_SET(?,age_scope)>0) ORDER BY RAND() LIMIT 120");
        $rows->execute([$game['id'],$floor,$difficulty,$age['slug']]);$pool=$rows->fetchAll();
        // Mix the levels in the window evenly on each pass, starting from the hardest.
        $buckets=[];foreach($pool as $row)$buckets[(int)$row['difficulty']][]=$row;
        $selected=[];$levels=range($difficulty,$floor);
        while(count($selected)<$limit){$added=false;foreach($levels as $d){if(!empty($buckets[$d])){$selected[]=array_shift($buckets[$d]);$added=true;if(count($selected)>=$limit)break;}}if(!$added)break;}
        $selected=shuffle_secure($selected);
        if(count($selected)<2) throw new UserException('There is not enough game content for this age group yet.',409);
        $items=[];
        foreach($selected as $q) {
            $pub=json_decode((string)$q['public_json'],true); $order=array_map(fn($o)=>(string)$o['id'],$pub['options']??[]);
            $items[]=['id'=>(int)$q['id'],'order'=>shuffle_secure($order)];
        }
        $id=bin2hex(random_bytes(16));$duration=(int)$game['seconds_limit'];
        $stmt=$pdo->prepare('INSERT INTO attempts (id,player_id,game_id,age_group_id,mode,status,items_json,responses_json,total_steps,started_at,ends_at) VALUES (?,?,?,?,?,\'active\',?,\'[]\',?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))');
        $stmt->execute([$id,$playerId,$game['id'],$ageId,$mode,json_encode($items,JSON_UNESCAPED_UNICODE),count($items),$duration]);
        $pdo->commit();return game_state($id,$playerId);
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function game_state(string $id,int $playerId):array {
    $attempt=get_attempt($id,$playerId);if(!$attempt)throw new UserException('Game session not found.',404);
    $items=json_decode((string)$attempt['items_json'],true) ?: [];
    $step=(int)$attempt['current_step'];
    $finished=$attempt['status']!=='active'||$step >=count($items)||remain_seconds($attempt)<=0;
    return ['attempt_id'=>$id,'game'=>$attempt['game_slug'],'game_name'=>$attempt['game_name'],
        'mode'=>$attempt['mode'],'status'=>$finished?'finished':'active','remaining'=>remain_seconds($attempt),
        'step'=>$step,'total'=>count($items),'score'=>(int)$attempt['score'],
        'task'=>$finished?null:public_task($items[$step])];
}
function answer_game(int $playerId,string $id,int $questionId,string $choice):array {
    if (!preg_match('/^[a-zA-Z0-9_-]{1,40}$/',$choice))throw new UserException('Invalid choice.',400);
    $pdo=db();$pdo->beginTransaction();
    try {
        $attempt=get_attempt($id,$playerId,true);
        if(!$attempt)throw new UserException('Game session not found.',404);
        if($attempt['status']!=='active')throw new UserException('This game is already finished.',409);
        if(remain_seconds($attempt)<=0) {
            $pdo->prepare("UPDATE attempts SET status='expired',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([$id]);
            $pdo->commit();throw new UserException('Time is up. You can start a new game.',410);
        }
        $items=json_decode((string)$attempt['items_json'],true)?:[];
        $step=(int)$attempt['current_step'];
        if(!isset($items[$step])||(int)$items[$step]['id']!==$questionId)throw new UserException('The task order could not be verified.',409);
        $q=$pdo->prepare('SELECT answer_json,explanation,public_json FROM questions WHERE id=? AND game_id=? AND active=1 LIMIT 1');
        $q->execute([$questionId,$attempt['game_id']]);$question=$q->fetch();
        if(!$question)throw new UserException('Question not found.',409);
        $public=json_decode($question['public_json'],true);$validIds=array_map(fn($o)=>(string)$o['id'],$public['options']??[]);
        if(!in_array($choice,$validIds,true))throw new UserException('This option is not valid.',400);
        $answer=json_decode($question['answer_json'],true) ?: [];
        $right=hash_equals((string)($answer['choice']??''),$choice);
        $count=(int)$attempt['correct_count']+($right?1:0);
        $next=$step+1;$total=count($items);$points=(int)round(100*$count/$total);
        $responses=json_decode((string)$attempt['responses_json'],true)?:[];
        $responses[]=['question_id'=>$questionId,'choice'=>$choice,'correct'=>$right];
        $done=$next>=$total;
        $update=$pdo->prepare('UPDATE attempts SET current_step=?,correct_count=?,score=?,responses_json=?,status=?,finished_at=IF(?=1,UTC_TIMESTAMP(),NULL) WHERE id=?');
        $update->execute([$next,$count,$points,json_encode($responses,JSON_UNESCAPED_UNICODE),$done?'finished':'active',$done?1:0,$id]);
        $correctText='';foreach(($public['options']??[]) as $option){if((string)($option['id']??'')===(string)($answer['choice']??'')){$correctText=(string)($option['text']??'');break;}}
        $positive=['You caught the clue!','Great analysis!','Your cyber reflexes are sharp!','Spot on!','You looked very carefully!'];
        $learning=['Good try. Now look at the key clue.','This one was tricky. Here is what settles it.','Close. Here is why the right answer is right.','Mistakes are part of learning. The key detail is:','You picked up a strong clue for the next round.'];
        $titles=$right?$positive:$learning;
        $reply=['correct'=>$right,'feedback_title'=>$titles[array_rand($titles)],'correct_answer'=>$right?'':$correctText,'correct_choice'=>(string)($answer['choice']??''),'explanation'=>$question['explanation'],'score'=>$points,'step'=>$next,'total'=>$total,'finished'=>$done,
            'mode'=>$attempt['mode'],'next'=>$done?null:public_task($items[$next])];
        $pdo->commit();return $reply;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function attempts_used(int $playerId): array {
    $q=db()->prepare('SELECT game_id,COUNT(*) FROM attempts WHERE player_id=? GROUP BY game_id');$q->execute([$playerId]);
    return array_map('intval',$q->fetchAll(PDO::FETCH_KEY_PAIR));
}
function has_active_attempt(int $playerId,int $gameId): bool {
    $q=db()->prepare("SELECT 1 FROM attempts WHERE player_id=? AND game_id=? AND status='active' AND ends_at>UTC_TIMESTAMP() LIMIT 1");$q->execute([$playerId,$gameId]);
    return $q->fetchColumn()!==false;
}
function best_scores(int $playerId): array {
    $q=db()->prepare("SELECT game_id,MAX(score) AS best,COUNT(*) AS completed FROM attempts WHERE player_id=? AND status='finished' AND mode='scored' GROUP BY game_id");$q->execute([$playerId]);$result=[];
    foreach($q->fetchAll() as $row)$result[(int)$row['game_id']]=['best'=>(int)$row['best'],'completed'=>(int)$row['completed']];
    return $result;
}
function ranking(?int $gameId=null,?int $ageId=null,int $limit=30):array {
    $args=[];$where="a.status='finished' AND a.mode='scored' AND p.status='active'";
    if($gameId!==null){$where.=' AND a.game_id=?';$args[]=$gameId;}
    if($ageId!==null){$where.=' AND p.age_group_id=?';$args[]=$ageId;}
    $limit=max(1,min(100,$limit));
    if($gameId!==null){
        $sql="SELECT p.id,p.username,p.avatar_id,ag.label age_label,MAX(a.score) points,1 AS completed FROM attempts a JOIN players p ON p.id=a.player_id JOIN age_groups ag ON ag.id=p.age_group_id WHERE $where GROUP BY p.id,p.username,p.avatar_id,ag.label ORDER BY points DESC,p.id ASC LIMIT $limit";
    }else{
        $sql="SELECT p.id,p.username,p.avatar_id,ag.label age_label,SUM(b.best) points,COUNT(b.game_id) completed FROM (SELECT player_id,game_id,MAX(score) best FROM attempts WHERE status='finished' AND mode='scored' GROUP BY player_id,game_id) b JOIN players p ON p.id=b.player_id JOIN age_groups ag ON ag.id=p.age_group_id WHERE p.status='active'";
        if($ageId!==null){$sql.=' AND p.age_group_id=?';}
        $sql.=" GROUP BY p.id,p.username,p.avatar_id,ag.label ORDER BY points DESC,completed DESC,p.id ASC LIMIT $limit";
        $args=$ageId!==null?[$ageId]:[];
    }
    $q=db()->prepare($sql);$q->execute($args);return $q->fetchAll();
}
