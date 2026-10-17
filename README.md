# Cyber Adventure

**Open-source cyber-awareness games for schools, universities and security institutes.**

Cyber Adventure is a self-hosted web platform with six short, interactive games that teach people of all ages to recognise online scams and threats. Run it at a school event, in a classroom, at a university open day or as part of an awareness campaign: players register with a nickname, play five-question rounds and compete on a leaderboard, while organisers manage questions and download participation reports.

Built by [XtremeLab.Co](https://github.com/XtremeLabCo). Code under the MIT licence, questions and docs under CC BY 4.0 (see [Licences](#licences)).

## The six games

| Game | What players practise |
|---|---|
| **AI or Real?** | Compare two images and decide which one is AI-generated (or which is the real photo). The explanation teaches source checking and reverse image search instead of "pixel hunting". |
| **Phishing Detective** | Read an email, inspect the sender, link and attachment with a detective checklist, and decide whether it is fake or trustworthy. |
| **Cyber Threat Hunter** | Scan a dark floor plan with a flashlight, inspect evidence tags and flag the one real security risk in an office, café, classroom, hotel room or on a phone. |
| **Password Hero** | Build an "account fortress" over five decisions about passwords, passkeys and two-step verification. Password questions unlock a strength test bench after answering. |
| **Think Before You Share** | Decide what to do before posting or when facing a risky situation online. Post drafts let players change the audience and location, then show how far a post can spread. |
| **Cyber Shield** | A general cyber-security knowledge quiz. |

## Who it is for

- **High schools:** awareness weeks, IT or citizenship lessons, parents' evenings. Players choose one of seven age groups when they register; players aged 0–13 only get the easier questions.
- **Universities and colleges:** freshers' week, open days, student societies.
- **Security institutes, CERTs and NGOs:** public awareness campaigns, festival stands, staff training.

## Features

- 188 ready-to-use questions with explanations, at difficulty levels 3–5. Each round is matched to the player's age group: 0–13 gets level 3, 14–18 gets levels 3–4 and adults get levels 3–5.
- Five questions and five minutes per round; up to three entries per game per player (configurable); 100 points per game and a leaderboard per game and age group.
- Players register with a nickname and come back with a 4-digit code. Real names and emails are never shown on the leaderboard.
- Admin panel: players, game settings, question bank (single form, image upload and CSV bulk import with a sample file), participation reports with CSV export, and an organization name shown on every page.
- Mobile-first: tested on 320–375 px phones; keyboard and screen reader friendly; respects "reduced motion".
- No external services and no tracking. The password strength test runs entirely in the browser.

## Quick start (local)

Requirements: PHP 8.1+ with `pdo_mysql`, `openssl`, `fileinfo`, `gd`, and MySQL 8.0+ or MariaDB 10.6+.

```bash
# 1. Create a database and import the install file
mysql -e "CREATE DATABASE cyberadventure CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql cyberadventure < database/INSTALL.sql

# 2. Configure: set db_name/db_user/db_password and two random secrets of 32+ characters
cp config/local.example.php config/local.php

# 3. Run the built-in PHP server
php -S localhost:8000 -t public public/router.php
```

Open http://localhost:8000/setup, enter your `setup_key` and create the first admin. Then go to http://localhost:8000/register to create a test player.

For a real server (cPanel or any Apache host) follow [docs/INSTALL.md](docs/INSTALL.md).

## Using your own questions

The shipped question bank is public, which is normal for an open-source learning tool. **For competitive events, add your own questions** so that players cannot look up the answers:

1. Admin → Question Bank → **Download sample CSV**.
2. Fill one question per row (the column guide is on the same page), upload the CSV.
3. Disable or edit the default questions you do not want to use.

Image questions for "AI or Real?" can be uploaded through the image library on the same page. See [CONTRIBUTING.md](CONTRIBUTING.md) if you want to improve the default question bank for everyone.

## Project layout

```
app/        PHP application (routing helpers, game logic, admin, views)
public/     web root: index.php, CSS, JS, images, sounds
config/     local.example.php (copy to local.php; never commit local.php)
database/   INSTALL.sql, schema_en.sql and the question sources (content/en_game*.json)
docs/       installation guide, translation guide, media licences
tests/      build scripts (INSTALL.sql, sample CSV)
```

## Contributing

Questions, translations, accessibility fixes and new scenes are all welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) first. Please report security issues privately as described in [SECURITY.md](SECURITY.md). Everyone taking part is expected to follow our [Code of Conduct](CODE_OF_CONDUCT.md).

## Licences

- **Code:** [MIT](LICENSE).
- **Questions, explanations and documentation:** [CC BY 4.0](LICENSE-CONTENT.md). You may use and adapt them, including commercially, as long as you credit "Cyber Adventure by XtremeLab.Co".
- **Images and music:** each file keeps its own licence (public domain, CC0, CC BY or CC BY-SA). See [docs/MEDIA_LICENSES.md](docs/MEDIA_LICENSES.md) and keep the attributions when you redistribute.
