<?php include __DIR__.'/admin_nav.php';
$kindNow=$edit['kind']??default_kind($filter);
$editPublic=$edit?(json_decode((string)$edit['public_json'],true)?:[]):[];
$editAnswer=$edit?(json_decode((string)$edit['answer_json'],true)?:[]):[];
$imageIds=array_map(fn($o)=>(string)($o['id']??''),$editPublic['options']??[]);
$correctIndex=array_search((string)($editAnswer['choice']??''),$imageIds,true);
?>
<section class="container admin-page"><div class="eyebrow">CONTENT MANAGEMENT</div><h1>Question and <em>Scenario Bank</em></h1><p>View and edit game content, add items one by one, or bulk upload them with a CSV file.</p><form action="/admin/questions" method="get" class="filters-box"><label>Select Game<select name="game"><?php foreach($all as $g):?><option value="<?=$g['id']?>" <?=(int)$g['id']===$filter?'selected':''?>><?=e($g['name'])?></option><?php endforeach;?></select></label><button class="btn btn-primary">Show Content</button><a class="small-button" href="/admin/questions?game=<?=$filter?>#question-form">Add a single question</a></form>

<div class="bulk-grid">
<div id="bulk-upload" class="admin-card"><h2>Bulk Upload (CSV)</h2>
<ol class="steps-list"><li>Download the sample CSV file and open it in Excel or Google Sheets.</li><li>Write one question per row. The <code>game_id</code> column decides which game the question belongs to; a single file can contain all six games.</li><li>For image questions (game 1), first upload the images in the Image Library section, then paste the paths you get into the <code>image_1</code> and <code>image_2</code> columns.</li><li>Save the file as CSV and upload it. If any row has an error, no questions are added; the errors are listed with their row numbers.</li></ol>
<p><a class="small-button" href="/admin/questions/sample.csv">Download the sample CSV file</a></p>
<?php if($importErrors):?><div class="import-errors" role="alert"><strong>Rows that need fixing</strong><ul><?php foreach(array_slice($importErrors,0,25) as $err):?><li><?=e($err)?></li><?php endforeach;?></ul><?php if(count($importErrors)>25):?><p>…and <?=count($importErrors)-25?> more rows.</p><?php endif;?></div><?php endif;?>
<form method="post" action="/admin/questions" enctype="multipart/form-data" class="upload-form"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="csv"><input type="hidden" name="game_id" value="<?=e($filter)?>"><label class="field">CSV file <small>(max 2 MB, 1000 rows; separator ; or ,)</small><input type="file" name="csv" accept=".csv,text/csv" required></label><button type="submit" class="btn btn-primary">Upload CSV</button></form>
<details class="csv-help"><summary>What do the columns mean?</summary><dl>
<dt>game_id</dt><dd>1 AI or Real? · 2 Phishing Detective · 3 Cyber Threat Hunter · 4 Password Hero · 5 Think Before You Share · 6 Cyber Shield</dd>
<dt>kind</dt><dd>Can be left empty; the game's type is selected automatically (ai, mail, hunt, password, story, quiz).</dd>
<dt>title, prompt, explanation</dt><dd>Required. The explanation is shown to the player after they answer. A question with the same title in the same game is not added again.</dd>
<dt>difficulty, age_scope</dt><dd>1–5 (2 if empty). Age: all or comma-separated 0-13,14-18,19-25,26-30,31-40,41-49,50+</dd>
<dt>option_1 … option_4, correct</dt><dd>At least two options. <code>correct</code> is the number of the correct option (1–4). For image questions, 1 = image_1, 2 = image_2.</dd>
<dt>image_1, image_2, credit</dt><dd>Image questions only. The path must exist on the server, e.g. /assets/media/uploads/….webp. <code>credit</code> is the image's license/source note.</dd>
<dt>category</dt><dd>The category shown at the top of Cyber Shield questions.</dd>
<dt>extra_json</dt><dd>Game-specific fields: for email, sender, subject, body, preview; for story, author, handle, post; for threat hunting, scene (cafe, class, home, hotel, library, office, phone), location and objects (object ids must be s1–s4). The sample file includes each of them.</dd>
<dt>active</dt><dd>1 or empty = published, 0 = unpublished.</dd>
</dl></details>
</div>

<div id="image-library" class="admin-card"><h2>Image Library</h2><p class="tiny-note">Copy the path of an uploaded image and use it in the CSV or the question form. JPG, PNG, WEBP or GIF; max 5 MB per image. Keeping both images at the same aspect ratio makes the comparison fair.</p>
<form method="post" action="/admin/questions" enctype="multipart/form-data" class="upload-form"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="media"><input type="hidden" name="game_id" value="<?=e($filter)?>"><label class="field">Images <small>(you can select more than one)</small><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required></label><button type="submit" class="btn btn-primary">Upload Images</button></form>
<?php if($media):?><ul class="media-grid"><?php foreach($media as $path):?><li><img src="<?=e($path)?>" alt="" loading="lazy"><code><?=e(basename($path))?></code><button type="button" class="small-button" data-copy="<?=e($path)?>">Copy path</button></li><?php endforeach;?></ul><?php else:?><p class="tiny-note">No images uploaded yet.</p><?php endif;?>
</div>
</div>

