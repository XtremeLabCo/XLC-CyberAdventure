<?php $isAdminRoute=str_starts_with(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'','/admin');$navPlayer=empty($_SESSION['player_id'])?null:['id'=>$_SESSION['player_id']]; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4A00A5">
<meta name="description" content="Cyber Adventure: Play, learn and explore cybersecurity.">
<title><?=e($page_title??'Cyber Adventure')?><?=setting('organization_name','XtremeLab.Co × ISTRL')!==''?' · '.e(setting('organization_name','XtremeLab.Co × ISTRL')):''?></title>
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/css/app.css?v=19">
<script defer src="/assets/js/password-strength.js?v=1"></script>
<script defer src="/assets/js/app.js?v=7"></script>
<meta name="csrf-token" content="<?=e(csrf())?>">
</head>
<body class="<?= $isAdminRoute?'admin-body':'' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="topbar"><div class="container nav-inner">
<a href="/" class="brand" aria-label="Cyber Adventure home"><span class="brand-mark">✦</span><span class="brand-text"><strong>CYBER<span>ADVENTURE</span></strong><small><?=e(setting('organization_name','XtremeLab.Co × ISTRL'))?></small></span></a>
<nav class="nav-links" aria-label="Main menu">
<?php if (!$isAdminRoute && !str_starts_with(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/','/game/')): ?><button type="button" class="global-music" aria-label="Turn music on or off" aria-pressed="true" data-main-music data-music-url="<?=e(setting('music_main_url','/assets/sounds/main-theme.mp3'))?>">♫ Music: On</button><?php endif; ?>
<a href="/rules">How to Play</a><a href="/leaderboard">Leaderboard</a>
<?php if($isAdminRoute): ?><a href="/admin">Admin</a><?php elseif($navPlayer): ?><a href="/dashboard">My Dashboard</a><?php else: ?><a href="/login">Log In</a><a class="nav-cta" href="/register">Join Now <span>↗</span></a><?php endif; ?>
</nav></div></header>
<?php if($flash): ?><div class="container"><div class="toast <?=e($flash['kind'])?>" role="status"><?=e($flash['message'])?></div></div><?php endif; ?>
<main id="main-content">
