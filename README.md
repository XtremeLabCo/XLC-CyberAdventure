# Cyber Adventure

A PHP + MySQL cyber-awareness platform for XtremeLab.Co × ISTRL with six games, fully in English.

## Games

| Game | What the player does |
|---|---|
| AI or Real? | Compares two images and picks the AI-generated or the real photo; a source card is shown afterwards. |
| Phishing Detective | Reads an email, checks the sender, link and attachment with a detective checklist, decides "Fake" or "Trustworthy". |
| Cyber Threat Hunter | Scans a dark floor plan with a flashlight, inspects evidence tags and flags the single real risk. |
| Password Hero | Builds an "account fortress" over five decisions; password questions unlock a strength test bench after answering. |
| Think Before You Share | Sees a post draft (audience and location controls) or a situation card; drafts show a reach simulation afterwards. |
| Cyber Shield | General cyber-security knowledge quiz. |

## Rules built into the platform

- Five questions per round, 5 minutes per round, 100 points per game, 600 in total.
- Each player can enter each game at most **3 times** (expired or unfinished rounds count). The best score goes to the leaderboard. The limit is editable per game in Admin → Games.
- Questions are chosen by age group: 0–13 gets difficulty 3, 14–18 gets 3–4, 19+ gets 3–5.
- 188 questions ship with the install: AI or Real? 20, Phishing Detective 32, Cyber Threat Hunter 31, Password Hero 37, Think Before You Share 31, Cyber Shield 37.
- Players sign in again with their username and a 4-digit code (stored as an HMAC; rate-limited).
- The registration form tells players that their participation certificate will be sent to their email and suggests fixes for common domain typos (gmial.com → gmail.com). The platform does not send certificates itself; use the email column of the CSV report.

## Admin

- `/admin/login`. Players, games (time, questions per round, entry limit), question bank, reports with CSV export, settings.
- Question bank: single-question form (image upload for "AI or Real?"), CSV bulk upload with a downloadable sample, and an image library whose paths can be pasted into the CSV.

## Installation

See `docs/INSTALL.md`. In short:

1. Create a MySQL database and user in cPanel.
2. Import `database/INSTALL.sql` into the **empty** database with phpMyAdmin.
3. Upload the project **outside** the web root and point the subdomain's document root to `public/`.
4. Copy `config/local.example.php` to `config/local.php` and fill in the database details plus two random secrets (at least 32 characters each).
5. Open `https://your-domain/setup` to create the first admin, then register a test player at `/register`.

Requirements: PHP 8.1+ with `pdo_mysql`, `openssl`, `json`, `session`, `fileinfo`, `gd`; MySQL 8.0+ or MariaDB 10.6+.

## Security notes

- Never move `config/local.php` into `public/` or put it under version control.
- Do not change `recovery_pepper` after launch; players' 4-digit codes depend on it.
- `public/assets/media/uploads/` must be writable by the web server; scripts cannot run there (`.htaccess`).

Media sources and licences: `docs/MEDIA_LICENSES.md`.
