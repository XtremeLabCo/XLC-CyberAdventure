<?php
declare(strict_types=1);

// Question bank: shared validation for the form and CSV, image upload and bulk import.

const QUESTION_KINDS = ['ai'=>'Image','mail'=>'Email','hunt'=>'Threat Hunt','password'=>'Password','story'=>'Story','quiz'=>'Quiz'];
const GAME_DEFAULT_KIND = [1=>'ai',2=>'mail',3=>'hunt',4=>'password',5=>'story',6=>'quiz'];
const UPLOAD_DIR = PROJECT_ROOT.'/public/assets/media/uploads';
const UPLOAD_URL = '/assets/media/uploads';
const IMAGE_MAX_BYTES = 5*1024*1024;
const IMAGE_TYPES = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
const CSV_MAX_BYTES = 2*1024*1024;
const CSV_MAX_ROWS = 1000;
const CSV_COLUMNS = ['game_id','kind','title','prompt','explanation','difficulty','age_scope','option_1','option_2','option_3','option_4','correct','image_1','image_2','credit','category','extra_json','active'];
const IMAGE_OPTION_IDS = ['a','b'];

function default_kind(int $gameId): string { return GAME_DEFAULT_KIND[$gameId] ?? 'quiz'; }

/** Validates a form or CSV row with the same rules; returns the fields to write to the database. */
function validate_question(array $in): array {
    $gameId=(int)($in['game_id']??0);
    if(!in_array($gameId,array_map(fn($g)=>(int)$g['id'],games()),true))throw new UserException('Invalid game.',400);
    $kind=(string)($in['kind']??'');
    if(!isset(QUESTION_KINDS[$kind]))throw new UserException('Invalid content type.',400);
    $title=trim((string)($in['title']??''));$prompt=trim((string)($in['prompt']??''));$explanation=trim((string)($in['explanation']??''));
    if($title===''||$prompt===''||$explanation==='')throw new UserException('Title, question text and explanation are required.',400);
    if(mb_strlen($title)>160||mb_strlen($prompt)>6000||mb_strlen($explanation)>6000)throw new UserException('The title can be at most 160 characters and the texts at most 6000 characters.',400);
    $public=(string)($in['public_json']??'');$answer=(string)($in['answer_json']??'');
    if(strlen($public)>1000000||strlen($answer)>10000)throw new UserException('The JSON content exceeds the allowed size.',400);
    $decoded=json_decode($public,true);$expected=json_decode($answer,true);
    if(!is_array($decoded)||!is_array($expected)||empty($decoded['options'])||!is_array($decoded['options']))throw new UserException('Task/answer data must be valid JSON.',400);
    $valid=[];
    foreach($decoded['options'] as $option){
        if(!isset($option['id'],$option['text'])||!preg_match('/^[a-zA-Z0-9_-]{1,40}$/',(string)$option['id'])||trim((string)$option['text'])===''||mb_strlen((string)$option['text'])>500)throw new UserException('Each option needs a valid id and 1–500 characters of text.',400);
        $valid[]=(string)$option['id'];
    }
    if(count($valid)<2||count($valid)!==count(array_unique($valid))||!in_array((string)($expected['choice']??''),$valid,true))throw new UserException('At least two unique options and a correct answer that matches one of them are required.',400);
    if($kind==='ai'){
        foreach($valid as $id)if(!image_path_ok((string)($decoded['images'][$id]??'')))throw new UserException('In image questions, every option needs an image that exists on the server.',400);
    }
    if($kind==='hunt'){
        $objects=$decoded['objects']??null;
        if(!is_array($objects)||!$objects)throw new UserException('Threat Hunt questions require scene objects (objects).',400);
        foreach($objects as $object)if(!in_array((string)($object['id']??''),$valid,true))throw new UserException('Each scene object id must match an option.',400);
    }
    $scope=trim((string)($in['age_scope']??'all'))?:'all';
    $requested=$scope==='all'?[]:array_map('trim',explode(',',$scope));
    if($scope!=='all'&&array_diff($requested,array_column(age_groups(),'slug')))throw new UserException('Invalid age scope. Use all or the active age groups.',400);
    if($scope!=='all')$scope=implode(',',$requested);
    return [
        'game_id'=>$gameId,'kind'=>$kind,'title'=>$title,'prompt'=>$prompt,'explanation'=>$explanation,
        'public_json'=>json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'answer_json'=>json_encode(['choice'=>(string)$expected['choice']],JSON_UNESCAPED_UNICODE),
        'age_scope'=>$scope,'difficulty'=>max(1,min(5,(int)($in['difficulty']??2))),'active'=>!empty($in['active'])?1:0,
    ];
}

