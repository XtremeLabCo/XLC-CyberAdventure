-- CYBER ADVENTURE - schema (MySQL 8.0 / MariaDB 10.6+)
-- Import with phpMyAdmin as UTF-8 after selecting the empty database.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS age_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(32) NOT NULL UNIQUE,
  label VARCHAR(30) NOT NULL,
  min_age SMALLINT UNSIGNED NOT NULL,
  max_age SMALLINT UNSIGNED NULL,
  difficulty TINYINT UNSIGNED NOT NULL DEFAULT 2,
  position TINYINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS players (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(32) NOT NULL,
  username_key VARCHAR(32) NOT NULL UNIQUE,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  age_group_id INT UNSIGNED NOT NULL,
  gender ENUM('female','male','prefer_not_to_say') NOT NULL DEFAULT 'prefer_not_to_say',
  avatar_id VARCHAR(32) NOT NULL DEFAULT 'robot',
  recovery_hash CHAR(64) NOT NULL UNIQUE,
  recovery_code_enc VARCHAR(255) NULL,
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  CONSTRAINT fk_player_age FOREIGN KEY(age_group_id) REFERENCES age_groups(id),
  INDEX idx_created(created_at),
  INDEX idx_age(age_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super','admin','staff') NOT NULL DEFAULT 'admin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS games (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(55) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(240) NOT NULL,
  seconds_limit SMALLINT UNSIGNED NOT NULL DEFAULT 180,
  questions_per_attempt TINYINT UNSIGNED NOT NULL DEFAULT 5,
  max_scored_attempts TINYINT UNSIGNED NOT NULL DEFAULT 2,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  position TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id TINYINT UNSIGNED NOT NULL,
  kind ENUM('ai','mail','hunt','password','story','quiz') NOT NULL,
  title VARCHAR(160) NOT NULL,
  prompt TEXT NOT NULL,
  public_json LONGTEXT NOT NULL,
  answer_json TEXT NOT NULL,
  explanation TEXT NOT NULL,
  age_scope VARCHAR(250) NOT NULL DEFAULT 'all',
  difficulty TINYINT UNSIGNED NOT NULL DEFAULT 2,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_question_game FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE,
  INDEX idx_question_filter(game_id,active,difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS attempts (
  id CHAR(32) PRIMARY KEY,
  player_id BIGINT UNSIGNED NOT NULL,
  game_id TINYINT UNSIGNED NOT NULL,
  age_group_id INT UNSIGNED NOT NULL,
  mode ENUM('scored','practice') NOT NULL DEFAULT 'scored',
  status ENUM('active','finished','expired') NOT NULL DEFAULT 'active',
  items_json LONGTEXT NOT NULL,
  responses_json LONGTEXT NOT NULL,
  current_step SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  correct_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_steps SMALLINT UNSIGNED NOT NULL,
  score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  CONSTRAINT fk_attempt_player FOREIGN KEY(player_id) REFERENCES players(id) ON DELETE CASCADE,
  CONSTRAINT fk_attempt_game FOREIGN KEY(game_id) REFERENCES games(id),
  CONSTRAINT fk_attempt_age FOREIGN KEY(age_group_id) REFERENCES age_groups(id),
  INDEX idx_player_game(player_id,game_id,status),
  INDEX idx_game_score(game_id,mode,status,score),
  INDEX idx_started(started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS auth_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fingerprint CHAR(64) NOT NULL,
  happened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fingerprint_time(fingerprint,happened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(70) NOT NULL,
  target VARCHAR(160) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_admin FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_audit_time(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO age_groups (id,slug,label,min_age,max_age,difficulty,position) VALUES
 (1,'0-13','0–13',0,13,3,1),(2,'14-18','14–18',14,18,4,2),
 (3,'19-25','19–25',19,25,5,3),(4,'26-30','26–30',26,30,5,4),
 (5,'31-40','31–40',31,40,5,5),(6,'50+','50+',50,NULL,5,7),
 (7,'41-49','41–49',41,49,5,6);
INSERT IGNORE INTO games (id,slug,name,description,seconds_limit,questions_per_attempt,max_scored_attempts,position) VALUES
 (1,'ai-or-real','AI or Real?','Investigate where an image comes from and explore the details.',300,5,3,1),
 (2,'phishing-detective','Phishing Detective','Track down fake emails and collect the clues.',300,5,3,2),
 (3,'threat-hunter','Cyber Threat Hunter','Find hidden security risks in interactive scenes.',300,5,3,3),
 (4,'password-hero','Password Hero','Solve password puzzles and protect your accounts.',300,5,3,4),
 (5,'think-before-sharing','Think Before You Share','Explore social media secrets and digital footprints.',300,5,3,5),
 (6,'cyber-shield','Cyber Shield','Prove yourself in the cyber knowledge arena.',300,5,3,6);
INSERT IGNORE INTO settings (`key`,value) VALUES
 ('site_title','Cyber Adventure'),('site_subtitle','Play. Learn. Collect Points!'),
 ('registration_open','1'),('site_notice',''),('organization_name','XtremeLab.Co × ISTRL'),('setup_complete','0'),
 ('music_main_url','/assets/sounds/main-theme.mp3'),('music_game_url','/assets/sounds/main-theme.mp3'),
 ('music_source_url','https://opengameart.org/content/brute-force');
