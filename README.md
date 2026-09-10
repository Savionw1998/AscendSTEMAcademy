# Ascend STEM Academy

Umbrella school — website code and local development environment for
[ascendstemacademy.com](https://ascendstemacademy.com).

This repository tracks **only first-party code**: the `astra-child` theme and
the `ascend-*` plugins we author. WordPress core, the Astra parent theme and
third-party plugins are installed by `make up` and are not version-controlled.

> **Local is not yet a full copy of the live site.** The child theme is here,
> but the seven first-party `ascend-*` plugins and the site's content are not.
> See [What's still missing](#whats-still-missing).

## Requirements

- Docker with Compose v2 (`docker compose version`)
- `make`

Nothing else needs installing. PHP, MySQL and wp-cli all run in containers.

## Quick start

```bash
git clone https://github.com/Savionw1998/AscendSTEMAcademy.git
cd AscendSTEMAcademy
cp .env.example .env     # optional; `make up` does this for you
make up
```

First run downloads the images, installs WordPress, pins the Astra parent
theme to 4.13.11, activates `astra-child`, and installs the third-party
plugins the site needs to render (a few minutes). When it finishes:

| | |
|---|---|
| Site | <http://localhost:8080> |
| Admin | <http://localhost:8080/wp-admin> |
| Username | `admin` |
| Password | `admin` |

Change the port or credentials in `.env` before the first `make up`.

## Everyday commands

```bash
make up          # start the stack (installs WordPress on first run)
make plugins     # (re)install the third-party plugins production runs
make fix-perms   # hand bind-mounted files back to your user after wp-cli writes
make down        # stop; database and uploads are preserved
make logs        # tail WordPress and MySQL logs
make shell       # shell inside the WordPress container
make lint        # PHP syntax-check every file in wp-content/
make status      # container status
make destroy     # stop and delete all local data (asks first)
```

Run any wp-cli command through `make wp`:

```bash
make wp CMD="plugin list"
make wp CMD="user list"
make wp CMD="search-replace ascendstemacademy.com localhost:8080 --dry-run"
```

Optional tools, started on demand:

```bash
make adminer     # database browser at http://localhost:8081 (server: db)
make mail        # Mailpit at http://localhost:8025
```

## How the local environment maps to production

Local mirrors production so bugs reproduce rather than hide:

| | Production | Local |
|---|---|---|
| PHP | 8.3.33 | 8.3 |
| MySQL | 5.7.44 | 5.7 |
| Theme | Astra Child 1.1.0 | same |
| Parent theme | Astra 4.13.11 | pinned to 4.13.11 |
| Permalinks | `/%postname%/` | same |
| Timezone | America/New_York | same |
| Memory limit | 512M | 512M |
| Max upload | 512 MB | 512 MB |
| Max execution | 600s | 600s |

Local additionally enables `WP_DEBUG`, `WP_DEBUG_LOG` and `SCRIPT_DEBUG`, and
sets `DISALLOW_FILE_EDIT` so the admin theme editor cannot be used to make
changes that bypass version control.

Production runs **38 active plugins**. `make up` installs the twelve that the
site needs in order to render (Elementor and its addons, WooCommerce, Ultimate
Member, bbPress and friends), pinned to production's versions. Plugins that
only talk to external services — W3 Total Cache, Cloudflare, Wordfence,
Jetpack, Site Kit, UpdraftPlus, WooPayments — are skipped on purpose, because
locally they either need credentials or actively hide your changes.
`scripts/plugins.sh` lists every skip and why.

## What's still missing

Three things stand between this and a faithful local copy of the live site.

**1. The seven first-party `ascend-*` plugins.** These are your own code and
they are the site's actual application — enrollment, time cards, the student
dashboard, the living transcript, lead capture, referrals, the games, the PWA.
None are in this repository. Copy each from `wp-content/plugins/` on the server
and commit it; `wp-content/plugins/README.md` lists all seven with the versions
production runs. `make up` activates any it finds automatically.

**2. The real child-theme `functions.php`.** `style.css` is the genuine
production file. `functions.php` is a *reconstruction* — it reproduces only the
priority-15 stylesheet enqueue that `style.css` documents. If the live child
theme has other PHP overrides, they are not here. Replace the file wholesale
with the real one rather than merging.

**3. Content.** The site has 28 pages and 26 posts, all Elementor-built and
stored in the database. A fresh `make up` gives you an empty site with the
right theme and plugins, not your pages. See [docs/parity.md](docs/parity.md)
for importing a database dump — and the warnings that go with it, since the
production database contains student and family data.

## Repository layout

```
docker-compose.yml              Local stack: MySQL, WordPress, wp-cli, Adminer, Mailpit
Makefile                        Developer commands (run `make` for the list)
php/uploads.ini                 PHP limits matching production
scripts/install.sh              Idempotent WordPress provisioning
scripts/plugins.sh              Third-party plugin install, pinned to prod versions
wp-content/
  themes/astra-child/           The child theme
    style.css                   Production file: theme header + all custom CSS
    functions.php               RECONSTRUCTED — enqueues style.css at priority 15
  plugins/
    README.md                   The seven first-party plugins and how to add them
    ascend-*/                   First-party plugins (tracked; none present yet)
  mu-plugins/
    ascend-local-dev.php        Local-only safety rails (inert elsewhere)
docs/parity.md                  Syncing local with production
```

## Working on the theme

Edit files under `wp-content/themes/astra-child/` on your machine — they are
bind-mounted into the container, so a browser refresh shows the change. No
rebuild or restart needed.

Two things to know before editing `style.css`:

- **Cascade order is load-bearing.** The CSS in `style.css` was migrated out of
  the Customizer on 2026-09-09. Customizer CSS printed inline very late
  (priority ~101); this stylesheet is enqueued at priority **15**, so it now
  loads *earlier*. The migrated rules survive that move only because they use
  `!important` or target unique classes. Read the note at the top of
  `style.css` before adding a rule that relies on simply being last.
- **Keep the Customizer's "Additional CSS" box empty.** It is empty in
  production today (confirmed via the site's MCP connection). Keeping styles in
  `style.css` is what makes them reviewable in a diff.

One block was deliberately left in the Customizer and is *not* in this file:
the Program Enhancements page (post 2782) workaround. It intentionally avoids
`!important` so Elementor wins once that page's CSS file is generated, and it
should disappear when post 2782 is re-saved in the Elementor editor.

## Safety notes

- `.env`, `db/` and all `*.sql` files are git-ignored. Database dumps from
  production contain student and family data — never commit one.
- Outbound email is routed to Mailpit when it is running, so local testing
  cannot email a real family. If Mailpit is not running, mail fails rather
  than being delivered.
- The local site sets `blog_public = 0` so it stays out of search engines.