function insert_question(array $q): int {
    $stmt=db()->prepare('INSERT INTO questions (game_id,kind,title,prompt,public_json,answer_json,explanation,age_scope,difficulty,active) VALUES (:game_id,:kind,:title,:prompt,:public_json,:answer_json,:explanation,:age_scope,:difficulty,:active)');
    $stmt->execute($q);
    return (int)db()->lastInsertId();
}

/** Builds the game data for an A/B image question; keeps existing extra fields (hint, note) when editing. */
function build_image_task(array $images,int $correctIndex,string $credit,array $base=[]): array {
    $options=$base['options']??[];
    $ids=array_map(fn($o)=>(string)($o['id']??''),$options);
    if(count($ids)!==2||in_array('',$ids,true)){$ids=IMAGE_OPTION_IDS;$options=[];}
    if(!isset($ids[$correctIndex]))throw new UserException('Select the correct image (A or B).',400);
    $public=$base;
    $public['images']=[$ids[0]=>$images[0],$ids[1]=>$images[1]];
    $public['options']=[['id'=>$ids[0],'text'=>(string)($options[0]['text']??'Image A')],['id'=>$ids[1],'text'=>(string)($options[1]['text']??'Image B')]];
    if($credit!=='')$public['credit']=$credit;else unset($public['credit']);
    return [json_encode($public,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode(['choice'=>$ids[$correctIndex]])];
}

function image_path_ok(string $path): bool {
    if(!preg_match('#^/assets/media/[a-zA-Z0-9_/-]+\.(webp|png|jpe?g|gif|svg)$#',$path)||str_contains($path,'..'))return false;
    return is_file(PROJECT_ROOT.'/public'.$path);
}

/** Stores one uploaded image under a random name after checking its type; returns its public path. */
function store_uploaded_image(array $file): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new UserException('The image could not be uploaded. Check the file size and try again.',400);
    if((int)$file['size']>IMAGE_MAX_BYTES)throw new UserException('Images can be at most 5 MB.',400);
    $tmp=(string)$file['tmp_name'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if(!isset(IMAGE_TYPES[$mime])||getimagesize($tmp)===false)throw new UserException('Only JPG, PNG, WEBP or GIF images can be uploaded.',400);
    if(!is_dir(UPLOAD_DIR)&&!mkdir(UPLOAD_DIR,0755,true)&&!is_dir(UPLOAD_DIR))throw new RuntimeException('Could not create the upload folder: '.UPLOAD_DIR);
    $name=gmdate('Ymd').'_'.bin2hex(random_bytes(6)).'.'.IMAGE_TYPES[$mime];
    if(!move_uploaded_file($tmp,UPLOAD_DIR.'/'.$name))throw new RuntimeException('The uploaded image could not be moved.');
    return UPLOAD_URL.'/'.$name;
}

/** Converts PHP's name[] multi-upload array into one array per file. */
function uploaded_files(string $field): array {
    $raw=$_FILES[$field]??null;
    if(!is_array($raw)||!is_array($raw['name']??null))return [];
    $files=[];
    foreach(array_keys($raw['name']) as $i){
        if(($raw['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
        $files[]=['name'=>$raw['name'][$i],'tmp_name'=>$raw['tmp_name'][$i],'size'=>$raw['size'][$i],'error'=>$raw['error'][$i]];
    }
    return $files;
}

function media_library(int $limit=40): array {
    $paths=glob(UPLOAD_DIR.'/*.{jpg,png,webp,gif}',GLOB_BRACE)?:[];
    usort($paths,fn($a,$b)=>filemtime($b)<=>filemtime($a));
    return array_map(fn($p)=>UPLOAD_URL.'/'.basename($p),array_slice($paths,0,$limit));
}

/** Converts a CSV row into validate_question input. Option ids are s1..s4 in order (a/b for images). */
function csv_row_to_question(array $row): array {
    $gameId=(int)$row['game_id'];
    $kind=$row['kind']!==''?$row['kind']:default_kind($gameId);
    $correct=(int)$row['correct'];
    $extra=[];
    if($row['extra_json']!==''){
        $extra=json_decode($row['extra_json'],true);
        if(!is_array($extra)||array_is_list($extra))throw new UserException('ekstra_json must be a JSON object.',400);
    }
    if($kind==='ai'){
        [$public,$answer]=build_image_task([$row['image_1'],$row['image_2']],$correct-1,$row['credit'],$extra);
    }else{
        $options=[];
        foreach([1,2,3,4] as $n)if($row['option_'.$n]!=='')$options[$n]=['id'=>'s'.$n,'text'=>$row['option_'.$n]];
        if(!isset($options[$correct]))throw new UserException('The dogru column must hold the number (1–4) of a filled option.',400);
        $public=array_merge($extra,['options'=>array_values($options)]);
        if($row['category']!=='')$public['category']=$row['category'];
        if($row['credit']!=='')$public['credit']=$row['credit'];
        $public=json_encode($public,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $answer=json_encode(['choice'=>'s'.$correct]);
    }
    $active=mb_strtolower($row['active']);
    return [
        'game_id'=>$gameId,'kind'=>$kind,'title'=>$row['title'],'prompt'=>$row['prompt'],'explanation'=>$row['explanation'],
        'public_json'=>$public,'answer_json'=>$answer,'age_scope'=>$row['age_scope'],'difficulty'=>$row['difficulty']!==''?$row['difficulty']:2,
        'active'=>!in_array($active,['0','no','false','off','inactive'],true),
    ];
}

/** Reads the CSV file. Some Excel locales use ';', so the delimiter is detected from the header row. */
function read_question_csv(string $path): array {
    $handle=fopen($path,'rb');
    if($handle===false)throw new UserException('The CSV file could not be read.',400);
    try{
        $first=(string)fgets($handle);rewind($handle);
        $delimiter=substr_count($first,';')>substr_count($first,',')?';':',';
        $header=fgetcsv($handle,0,$delimiter,'"','');
        if(!is_array($header))throw new UserException('The CSV file is empty.',400);
        $header=array_map(fn($h)=>mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$h))),$header);
        $missing=array_diff(['game_id','title','prompt','explanation','correct'],$header);
        if($missing)throw new UserException('Missing column(s) in the CSV header: '.implode(', ',$missing).'. Use the header from the sample CSV file.',400);
        $rows=[];$line=1;
        while(($cells=fgetcsv($handle,0,$delimiter,'"',''))!==false){
            $line++;
            if($cells===[null]||!array_filter($cells,fn($c)=>trim((string)$c)!==''))continue;
            if(count($rows)>=CSV_MAX_ROWS)throw new UserException('A single file can contain at most '.CSV_MAX_ROWS.' questions.',400);
            $row=array_fill_keys(CSV_COLUMNS,'');
            foreach($header as $i=>$name)if(isset($row[$name]))$row[$name]=trim((string)($cells[$i]??''));
            $rows[$line]=$row;
        }
        return $rows;
    }finally{fclose($handle);}
}

/**
 * Validates all rows first; if any row has an error, nothing is inserted.
 * Questions whose title already exists in the same game are skipped, so a file can be re-uploaded.
 */
function import_question_csv(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new UserException('No CSV file was selected or it could not be uploaded.',400);
    if((int)$file['size']>CSV_MAX_BYTES)throw new UserException('The CSV file can be at most 2 MB.',400);
    $valid=[];$errors=[];
    foreach(read_question_csv((string)$file['tmp_name']) as $line=>$row){
        try{$valid[$line]=validate_question(csv_row_to_question($row));}
        catch(UserException $e){$errors[]='Row '.$line.($row['title']!==''?' ('.$row['title'].')':'').': '.$e->getMessage();}
    }
    if($errors)return ['inserted'=>0,'skipped'=>0,'errors'=>$errors];
    if(!$valid)throw new UserException('No question rows were found in the CSV file.',400);
    $pdo=db();$exists=$pdo->prepare('SELECT 1 FROM questions WHERE game_id=? AND title=? LIMIT 1');
    $inserted=0;$skipped=0;
    $pdo->beginTransaction();
    try{
        foreach($valid as $q){
            $exists->execute([$q['game_id'],$q['title']]);
            if($exists->fetchColumn()){$skipped++;continue;}
            insert_question($q);$inserted++;
        }
        $pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
    return ['inserted'=>$inserted,'skipped'=>$skipped,'errors'=>[]];
}