<div class="admin-card"><div class="data-table-wrap"><table class="data-table"><thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Difficulty</th><th>Age</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=$r['id']?></td><td><?=e($r['title'])?></td><td><?=e($r['kind'])?></td><td><?=e($r['difficulty'])?></td><td><?=e($r['age_scope'])?></td><td><?=$r['active']?'Published':'Unpublished'?></td><td><a class="small-button" href="/admin/questions?game=<?=$filter?>&edit=<?=$r['id']?>#question-form">Edit</a></td></tr><?php endforeach;?></tbody></table></div></div>

<div id="question-form" class="admin-card"><h2><?=$edit?'Edit Content':'Add New Content'?></h2>
<form method="post" action="/admin/questions" enctype="multipart/form-data" class="auth-form"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=e($edit['id']??0)?>"><input type="hidden" name="game_id" value="<?=e($edit['game_id']??$filter)?>"><input type="hidden" name="kind" value="<?=e($kindNow)?>">
<div class="form-columns"><div class="field">Content Type<strong class="kind-label"><?=e(QUESTION_KINDS[$kindNow]??$kindNow)?></strong></div><label class="field">Difficulty (1–5)<input type="number" name="difficulty" min="1" max="5" value="<?=e($edit['difficulty']??2)?>"></label></div>
<label class="field">Title<input name="title" required maxlength="160" value="<?=e($edit['title']??'')?>"></label><label class="field">Task Text<textarea name="prompt" rows="3" required><?=e($edit['prompt']??'')?></textarea></label><label class="field">Explanation<textarea name="explanation" rows="3" required><?=e($edit['explanation']??'')?></textarea></label><label class="field">Age Scope <small>(all or 0-13,14-18,19-25,26-30,31-40,41-49,50+)</small><input name="age_scope" value="<?=e($edit['age_scope']??'all')?>"></label>
<?php if($kindNow==='ai'):?>
<input type="hidden" name="image_form" value="1">
<fieldset class="image-pair"><legend>Images to compare</legend><p class="tiny-note">The player sees the two images side by side; their order is shuffled each round. If you choose a file, a new image is uploaded; if not, the image in the path field is used. Mark the image that the task text asks about as the correct answer.</p>
<div class="image-slots"><?php foreach([1,2] as $n): $current=(string)($editPublic['images'][$imageIds[$n-1]??'']??'');?>
<div class="image-slot"><span class="slot-label">IMAGE <?=$n===1?'A':'B'?></span><?php if($current!==''):?><img src="<?=e($current)?>" alt="Current image <?=$n===1?'A':'B'?>"><?php else:?><div class="image-empty">No image selected</div><?php endif;?>
<label class="field">Upload file<input type="file" name="image_<?=$n?>" accept="image/jpeg,image/png,image/webp,image/gif"></label>
<label class="field">or path from the library<input name="image_path_<?=$n?>" value="<?=e($current)?>" placeholder="/assets/media/uploads/…"></label>
<label class="check-line"><input type="radio" name="correct_image" value="<?=$n-1?>" required <?=$correctIndex===$n-1?'checked':''?>> This image is the correct answer</label></div>
<?php endforeach;?></div>
<label class="field">Source / license note<input name="credit" maxlength="500" value="<?=e($editPublic['credit']??'')?>" placeholder="Photo: … ; synthetic pair: …"></label></fieldset>
<input type="hidden" name="public_json" value="<?=e($edit['public_json']??'{}')?>">
<?php else:?>
<p class="tiny-note">Structured content fields use JSON. Options must match the <code>options</code> field and the correct answer the <code>choice</code> field. For many questions, the CSV upload above is faster.</p>
<label class="field">Game Data (JSON)<textarea name="public_json" rows="9" spellcheck="false" required><?=e($edit['public_json']??'{"options":[{"id":"a","text":"Option A"},{"id":"b","text":"Option B"}]}')?></textarea></label><label class="field">Correct Answer (JSON)<textarea name="answer_json" rows="3" spellcheck="false" required><?=e($edit['answer_json']??'{"choice":"a"}')?></textarea></label>
<?php endif;?>
<label class="check-line"><input type="checkbox" name="active" <?=(!isset($edit['active'])||(int)$edit['active'])?'checked':''?>> Published</label><button type="submit" class="btn btn-primary">Save Content</button></form></div></section>
