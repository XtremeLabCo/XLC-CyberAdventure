# Security policy

Cyber Adventure is used by schools and stores players' names and email addresses, so we take security reports seriously.

## Reporting a vulnerability

**Please do not open a public issue for security problems.** Use GitHub's private vulnerability reporting instead: go to the repository's **Security** tab → **Report a vulnerability**. Include the affected page or file, steps to reproduce and the impact you expect.

We aim to acknowledge a report within 7 days and to publish a fix or mitigation as soon as possible. We will credit you in the release notes unless you prefer otherwise.

## Scope

In scope: the PHP application, the admin panel, authentication and the 4-digit player codes, file uploads, CSV import/export and anything that could expose player data.

Out of scope: the content of the public question bank (it is intentionally public), and problems that require a server misconfiguration contrary to [docs/INSTALL.md](docs/INSTALL.md), for example serving the whole project directory instead of `public/`.

## For organisers running an instance

- Serve only the `public/` directory; keep `config/local.php` private and never commit it.
- Use HTTPS, strong admin passwords and different random values for `setup_key` and `recovery_pepper`. Do not change `recovery_pepper` after launch.
- Delete player data you no longer need after your event, in line with your local data protection law.
