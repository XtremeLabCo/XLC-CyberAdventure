<?php
declare(strict_types=1);

// Builds database/INSTALL.sql = database/schema_en.sql + every question in database/content/en_game*.json.
// Every question is checked with the app's own validate_question(); nothing is written if one fails.
// Usage: CYBER_ADVENTURE_DB_NAME=<temp db already loaded with schema_en.sql> php tests/build_install_sql.php

$root=dirname(__DIR__);
require $root.'/app/core.php';require $root.'/app/question_bank.php';

$pdo=db();$errors=[];$inserts=[];$titles=[];
foreach(range(1,6) as $game){
    $file=$root."/database/content/en_game$game.json";
    if(!is_file($file)){$errors[]="Missing $file";continue;}
    foreach(json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR) as $item){
        $key=$item['game_id'].'|'.mb_strtolower((string)$item['title']);
        if(isset($titles[$key])){$errors[]="Duplicate title: {$item['title']}";continue;}
        $titles[$key]=true;
        try{
            $q=validate_question(['game_id'=>$item['game_id'],'kind'=>$item['kind'],'title'=>$item['title'],'prompt'=>$item['prompt'],'explanation'=>$item['explanation'],
                'public_json'=>json_encode($item['public'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'answer_json'=>json_encode(['choice'=>(string)$item['answer']]),
                'age_scope'=>$item['age_scope'],'difficulty'=>$item['difficulty'],'active'=>1]);
        }catch(UserException $e){$errors[]="{$item['key']} {$item['title']}: ".$e->getMessage();continue;}
        $inserts[]='INSERT INTO questions (game_id,kind,title,prompt,public_json,answer_json,explanation,age_scope,difficulty) VALUES ('
            .implode(',',[(int)$q['game_id'],$pdo->quote($q['kind']),$pdo->quote($q['title']),$pdo->quote($q['prompt']),$pdo->quote($q['public_json']),$pdo->quote($q['answer_json']),$pdo->quote($q['explanation']),$pdo->quote($q['age_scope']),(int)$q['difficulty']]).');';
    }
}
if($errors){fwrite(STDERR,implode("\n",$errors)."\n");exit(1);}
$sql=(string)file_get_contents($root.'/database/schema_en.sql')."\n-- ===== Questions (".count($inserts).") =====\nSTART TRANSACTION;\n".implode("\n",$inserts)."\nCOMMIT;\n";
file_put_contents($root.'/database/INSTALL.sql',$sql);
echo 'Written: database/INSTALL.sql ('.count($inserts)." questions)\n";
