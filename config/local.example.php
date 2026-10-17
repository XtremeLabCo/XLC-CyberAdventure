<?php
// Copy this file to config/local.php. local.php must never be inside the web root (public/).
return [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'CPANELUSER_cyberadventure',
    'db_user' => 'CPANELUSER_game',
    'db_password' => 'PUT_THE_DATABASE_PASSWORD_HERE',
    'setup_key' => 'PUT_AT_LEAST_32_RANDOM_CHARACTERS_HERE',
    'recovery_pepper' => 'PUT_A_SEPARATE_32_CHARACTER_SECRET_HERE',
    'app_name' => 'Cyber Adventure',
    'app_url' => '',
    'session_name' => 'cyber_adventure_session',
    'show_errors' => false,
];
