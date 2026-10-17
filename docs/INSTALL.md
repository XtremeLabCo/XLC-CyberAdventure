# Cyber Adventure: cPanel installation guide

## 1. Server requirements

- PHP 8.1 or newer with `pdo_mysql`, `json`, `openssl`, `session`, `fileinfo`, `gd`.
- MySQL 8.0+ or MariaDB 10.6+, InnoDB, `utf8mb4`.
- Apache with `mod_rewrite`.
- A valid HTTPS certificate (cPanel AutoSSL is fine).
- The ability to change the subdomain's **document root**.

Recommended layout (replace `cpuser` with your cPanel user):

```
/home/cpuser/CyberAdventure/        <- private code, outside the web root
  app/
  config/
  database/
  docs/
  public/                           <- the subdomain's document root
    index.php
    .htaccess
    assets/
```

Do not copy the whole project into `public_html`. `app/`, `config/`, `database/` and `docs/` contain an `.htaccess` that denies web access as a safety net, but they should still live outside the web root.

## 2. Database

1. cPanel → **MySQL Databases**: create a database and a user, and give the user all privileges on that database only.
2. cPanel → **phpMyAdmin**: select the new, empty database.
3. **Import** → `database/INSTALL.sql`. It creates the tables, the six games, age groups, settings and 188 questions.

## 3. Configuration

Copy `config/local.example.php` to `config/local.php` and set:

| Key | Value |
|---|---|
| `db_host`, `db_port` | usually `localhost`, `3306` |
| `db_name`, `db_user`, `db_password` | from step 2 |
| `setup_key` | a random string of at least 32 characters (used once on `/setup`) |
| `recovery_pepper` | another random string of at least 32 characters; **never change it after launch** |
| `app_url` | e.g. `https://en.example.com` |
| `show_errors` | `false` in production |

## 4. First admin

Open `https://your-domain/setup`, enter the `setup_key` and create the first admin (username 4–50 characters, password at least 12 characters). The setup page closes itself after the first admin exists.

## 5. Checks

- `public/assets/media/uploads/` is writable (755 is usually enough).
- Home page, `/register`, `/login`, `/password-test` and `/leaderboard` open.
- Register a test player and finish one round of each game.
- Admin → Question Bank → download the sample CSV and upload it back: six sample questions (inactive) are added, a second upload skips them.

## 6. Before a live event

- Review the privacy notice required by your local data protection law (e.g. GDPR).
- Consider a load test and two-step verification for admin accounts.
