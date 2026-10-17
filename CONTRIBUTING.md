# Contributing to Cyber Adventure

Thank you for helping people learn to stay safe online. Teachers, students, security professionals and developers are all welcome.

## Ways to help

- **Improve or add questions:** new scenarios, clearer explanations, better distractors, questions for a specific age group.
- **Translate** the platform or the question bank (see [docs/TRANSLATING.md](docs/TRANSLATING.md)).
- **Report a problem:** a wrong answer, a confusing explanation, a layout issue on a phone, an accessibility barrier. Open an issue with the game name, the question title and a screenshot if possible.
- **Code:** bug fixes, accessibility, performance, new scenes for Cyber Threat Hunter.

## Question guidelines

Every question lives in `database/content/en_gameN.json` (N = 1 AI or Real?, 2 Phishing Detective, 3 Cyber Threat Hunter, 4 Password Hero, 5 Think Before You Share, 6 Cyber Shield).

- **Accurate and current.** Base security advice on primary sources (for example NIST SP 800-63B, CISA, NCSC, your national CERT). No outdated rules such as forced periodic password changes.
- **One unambiguous correct answer.** Distractors must be plausible, not silly. For game 2 every option starts with "Fake:" or "Trustworthy:".
- **Teach, don't just judge.** Explanations have 2–3 short sentences: the clue, why it matters, what to do.
- **Fictional and safe.** No real people, brands, banks or working links. All domains end in `.example`. Example passwords must never be anyone's real password.
- **Age-appropriate.** `age_scope` is `0-13`, `14-18`, `19-25,26-30,31-40,41-49,50+` or `50+`; `difficulty` is 3, 4 or 5. Keep sensitive topics (for example sextortion) non-explicit and point young players to a trusted adult.
- **Neutral ids.** Option ids are `s1`, `s2`... (never ids that reveal the answer). Image file names must not reveal whether an image is real or AI-generated.
- **Licensed media only.** Add an image only if its licence allows redistribution (public domain, CC0, CC BY, CC BY-SA), and add a row to `docs/MEDIA_LICENSES.md`.

After editing the JSON, rebuild the install file. The build script validates every question with the same rules the admin panel uses:

```bash
mysql -e "CREATE DATABASE ca_build CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql ca_build < database/schema_en.sql
CYBER_ADVENTURE_DB_NAME=ca_build php tests/build_install_sql.php   # writes database/INSTALL.sql
```

Include both the JSON and the regenerated `database/INSTALL.sql` in your pull request.

## Code guidelines

- PHP 8.1+, no framework and no Composer dependencies; vanilla JavaScript, no build step. Keep it that way so that schools can install the platform on simple shared hosting.
- Match the style of the surrounding code. Validate input on the server, use prepared statements for every query, and escape all output with `e()`.
- Test on a 375 px phone and with the keyboard before opening a pull request. Run `php -l` on changed PHP files and `node --check` on changed JS files.
- Never commit `config/local.php`, database dumps with player data, or uploaded images.

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Make focused changes: one topic per pull request.
3. Describe what changed and why, and for questions say which source the advice is based on.
4. By contributing you agree that your code is released under the MIT licence and your questions and documentation under CC BY 4.0.
