# Translating Cyber Adventure

The platform has no runtime language switch yet: a translation is a copy of the project with its texts and questions in another language. A Turkish version already exists this way. If you plan a translation, open an issue first so that efforts are not duplicated.

## 1. Interface texts

| Where | What to translate |
|---|---|
| `app/views/*.php` | Visible text, placeholders, `aria-label`, `title`, `alt`. Do not change PHP code, class names, ids, `name`/`value` attributes or URLs. |
| `app/*.php`, `public/index.php` | Error and flash messages, page titles, CSV report headers, game short names in `theme_game()`. |
| `public/assets/js/games.js`, `app.js` | Texts inside template strings, notes per game, checklist questions, scene labels in the `plans` object. Use your locale in `toLocaleString()` calls. |
| `public/assets/js/password-strength.js` | Replace the `COMMON` and `WORDS` lists with common passwords, first names, cities, sports clubs and everyday words of your language. Re-check every Password Hero question that has `password_test: true`: the strongest password must still be the correct answer. |
| `database/schema_en.sql` | Game names and descriptions, `site_title`, `site_subtitle`. |
| `<html lang="en">` in `app/views/header.php` | Your language code. |

URLs (`/register`, `/game/ai-or-real` ...) may stay in English.

## 2. Questions

Translate `database/content/en_game1.json` ... `en_game6.json` into new files and rebuild `database/INSTALL.sql` with `tests/build_install_sql.php` (see CONTRIBUTING.md).

- **Never change machine fields:** `key`, `game_id`, `kind`, `difficulty`, `age_scope`, `answer`, option and object `id`, `x`, `y`, `emoji`, `scene`, `icon`, image paths, `share_mode`, `password_test`.
- **Translate every human-readable string:** `title`, `prompt`, `explanation`, option `text`, and in `public`: `subject`, `body`, `post`, `handle`, `location`, `label`, `category`, `credit`, `interaction_hint`.
- **Localise, don't just translate:** use common local first names, the local emergency number and the local name of the government portal. Keep all domains on `.example`.
- **Keep the clue intact.** In Phishing Detective the sender address and the link preview are the clues: translate the words inside them but keep exactly the same trick (lookalike domain, extra subdomain, `.pdf.scr` file, rn instead of m).
- Phishing Detective options start with your words for "Fake:" and "Trustworthy:". In Cyber Threat Hunter, an object `label` and its option `text` must be identical.
- Keep titles in the "Topic · Detail" form where present: Password Hero shows the part after " · " as a short layer label.

## 3. Check before publishing

- `php -l` on every PHP file and `node --check` on every JS file.
- Rebuild `INSTALL.sql`, import it into an empty database and play one round of every game for a young and an adult age group.
- Search the rendered pages for words left in the source language.
- Test on a 320–375 px phone: translations are often longer.
