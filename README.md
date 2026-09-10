# Ascend STEM Academy

Umbrella school — website code and local development environment for
[ascendstemacademy.com](https://ascendstemacademy.com).

This repository tracks **only the code we write**: the `astra-child` theme and
our must-use plugins. WordPress core, third-party plugins and uploads are not
version-controlled — they are provided by the Docker image and local volumes.

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

First run downloads the images and installs WordPress (a few minutes).
When it finishes:

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
| Theme | Astra Child 1.1.0 (parent: Astra) | same |
| Permalinks | `/%postname%/` | same |
| Timezone | America/New_York | same |
| Memory limit | 512M | 512M |
| Max upload | 512 MB | 512 MB |
| Max execution | 600s | 600s |

Local additionally enables `WP_DEBUG`, `WP_DEBUG_LOG` and `SCRIPT_DEBUG`, and
sets `DISALLOW_FILE_EDIT` so the admin theme editor cannot be used to make
changes that bypass version control.

Production runs **39 active plugins**, which this repository does not track.
See [docs/parity.md](docs/parity.md) for how to bring the local install closer
to production when you need to debug a plugin interaction.

## Repository layout

```
docker-compose.yml              Local stack: MySQL, WordPress, wp-cli, Adminer, Mailpit
Makefile                        Developer commands (run `make` for the list)
php/uploads.ini                 PHP limits matching production
scripts/install.sh              Idempotent WordPress provisioning
wp-content/
  themes/astra-child/           The child theme — our styles and PHP
    style.css                   Theme header only
    functions.php               Bootstrap: enqueues styles, auto-loads inc/
    assets/css/theme.css        Custom styles go here
    inc/                        Feature modules; any .php here loads automatically
  mu-plugins/
    ascend-local-dev.php        Local-only safety rails (inert elsewhere)
docs/parity.md                  Syncing local with production
```

## Working on the theme

Edit files under `wp-content/themes/astra-child/` on your machine — they are
bind-mounted into the container, so a browser refresh shows the change. No
rebuild or restart needed.

Two conventions worth keeping:

- **Put styles in `assets/css/theme.css`, not the Customizer's "Additional
  CSS" box.** The live site's Additional CSS is currently empty, and keeping it
  that way means every style change is reviewable in a diff.
- **Add features as separate files in `inc/`.** They are included
  automatically, so `functions.php` stays readable.

## Safety notes

- `.env`, `db/` and all `*.sql` files are git-ignored. Database dumps from
  production contain student and family data — never commit one.
- Outbound email is routed to Mailpit when it is running, so local testing
  cannot email a real family. If Mailpit is not running, mail fails rather
  than being delivered.
- The local site sets `blog_public = 0` so it stays out of search engines.
